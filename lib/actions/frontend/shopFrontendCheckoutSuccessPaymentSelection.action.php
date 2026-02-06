<?php
/**
 * Part of /checkout/success page. Draws a list of payment options. Click on any option
 * reloads the page with selected payment plugin's form (with autosubmit).
 * 
 * Also renders a single payment image if at least one payment plugin supports it.
 * Periodically checks in backgroud whether order has been paid via the image.
 * Reloads the page if so (showing a proper success page without payment options).
 * 
 * Background checks are processed with this controller, executeBackgroundCheck() method.
 */
class shopFrontendCheckoutSuccessPaymentSelectionAction extends waViewAction
{
    protected $order;
    protected $methods;

    protected function loadOrderAndMethods($order_id)
    {
        $this->order = new shopOrder($order_id);
        $this->methods = shopPayment::getMethodsByOrder($this->order);
        if ( ( $payment_ids = waRequest::param('payment_id'))) {
            $this->methods = array_intersect_key($this->methods, array_fill_keys((array)$payment_ids, true));
        }

        $order_selected_payment_id = ifset($this->order, 'params', 'payment_id', null);
        if ($order_selected_payment_id) {
            $this->methods = array_intersect_key($this->methods, [$order_selected_payment_id => 1]);
        }
    }

    public function execute()
    {
        $order_id = ifset($this->params, 'order_id', null);
        if (!$order_id) {
            throw new waException(); // can not happen
        }
        $this->loadOrderAndMethods($order_id);

        // Only keep a single plugin in case user selected it
        $form_payment_id = waRequest::get('payment_id', null, 'string');
        $form_option_index = waRequest::get('index', null, 'int');
        if ($form_payment_id) {
            $this->methods = array_intersect_key($this->methods, [$form_payment_id => 1]);
        }

        // Load payment options for all plugins
        // This also loads $m['instance'] for all and $m['order_data'] for some of the methods
        $payment_options = shopPayment::getPaymentOptions($this->methods, $this->order);

        // First plugin that supports payment by image (QR code) gets to show its image
        $payment_image = shopPayment::getPaymentImage($this->methods, $this->order);

        // If user selected a specific payment option, then instead of plugin list show (and autosubmit) the form
        $payment_form_html = null;
        if ($form_payment_id) {
            foreach ($payment_options as $opt) {
                if ($opt['id'] == $form_payment_id && $opt['index'] === $form_option_index) {
                    $m = $this->methods[$opt['id']];
                    $plugin = $m['instance'];
                    try {
                        $payment_form_data = ifset($m, 'payment_options', $form_option_index, 'payment_form_data', []);
                        $m['order_data'] = $m['order_data'] ?? shopPayment::getOrderData($this->order['id'], $plugin);
                        $payment_form_html = $plugin->payment($payment_form_data, $m['order_data'], true);

                        // Save selected payment_id to order
                        (new shopOrderParamsModel())->set($this->order['id'], array_merge($this->order['params'], [
                            'payment_id' => $form_payment_id,
                            'payment_plugin' => ifset($m, 'plugin', null),
                            'payment_name' => ifset($m, 'name', null),
                        ]));
                    } catch (waException $ex) {
                        $payment_form_html = $ex->getMessage();
                    }
                    break;
                }
            }
        }

        if ($payment_image) {
            $status_check_url = wa()->getRouteUrl('shop/frontend/checkoutSuccessPaymentSelection');
        }

        $this->view->assign([
            'payment_image' => $payment_image,
            'payment_options' => $payment_options,
            'payment_form_html' => $payment_form_html,
            'status_check_url' => ifset($status_check_url),
            'order_id' => $this->order['id_str'],
            'order_total_html' => shop_currency_html($this->order['total'], $this->order['currency'], $this->order['currency']),
        ]);
    }

    public function executeBackgroundCheck()
    {
        $order_id = wa()->getStorage()->get('shop/order_id');
        if ($order_id) {

            // Query payment plugin if order state has changed
            try {
                $this->loadOrderAndMethods($order_id);
                if (empty($this->order['paid_date'])) {
                    $plugin = null;
                    foreach($this->methods as $m) {
                        $plugin = $m['instance'] ?? shopPayment::getPlugin($m['plugin'], $m['id']);
                        if (shopPayment::pluginSupportsQRCode($plugin)) {
                            break;
                        }
                    }
                    shopPayment::statePolling($this->order, $plugin);
                }
            } catch (Throwable $e) {
            }

            try {
                // Reload order info in case state polling changed its state
                $order_model = new shopOrderModel();
                $order = $order_model->getById($order_id);
                if ($order) {
                    return ['is_paid' => !empty($order['paid_date']), 'order_id' => $order_id,];
                }
            } catch (Throwable $e) {
            }
        }
        return ['is_paid' => false, 'error' => true];
    }

    public function display($clear_assign = true)
    {
        try {
            $order_id = ifset($this->params, 'order_id', null);
            if ($order_id === null) {
                // Background checker for order status
                $response = $this->executeBackgroundCheck();
                $this->getResponse()->addHeader('Content-Type', 'application/json');
                $this->getResponse()->sendHeaders();
                echo waUtils::jsonEncode($response);
                return '';
            }

            // Call as a part of checkout/success screen
            return parent::display($clear_assign);
        } catch (Throwable $e) {
            if (SystemConfig::isDebug() && wa()->getUser()->get('is_user') > 0) {
                return (string) $e;
            }
            return '';
        }
    }
}
