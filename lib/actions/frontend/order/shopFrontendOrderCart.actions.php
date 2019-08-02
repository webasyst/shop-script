<?php
/**
 * /order/cart/<add|save|get|render> in frontend: JSON API for new single-page cart.
 *
 * New cart items are added via `add` endpoint.
 *
 * Existing items are modified and deleted via `save` endpoint.
 *
 * `get` returns JSON describing current cart state. `add` and `save` use the same output as `get`.
 *
 * `render` returns cart HTML as would be rendered by $wa->shop->cgeckout()->cart().
 * `add` and `save` can optionally render cart instead of returhing JSON if a certain parameter is passed in.
 */
class shopFrontendOrderCartActions extends waJsonActions
{
    public function getAction()
    {
        try {
            $this->response = (new shopCheckoutViewHelper())->cartVars();
            unset($this->response['cart']['discount_description']);
            unset($this->response['event_hook']);
            unset($this->response['features']);
            foreach($this->response['cart']['items'] as &$item) {
                if (!empty($item['services'])) {
                    foreach($item['services'] as &$service) {
                        if (!empty($service['services'])) {
                            foreach($service['services'] as &$variant) {
                                $variant = array_intersect_key($variant, [
                                    "service_id" => 1,
                                    "name" => 1,
                                    "price" => 1,
                                    "status" => 1,
                                    "currency" => 1,
                                ]);
                            }
                            unset($variant);
                        }
                        $service = array_intersect_key($service, [
                            'id' => 1,
                            "name" => 1,
                            "description" => 1,
                            "price" => 1,
                            "currency" => 1,
                            "variant_id" => 1,
                            "variants" => 1,
                        ]);
                    }
                    unset($service);
                }
                $item = array_intersect_key($item, [
                    'id' => 1,
                    'product_id' => 1,
                    'sku_id' => 1,
                    'create_datetime' => 1,
                    'quantity' => 1,
                    'type' => 1,
                    'service_id' => 1,
                    'service_variant_id' => 1,
                    'parent_id' => 1,
                    'sku_code' => 1,
                    'sku_name' => 1,
                    'currency' => 1,
                    'price' => 1,
                    'name' => 1,
                    'services' => 1,
                    'full_price' => 1,
                    'full_compare_price' => 1,
                    'stock_count' => 1,
                    'sku_available' => 1,
                    'can_be_ordered' => 1,
                    'discount' => 1,
                    'weight' => 1,
                    'weight_html' => 1,
                    'total_weight' => 1,
                    'total_weight_html' => 1,
                ]);
            }
            unset($item);
        } catch (waException $e) {
            $this->errors = (string) $e;
        }
    }

    protected function validateNewProduct($cart, $new_item)
    {
        // Make sure product is not already in the cart
        foreach($cart->items() as $item) {
            if ($item['product_id'] == $new_item['product_id'] && $item['sku_id'] == $new_item['sku_id']) {
                return [[
                    'id' => 'already_in_cart',
                    'text' => _w('The product is already in your cart.'),
                ]];
            }
        }

        // Make sure product exists
        $sku_model = new shopProductSkusModel();
        $sku = $sku_model->getById($new_item['sku_id']);
        if (!$sku || $sku['product_id'] != $new_item['product_id']) {
            return [[
                'id' => 'not_found',
                'text' => _w('Product not found'),
            ]];
        }

        $product_model = new shopProductModel();
        $product = $product_model->getById($new_item['product_id']);
        if (!$product) {
            return [[
                'id' => 'not_found',
                'text' => _w('Product not found'),
            ]];
        }

        // Make sure product is available on current storefront
        if ( ( $types = waRequest::param('type_id'))) {
            if (!in_array($product['type_id'], (array)$types)) {
                return [[
                    'id' => 'not_found',
                    'text' => _w('Product not found'),
                ]];
            }
        }

        // Make sure product is in stock
        if (!wa('shop')->getSetting('ignore_stock_count')) {

            // limit by main stock set in storefront settings
            if (wa('shop')->getSetting('limit_main_stock') && waRequest::param('stock_id')) {
                $stock_id = waRequest::param('stock_id');
                $product_stocks_model = new shopProductStocksModel();
                $sku_stock = shopHelper::fillVirtulStock($product_stocks_model->getCounts($sku['id']));
                if (isset($sku_stock[$stock_id])) {
                    $sku['count'] = $sku_stock[$stock_id];
                }
            }

            // Check if in stock
            if ($sku['count'] !== null && $new_item['quantity'] > $sku['count']) {
                $name = $product['name'].($sku['name'] ? ' ('.$sku['name'].')' : '');
                return [[
                    'id' => 'out_of_stock',
                    'text' => sprintf(_w('Oops! %s just went out of stock and is not available for purchase at the moment. We apologize for the inconvenience.'), $name),
                ]];
            }
        }

        return [];
    }

