<?php

class shopCheckoutConfirmation extends shopCheckout
{
    protected $step_id = self::STEP_CONFIRMATION;

    public function display()
    {
        $settings = self::getCheckoutSettings();

        if (waRequest::get('terms')) {
            if (!empty($settings['confirmation']['terms'])) {
                echo $settings['confirmation']['terms'];
                exit;
            } else {
                throw new waException(_ws('Page not found'), 404);
            }
        }

        $contact = $this->getContact();

        if (!$contact) {
            $this->assign('error', _w('Not enough data in the contact information to place the order.'));
            return;
        }
        $order = array(
            'contact'  => $contact,
            'total'    => $this->cart->total(false),
            'items'    => $this->cart->items(false),
            'shipping' => 0,
        );

        shopOrderItemsModel::sortItemsByGeneralSettings($order['items']);
        $order['discount'] = shopDiscounts::calculate($order);


        $shipping_address = $contact->getFirst('address.shipping');
        if (!$shipping_address) {
            $shipping_address = array(
                'data'  => array(),
                'value' => '',
            );
        }
        $billing_address = $contact->getFirst('address.billing');
        if (!$billing_address) {
            $billing_address = array(
                'data'  => array(),
                'value' => '',
            );
        }

        $params = array();

        if ($shipping = $this->getSessionData('shipping')) {
            $params['shipping_id'] = $shipping['id'];
            if ($shipping['id']) {
                $plugin_info = $this->plugin_model->getById($shipping['id']);
                $params['shipping_rate_id'] = $shipping['rate_id'];
                $params['shipping_tax_id'] = ifset($plugin_info['options']['tax_id']);
                $params['shipping_name'] = $shipping['name'];
                $params['shipping_description'] = $plugin_info['description'];

                $order['shipping'] = isset($shipping['rate']) ? (float)$shipping['rate'] : 0;
            }
        }

        if ($payment_id = $this->getSessionData('payment')) {
            $params['payment_id'] = $payment_id;
            $plugin_info = $this->plugin_model->getById($payment_id);
            $params['payment_name'] = $plugin_info['name'];
            $params['payment_plugin'] = $plugin_info['plugin'];
            $params['payment_description'] = $plugin_info['description'];
        }

        $taxes_params = array(
            'shipping'      => $shipping_address['data'],
            'billing'       => $billing_address['data'],
            'discount_rate' => ($order['total']) ? ($order['discount'] / $order['total']) : 0,
        );

        /** @var shopConfig $config */
        $config = wa('shop')->getConfig();


        $frontend_currency = $config->getCurrency(false);

        if (!empty($params['shipping_tax_id'])) {
            $order['items']['%shipping%'] = array(
                'type'     => 'shipping',
                'tax_id'   => $params['shipping_tax_id'],
                'quantity' => 1,
                'price'    => $order['shipping'],
                'currency' => $frontend_currency,
            );
        }

        $taxes = shopTaxes::apply($order['items'], $taxes_params, $frontend_currency);
        unset($order['items']['%shipping%']);

        $tax = 0;
        foreach ($taxes as $t) {
            if (isset($t['sum'])) {
                $tax += $t['sum'];
            }
        }

        unset($order['items']['%shipping%']);

        $this->assign(array(
            'params'           => $params,
            'contact'          => $contact,
            'items'            => $order['items'],
            'shipping'         => $order['shipping'],
            'discount'         => $order['discount'],
            'total'            => $order['total'] - $order['discount'] + $order['shipping'] + $tax,
            'tax'              => $tax,
            'subtotal'         => $order['total'],
            'shipping_address' => $shipping_address,
            'billing_address'  => !empty($settings['contactinfo']['fields']['address.billing']) ? $billing_address : false,
            'terms'            => !empty($settings['confirmation']['terms']) ? $settings['confirmation']['terms'] : false,
        ));

        $this->addFlowStep();
    }


    public function execute()
    {
        if (waRequest::get('terms')) {
            return false;
        }
        if ($comment = waRequest::post('comment')) {
            $this->setSessionData('comment', $comment);
        }
        $settings = self::getCheckoutSettings();
        if (!empty($settings['confirmation']['terms']) && !waRequest::post('terms')) {
            return false;
        }
        return true;
    }

    public function getOptions($config)
    {
        $terms = array();
        $terms_path = wa('shop')->getConfig()->getAppPath('lib/config/data/terms.php');
        if (file_exists($terms_path)) {
            $terms = include($terms_path);
            if (!is_array($terms)) {
                $terms = array();
            }
        }
        $locale = wa()->getLocale();
        if (!isset($terms[$locale])) {
            $locale = 'en_US';
        }

        $terms = isset($terms[$locale]) ? $terms[$locale] : reset($terms);

        if (wa()->whichUI() == '1.3') {
            return '<div class="field">
                <div class="name">'._w('Terms of service').'<br><span class="hint">HTML</span></div>
                <div class="value">
                    <textarea id="confirmation-terms" name="config[terms]">'.(!empty($config['terms']) ? $config['terms'] : '').'</textarea>
                </div>
                <div class="value">
                    <p class="hint">'._w('If you want your customers to be prompted to read and agree to your company’s terms of service, refund and privacy policies or any other legal information during the checkout, enter the text to the field above. A checkbox to agree and a link to read this legal information will be shown on the Confirmation checkout step.').'
                    <a id="confirmation-generate-terms" href="#" class="inline-link"><b><i>'._w('Generate sample policy').'</i></b></a></p>
                </div>
                <div style="display:none" id="confirmation-terms-sample">'.$terms.'</div>
                <script>
                    $("#confirmation-generate-terms").click(function () {
                        var t = $("#confirmation-terms");
                        if (!t.val().length || confirm("'._w('Your current terms of service content will be erased. Are you sure?').'")) {
                            t.val($("#confirmation-terms-sample").html());
                            $("#confirmation-terms-red").show();
                        }
                        return false;
                    });
                    $("#confirmation-terms").keyup(function () {
                        if ($(this).val().length) {
                            $("#confirmation-terms-red").show();
                        } else {
                            $("#confirmation-terms-red").hide();
                        }
                    });
                </script>
            </div>';
        }

        return '<div class="field">
                <div class="name">'._w('Terms of service').'<br><span class="hint">HTML</span></div>
                <div class="value">
                    <ul>
                        <li>
                            <textarea id="confirmation-terms" name="config[terms]">'.(!empty($config['terms']) ? $config['terms'] : '').'</textarea>
                        </li>
                        <li>
                            <p class="hint">'._w('If you want your customers to be prompted to read and agree to your company’s terms of service, refund and privacy policies or any other legal information during the checkout, enter the text to the field above. A checkbox to agree and a link to read this legal information will be shown on the Confirmation checkout step.').'
                            <a id="confirmation-generate-terms" href="#">'._w('Generate sample policy').'</a></p>
                            <div style="display:none" id="confirmation-terms-sample">'.$terms.'</div>
                        </li>
                    </ul>
                </div>

                <script style="display: none">
                    $("#confirmation-generate-terms").click(function () {
                        var t = $("#confirmation-terms");
                        if (!t.val().length || confirm("'._w('Your current terms of service content will be erased. Are you sure?').'")) {
                            t.val($("#confirmation-terms-sample").html());
                            $("#confirmation-terms-red").show();
                        }
                        return false;
                    });
                    $("#confirmation-terms").keyup(function () {
                        if ($(this).val().length) {
                            $("#confirmation-terms-red").show();
                        } else {
                            $("#confirmation-terms-red").hide();
                        }
                    });
                </script>
            </div>';

    }
}
