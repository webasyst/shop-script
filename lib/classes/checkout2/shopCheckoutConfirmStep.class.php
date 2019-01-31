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
        $result = [
            'subtotal'      => $order['subtotal'],
            'discount'      => $order['discount'],
            'shipping'      => $order['shipping'],
            'tax'           => $order['tax'],
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

            // Validate cart against stock counts
            $cart_is_ok = !array_filter($this->getCartItems(), function ($item) {
                return isset($item['error']);
            });
            if (!$cart_is_ok) {
                $errors[] = [
                    'id'      => 'cart_invalid',
                    'text'    => _w('Some items in your order are not available. Please remove them from your cart.'),
                    'section' => 'cart',
                ];
            }
        }

        $result['comment'] = ifset($data, 'input', 'confirm', 'comment', '');
        $result['terms'] = ifset($data, 'input', 'confirm', 'terms', '');
        $result['channel'] = $this->getChannelData();

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

    protected function getCartItems()
    {
        // This is global and should be overridden in subclasses
        $cart_vars = (new shopCheckoutViewHelper())->cartVars();
        return ifset($cart_vars, 'cart', 'items', []);
    }

    protected function getChannelData()
    {
        $result = [
            'status'             => $this->getChannelStatus(),
            'order_without_auth' => $this->checkout_config['confirmation']['order_without_auth'],
            'auth_with_code'     => $this->checkout_config['confirmation']['auth_with_code'],
        ];

        return $result;
    }

    protected function getChannelStatus()
    {
        $user = wa()->getUser();

        if ($user->isAuth()) {
            $auth_type = 'email';
            if ($auth_type === 'email') {
                $channel = $user->get('email');
            } elseif ($auth_type === 'sms') {
                $channel = $user->get('phone');
            }

            $status = ifset($channel, '0', 'status', null);
        }

        if (empty($status)) {
            $status = wa('shop')->getStorage()->get('channel_confirmation');
            $status = $status ? $status : 'unconfirmed';
        }

        return $status;
    }
}