    protected function validateNewService($parent_item, $new_item)
    {
        // Make sure service and variant exist for given product
        $service = ifset($parent_item, 'services', $new_item['service_id'], null);
        if (!$service) {
            return [[
                'id' => 'service_not_found',
                'text' => _w('Service does not exist.'),
            ]];
        }

        // Make sure service is not taken into cart already
        if (!empty($service['id'])) {
            return [[
                'id' => 'service_already_in_cart',
                'text' => _w('Service is already in cart.'),
            ]];
        }

        // Make sure service variant exists
        $variant_exists = $new_item['service_variant_id'] == $service['variant_id'];
        $variant_exists = $variant_exists || !empty($service['variants'][$new_item['service_variant_id']]);
        if (!$variant_exists) {
            return [[
                'id' => 'variant_not_found',
                'text' => _w('Service variant does not exist.'),
            ]];
        }

        return array();
    }

    public function addAction()
    {
        $errors = [];

        $cart = new shopCart('', [
            'generate_code' => true,
        ]);

        $new_item = waRequest::request('item', [], 'array');
        if (!empty($new_item['parent_id'])) {
            // Add a service
            $new_item = array_intersect_key($new_item, [
                'parent_id' => 1,
                'service_id' => 1,
                'service_variant_id' => 1,
            ]);
            if (count($new_item) !== 3) {
                $errors[] = _w('Incorrect input data');
            }

            // Fill in rest of the data
            $checkout_vars = (new shopCheckoutViewHelper())->cartVars();
            $parent_item = ifset($checkout_vars, 'cart', 'items', $new_item['parent_id'], null);
            if (!$parent_item) {
                $errors[] = _w('No product to add service to.');
            } else {
                $new_item += [
                    'product_id' => $parent_item['product_id'],
                    'sku_id' => $parent_item['sku_id'],
                    'type' => 'service',
                ];
            }

            if (!$errors) {
                $errors = $this->validateNewService($parent_item, $new_item);
            }

        } else {
            $new_item = array_intersect_key($new_item, [
                'product_id' => 1,
                'sku_id' => 1,
                'quantity' => 1,
            ]);
            if (count($new_item) !== 3) {
                $errors[] = _w('Incorrect input data');
            }

            $new_item += [
                'service_variant_id' => null,
                'service_id' => null,
                'type' => 'product',
            ];

            if (!$errors) {
                $errors = $this->validateNewProduct($cart, $new_item);
            }
        }

        if (!$errors) {

            /**
             * @event frontend_order_cart_add_before
             * Allows to validate and modify item before adding it to the cart
             */
            wa('shop')->event('frontend_order_cart_add_before', ref([
                'new_item' => &$new_item,
                'errors' => &$errors,
            ]));

            if (!$errors) {
                $new_item['id'] = $cart->addItem($new_item);

                /**
                 * @event frontend_order_cart_add
                 * Notify plugins about item just added to the cart.
                 */
                wa('shop')->event('frontend_order_cart_add', ref([
                    'new_item' => $new_item,
                ]));
            }

        }

        // Reset vars cache
        (new shopCheckoutViewHelper())->cartVars(true);

        // Build response
        if (waRequest::request('render')) {
            $this->renderAction();
        } else {
            $this->getAction();
            if (!empty($errors)) {
                $this->response['errors'] = $errors;
            } else {
                // mark item that was just added
                if ($new_item['type'] == 'service') {
                    $this->response['cart']['items'][$new_item['parent_id']]['services'][$new_item['service_id']]['just_added'] = true;
                } else {
                    $this->response['cart']['items'][$new_item['id']]['just_added'] = true;
                }
                $this->response['just_added_item'] = $new_item;
            }
        }
    }

