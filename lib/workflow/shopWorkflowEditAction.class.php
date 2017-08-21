<?php

class shopWorkflowEditAction extends shopWorkflowAction
{
    private $order_item;
    protected function price($value)
    {
        if (strpos($value, ',') !== false) {
            $value = str_replace(',', '.', $value);
        }
        return (double)$value;
    }

    public function isAvailable($order)
    {
        if (is_array($order)) {
            $this->order_item = $order;
        }
        return parent::isAvailable($order);
    }

    public function execute($data = null)
    {
        $order = $this->order_model->getById($data['id']);

        # after editing every unsettled order became simple order
        if ($order['unsettled']) {
            $data['unsettled'] = 0;
        }

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
            //XXX check, that services && products not deleted
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

        $taxes_params = array(
            'shipping'      => $shipping_address,
            'billing'       => $billing_address,
            'discount_rate' => $discount_rate,
        );

        $taxes = shopTaxes::apply($data['items'], $taxes_params, $order['currency']);
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

        if (!empty($data['params']['shipping_id'])) {
            try {
                if ($shipping_plugin = shopShipping::getPlugin(null, $data['params']['shipping_id'])) {
                    $shipping_currency = $shipping_plugin->allowedCurrency();
                    $data['params']['shipping_currency'] = $shipping_currency;
                    $rate_model = new shopCurrencyModel();
                    if ($row = $rate_model->getById($shipping_currency)) {
                        $data['params']['shipping_currency_rate'] = $row['rate'];
                    }
                }

            } catch (waException $ex) {

            }
        }

        // update
        $this->order_model->update($data, $data['id']);

        $this->waLog('order_edit', $data['id']);

        shopProductStocksLogModel::clearContext();

        if (!empty($data['params'])) {
            $this->order_params_model->set($data['id'], $data['params'], false);
        }
        return true;
    }

    public function postExecute($order = null, $result = null)
    {
        $order_id = $order['id'];
        $data = $this->setPackageState(waShipping::STATE_DRAFT, $order);
        if ($data) {
            if (is_array($result)) {
                $result += $data;
            } else {
                $result = $data;
            }
        }
        return parent::postExecute($order_id, $result);
    }

    public function getButton()
    {
        $attrs = '';
        if (is_array($this->order_item)) {
            if (!empty($this->order_item['unsettled'])) {
                $total = wa_currency($this->order_item['total'], $this->order_item['currency']);
                $confirm = _w("Editing this order will mark this order as settled and will re-calculate it's current amount (%s) based on the order content your enter. Are you sure you want to settle this order and reset the order amount?");
                $confirm = sprintf($confirm, $total);
                $attrs = sprintf('data-confirm="%s"', htmlentities($confirm, ENT_QUOTES, 'utf-8'));
            }
        }
        return <<<HTML
<a href="#" class="s-edit-order" {$attrs}>
    <i class="icon16 edit"></i><span>{$this->getName()}</span>
    <i class="icon16 loading" style="margin-left: 4px; display:none;"></i>
</a>
HTML;
    }
}
