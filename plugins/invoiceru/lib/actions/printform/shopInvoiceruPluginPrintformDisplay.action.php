<?php

class shopInvoiceruPluginPrintformDisplayAction extends waViewAction
{
    public function execute()
    {
        if (!wa()->getUser()->getRights('shop', 'orders')) {
            throw new waRightsException('Access denied');
        }

        $plugin = waSystem::getInstance()->getPlugin('invoiceru');
        /**
         * @var shopInvoiceruPlugin $plugin
         */

        $order = $plugin->getOrder(waRequest::request('order_id', null, waRequest::TYPE_INT));

        if ($order && $order->items) {
            $items = $this->getItems($order);
        } else {
            $items = array();
        }

        $settings = $plugin->getSettings();

        $contact = array(
            'inn'     => empty($settings['CUSTOMER_INN_FIELD']) ? '' : $order->getContactField($settings['CUSTOMER_INN_FIELD']),
            'kpp'     => empty($settings['CUSTOMER_KPP_FIELD']) ? '' : $order->getContactField($settings['CUSTOMER_KPP_FIELD']),
            'phone'   => empty($settings['CUSTOMER_PHONE_FIELD']) ? '' : $order->getContactField($settings['CUSTOMER_PHONE_FIELD'],'value'),
            'company' => empty($settings['CUSTOMER_COMPANY_FIELD']) ? '' : $order->getContactField($settings['CUSTOMER_COMPANY_FIELD']),

        );
        $this->setTemplate($plugin->getTemplatePath());
        $this->view->assign(compact('settings', 'order', 'items', 'contact'));
    }

    /**
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
        $params = array(
            'billing'  => $order->billing_address,
            'shipping' => $order->shipping_address,
        );
        shopTaxes::apply($items, $params, $order->currency);

        if ($order->discount) {
            if ($order->total + $order->discount - $order->shipping > 0) {
                $k = 1.0 - ($order->discount) / ($order->total + $order->discount - $order->shipping);
            } else {
                $k = 0;
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
