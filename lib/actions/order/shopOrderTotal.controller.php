<?php

/**
 * Class shopOrderTotalController
 *
 * @method shopConfig getConfig()
 */
class shopOrderTotalController extends waJsonController
{
    public function execute()
    {
        $items = waRequest::post('items');
        $product_ids = array();
        foreach ($items as $i) {
            $product_ids[] = $i['product_id'];
        }
        $product_ids = array_unique($product_ids);
        $feature_model = new shopFeatureModel();
        $f = $feature_model->getByCode('weight');
        if (!$f) {
            $values = array();
        } else {
            $values_model = $feature_model->getValuesModel($f['type']);
            $values = $values_model->getProductValues($product_ids, $f['id']);
        }

        $contact = $this->getContact();
        $shipping_address = $contact->getFirst('address.shipping');
        if ($shipping_address) {
            $shipping_address = $shipping_address['data'];
        }


        $shipping_items = array();
        foreach ($items as $i) {
            if (isset($values['skus'][$i['sku_id']])) {
                $w = $values['skus'][$i['sku_id']];
            } else {
                $w = isset($values[$i['product_id']]) ? $values[$i['product_id']] : 0;
            }
            $shipping_items[] = array(
                'name' => '',
                'price' => $i['price'],
                'quantity' => $i['quantity'],
                'weight' => $w
            );
        }

        if (waRequest::post('order_id')) {
            $order_model = new shopOrderModel();
            $order = $order_model->getById(waRequest::post('order_id'));
            $currency = $order['currency'];
        } else {
            $currency = $this->getConfig()->getCurrency();
        }

        $total = waRequest::post('subtotal') - waRequest::post('discount');

        $order = array(
            'currency' => $currency,
            'contact' => $contact,
            'items'   => $items,
            'total'   => waRequest::post('subtotal'),
        );
        $this->response['discount'] = shopDiscounts::calculate($order);

        $this->response['shipping_methods'] = shopHelper::getShippingMethods($shipping_address, $shipping_items,
            array('currency' => $currency, 'total_price' => $total));
        // for saving order in js
        $this->response['shipping_method_ids'] = array_keys($this->response['shipping_methods']);
    }

    /**
     * @return waContact
     */
    protected function getContact()
    {
        $customer = waRequest::post('customer');
        if ($customer) {
            return new waContact($customer);
        }
        return new waContact();
    }
}