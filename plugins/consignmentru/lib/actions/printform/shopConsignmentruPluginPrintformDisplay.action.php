<?php

class shopConsignmentruPluginPrintformDisplayAction extends waViewAction
{
    public function execute()
    {
        $plugin_id = 'consignmentru';
        $plugin = waSystem::getInstance()->getPlugin($plugin_id);
        /**
         * @var shopConsignmentruPlugin $plugin
         */
        $order_id = waRequest::request('order_id', null, waRequest::TYPE_INT);
        $order = shopPayment::getOrderData($order_id, $this->allowedCurrency());
        switch (wa()->getEnv()) {
            case 'backend':
                if (!wa()->getUser()->getRights('shop', 'orders')) {
                    throw new waRightsException('Access denied');
                }
                if (!$order && !$order_id) {
                    $allowed_currency = $this->allowedCurrency();
                    $dummy_order = array(
                        'contact_id' => $this->getUser()->getId(),
                        'id'         => 1,
                        'id_str'     => shopHelper::encodeOrderId(1),
                        'currency'   => reset($allowed_currency),
                    );
                    $order = waOrder::factory($dummy_order);
                } elseif (!$order) {
                    throw new waException('Order not found', 404);
                }
                break;
            default:
                if (!$order) {
                    throw new waException('Order not found', 404);
                }
                break;
        }

        if ($order->id && $order->items) {
            $items = $this->getItems($order);
        } else {
            $items = array();
        }

        $this->setTemplate($plugin->getTemplatePath());

        $this->view->assign('settings', $plugin->getSettings());
        $this->view->assign('order', $order);
        $this->view->assign('items', $items);
    }

    public function allowedCurrency()
    {
        return array('RUB', 'UAH');
    }

    /**
     *
     * @param waOrder $order
     * @return array
     */
    private function getItems($order)
    {
        $items = $order->items;
        $product_model = new shopProductModel();
        foreach ($items as & $item) {
            $data = $product_model->getById($item['product_id']);
            $item['tax_id'] = ifset($data['tax_id']);
            $item['currency'] = $order->currency;
        }

        unset($item);
        shopTaxes::apply($items, array(
            'billing'  => $order->billing_address,
            'shipping' => $order->shipping_address,
        ), $order->currency);

        if ($order->discount) {
            if ($order->total + $order->discount - $order->shipping > 0) {
                $k = 1.0 - ($order->discount) / ($order->total + $order->discount - $order->shipping);
            } else {
                $k = 0.0;
            }

            foreach ($items as & $item) {
                if ($item['tax_included']) {
                    $item['tax'] = round($k * $item['tax'], 4);
                }
                $item['price'] = round($k * $item['price'], 4);
                $item['total'] = round($k * $item['total'], 4);
            }
            unset($item);
        }
        return $items;
    }
}
