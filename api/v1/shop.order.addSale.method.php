<?php
/**
 */
class shopOrderAddSaleMethod extends shopApiMethod
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
            'items' => $this->getItems($currency),
            'currency' => $currency,
            'shipping' => 0,
            'discount' => waRequest::post('discount', '', 'string'),
            'state_id' => 'pos',
            'params' => [
                'ip' => waRequest::getIp(),
                'user_agent' => ifempty(ref(waRequest::getUserAgent()), 'api'),
                'api_contact_id' => wa()->getUser()->getId(),
                'sales_channel' => ifset($params, 'sales_channel', 'pos:'),
            ] + $params,
            'notifications_silent' => true,
        ];
        if (!empty($comment)) {
            $order['comment'] = $comment;
        }
        if (empty($order['items'])) {
            throw new waAPIException('items_required', 'Order items are required', 400);
        }
        if (empty($order['params']['coupon_id'])) {
            $coupon_id = waRequest::post('coupon_id', null, 'int');
            if ($coupon_id) {
                $order['params']['coupon_id'] = $coupon_id;
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

    protected function getItems($order_currency)
    {
        $items_data = $this->post('items', true);

        $duplicate_sku_items = [];
        $items = $product_ids = $sku_ids = $service_ids = $variant_ids = [];
        foreach ($items_data as $item) {
            $sku_ids[$item['sku_id']] = $item['sku_id'];
            if (!empty($item['service_variant_id'])) {
                $items[$item['sku_id']]['services'][$item['service_variant_id']] = $item;
                $variant_ids[$item['service_variant_id']] = $item['service_variant_id'];
            } else {
                unset($item['service_variant_id']);
                if (isset($items[$item['sku_id']]['sku_id'])) {
                    // this allows for multiple items with the same sku_id
                    // useful e.g. when same sku_id is added to cart with different services
                    $duplicate_sku_items[] = $items[$item['sku_id']];
                    $items[$item['sku_id']] = $item;
                } else {
                    $items[$item['sku_id']] = ifset($items, $item['sku_id'], []) + $item;
                }
            }
        }
        $items = array_merge($duplicate_sku_items, array_values($items));
        unset($items_data, $duplicate_sku_items);

        $sku_model = new shopProductSkusModel();
        $skus = $sku_model->getByField('id', $sku_ids, 'id');

        foreach ($skus as $sku) {
            $product_ids[$sku['product_id']] = $sku['product_id'];
        }
        $product_model = new shopProductModel();
        $products = $product_model->getById($product_ids);

        $rounding_enabled = shopRounding::isEnabled();
        $rounding_enabled && shopRounding::roundProducts($products);
        $rounding_enabled && shopRounding::roundSkus($skus, $products);

        $service_variants_model = new shopServiceVariantsModel();
        $variants = $service_variants_model->getById($variant_ids);
        foreach ($variants as $v) {
            $service_ids[$v['service_id']] = $v['service_id'];
        }

        $service_model = new shopServiceModel();
        $services = $service_model->getById($service_ids);

        $rounding_enabled && shopRounding::roundServices($services);
        $rounding_enabled && shopRounding::roundServiceVariants($variants, $services);

        $product_services_model = new shopProductServicesModel();
        $service_variants = $product_services_model->getByProducts($product_ids);
        $rounding_enabled && shopRounding::roundServiceVariants($service_variants, $services);

        $product_services = $sku_services = array();
        foreach ($service_variants as $row) {
            if ($row['sku_id'] && !isset($sku_ids[$row['sku_id']])) {
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
            if (empty($item['sku_id']) || !isset($skus[$item['sku_id']])) {
                unset($items[$item_key]);
                continue;
            }

            $sku = $skus[$item['sku_id']];
            $product = $products[$sku['product_id']];

            $item = [
                'type' => 'product',
                'product_id' => $sku['product_id'],
                'sku_id' => $item['sku_id'],

                'sku_code' => $sku['sku'],
                'name' => $product['name'],

                'price' => ifset($item, 'price', $sku['price']),
                'purchase_price' => $sku['purchase_price'],
                'currency' => isset($item['price']) ? $order_currency : $product['currency'],

                'quantity' => ifset($item, 'quantity', 1),
                'stock_unit_id' => $product['stock_unit_id'],
                'quantity_denominator' => $product['count_denominator'],
                'services' => ifset($item, 'services', []),
                'codes' => ifset($item, 'codes', []),
            ];
            if ($sku['name']) {
                $item['name'] .= ' ('.$sku['name'].')';
            }

            foreach ($item['services'] as $service_key => &$item_service) {
                if (!isset($variants[$item_service['service_variant_id']])) {
                    unset($item['services'][$service_key]);
                    continue;
                }

                $variant = $variants[$item_service['service_variant_id']];
                $service = $services[$variant['service_id']];
                $overriden_price = ifset($item_service, 'price', null);

                $item_service = [
                    'type' => 'service',
                    'product_id' => $item['product_id'],
                    'sku_id' => $item['sku_id'],
                    'service_id' => $variant['service_id'],
                    'service_variant_id' => $variant['id'],

                    'name' => $service['name'],

                    'quantity' => $item['quantity'],
                    'quantity_denominator' => $item['quantity_denominator'],
                    'currency' => $overriden_price ? $order_currency : $service['currency'],
                    'price' => ifset($overriden_price, $variant['price']),
                ];
                if ($variant['name']) {
                    $item_service['name'] .= ' (' . $variant['name'] . ')';
                }

                if ($overriden_price === null) {
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
                }

            }
            unset($item_service);
        }
        unset($item);

        $result = [];
        foreach ($items as $item) {
            $services = ifset($item, 'services', []);
            unset($item['services']);
            $result[] = $item;
            foreach ($services as $s) {
                $result[] = $s;
            }
        }

        return $result;
    }
}
