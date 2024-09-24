<?php

class shopOrderInitpayMethod extends shopApiMethod
{
    protected $method = 'POST';

    public function execute()
    {
        $order_id = $this->post('id', true);
        $payment_id = $this->post('payment_id', false);
        $payment_params = $this->post('payment_params', false);
        if (!$order_id) {
            throw new waAPIException('not_found', _w('Order not found.'), 404);
        }

        try {
            if ($payment_id) {
                $so = new shopOrder([
                    'id' => $order_id,
                    'payment_params' => ifempty($payment_params, []),
                    'discount' => null, // hold previously saved discount, do not recalculate
                    'params' => [
                        'payment_id' => $payment_id,
                    ],
                ], [
                    'ignore_stock_validate'  => true,
                ]);
                try {
                    $so->save();
                } catch (waException $ex) {
                    throw new waAPIException('error_saving_order', sprintf_wp('Unable to update order data: “%s”', $ex->getMessage()), 400, [
                        'error_details' => $so->errors(),
                    ]);
                    return;
                }
            }
            $so = new shopOrder($order_id);
        } catch (waException $e) {
            throw new waAPIException('not_found', _w('Order not found.'), 404);
        }
        if (!$so['state']->paymentAllowed()) {
            throw new waAPIException('order_cant_be_paid', _w('Payment is not available for this order.'), 400);
        }

        // If selected payment plugin requires initpay, then do that
        $actions = $so['state']->getActions((new shopOrderModel())->getById($order_id));
        if (!empty($actions['initpay'])) {
            try {
                $actions['initpay']->run($order_id);
                $this->response = [];
            } catch (waException $e) {
                throw new waAPIException('payment_plugin_initpay_error', _w('Unable to initialize payment.'), 500);
            }
            return;
        }

        // Otherwise, if selected payment plugin supports waIPaymentImage->image(), then use that
        $payment_plugin = $so['payment_plugin'];
        if ($payment_plugin && $payment_plugin instanceof waIPaymentImage) {
            $wa_order = shopPayment::getOrderData($so['id'], $payment_plugin);
            try {
                $payment_image_data = $payment_plugin->image($wa_order);
            } catch (waException $e) {
                throw new waAPIException('payment_plugin_image_error', _w('Payment initialization error: unable to generate an image.'), 500);
            }
            $this->response = [
                'payment_image_url' => ifset($payment_image_data, 'image_url', null),
                'payment_image_data_url' => ifset($payment_image_data, 'image_data_url', null),
            ];
            return;
        }

        if (!empty($so['state']->getActions()['initpay'])) {
            throw new waAPIException('unsupported_payment_type', _w('Selected payment method does not support payment initialization.'), 400);
        } else {
            throw new waAPIException('unsupported_payment_type', _w('Selected payment method does not support payment by image. Payment initialization is disabled for the current order status.'), 400);
        }
    }
}
