<?php

/**
 * Third checkout step. Determine shipping options based on region defined in previous step.
 * Accept user selection of one of available shipping options.
 */
class shopCheckoutShippingStep extends shopCheckoutStep
{
    public function process($data, $prepare_result)
    {
        // Is shipping step disabled altogether?
        $config = $this->getCheckoutConfig();
        if (empty($config['shipping']['used'])) {
            $result = $this->addRenderedHtml([
                'disabled' => true,
            ], $data, []);
            return array(
                'result'       => $result,
                'errors'       => [],
                'can_continue' => true,
            );
        }

        $address = [
            'country' => ifset($data, 'result', 'region', 'selected_values', 'country_id', null),
            'region'  => ifset($data, 'result', 'region', 'selected_values', 'region_id',
                ifset($data, 'result', 'region', 'selected_values', 'region', null)
            ),
            'city'    => ifset($data, 'result', 'region', 'selected_values', 'city_id',
                ifset($data, 'result', 'region', 'selected_values', 'city', null)
            ),
            'zip'     => ifset($data, 'result', 'region', 'selected_values', 'zip', null),
        ];

        $errors = [];
        if (!$config['shipping']['ask_zip']) {
            unset($address['zip']);

            //
            // When checkout is set up not to ask ZIP code at Region step,
            // but user already entered it at Details step,
            // use that ZIP code to calculate list of shipping variants.
            //

            // Try to get ZIP code from Details step data
            $zip_from_future = ifset($data, 'input', 'details', 'shipping_address', 'zip', null);

            // Try to get ZIP code from session storage
            if (!$zip_from_future) {
                $storage = $this->getCheckoutConfig()->getStorage();
                $stored_address = $storage->get('details_address');
                $zip_from_future = ifset($stored_address, 'zip', null);
            }

            if ($zip_from_future) {
                $address['zip'] = $zip_from_future;
            }
        }

        if (empty($address['country']) || empty($address['region']) || empty($address['city'])) {
            // This cannot happen. It means previous step did not properly validate shipping region selection,
            // or a plugin interfered and broke things, or some other terrible stuff occurred. Like, Godzilla. Blame Godzilla.
            $errors[] = [
                'id'      => 'general',
                'text'    => 'Unable to prepare list of shipping options because shipping region is not properly selected.',
                'section' => $this->getId(),
            ];
            return array(
                'data'         => $data,
                'result'       => $this->addRenderedHtml([], $data, $errors),
                'errors'       => $errors,
                'can_continue' => false,
            );
        }

        /** @var shopOrder $order we take cart items from there */
        $order = $data['order'];

        // Before fetching order items, we need to apply discounts.
        // We ask shopOrder to do that. This has a side effect of gathering data
        // into $order->items, which is what we use.
        $order->discount;

        // Discount calculation above may also have had another side effect.
        // Discount plugins are allowed to reset shipping cost to 0. We have to take note of that.
        // Alternatively, client may have endered a free shipping coupon.
        $is_free_shipping = ifempty($data, 'order', 'coupon', 'type', '') === '$FS';
        if ($order->shipping !== null) {
            if (!$is_free_shipping && $order->shipping == 0) {
                $is_free_shipping = true;
            }
        }

        // Order items to pass to shipping plugins to calculate rates
        $items = $order->items;

        /** @var waContact $contact */
        $contact = $data['contact'];
        $customer_type = $contact['is_company'] ? shopCheckoutConfig::CUSTOMER_TYPE_COMPANY : shopCheckoutConfig::CUSTOMER_TYPE_PERSON;

        // Fetch list of shipping plugins enabled for current storefront
        // Ask them to provide shipping variants using $address given
        $services_flat = array();
        $rates = $config->getShippingRates($address, $items, $customer_type);
        foreach ($rates as $id => $rate) {
            if (isset($rate['type'])) {
                $services_flat[$id] = $rate;
            }
        }

        if (!$services_flat) {
            $errors[] = [
                'id'      => 'empty_region_options',
                'text'    => _w('No shipping options available for selected region.'),
                'section' => $this->getId(),
            ];
            return array(
                'data'         => $data,
                'result'       => $this->addRenderedHtml([], $data, $errors),
                'errors'       => $errors,
                'can_continue' => false,
            );
        }

        //
        // Convert variants for template
        //
        $shipping_types = [
            'pickup' => [
                'id'             => 'pickup',
                'is_selected'    => false,
                'name'           => $config['shipping']['pickuppoint_name'],
                'rate_min'       => null, // null|number
                'rate_max'       => null, // null|number
                'currency'       => $data['order']['currency'],
                'date_min'       => null, // null|'Y-m-d H:i:s'
                'date_max'       => null, // null|'Y-m-d H:i:s'
                'date_min_ts'    => null, // null|int
                'date_max_ts'    => null, // null|int
                'date_formatted' => '',
                'variants'       => [],
            ],
            'todoor' => [
                'id'             => 'todoor',
                'is_selected'    => false,
                'name'           => $config['shipping']['courier_name'],
                'rate_min'       => null,
                'rate_max'       => null,
                'currency'       => $data['order']['currency'],
                'date_min'       => null,
                'date_max'       => null,
                'date_min_ts'    => null,
                'date_max_ts'    => null,
                'date_formatted' => '',
                'variants'       => [],
            ],
            'post'   => [
                'id'             => 'post',
                'is_selected'    => false,
                'name'           => $config['shipping']['post_name'],
                'rate_min'       => null,
                'rate_max'       => null,
                'currency'       => $data['order']['currency'],
                'date_min'       => null,
                'date_max'       => null,
                'date_min_ts'    => null,
                'date_max_ts'    => null,
                'date_formatted' => '',
                'variants'       => [],
            ],
        ];

        $selected_type_id = null;
        $selected_variant_id = ifset($data, 'input', 'shipping', 'variant_id', null);
        if (empty($selected_variant_id)) {
            $selected_type_id = ifset($data, 'input', 'shipping', 'type_id', null);
            if ($selected_type_id && empty($shipping_types[$selected_type_id])) {
                $selected_type_id = null;
            }
        }

        $currencies = $config->getCurrencies();

        $proper_variant_is_selected = false;
        foreach ($services_flat as $s_id => $s) {
            if (!isset($s['type']) || !isset($shipping_types[$s['type']])) {
                continue;
            }

            // Apply free shipping coupon if set
            if ($is_free_shipping) {
                $s['rate'] = 0;
            }

            $type =& $shipping_types[$s['type']];
            $s = self::prepareShippingVariant($s, $currencies);

            $s['is_selected'] = $selected_variant_id && $selected_variant_id == $s_id;
            if ($s['is_selected']) {
                $selected_type_id = $type['id'];
                $proper_variant_is_selected = true;
            }

            // Update type rates
            if (
                !empty($s['currency']) &&
                !empty($currencies[$s['currency']]['rate']) &&
                !empty($currencies[$type['currency']]['rate']) &&
                isset($s['rate_min']) &&
                isset($s['rate_max'])
            ) {
                $rate_from = $currencies[$s['currency']]['rate'];
                $rate_to = $currencies[$type['currency']]['rate'];
                $rate_min = $s['rate_min'] * $rate_from / $rate_to;
                $rate_max = $s['rate_max'] * $rate_from / $rate_to;
                if ($type['rate_min'] === null || $type['rate_min'] > $rate_min) {
                    $type['rate_min'] = $rate_min;
                }
                if ($type['rate_max'] === null || $type['rate_max'] < $rate_max) {
                    $type['rate_max'] = $rate_max;
                }
            }

            // Update type dates
            if (!empty($s['delivery_date'])) {
                if ($type['date_min_ts'] === null || $type['date_min_ts'] > $s['date_min_ts']) {
                    $type['date_min_ts'] = $s['date_min_ts'];
                    $type['date_min'] = $s['date_min'];
                }
                if ($s['date_max_ts'] && ($type['date_max_ts'] === null || $type['date_max_ts'] < $s['date_max_ts'])) {
                    $type['date_max_ts'] = $s['date_max_ts'];
                    $type['date_max'] = $s['date_max'];
                }
            }

            $type['variants'][$s_id] = $s;
            unset($type);
        }

        if (!$proper_variant_is_selected) {
            $selected_variant_id = null;

            // If there's only one active shipping type, select it immediately
            $nonempty_types = array_filter($shipping_types, function ($type) {
                return !empty($type['variants']);
            });
            if (1 === count($nonempty_types)) {
                $type = reset($nonempty_types);
                $selected_type_id = $type['id'];
            }
        }

        // Format expected delivery date into human-readable form
        foreach ($shipping_types as &$type) {
            if (empty($type['date_min_ts'])) {
                continue;
            }

            if ($type['id'] !== 'post') {
                // Today and tommorrow
                $date = waDateTime::format('Ymd', $type['date_min_ts']);
                if ($date == waDateTime::format('Ymd')) {
                    $type['date_formatted'] = _ws('Today');
                    continue;
                } elseif ($date == waDateTime::format('Ymd', strtotime('+1 day'))) {
                    $type['date_formatted'] = _ws('Tomorrow');
                    continue;
                }
            }

            // Format into day+month+year
            $type['date_formatted'] = waDateTime::format('humandate', $type['date_min_ts']);
            if (empty($type['date_max_ts']) || $type['date_max_ts'] != $type['date_min_ts']) {
                $type['date_formatted'] = _w('from').' '.$type['date_formatted'];
            }

            // Do not show year if reasonable
            $type['date_formatted'] = str_replace(date('Y'), '', $type['date_formatted']);
            if ($type['date_min_ts'] - time() < 3600 * 24 * 365 / 2) {
                $type['date_formatted'] = str_replace(date('Y', strtotime('+1 year')), '', $type['date_formatted']);
            }
            $type['date_formatted'] = trim($type['date_formatted'], " ,\t\r\n");
        }
        unset($type);

        if ($selected_type_id && isset($shipping_types[$selected_type_id])) {
            $shipping_types[$selected_type_id]['is_selected'] = true;

            // Select single variant if user selected type
            if (!$selected_variant_id && 1 == count($shipping_types[$selected_type_id]['variants'])) {
                $selected_variant_id = key($shipping_types[$selected_type_id]['variants']);
            }
        } else {
            $selected_type_id = null;
        }

        if (!$selected_variant_id) {
            $errors[] = [
                'name'    => 'shipping[variant_id]',
                'text'    => _w('Please select shipping option.'),
                'section' => $this->getId(),
            ];
        } else {
            if (!$config['shipping']['ask_zip']) {
                unset($address['zip']);
            }

            // This is used by Details step later
            $data['shipping']['items'] = $items;
            $data['shipping']['address'] = $address;
            $data['shipping']['selected_variant'] = $shipping_types[$selected_type_id]['variants'][$selected_variant_id];
            $data['shipping']['is_free_shipping'] = $is_free_shipping;
        }

        // These are used by map that shows pickpoint locations
        $adapter = null;
        $apikey = null;
        try{
            $adapter = wa()->getMap()->getId();
            if ($adapter === 'yandex') {
                $apikey = 'apikey';
            } else {
                $apikey = 'key';
            }
            $apikey = wa()->getMap()->getSettings($apikey);
        } catch (Exception $e) {

        }

        $map = [
            'adapter' => $adapter,
            'api_key' => $apikey
        ];

        $result = $this->addRenderedHtml([
            'selected_type_id'    => $selected_type_id,
            'selected_variant_id' => $selected_variant_id,
            'types'               => $shipping_types,
            'map'                 => $map
        ], $data, $errors);

        if ($data['origin'] !== 'form' && 'only' === ifset($data, 'input', 'shipping', 'html', null)) {
            unset($result['types']);
        }

        return [
            'data'         => $data,
            'result'       => $result,
            'errors'       => $errors,
            'can_continue' => !$errors,
        ];
    }

