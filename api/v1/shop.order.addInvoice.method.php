<?php

class shopOrderAddInvoiceMethod extends shopApiMethod
{
    protected $method = 'POST';

    public function execute()
    {
        if (wa()->getUser()->getRights('shop', 'orders') == shopRightConfig::RIGHT_ORDERS_COURIER) {
            throw new waAPIException('access_denied', 'Action not available for user', 403);
        }
        $contact_id = $this->post('contact_id');
        if ($contact_id !== null) {
            if  ($contact_id > 0) {
                $contact = new waContact($contact_id);
            } else {
                $contact_id = null;
            }
        }

        $currency = $this->post('currency');
        if (!$currency) {
            $currency = wa('shop')->getConfig()->getCurrency();
        }

        $params = waRequest::post('params', [], 'array');
        $comment = $this->post('comment');
        $order = [
            'contact' => $contact_id === null ? 0 : $contact,
            'items' => [[
                'quantity' => 1,
                'type' => 'product',
                'product_id' => 0,
                'sku_id' => 0,
                'sku_code' => '',
                'purchase_price' => 0,
                'sku_name' => '',
                'currency' => $currency,
                'price' => $this->post('total', true),
                'name' => mb_strlen(trim($comment)) ? mb_substr(trim($comment), 0, 255) : wa('shop')->getConfig()->getOrderNoproductItemName(),
            ]],
            'currency' => $currency,
            'shipping' => 0,
            'discount' => 0,
            'params' => [
                'storefront' => wa()->getConfig()->getDomain(),
                'ip' => waRequest::getIp(),
                'user_agent' => ifempty(ref(waRequest::getUserAgent()), 'api'),
                'api_contact_id' => wa()->getUser()->getId(),
                'prepayment' => 1,
                'payment_id' => $this->post('payment_id'),
            ] + $params,
        ];

        if (isset($contact)) {
            foreach (array('shipping', 'billing') as $ext) {
                $address = $contact->getFirst('address.'.$ext);
                if ($address) {
                    foreach ($address['data'] as $k => $v) {
                        $order['params'][$ext.'_address.'.$k] = $v;
                    }
                }
            }
        }

        $workflow = new shopWorkflow();
        if ($order_id = $workflow->getActionById('create')->run($order)) {
            $_GET['id'] = $order_id;
            $method = new shopOrderGetInfoMethod();
            $this->response = $method->getResponse(true);
        } else {
            throw new waAPIException('server_error', 'Error', 500);
        }
    }
}
