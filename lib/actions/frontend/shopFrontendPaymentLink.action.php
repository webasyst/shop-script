<?php
/**
 * Controller for processing links directly to order payment.
 * 1) Renders HTML form that redirects to self (via POST). This protects from messenger prefetch bots:
 *    we don't want to initialize payment plugins unless it's a real human.
 * 2) Then renders a proper form as returned by payment plugin. Most of the time it auto-submits.
 *    In anu case, this second form is supposed to redirect user to payment gateway.
 *
 * Template for this action is in app templates, not in theme.
 */
class shopFrontendPaymentLinkAction extends waViewAction
{
    public function execute()
    {
        $hash = waRequest::param('hash');
        if (!preg_match('~^(.{16})(.+)(.{16})$~', $hash, $matches)) {
            throw new waException(_w('Order not found.'), 404);
        }
        $order_id = substr($hash, 16, -16);
        $order = new shopOrder($order_id);
        if ($hash != $order->getPaymentLinkHash()) {
            throw new waException(_w('Order not found.'), 404);
        }

        $user_agent = waRequest::getUserAgent();
        $this_is_a_bot = preg_match('~WhatsApp|TelegramBot|TwitterBot|facebookexternalhit|Facebot|vkShare|snapchat|Discordbot~i', $user_agent);

        $payment_form_html = null;
        if (waRequest::post() || wa()->getStorage()->get('shop_paymentlink_challenge_passed')) {
            $challenge = waRequest::post('challenge', null, waRequest::TYPE_STRING_TRIM);
            if (wa()->getStorage()->get('shop_paymentlink_challenge_passed') || $challenge === wa()->getStorage()->get('shop_paymentlink_challenge')) {
                $payment_form_html = $this->getPaymentFormHtml($order);
                wa()->getStorage()->set('shop_paymentlink_challenge_passed', true);
                wa()->getStorage()->set('shop/order_id', $order_id); // auth for printforms
            }
        }

        $challenge = wa()->getStorage()->get('shop_paymentlink_challenge');
        if (!$challenge) {
            $challenge = waUtils::getRandomHexString(21);
            wa()->getStorage()->set('shop_paymentlink_challenge', $challenge);
        }

        $methods = [];
        $payment_id = waRequest::get('payment_id', null, waRequest::TYPE_INT);
        $show_methods = waRequest::post('challenge', null, waRequest::TYPE_STRING_TRIM) && empty($payment_form_html);
        if ($show_methods) {
            $methods = $this->getMethods($order);
        }
        if ($payment_id && $payment_form_html && empty($order['params']['payment_id'])) {
            $order_params_model = new shopOrderParamsModel();
            $order_params_model->set($order['id'], array_merge($order['params'], ['payment_id' => $payment_id]));
        }

        $this->view->assign([
            'order' => $order,
            'methods' => $methods,
            'challenge' => $challenge,
            'payment_form_html' => $payment_form_html,
            'enable_auto_submit' => !$this_is_a_bot,
            'show_methods' => $show_methods
        ]);
    }

    public function getPaymentFormHtml(shopOrder $order)
    {
        if (!$order['state']->paymentAllowed()) {
            return $order['state']->paymentNotAllowedText();
        }

        $payment_id = waRequest::get('payment_id', null, waRequest::TYPE_INT);
        if (!$payment_id) {
            $payment_id = ifset($order, 'params', 'payment_id', null);
        }
        if ($payment_id !== null) {
            try {
                $plugin = shopPayment::getPlugin(null, $payment_id);
                return $plugin->payment(waRequest::post(), shopPayment::getOrderData($order['id'], $plugin), false);
            } catch (waException $ex) {
                return '';
            }
        } else {
            return null;
        }
    }

    protected function getMethods($order)
    {
        $plugin_model = new shopPluginModel();
        $methods = $plugin_model->listPlugins(shopPluginModel::TYPE_PAYMENT);

        $currencies = wa()->getConfig()->getCurrencies();
        $order_has_frac = shopFrac::itemsHaveFractionalQuantity($order->items);
        $order_has_units = shopUnits::itemsHaveCustomStockUnits($order->items);

        foreach ($methods as $method_index => &$m) {
            // Some plugins are disabled
            if (empty($m['available']) || (!empty($m['info']['type']) && $m['info']['type'] == 'manual')) {
                unset($methods[$method_index]);
                continue;
            }

            try {
                $plugin = shopPayment::getPlugin($m['plugin'], $m['id']);
                $plugin_info = $plugin->info($m['plugin']);
                $methods[$method_index]['icon'] = ifset($plugin_info, 'icon', null);
                $allowed_currencies = $plugin->allowedCurrency();
                if ($allowed_currencies !== true) {
                    $allowed_currencies = (array)$allowed_currencies;
                    if (!array_intersect($allowed_currencies, array_keys($currencies))) {
                        $format = _w('Payment procedure cannot be processed because required currency %s is not defined in your store settings.');
                        throw new waException(sprintf($format, implode(', ', $allowed_currencies)));
                    }
                }
            } catch (waException $ex) {
                waLog::log($ex->getMessage(), 'shop/paymentlink.error.log');
                unset($methods[$method_index]);
                continue;
            }

            if ($order_has_units && shopUnits::stockUnitsEnabled()) {
                if (!isset($plugin_info['stock_units'])) {
                    $plugin_mode = shopFrac::getPluginFractionalMode($m['plugin'], shopFrac::PLUGIN_MODE_UNITS);
                    if ($plugin_mode == shopFrac::PLUGIN_TRANSFER_DISABLED) {
                        // Store admin disabled this payment method for orders containing custom stock units
                        unset($methods[$method_index]);
                        continue;
                    }
                } else if ($plugin_info['stock_units'] !== true) {
                    // Plugin declared it does not support custom stock units
                    unset($methods[$method_index]);
                    continue;
                }
            }
            if ($order_has_frac && shopFrac::isEnabled()) {
                if (!isset($plugin_info['fractional_quantity'])) {
                    $plugin_mode = shopFrac::getPluginFractionalMode($m['plugin']);
                    if ($plugin_mode == shopFrac::PLUGIN_TRANSFER_DISABLED) {
                        // Store admin disabled this payment method for orders containing fractional quantities
                        unset($methods[$method_index]);
                    }
                } else if ($plugin_info['fractional_quantity'] !== true) {
                    // Plugin declared it does not support fractional quantities
                    unset($methods[$method_index]);
                }
            }
        }

        return $methods;
    }
}
