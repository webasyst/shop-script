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
            'subtotal' => $order['subtotal'],
            'discount' => $order['discount'],
            'shipping' => $order['shipping'],
            'tax' => $order['tax'],
            'total' => $order['total'],
            'currency_info' => $this->getCurrencyInfo($order['currency']),
        ];

        $errors = [];

        // Delayed errors from auth step
        if ($data['origin'] == 'create') {
            if (!empty($data['auth']['delayed_errors'])) {
                foreach($data['auth']['delayed_errors'] as $field_name => $error_text) {
                    $errors[] = [
                        'name' => $field_name,
                        'text' => $error_text,
                        'section' => 'auth',
                    ];
                }
            }
            if (!empty($data['details']['delayed_errors'])) {
                foreach($data['details']['delayed_errors'] as $field_name => $error_text) {
                    $errors[] = [
                        'name' => $field_name,
                        'text' => $error_text,
                        'section' => 'details',
                    ];
                }
            }

            // Validate cart against stock counts
            $cart_is_ok = !array_filter($this->getCartItems(), function($item) {
                return isset($item['error']);
            });
            if (!$cart_is_ok) {
                $errors[] = [
                    'id' => 'cart_invalid',
                    'text' => _w('Some items in your order are not available. Please remove them from your cart.'),
                    'section' => 'cart',
                ];
            }
        }

        $result['comment'] = ifset($data, 'input', 'confirm', 'comment', '');
        $result['terms'] = ifset($data, 'input', 'confirm', 'terms', '');

        // Render template in case process() won't have a chance to do that
        $result = $this->addRenderedHtml($result, $data, $errors);

        return array(
            'result' => $result,
            'errors' => $errors,
            'can_continue' => !$errors,
        );
    }

    public function getTemplatePath()
    {
        return 'confirm.html';
    }

    protected function getCurrencyInfo($currency, $locale = null)
    {
        $currency_info = reset(ref(wa('shop')->getConfig()->getCurrencies($currency)));
        $locale_info = waLocale::getInfo(ifset($locale, wa()->getLocale()));

        return [
            'code' => $currency_info['code'],
            'fraction_divider' => $locale_info['decimal_point'],
            'fraction_size' => $currency_info['precision'],
            'group_divider' => $locale_info['thousands_sep'],
            'group_size' => 3,

            'pattern_html' => str_replace('0', '%s', waCurrency::format('%{h}', 0, $currency)),
            'pattern_text' => str_replace('0', '%s', waCurrency::format('%{s}', 0, $currency)),

            'is_primary' => $currency_info['is_primary'],
            'rate' => $currency_info['rate'],
            'rounding' => $currency_info['rounding'],
            'round_up_only' => $currency_info['round_up_only'],
        ];
    }

    protected function getCartItems()
    {
        // This is global and should be overriden in subclasses
        $cart_vars = (new shopCheckoutViewHelper())->cartVars();
        return ifset($cart_vars, 'cart', 'items', []);
    }
}