    /**
     * Format rates and dates for use in template
     * Also used by shopCheckoutDetailsStep
     * @param array $s variant as returned by shopCheckoutConfig::getShippingRates()
     * @return array
     */
    public static function prepareShippingVariant($s, $currencies)
    {
        // Parse rates
        $s['rate_min'] = null;
        $s['rate_max'] = null;
        if (!empty($s['currency']) && !empty($currencies[$s['currency']])) {
            $rate = ifset($s, 'rate', null);
            if (is_array($rate)) {
                $rate = array_filter($rate, 'is_numeric');
                if ($rate) {
                    $s['rate_min'] = min($rate);
                    $s['rate_max'] = max($rate);
                }
            } elseif ($rate !== null && is_numeric($rate)) {
                $s['rate_min'] = $rate;
                $s['rate_max'] = $rate;
            }
        }

        // Pickup has its own delivery date field
        if (isset($s['custom_data']['pickup']['interval'])) {
            $s['delivery_date'] = $s['custom_data']['pickup']['interval'];
        }

        // Parse dates
        $s['date_min'] = null;
        $s['date_max'] = null;
        $s['date_min_ts'] = null;
        $s['date_max_ts'] = null;
        $date = ifset($s, 'delivery_date', null); // this is mysql date (string) or array of them
        if ($date) {
            if (!is_array($date)) {
                $date = [$date];
            }
            $date = array_map('strtotime', $date);

            // Do not allow delivery dates in the past.
            // Cut off minutes and seconds to improve caching.
            $time_now = strtotime(date('Y-m-d H:00:00'));

            $s['date_min_ts'] = max($time_now, min($date));
            $s['date_max_ts'] = max($time_now, max($date));
            $s['date_min'] = date('Y-m-d H:i:s', $s['date_min_ts']);
            $s['date_max'] = date('Y-m-d H:i:s', $s['date_max_ts']);
        }

        return $s;
    }

