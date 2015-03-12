<?php

class shopWorkflowEditAction extends shopWorkflowAction
{
    protected function price($value)
    {
        if (strpos($value, ',') !== false) {
            $value = str_replace(',', '.', $value);
        }
        return (double)$value;
    }

    public function execute($data = null)
    {
        $order_model = new shopOrderModel();

        $order = $order_model->getById($data['id']);

        $subtotal = 0;
        $services = $products = array();
        foreach ($data['items'] as $item) {
            if ($item['service_id']) {
                $services[] = $item['service_id'];
            } else {
                $products[] = $item['product_id'];
            }
        }
        $service_model = new shopServiceModel();
        $product_model = new shopProductModel();
        $services = $service_model->getById($services);
        $products = $product_model->getById($products);

        foreach ($data['items'] as &$item) {
            $item['currency'] = $order['currency'];
            $item['price'] = $this->price($item['price']);
            if ($item['service_id']) {
                $item['service'] = $services[$item['service_id']];
            } else {
                $item['product'] = $products[$item['product_id']];
            }
            $subtotal += $item['price'] * $item['quantity'];
        }
        unset($item);
        foreach (array('shipping', 'discount') as $k) {
            if (!isset($data[$k])) {
                $data[$k] = 0;
            }
        }
        $contact = new waContact($order['contact_id']);
        $shipping_address = $contact->getFirst('address.shipping');
        if (!$shipping_address) {
            $shipping_address = $contact->getFirst('address');
        }
        $shipping_address = $shipping_address ? $shipping_address['data'] : array();
        $billing_address = $contact->getFirst('address.billing');
        if (!$billing_address) {
            $billing_address = $contact->getFirst('address');
        }
        $billing_address = $billing_address ? $billing_address['data'] : array();

        $discount_rate = $subtotal ? ($data['discount'] / $subtotal) : 0;

        $taxes = shopTaxes::apply($data['items'], array('shipping' => $shipping_address,
            'billing' => $billing_address, 'discount_rate' => $discount_rate), $order['currency']);
        $tax = $tax_included = 0;
        foreach ($taxes as $t) {
            if (isset($t['sum'])) {
                $tax += $t['sum'];
            }
            if (isset($t['sum_included'])) {
                $tax_included += $t['sum_included'];
            }
        }

        $data['tax'] = $tax_included + $tax;
        $data['total'] = $subtotal + $tax + $this->price($data['shipping']) - $this->price($data['discount']);
        
        // for logging changes in stocks
        shopProductStocksLogModel::setContext(
                shopProductStocksLogModel::TYPE_ORDER,
                /*_w*/('Order %s was edited'),
                array(
                    'order_id' => $data['id']
                )
        );
        
        // update
        $order_model->update($data, $data['id']);

        $log_model = new waLogModel();
        $log_model->add('order_edit', $data['id']);
        
        shopProductStocksLogModel::clearContext();

        if (!empty($data['params'])) {
            $params_model = new shopOrderParamsModel();
            $params_model->set($data['id'], $data['params'], false);
        }
        return true;
    }

    public function postExecute($order = null, $result = null)
    {
        $order_id = $order['id'];
        return parent::postExecute($order_id, $result);
    }

    public function getButton()
    {
        return '<a href="#" class="s-edit-order"><i class="icon16 edit"></i><span>'.
            $this->getName().
        '</span><i class="icon16 loading" style="margin-left: 4px; display:none;"></i></a>';
    }

}