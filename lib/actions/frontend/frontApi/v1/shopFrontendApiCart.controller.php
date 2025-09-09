<?php
/**
 * Cart management GET/POST/PUT/DELETE
 */
class shopFrontendApiCartController extends shopFrontApiJsonController
{
    protected function getCollectionFields()
    {
        $fields = ['*' => 1, 'skus' => 1];
        $additional_fields = waRequest::request('fields');
        if (!$additional_fields) {
            $additional_fields = 'skus_image,sku_filtered';
        }
        if ($additional_fields) {
            if (is_string($additional_fields)) {
                $additional_fields = explode(',', $additional_fields);
            }
            if (!is_array($additional_fields)) {
                throw new waAPIException('invalid_param', 'Fields parameter must be a string or a list of strings', 400);
            }
            foreach ($additional_fields as $f) {
                $f = trim($f);
                $fields[$f] = 1;
            }
        }

        return array_keys($fields);
    }

    public function get($token = null)
    {
        $token = waRequest::request('customer_token', $token, waRequest::TYPE_STRING_TRIM);
        $coupon_code = waRequest::request('coupon_code', null, waRequest::TYPE_STRING_TRIM);

        if (empty($token)) {
            throw new waAPIException('required_param', 'Missing required parameter: customer_token', 400);
        }
        $cart = new shopApiCart($token);

        $items = $cart->getItems();
        $total = $cart->getTotal();

        $formatter_product = new shopFrontApiProductFormatter([
            'fields' => $this->getCollectionFields(),
        ]);
        $formatter_item = new shopFrontApiItemFormatter();
        $formatter_service = new shopFrontApiServiceFormatter();

        $product_ids = array_column(array_filter($items, function ($item) {
            return $item['type'] === 'product';
        }), 'product_id');

        $hash = 'id/'.implode(',', $product_ids);
        $collection = new shopProductsCollection($hash, [
            'overwrite_product_prices' => true,
            'frontend' => true,
        ]);
        $products = $collection->getProducts($formatter_product->getCollectionFields());
        $products = $formatter_product->format($products);

        $variant_ids = array_column(array_filter($items, function ($item) {
            return $item['type'] === 'service';
        }), 'service_variant_id');
        $variant_services = (new shopServiceVariantsModel())->getWithPrice($variant_ids);
        $services = (new shopServiceModel())->getById(array_column($variant_services, 'id'));

        foreach ($items as &$_item) {
            $_item['total_discount'] = 0.0;
            $_item['product'] = ifset($products, $_item['product_id'], null);
            $_item = $formatter_item->format($_item);
            if ($_item['type'] === 'service') {
                $variant = ifset($variant_services, $_item['service_variant_id'], null);
                if ($variant && !empty($variant['service_id'])) {
                    $_item['service'] = $formatter_service->format(ifset($services, $variant['service_id'], null), [$variant]);
                } else {
                    $_item['service'] = null;
                }
            }
        }
        unset($_item);

        $discount = 0.0;
        $coupon_valid = false;
        if ($items && $coupon_code !== null) {
            // Calculate items discount
            $order = $this->makeShopOrder($token, $coupon_code);
            $item_discounts = waUtils::getFieldValues($order->items_discount, 'value', 'cart_item_id');
            foreach ($items as &$item) {
                $item['total_discount'] = ifset($item_discounts, $item['id'], 0.0);
                $discount += $item['total_discount'];
            }
            unset($item);
            $total -= $discount;
            $coupon_valid = !empty($order['params']['coupon_id']);
        }

        $this->response = [
            'total' => $total,
            'coupon_valid' => $coupon_valid,
            'discount' => $discount,
            'items' => $items,
        ];
        if (empty($coupon_code)) {
            unset($this->response['coupon_valid']);
        }
    }

    public function post()
    {
        $token = waRequest::request('customer_token', null, waRequest::TYPE_STRING_TRIM);
        $item_id = waRequest::post('item_id', null, waRequest::TYPE_INT);
        $quantity = (float) waRequest::post('quantity', null, waRequest::TYPE_STRING_TRIM);

        if (empty($token)) {
            throw new waAPIException('required_param', 'Missing required parameter: customer_token', 400);
        } elseif (empty($item_id)) {
            throw new waAPIException('required_param', 'Missing required parameter: item_id', 400);
        }

        $cart = new shopApiCart($token);

        if ($item = $cart->getItem($item_id)) {
            $sku_id = ifset($item, 'sku_id', null);
            $sku_model = new shopProductSkusModel();
            $sku = $sku_model->getById($sku_id);
            if (empty($sku)) {
                $cart->setQuantity($sku_id, 0);
                throw new waAPIException('not_found', 'Product not found', 404);
            }

            $cart = new shopApiCart($token);
            $cart->setQuantity($sku_id, $quantity);
        } else {
            throw new waAPIException('not_found', 'Cart item not found', 404);
        }

        $this->get($token);
    }

    public function put()
    {
        $token = waRequest::request('customer_token', null, waRequest::TYPE_STRING_TRIM);
        $sku_id = waRequest::post('sku_id', null, waRequest::TYPE_INT);
        $service_variant_id = waRequest::post('service_variant_id', null, waRequest::TYPE_INT);
        $quantity = (float) waRequest::post('quantity', null, waRequest::TYPE_STRING_TRIM);

        if (empty($token)) {
            throw new waAPIException('required_param', 'Missing required parameter: customer_token', 400);
        } elseif (empty($sku_id)) {
            throw new waAPIException('required_param', 'Missing required parameter: sku_id', 400);
        }

        $sku_model = new shopProductSkusModel();
        $product_model = new shopProductModel();

        $sku = $sku_model->getById($sku_id);
        $product = $product_model->getById($sku['product_id']);

        if (empty($sku) || empty($product)) {
            throw new waAPIException('not_found', 'Product not found', 404);
        }

        $order_count_min = !empty($sku['order_count_min']) ? $sku['order_count_min'] : $product['order_count_min'];
        $quantity = $quantity ?: $order_count_min;
        if ($quantity < $order_count_min) {
            $quantity = $order_count_min;
        }

        $item = [
            'type'       => 'product',
            'product_id' => $product['id'],
            'sku_id'     => $sku_id,
            'quantity'   => $quantity,
        ];
        $cart = new shopApiCart($token);
        $item['parent_id'] = $cart->addItem($item);

        if ($service_variant_id) {
            if ($variant_service = (new shopServiceVariantsModel())->getById($service_variant_id)) {
                $item = [
                    'type' => 'service',
                    'service_id' => $variant_service['service_id'],
                    'service_variant_id' => $service_variant_id,
                ] + $item;
                $cart->addItem($item);
            }
        }

        $this->get($token);
    }

    public function delete()
    {
        $token = waRequest::request('customer_token', null, waRequest::TYPE_STRING_TRIM);

        if (empty($token)) {
            throw new waAPIException('required_param', 'Missing required parameter: customer_token', 400);
        }
        $cart = new shopApiCart($token);
        $cart->clear();
        wa()->getResponse()->setStatus(204);
    }
}