    /**
     * Format pickup schedule for use in templates
     * Also used by shopCheckoutDetailsStep
     * @param array $schedule
     * @param array $timezones
     * @param string $default_timezone
     * @param $timezone
     * @return array
     * @throws waException
     */
    public static function formatPickupSchedule($schedule, $timezones, $default_timezone, $timezone = null)
    {

        static $weekday_names_full = null;
        static $weekday_names_short = null;
        static $server_timezone = null;
        if ($weekday_names_full === null) {
            $weekday_names_full = waDateTime::getWeekdayNames();
            $weekday_names_short = waDateTime::getWeekdayNames('ucfirst', 'short');
            $server_timezone = new DateTimeZone(waDateTime::getDefaultTimeZone());
        }

        $timezone = $timezone ? $timezone : $default_timezone;

        $timezone_html = ifset($timezones, $timezone, $timezone);
        $days = [];
        foreach ($schedule['weekdays'] as $d) {
            list($date, $time_start) = explode(' ', $d['start_work']);
            list($_, $time_end) = explode(' ', $d['end_work']);
            $timestamp = strtotime($d['start_work']);
            $weekday_id = date('N', $timestamp);
            if ($time_start) {
                if (strlen($time_start) == 8) {
                    $time_start = substr($time_start, 0, -3);
                }
                if ($time_start[0] === '0') {
                    $time_start = substr($time_start, 1);
                }
            }
            if ($time_end) {
                if (strlen($time_end) == 8) {
                    $time_end = substr($time_end, 0, -3);
                }
                if ($time_end[0] === '0') {
                    $time_end = substr($time_end, 1);
                }
            }

            // pass in default time zone to make it not convert the time
            $date_formatted = waDateTime::format('humandate', $date, $server_timezone);
            $date_formatted = str_replace(date('Y'), '', $date_formatted);
            if ($timestamp - time() < 3600 * 24 * 365 / 2) {
                $date_formatted = str_replace(date('Y', strtotime('+1 year')), '', $date_formatted);
            }
            $date_formatted = trim($date_formatted, " ,\t\r\n");

            $days[] = [
                'date'           => $date,
                'works'          => $d['type'] == 'workday',
                'date_formatted' => $date_formatted,
                'time_start'     => $time_start,
                'time_end'       => $time_end,
                'weekday_full'   => $weekday_names_full[$weekday_id],
                'weekday_short'  => $weekday_names_short[$weekday_id],
                'additional'     => mb_substr(ifset($d, 'additional', ''), 0, 64),
            ];
        }
        return [
            'timezone'      => $timezone,
            'timezone_text' => $timezone_html,
            'days'          => $days,
        ];
    }

    public function getTemplatePath()
    {
        return 'shipping.html';
    }
}