    public function saveAction()
    {
        /*
            items: [{
                id: int, // item id!
                sku_id: int,
                quantity: int,
                services: [{
                    id: int, // item id! (if not present, will be removed from cart)
                    service_variant_id: int,
                    enabled: int // 0 = remove service; 1 (default) - do not remove
                }]
            }]

            coupon: {
                // if empty coupon will be cancelled, if set and valid then will be applied
                code: string
            },

            affiliate: {
                // if empty will not be used; if 'all' will be used as much as possible
                enabled: string
            }
        */

        $errors = [];

        //
        // Update coupon
        //
        $session_data = wa()->getStorage()->get('shop/checkout', array());
        $coupon_data = waRequest::post('coupon', [], 'array');
        if ($coupon_data) {
            $coupon_code = $coupon_data['code'];
            if ($coupon_code) {
                $session_data['coupon_code'] = $coupon_code;
            } else {
                unset($session_data['coupon_code']);
            }
        }

        //
        // Update affiliate bonus use
        //
        $affiliate_data = waRequest::post('affiliate', [], 'array');
        if ($affiliate_data) {
            $use = ifset($affiliate_data, 'enabled', 0);
            if ($use) {
                $session_data['use_affiliate'] = 1;
            } else {
                unset($session_data['use_affiliate']);
            }
        }
        $cart = new shopCart();
        if ($coupon_data || $affiliate_data) {
            wa()->getStorage()->set('shop/checkout', $session_data);
            $cart->clearSessionData();
        }

        //
        // Update all items
        //
        try {
            $old_cart_items = $cart->items();
            $items_data = waRequest::post('items', [], 'array');
            list($update, $item_errors) = $this->getItemsUpdate($old_cart_items, $items_data);
            if ($item_errors) {
                $errors['items'] = $item_errors;
            }

            /**
             * @event frontend_order_cart_save_before
             * Allows to validate and modify items before saving the cart
             */
            wa('shop')->event('frontend_order_cart_save_before', ref([
                'update' => &$update,
                'errors' => &$errors,
            ]));

            foreach($update as $item_id => $item) {
                if ($item) {
                    $cart->updateItem($item_id, $item);
                } else {
                    $cart->deleteItem($item_id);
                }
            }
        } catch (waException $e) {
            // This is exceptional and should never happen.
            $errors['items'] = $e->getMessage();
            if (waSystemConfig::isDebug()) {
                $errors['items'] .= " (".$e->getCode().")\n".$e->getFullTraceAsString();
            }
        }

        if (!empty($update)) {
            /**
             * @event frontend_order_cart_save
             * Notify plugins about cart being modified
             */
            wa('shop')->event('frontend_order_cart_save', ref([
                'update' => $update,
                'errors' => $errors,
            ]));
        }

        if (waRequest::request('render')) {
            $this->renderAction($errors);
        } else {
            $this->getAction();
            if ($errors) {
                $this->response['errors'] = $errors;
            }
        }
    }

