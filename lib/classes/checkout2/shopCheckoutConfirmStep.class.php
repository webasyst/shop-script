<?php

/**
 * Sixth checkout step.
 * Output final order data. Validate stuff.
 */
class shopCheckoutConfirmStep extends shopCheckoutStep
{
    public function prepare($data)
    {
        $order = $data['order'];

        // Make sure waOrder recalculates discount instead of using cached value.
        // Discounts were calculated once in 'shipping' step code
        // so that all shipping plugins get proper discounted prices.
        // But it has to run again at this time so that discount plugins have a chance
        // to see proper selected shipping plugin.
        $order->discount = 'calculate';

        $result = [
            'subtotal'      => $order['subtotal'],
            'discount'      => $order['discount'],
            'shipping'      => $order['shipping'],
            'tax'           => $order['not_included_tax'],
            'total'         => $order['total'],
            'currency_info' => $this->getCurrencyInfo($order['currency']),
        ];

        $errors = [];

        // Delayed errors from auth step
        if ($data['origin'] == 'create') {
            if (!empty($data['auth']['delayed_errors'])) {
                foreach ($data['auth']['delayed_errors'] as $field_name => $error_text) {
                    $errors[] = [
                        'name'    => $field_name,
                        'text'    => $error_text,
                        'section' => 'auth',
                    ];
                }
            }
            if (!empty($data['details']['delayed_errors'])) {
                foreach ($data['details']['delayed_errors'] as $field_name => $error_text) {
                    $errors[] = [
                        'name'    => $field_name,
                        'text'    => $error_text,
                        'section' => 'details',
                    ];
                }
            }

            $errors = array_merge($errors, $this->confirmationChannelErrors($data));
        }

        $result['comment'] = ifset($data, 'input', 'confirm', 'comment', '');
        $result['terms'] = ifset($data, 'input', 'confirm', 'terms', '');
        $timezone = ifset($data, 'input', 'confirm', 'timezone', null);
        if ($timezone && in_array($timezone, DateTimeZone::listIdentifiers())) {
            $data['order']['params'] += ['customer_timezone' => $timezone];
        }

        if ($this->checkout_config['confirmation']['terms'] && !$result['terms']) {
            $errors[] = [
                'name'    => 'confirm[terms]',
                'text'    => _w('You have not accepted the terms of service.'),
                'section' => 'confirm',
            ];
        }

        // Render template in case process() won't have a chance to do that
        $result = $this->addRenderedHtml($result, $data, $errors);

        return array(
            'result'       => $result,
            'errors'       => $errors,
            'can_continue' => !$errors,
        );
    }

    public function getTemplatePath()
    {
        return 'confirm.html';
    }

    protected function getCurrencyInfo($currency, $locale = null)
    {
        /** @var shopConfig $config */
        $config = wa('shop')->getConfig();
        $currency_info = reset(ref($config->getCurrencies($currency)));
        $locale_info = waLocale::getInfo(ifset($locale, wa()->getLocale()));

        return [
            'code'             => $currency_info['code'],
            'fraction_divider' => ifset($locale_info, 'decimal_point', '.'),
            'fraction_size'    => ifset($currency_info, 'precision', 2),
            'group_divider'    => ifset($locale_info, 'thousands_sep', ''),
            'group_size'       => 3,

            'pattern_html' => str_replace('0', '%s', waCurrency::format('%{h}', 0, $currency)),
            'pattern_text' => str_replace('0', '%s', waCurrency::format('%{s}', 0, $currency)),

            'is_primary'    => $currency_info['is_primary'],
            'rate'          => $currency_info['rate'],
            'rounding'      => $currency_info['rounding'],
            'round_up_only' => $currency_info['round_up_only'],
        ];
    }

    protected function confirmationChannelErrors($data)
    {
        $options = [
            'is_company' => (int)$data['contact']['is_company'],
            'address'    => [
                'email' => ifset($data, 'result', 'auth', 'fields', 'email', 'value', null),
                'phone' => ifset($data, 'result', 'auth', 'fields', 'phone', 'value', null),
            ]
        ];
        $errors = [];

        $confirmation = new shopConfirmationChannel($options);
        $confirm_channel = $confirmation->getConfirmChannel();

        if ($confirm_channel) {
            $errors[] = [
                'id'   => 'confirm_channel',
                'text' => _w('Please confirm your contact data.'),
                'type' => $confirm_channel,
            ];
        }

        return $errors;
    }

}
