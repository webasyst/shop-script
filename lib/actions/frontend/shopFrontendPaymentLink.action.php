<?php
/**
 * Controller for processing links directly to order payment.
 * 1) Renders HTML form that redirects to self (via POST). This protects from messenger prefetch bots:
 *    we don't want to initialize payment plugins unless it's a real human.
 * 2) Then renders a proper form as returned by payment plugin. Most of the time it auto-submits.
 *    In any case, this second form is supposed to redirect user to payment gateway.
 *
 * Template for this action is in app templates, not in theme.
 */
class shopFrontendPaymentLinkAction extends waViewAction
{
    public function execute()
    {
        $hash = waRequest::param('hash');
        if (!preg_match('~^(.{16})(\d+)(.{16})$~', $hash, $matches)) {
            throw new waException(_w('Order not found.'), 404);
        }
        $order_id = substr($hash, 16, -16);
        $order = new shopOrder($order_id);
        if ($hash != $order->getPaymentLinkHash()) {
            throw new waException(_w('Order not found.'), 404);
        }
        if (!empty($order['paid_date'])) {
            $auth_user_id = wa()->getUser()->getId();
            if ($auth_user_id && $auth_user_id == $order['contact_id']) {
                $url = wa()->getRouteUrl('shop/frontend/myOrder', [
                    'id' => $order['id'],
                ], true);
            } else {
                $url = wa()->getRouteUrl('shop/frontend/myOrderByCode', [
                    'id' => $order['id'],
                    'code' => ifset($order, 'params', 'auth_code', ''),
                ], true);
            }
            wa()->getResponse()->redirect($url, 302);
            exit;
        }

        if (waRequest::post('clear_payment_selection')) {
            // Clear order payment plugin selection before attempting to pay via QR code.
            // Otherwise Shop-Script will not accept payment callback from wrong merchant_id.
            (new shopOrderParamsModel())->set($order['id'], [
                'payment_id' => null,
                'payment_plugin' => null,
                'payment_name' => null,
            ], false);
            exit; // we don't care about JSON result and don't want to render template
        }

        $user_agent = waRequest::getUserAgent();
        $this_is_a_bot = preg_match('~WhatsApp|TelegramBot|TwitterBot|facebookexternalhit|Facebot|vkShare|snapchat|Discordbot~i', $user_agent);

        $challenge_passed = false;
        $payment_form_html = null;
        if (waRequest::post() || wa()->getStorage()->get('shop_paymentlink_challenge_passed')) {
            $challenge = waRequest::post('challenge', null, waRequest::TYPE_STRING_TRIM);
            if (wa()->getStorage()->get('shop_paymentlink_challenge_passed') || $challenge === wa()->getStorage()->get('shop_paymentlink_challenge')) {
                $payment_form_html = $this->getPaymentFormHtml($order);
                wa()->getStorage()->set('shop_paymentlink_challenge_passed', true);
                wa()->getStorage()->set('shop/order_id', $order_id); // auth for printforms
                $challenge_passed = true;
            }
        }

        $challenge = wa()->getStorage()->get('shop_paymentlink_challenge');
        if (!$challenge) {
            $challenge = waUtils::getRandomHexString(21);
            wa()->getStorage()->set('shop_paymentlink_challenge', $challenge);
        }

        $payment_options = [];
        $payment_id = waRequest::get('payment_id', null, waRequest::TYPE_INT);
        $show_methods = $challenge_passed;
        if ($show_methods) {
            $methods = shopPayment::getMethodsByOrder($order);
            $payment_ids = waRequest::param('payment_id');
            foreach ($methods as $method_index => &$m) {
                // Ignore manual payment e.g. cash
                if (!empty($m['info']['type']) && $m['info']['type'] == 'manual') {
                    unset($methods[$method_index]);
                } elseif (!empty($payment_ids) && !in_array($method_index, $payment_ids)) {
                    unset($methods[$method_index]);
                }
            }
            unset($m);

            $payment_options = shopPayment::getPaymentOptions($methods, $order);
            $payment_image = shopPayment::getPaymentImage($methods, $order);
        }
        if ($payment_id && $payment_form_html) {
            $order_params_model = new shopOrderParamsModel();
            $plugin_info = (new shopPluginModel())->getByField([
                'id' => $payment_id,
                'type' => 'payment',
            ]);
            $order_params_model->set($order['id'], array_merge($order['params'], [
                'payment_id' => $payment_id,
                'payment_plugin' => ifset($plugin_info, 'plugin', null),
                'payment_name' => ifset($plugin_info, 'name', null),
            ]));
        }

        // Open overlay immediately if there's a QR code and no payment plugin selected
        $auto_open_overlay = !empty($payment_image) && !$payment_form_html;
        // Open overlay immediately if plugin saved in order is WA Pay (unless user clicks WA Pay option in overlat itself)
        $auto_open_overlay = $auto_open_overlay || ($payment_form_html && !$payment_id && ifset($order, 'params', 'payment_plugin', null) === 'pay');

        if (!empty($payment_image) ) {
            $status_check_url = wa()->getRouteUrl('shop/frontend/checkoutSuccessPaymentSelection');
        }

        $this->view->assign([
            'order' => $order,
            'challenge' => $challenge,
            'methods' => $payment_options,
            'payment_image' => ifset($payment_image),
            'status_check_url' => ifset($status_check_url),
            'auto_open_overlay' => $auto_open_overlay,
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
        $payment_index = waRequest::get('index', null, waRequest::TYPE_INT);
        if (!$payment_id) {
            $auto_submit = false;
            $payment_id = ifset($order, 'params', 'payment_id', null);
        } else {
            $auto_submit = true;
        }
        if ($payment_id !== null) {
            try {
                $payment_form_data = [];
                $plugin = shopPayment::getPlugin(null, $payment_id);
                $order_data = shopPayment::getOrderData($order['id'], $plugin);
                if ($plugin instanceof waIPaymentMultipleOptions && $payment_index !== null) {
                    $payment_options = array_values($plugin->paymentOptions($order_data));
                    $payment_form_data = ifempty($payment_options, $payment_index, 'payment_form_data', []);
                }
                $payment_form_data += waRequest::post();
                unset($payment_form_data['challenge']);
                return $plugin->payment($payment_form_data + waRequest::post(), $order_data, $auto_submit);
            } catch (waException $ex) {
                return '';
            }
        } else {
            return null;
        }
    }
}