    // Helper for saveAction()
    protected function getItemsUpdate($old_cart_items, $items_data)
    {
        $type_by_id = [];
        $new_items_data = [];
        $everything_is_deleted = true;
        foreach($items_data as $item) {
            if (empty($item['id'])) {
                continue;
            }
            $item_id = $item['id'];
            $type_by_id[$item_id] = 'product';

            if (empty($item['quantity'])) {
                $new_items_data[$item_id] = false;
                continue;
            }
            if (!empty($item['services']) && is_array($item['services'])) {
                foreach($item['services'] as $s) {
                    if (empty($s['id'])) {
                        continue;
                    }
                    $enabled = ifset($s, 'enabled', 1);
                    $service_id = $s['id'];
                    $type_by_id[$service_id] = $item_id;
                    if ($enabled) {
                        unset($s['enabled'], $s['id']);
                        $new_items_data[$service_id] = $s;
                    } else {
                        $new_items_data[$service_id] = false;
                    }
                }
            }

            if (empty($item['quantity'])) {
                $new_items_data[$item_id] = false;
            } else {
                unset($item['id'], $item['services']);
                $new_items_data[$item_id] = $item;
                $everything_is_deleted = false;
            }
        }
        unset($item);

        // Fetch data to check product counts against existing stock.
        $cart_model = new shopCartItemsModel();
        if (!$everything_is_deleted) {
            if (wa()->getSetting('ignore_stock_count', null, 'shop')) {
                $check_count = false;
            } else {
                $check_count = true;
                if (wa()->getSetting('limit_main_stock', null, 'shop') && waRequest::param('stock_id')) {
                    $stock_id = waRequest::param('stock_id', null, 'string');
                    $check_count = $stock_id;
                }
            }
        }

        // Make sure all existing cart items are present in data,
        // and nothing is present that does int exist in cart
        $item_errors = array();
        $errors_cancel_update = false;
        $unknown_new_items = $new_items_data;
        $cart_fields = $cart_model->getMetadata();
        foreach($old_cart_items as $item) {

            // Check services
            foreach(ifset($item, 'services', []) as $s) {
                $type_by_id[$s['id']] = $item['id'];
                // New data for service exists: all fine
                if (isset($new_items_data[$s['id']])) {
                    unset($unknown_new_items[$s['id']]);
                    continue;
                }
                // Parent product item removed: all fine
                if (isset($new_items_data[$s['parent_id']]) && !$new_items_data[$s['parent_id']]) {
                    unset($unknown_new_items[$s['id']]);
                    continue;
                }
                // no data came for this service
                $item_errors[$item['id']]['services'][$s['id']]['general'] = _w('Data mismatch. Shopping cart parameters have changed beyond this page?');
                $errors_cancel_update = true;
            }

            $type_by_id[$item['id']] = 'product';
            if (!isset($new_items_data[$item['id']])) {
                // No data came for this product
                $item_errors[$item['id']]['general'] = _w('Data mismatch. Shopping cart parameters have changed beyond this page?');
                $errors_cancel_update = true;
                continue;
            }

            // New data for product exists: all fine
            unset($unknown_new_items[$item['id']]);

            // More validation applies unless user asked to delete item
            if ($new_items_data[$item['id']]) {

                // Check that quantity is integer
                if (strtolower($cart_fields['quantity']['type']) == 'int' && !wa_is_int($new_items_data[$item['id']]['quantity'])) {
                    $item_errors[$item['id']]['quantity'] = _w('Quantity must be integer.');
                }
            }
        }

        if ($unknown_new_items) {
            foreach(array_keys($unknown_new_items) as $item_id) {
                // Asked to delete product pr service that is already deleted? That's fine.
                if ($new_items_data[$item_id] === false) {
                    unset($new_items_data[$item_id]);
                    continue;
                }
                // Otherwise it's an error
                $errors_cancel_update = true;
                if (empty($type_by_id[$item_id]) || $type_by_id[$item_id] === 'product') {
                    $item_errors[$item_id]['general'] = _w('Data mismatch. Shopping cart parameters have changed beyond this page?');
                } else {
                    $item_errors[$type_by_id[$item_id]]['services'][$item_id]['general'] = _w('Data mismatch. Shopping cart parameters have changed beyond this page?');
                }
            }
        }

        if ($errors_cancel_update) {
            // Bad errors cancel the whose save
            $new_items_data = [];
        } else {
            // Otherwise update everything except items with errors
            $new_items_data = array_diff_key($new_items_data, $item_errors);
        }

        return [$new_items_data, $item_errors];
    }

    public function renderAction($errors = array())
    {
        echo (new shopCheckoutViewHelper())->cart([
            'errors' => $errors,
        ] + $this->getOpts());
        exit;
    }

    protected function getOpts()
    {
        $opts = waRequest::post('opts', array(), 'array');
        $opts = array_intersect_key($opts, [
            'wrapper' => 1,
            'adaptive' => 1,
            'DEBUG' => 1,
        ]) + [
            'adaptive' => true,
        ];
        if (isset($opts['wrapper'])) {
            $opts['wrapper'] = htmlspecialchars($opts['wrapper']);
        }
        return $opts;
    }
}
