<?php

class shopOrderAddMethod extends waAPIMethod
{
    protected $method = 'POST';

    public function execute()
    {
        $contact_id = $this->post('contact_id');
        if ($contact_id) {
            $contact = new waContact($contact_id);
        } else {
            $contact = $this->post('contact', true);
            $contact = new waContact($contact);
        }
        $items = $this->post('items', true);

        // @todo: add support shipping and payment methods
        $order = array(
            'contact' => $contact,
            'items' => $this->getItems($items),
            'shipping' => 0,
            'discount' => '',
            'params' => array()
        );

        $order['params']['storefront'] = wa()->getConfig()->getDomain();
        $order['params']['ip'] = waRequest::getIp();
        $order['params']['user_agent'] = waRequest::getUserAgent();
        if (!$order['params']['user_agent']) {
            $order['params']['user_agent'] = 'api';
        }
        foreach (array('shipping', 'billing') as $ext) {
            $address = $contact->getFirst('address.'.$ext);
            if ($address) {
                foreach ($address['data'] as $k => $v) {
                    $order['params'][$ext.'_address.'.$k] = $v;
                }
            }
        }

        if (!empty($this->post('comment'))) {
            $order['comment'] = $this->post('comment');
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

    protected function getItems($items)
    {
        $rounding_enabled = shopRounding::isEnabled();

        $product_ids = $sku_ids = $service_ids = $variant_ids = array();
        foreach ($items as $item) {
            $sku_ids[] = $item['sku_id'];
            if (!empty($item['services'])) {
                foreach ($item['services'] as $s) {
                    $variant_ids[] = $s['service_variant_id'];
                }
            }
        }

        $sku_model = new shopProductSkusModel();
        $skus = $sku_model->getByField('id', $sku_ids, 'id');

        foreach ($skus as $sku) {
            $product_ids[] = $sku['product_id'];
        }
        $product_model = new shopProductModel();
        $products = $product_model->getById($product_ids);

        $rounding_enabled && shopRounding::roundProducts($products);
        $rounding_enabled && shopRounding::roundSkus($skus, $products);

        $service_variants_model = new shopServiceVariantsModel();
        $variants = $service_variants_model->getById($variant_ids);
        foreach ($variants as $v) {
            $service_ids[] = $v['service_id'];
        }

        $service_model = new shopServiceModel();
        $services = $service_model->getById($service_ids);

        $rounding_enabled && shopRounding::roundServices($services);
        $rounding_enabled && shopRounding::roundServiceVariants($variants, $services);

        $product_services_model = new shopProductServicesModel();
        $rows = $product_services_model->getByProducts($product_ids);
        $rounding_enabled && shopRounding::roundServiceVariants($rows, $services);

        $product_services = $sku_services = array();
        foreach ($rows as $row) {
            if ($row['sku_id'] && !in_array($row['sku_id'], $sku_ids)) {
                continue;
            }
            if (!$row['sku_id']) {
                $product_services[$row['product_id']][$row['service_variant_id']] = $row;
            }
            if ($row['sku_id']) {
                $sku_services[$row['sku_id']][$row['service_variant_id']] = $row;
            }
        }

        foreach ($items as $item_key => &$item) {
            if (isset($skus[$item['sku_id']])) {
                $sku = $skus[$item['sku_id']];
                if (!isset($item['quantity'])) {
                    $item['quantity'] = 1;
                }
                $item['type'] = 'product';
                $item['product_id'] = $sku['product_id'];
                $item['product'] = $products[$sku['product_id']];
                $item['sku_code'] = $sku['sku'];
                $item['purchase_price'] = $sku['purchase_price'];
                $item['sku_name'] = $sku['name'];
                $item['currency'] = $item['product']['currency'];
                $item['price'] = $sku['price'];
                $item['name'] = $item['product']['name'];
                if ($item['sku_name']) {
                    $item['name'] .= ' ('.$item['sku_name'].')';
                }
                if (!empty($item['services'])) {
                    foreach ($item['services'] as $service_key => &$item_service) {
                        if (isset($variants[$item_service['service_variant_id']])) {
                            $item_service['type'] = 'service';
                            $item_service['quantity'] = $item['quantity'];
                            $item_service['product_id'] = $item['product_id'];
                            $item_service['sku_id'] = $item['sku_id'];
                            $variant = $variants[$item_service['service_variant_id']];
                            $service = $services[$variant['service_id']];

                            $item_service['name'] = $item_service['service_name'] = $service['name'];
                            $item_service['currency'] = $service['currency'];
                            $item_service['service'] = $service;
                            $item_service['service_id'] = $variant['service_id'];
                            $item_service['variant_name'] = $variant['name'];
                            if ($item_service['variant_name']) {
                                $item_service['name'] .= ' (' . $item['variant_name'] . ')';
                            }
                            $item_service['price'] = $variant['price'];
                            if (isset($product_services[$item['product_id']][$item_service['service_variant_id']])) {
                                if ($product_services[$item['product_id']][$item_service['service_variant_id']]['price'] !== null) {
                                    $item_service['price'] = $product_services[$item['product_id']][$item_service['service_variant_id']]['price'];
                                }
                            }
                            if (isset($sku_services[$item['sku_id']][$item_service['service_variant_id']])) {
                                if ($sku_services[$item['sku_id']][$item_service['service_variant_id']]['price'] !== null) {
                                    $item_service['price'] = $sku_services[$item['sku_id']][$item_service['service_variant_id']]['price'];
                                }
                            }
                            if ($item_service['currency'] == '%') {
                                $item_service['price'] = $item_service['price'] * $item['price'] / 100;
                                $item_service['currency'] = $item['currency'];
                            }

                        } else {
                            unset($item['services'][$service_key]);
                        }
                    }
                    unset($item_service);
                }
            } else {
                unset($items[$item_key]);
            }
        }
        unset($item);

        $result = array();
        foreach ($items as $item) {
            if (isset($item['services'])) {
                $i = $item;
                unset($i['services']);
                $result[] = $i;
                foreach ($item['services'] as $s) {
                    $result[] = $s;
                }
            } else {
                $result[] = $item;
            }
        }
        return $result;
    }
}