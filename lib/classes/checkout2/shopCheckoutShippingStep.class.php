<?php
/**
 * Third checkout step. Determine shipping options based on region defined in previous step.
 * Accept user selection of one of available shipping options.
 */
class shopCheckoutShippingStep extends shopCheckoutStep
{
    public function process($data, $prepare_result)
    {
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

        $config = $this->getCheckoutConfig();
        if (!$config['shipping']['ask_zip']) {
            unset($address['zip']);
        }

        if (empty($address['country']) || empty($address['region']) || empty($address['city'])) {
            // This can not happen. It means previous step did not properly validate shipping region selection,
            // or a plugin interfered and broke things, or some other terrible stuff occurred. Like, Godzilla. Blame Godzilla.
            $errors = [
                'general_error' => 'Unable to prepare list of shipping options because shipping region is not properly selected.',
            ];
            return array(
                'data'         => $data,
                'result'       => $this->addRenderedHtml([], $data, $errors),
                'errors'       => $errors,
                'can_continue' => false,
            );
        }

        /** @var waStorage $storage to use instead of wa()->getStorage() being friendly to unit tests */
        $storage = $data['storage'];

        /** @var shopOrder $order we take cart items from there */
        $order = $data['order'];
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
            $errors = [
                'empty_region_options' => _w('No shipping options available for selected region.'),
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
                'id'          => 'pickup',
                'is_selected' => false,
                'name'        => $config['shipping']['pickuppoint_name'],
                'rate_min'    => null, // null|number
                'rate_max'    => null, // null|number
                'currency'    => $data['order']['currency'],
                'date_min'    => null, // null|'Y-m-d H:i:s'
                'date_max'    => null, // null|'Y-m-d H:i:s'
                'date_min_ts' => null, // null|int
                'date_max_ts' => null, // null|int
                'variants'    => [],
            ],
            'todoor' => [
                'id'          => 'todoor',
                'is_selected' => false,
                'name'        => $config['shipping']['courier_name'],
                'rate_min'    => null,
                'rate_max'    => null,
                'currency'    => $data['order']['currency'],
                'date_min'    => null,
                'date_max'    => null,
                'date_min_ts' => null,
                'date_max_ts' => null,
                'variants'    => [],
            ],
            'post'   => [
                'id'          => 'post',
                'is_selected' => false,
                'name'        => $config['shipping']['post_name'],
                'rate_min'    => null,
                'rate_max'    => null,
                'currency'    => $data['order']['currency'],
                'date_min'    => null,
                'date_max'    => null,
                'date_min_ts' => null,
                'date_max_ts' => null,
                'variants'    => [],
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
            $type =& $shipping_types[$s['type']];
            $s = self::prepareShippingVariant($s, $currencies);
            if (!empty($s['custom_data']['pickup']['schedule'])) {
                if (is_array($s['custom_data']['pickup']['schedule'])) {
                    if (!isset($timezones)) {
                        $timezones = waDateTime::getTimeZones();
                    }
                    $plugin_timezone = ifset($s, 'custom_data', 'pickup', 'timezone', null);
                    $s['pickup_schedule'] = self::formatPickupSchedule($s['custom_data']['pickup']['schedule'], $timezones, $config['schedule']['timezone'], $plugin_timezone);
                } else {
                    //$s['pickup_schedule_html'] = $s['custom_data']['pickup']['schedule'];
                }
            }

            $s['is_selected'] = $selected_variant_id && $selected_variant_id == $s_id;
            if ($s['is_selected']) {
                $selected_type_id = $type['id'];
                $proper_variant_is_selected = true;
            }

            // Update type rates
            if (!empty($s['currency']) && !empty($currencies[$s['currency']]) && isset($s['rate_min']) && isset($s['rate_max'])) {
                $rate_min = shop_currency($s['rate_min'], $s['currency'], $type['currency'], false);
                $rate_max = shop_currency($s['rate_max'], $s['currency'], $type['currency'], false);
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
        }

        if ($selected_type_id && isset($shipping_types[$selected_type_id])) {
            $shipping_types[$selected_type_id]['is_selected'] = true;
        } else {
            $selected_type_id = null;
        }

        $errors = [];
        if (!$selected_variant_id) {
            $errors = [
                'shipping[variant_id]' => _w('Please select shipping option.'),
            ];
        } else {
            // This is used by Details step
            $data['shipping']['address'] = $address;
            $data['shipping']['selected_variant'] = $shipping_types[$selected_type_id]['variants'][$selected_variant_id];
        }

        $result = $this->addRenderedHtml([
            'selected_type_id'    => $selected_type_id,
            'selected_variant_id' => $selected_variant_id,
            'types'               => $shipping_types,
        ], $data, $errors);

        if ($data['origin'] !== 'form' && 'only' === ifset($data, 'input', 'shipping', 'html', null)) {
            unset($result['types']);
        }

        return array(
            'data'         => $data,
            'result'       => $result,
            'errors'       => $errors,
            'can_continue' => !$errors,
        );
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
            if ($rate) {
                if (is_array($rate)) {
                    $s['rate_min'] = min($rate);
                    $s['rate_max'] = max($rate);
                } else {
                    $s['rate_min'] = $rate;
                    $s['rate_max'] = $rate;
                }
            }
        }

        // Parse dates
        $s['date_min'] = null;
        $s['date_max'] = null;
        $s['date_min_ts'] = null;
        $s['date_max_ts'] = null;
        $date = ifset($s, 'delivery_date', null); // this is unix timestamp or array of them
        if ($date) {
            if (is_array($date)) {
                $s['date_min_ts'] = min($date);
                $s['date_max_ts'] = max($date);
                $s['date_min'] = date('Y-m-d H:i:s', $s['date_min_ts']);
                $s['date_max'] = date('Y-m-d H:i:s', $s['date_max_ts']);
            } else {
                $s['date_min_ts'] = $date;
                $s['date_min'] = date('Y-m-d H:i:s', $s['date_min_ts']);
            }
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
        $timezone = $timezone ? $timezone : $default_timezone;

        $timezone_html = ifset($timezones, $timezone, $timezone);
        $days = [];
        $weekday_names_full = waDateTime::getWeekdayNames();
        $weekday_names_short = waDateTime::getWeekdayNames('ucfirst', 'short');
        foreach ($schedule['weekdays'] as $d) {
            list($date, $time_start) = explode(' ', $d['start_work']);
            list($_, $time_end) = explode(' ', $d['end_work']);
            $weekday_id = date('N', strtotime($d['start_work']));
            if ($time_start && $time_start{0} === '0') {
                $time_start = substr($time_start, 1);
            }
            if ($time_end && $time_end{0} === '0') {
                $time_end = substr($time_end, 1);
            }

            // pass in default time zone to make it not convert the time
            $date_formatted = waDateTime::format('humandate', $date, waDateTime::getDefaultTimeZone());
            $date_formatted = trim(str_replace(date('Y'), '', $date_formatted));

            $days[] = [
                'date'           => $date,
                'works'          => $d['type'] == 'workday',
                'date_formatted' => $date_formatted,
                'time_start'     => $time_start,
                'time_end'       => $time_end,
                'weekday_full'   => $weekday_names_full[$weekday_id],
                'weekday_short'  => $weekday_names_short[$weekday_id],
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
        return wa()->getAppPath('templates/actions/frontend/order/form/shipping.html', 'shop');
    }
}
