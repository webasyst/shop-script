<?php
class shopInvoiceruPluginPrintformDisplayAction extends waViewAction
{
    public function execute()
    {
        //XXX check rights
        $plugin_id = 'invoiceru';
        $plugin = waSystem::getInstance()->getPlugin($plugin_id);
        $order = shopPayment::getOrderData($order_id = waRequest::request('order_id', null, waRequest::TYPE_INT));
        if (!$order && ($order_id || (wa()->getEnv() != 'backend'))) {
            throw new waException('Order not found', 404);
        }

        if ($order && $order->items) {
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
            ));
        } else {
            $items = array();
        }

        $this->view->assign('settings', $plugin->getSettings());
        $this->view->assign('order', $order);
        $this->view->assign('items', $items);
    }
}
