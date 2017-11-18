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
        $order_id = waRequest::post('order_id');
        if ($order_id) {
            $order_model = new shopOrderModel();
            $order_info = $order_model->getById($order_id);
            if (empty($order_info)) {
                throw new waException('Order not found', 404);
            }
            $currency = $order_info['currency'];
        } else {
            $currency = waRequest::post('currency');
            if (!$currency) {
                $currency = $this->getConfig()->getCurrency();
            }
        }

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
        foreach ($items as &$i) {
            if (isset($values['skus'][$i['sku_id']])) {
                $i['weight'] = $values['skus'][$i['sku_id']];
            } else {
                $i['weight'] = isset($values[$i['product_id']]) ? $values[$i['product_id']] : 0;
            }
            $i['price']=shop_currency($i['price'], $currency, $currency, false);
            $shipping_items[] = array(
                'name'     => '',
                'price'    => $i['price'],
                'quantity' => $i['quantity'],
                'weight'   => $i['weight'],
            );
            unset($i);
        }

        $total = waRequest::post('subtotal') - waRequest::post('discount');

        // Prepare order data for discount calculation
        $order = array(
            'currency' => $currency,
            'contact'  => $contact,
            'items'    => $this->itemsForDiscount($currency, $items),
            'total'    => shop_currency(waRequest::post('subtotal'), $currency, $currency, false),
        );
        if (!empty($order_info)) {
            $order['id'] = $order_info['id'];
        }

        $this->response['discount_description'] = '';
        $this->response['discount'] = shopDiscounts::calculate($order, false, $this->response['discount_description']);
        $this->response['items_discount'] = array();
        $template = array(
            'product' => _w('Total discount for this order item: %s.'),
            'service' => _w('Total discount for this service: %s.'),
        );
        foreach ($order['items'] as $id => $item) {
            $item['total_discount'] = round(ifset($item['total_discount'], 0), 4);

            if (!empty($item['total_discount'])) {
                switch ($item['type']) {
                    case 'service':
                        $selector = sprintf('%d_%d', $item['_parent_index'], $item['service_id']);
                        break;
                    default:
                        $selector = $item['_index'];
                        break;
                }
                $this->response['items_discount'][] = array(
                    'value'    => $item['total_discount'],
                    'html'     => sprintf($template[$item['type']], shop_currency_html(-$item['total_discount'], $currency, $currency)),
                    'selector' => $selector,
                );
            }
        }

        $method_params = array(
            'currency'           => $currency,
            'total_price'        => $total,
            'no_external'        => true,
            'allow_external_for' => array(waRequest::request('shipping_id', 0, 'int')),
            'custom_html'        => true,
            'shipping_params'    => array(),
        );

        if (!empty($order['id'])) {
            $order_params_model = new shopOrderParamsModel();
            $order['params'] = $order_params_model->get($order['id']);

            if ($order['params'] && !empty($order['params']['shipping_id'])) {
                $shipping_id = $order['params']['shipping_id'];
                foreach ($order['params'] as $name => $value) {
                    if (preg_match('@^shipping_params_(.+)$@', $name, $matches)) {
                        if (!isset($method_params['shipping_params'][$shipping_id])) {
                            $method_params['shipping_params'][$shipping_id] = array();
                        }
                        $method_params['shipping_params'][$shipping_id][$matches[1]] = $value;
                    }
                }
            }
        }

        foreach (waRequest::post() as $name => $value) {
            if (preg_match('@^shipping_(\d+)$@', $name, $matches)) {
                $method_params['shipping_params'][$matches[1]] = $value;
            }
        }

        $this->response['shipping_methods'] = shopHelper::getShippingMethods($shipping_address, $shipping_items, $method_params);

        if (isset($order['shipping']) && ($order['shipping'] == 0)) {
            foreach ($this->response['shipping_methods'] as &$m) {
                if (!is_string($m['rate'])) {
                    $m['rate'] = 0;
                }
                unset($m);
            }
        }
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
        foreach ($items_tree as $i) {
            $products[$i['product_id']] = $i['product_id'];
            foreach (ifset($i['services'], array()) as $s) {
                $services[$s['id']] = $s['id'];
            }
        }

        // Fetch products and skus info
        $product_model = new shopProductModel();
        $products = $product_model->getById($products);
        $product_skus_model = new shopProductSkusModel();
        foreach ($product_skus_model->getByField('product_id', array_keys($products), true) as $row) {
            $products[$row['product_id']]['skus'][$row['id']] = $row;
        }

        // Fetch services and variants info
        $service_model = new shopServiceModel();
        $services = $service_model->getById($services);
        $service_variants_model = new shopServiceVariantsModel();
        foreach ($service_variants_model->getByField('service_id', array_keys($services), true) as $row) {
            $services[$row['service_id']]['variants'][$row['id']] = $row;
        }

        $items = array();
        foreach ($items_tree as $index => $i) {
            $i += array(
                'type'               => 'product',
                'service_id'         => null,
                'service_variant_id' => null,
                'purchase_price'     => 0,
                'sku_code'           => '',
                'name'               => 'product_id='.$i['product_id'],
                '_index'             => $index,
            );
            unset($product);
            if (!empty($products[$i['product_id']]['skus'][$i['sku_id']])) {
                $product = $products[$i['product_id']];
                $sku = $product['skus'][$i['sku_id']];
                if (!empty($sku['name'])) {
                    $i['name'] = sprintf('%s (%s)', ifempty($product['name'], $i['name']), $sku['name']);
                } else {
                    $i['name'] = ifempty($product['name'], $i['name']);
                }
                $i['purchase_price'] = shop_currency($sku['purchase_price'], $product['currency'], $order_currency, false);
                $i['sku_code'] = $sku['sku'];

                $i += compact('product');
            }

            $item_services = ifset($i['services'], array());
            unset($i['services']);
            $items[] = $i;

            foreach ($item_services as $s) {
                $i = array(
                        '_parent_index'   => $index,
                        'type'               => 'service',
                        'price'              => shop_currency($s['price'], $order_currency, $order_currency, false),
                        'service_id'         => $s['id'],
                        'service_variant_id' => 0,
                        'purchase_price'     => 0,
                        'name'               => 'service_id='.$i['service_id'],
                    ) + $i;

                if (!empty($services[$s['id']]['variants'])) {
                    $service = $services[$s['id']];
                    $variant = reset($service['variants']);
                    $i['name'] = $service['name'].($variant['name'] ? ' ('.$variant['name'].')' : '');
                    $i['service_variant_id'] = $variant['id'];

                    $i += compact('service', 'product');
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
