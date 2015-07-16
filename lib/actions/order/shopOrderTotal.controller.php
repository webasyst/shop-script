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

        $order_id = waRequest::post('order_id');
        if ($order_id) {
            $order_model = new shopOrderModel();
            $order_info = $order_model->getById($order_id);
            $currency = $order_info['currency'];
        } else {
            $currency = waRequest::post('currency');
            if (!$currency) {
                $currency = $this->getConfig()->getCurrency();
            }
        }

        $total = waRequest::post('subtotal') - waRequest::post('discount');

        // Prepare order data for discount calculation
        $order = array(
            'currency' => $currency,
            'contact' => $contact,
            'items'   => $this->itemsForDiscount($currency, $items),
            'total'   => waRequest::post('subtotal'),
        );
        if ($order_id) {
            $order['id'] = $order_info['id'];
        }
        $this->response['discount_description'] = '';
        $this->response['discount'] = shopDiscounts::calculate($order, false, $this->response['discount_description']);

        $this->response['shipping_methods'] = shopHelper::getShippingMethods($shipping_address, $shipping_items, array(
            'currency' => $currency,
            'total_price' => $total,
            'no_external' => true,
            'allow_external_for' => array(waRequest::request('shipping_id', 0, 'int')),
        ));
        // for saving order in js
        $this->response['shipping_method_ids'] = array_keys($this->response['shipping_methods']);
    }

    /**
     * Convert tree-like structure where services are part of products
     * into flat list where services and products are on the same level.
     */
    protected function itemsForDiscount($order_currency, $items_tree)
    {
        $products = array();
        $services = array();
        foreach($items_tree as $i) {
            $products[$i['product_id']] = $i['product_id'];
            foreach(ifset($i['services'], array()) as $s) {
                $services[$s['id']] = $s['id'];
            }
        }

        // Fetch products and skus info
        $product_model = new shopProductModel();
        $products = $product_model->getById($products);
        $product_skus_model = new shopProductSkusModel();
        foreach($product_skus_model->getByField('product_id', array_keys($products), true) as $row) {
            $products[$row['product_id']]['skus'][$row['id']] = $row;
        }

        // Fetch services and variants info
        $service_model = new shopServiceModel();
        $services = $service_model->getById($services);
        $service_variants_model = new shopServiceVariantsModel();
        foreach($service_variants_model->getByField('service_id', array_keys($services), true) as $row) {
            $services[$row['service_id']]['variants'][$row['id']] = $row;
        }

        $items = array();
        foreach($items_tree as $i) {
            $i += array(
                'type' => 'product',
                'service_id' => null,
                'service_variant_id' => null,
                'purchase_price' => 0,
                'sku_code' => '',
                'name' => 'product_id='.$i['product_id'],
            );

            if (!empty($products[$i['product_id']]['skus'][$i['sku_id']])) {
                $product = $products[$i['product_id']];
                $sku = $product['skus'][$i['sku_id']];
                $i = array(
                    'purchase_price' => shop_currency($sku['purchase_price'], $product['currency'], $order_currency, false),
                    'sku_code' => $sku['sku'],
                    'name' => $sku['name'],
                ) + $i + array(
                    'product' => $product,
                );
            }

            $item_services = ifset($i['services'], array());
            unset($i['services']);
            $items[] = $i;
            foreach($item_services as $s) {
                $i = array(
                    'type' => 'service',
                    'price' => $s['price'],
                    'service_id' => $s['id'],
                    'service_variant_id' => 0,
                    'purchase_price' => 0,
                    'name' => '',
                ) + $i;

                if (!empty($services[$s['id']]['variants'])) {
                    $service = $services[$s['id']];
                    $variant = reset($service['variants']);
                    $i = array(
                        'name' => $service['name'].($variant['name'] ? ' ('.$variant['name'].')' : ''),
                        'service_variant_id' => $variant['id'],
                    ) + $i + array(
                        'service' => $service,
                    );
                }
                $items[] = $i;
            }
        }
        return $items;
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