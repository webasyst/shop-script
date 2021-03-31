<?php

/**
 * Helper object available in templates as $wa->shop->checkout()
 */
class shopCheckoutViewHelper
{
    /**
     * Returns HTML rendering cart block for new one-page checkout
     *
     * @param array
     * @return string
     */
    public function cart($opts = array())
    {
        $template_path = wa()->getAppPath('templates/actions/frontend/FrontendOrderCart.html', 'shop');

        return $this->renderTemplate($template_path, $this->cartVars() + array(
                'shop_checkout_include_path' => wa()->getAppPath('templates/actions/frontend/order/', 'shop'),
                'options'                    => $opts + [
                        'adaptive' => true,
                    ],
            ));
    }

    /**
     * Returns variables that $wa->shop->checkout()->cart() assigns to its template.
     * @param bool $clear_cache
     * @return array
     */
    public function cartVars($clear_cache = false)
    {
        static $result = null;
        if ($clear_cache || $result === null) {
            $old_is_template = waConfig::get('is_template');
            waConfig::set('is_template', null);
            $data = wa()->getStorage()->get('shop/checkout');
            $order = $this->getFakeOrder(new shopCart());
            $result = array_merge(
                $this->cartBasicVars($order),
                $this->cartCouponVars($data, $order),
                $this->cartAffiliateVars($data, $order)
            );

            /**
             * @event frontend_order_cart_vars
             * Allows to modify template vars of $wa->shop->checkout()->cart() before sending them to template,
             * or add custom HTML to cart page.
             */
            $result['event_hook'] = wa('shop')->event('frontend_order_cart_vars', $result);

            waConfig::set('is_template', $old_is_template);
        }
        return $result;
    }

    protected function cartBasicVars($order)
    {
        $currency = $order['currency'];
        $currency_info = reset(ref($this->getShopConfig()->getCurrencies($currency)));
        $locale_info = waLocale::getInfo(wa()->getLocale());

        $feature_codes = array_reduce($order['items'], function ($carry, $item) {
            return $carry + $item['product']['features'];
        }, []);

        $config = new shopCheckoutConfig(ifset(ref(wa()->getRouting()->getRoute()), 'checkout_storefront_id', []));
        return [
            'cart'          => $order,
            'currency_info' => [
                'code'             => $currency_info['code'],
                'fraction_divider' => ifset($locale_info, 'decimal_point', '.'),
                'fraction_size'    => ifset($currency_info, 'precision', 2),
                'group_divider'    => ifset($locale_info, 'thousands_sep', ''),
                'group_size'       => 3,

                'pattern_html' => str_replace('0', '%s', waCurrency::format('%{h}', 0, $currency)),
                'pattern_text' => str_replace('0', '%s', waCurrency::format('%{s}', 0, $currency)),

                'is_primary'    => $currency_info['is_primary'],
                'rate'          => $currency_info['rate'],
                'rounding'      => $currency_info['rounding'],
                'round_up_only' => $currency_info['round_up_only'],
            ],
            'features'      => (new shopFeatureModel())->getByCode(array_keys($feature_codes)),
            'config'        => $config,
        ];
    }

    protected function getTotalWeightHtml($weight_value)
    {
        if (!$weight_value) {
            return '';
        }

        $f = (new shopFeatureModel())->getByCode('weight');
        if (!$f || $f['type'] != 'dimension.weight') {
            return waLocale::format($weight_value);
        }

        $weight_info = shopDimension::getInstance()->getDimension('weight');
        $feature_values_dimension = new shopFeatureValuesDimensionModel();
        $dimension = new shopDimensionValue([
                'type'            => 'weight',
                'feature_id'      => $f['id'],
                'value'           => $weight_value,
                'value_base_unit' => $weight_value,
                'unit'            => $weight_info['base_unit'],
            ] + $feature_values_dimension->getEmptyRow());

        return $dimension->format('@locale');
    }

    /**
     * @param shopCart $cart
     * @return array
     */
    protected function getFakeOrder($cart)
    {
        //
        // Most of the fields (including, most importantly, discount)
        // are calculated using shopOrder
        //
        $cart_items = $cart->items(true);
        $order = (new shopFrontendOrderActions())->makeOrderFromCart($cart_items);

        $result = [
            'cart_code' => $cart->getCode(),
        ];
        foreach (['currency', 'total', 'subtotal', 'discount', 'discount_description'] as $i) {
            $result[$i] = $order[$i];
        }
        $result['params'] = $order['discount_params'];

        // discounts by item
        $items_discount = array_map(function ($discount) {
            return array(
                'id'       => $discount['cart_item_id'],
                'discount' => $discount['value'],
            );
        }, $order->items_discount);
        $items_discount = waUtils::getFieldValues($items_discount, 'discount', 'id');

        // Cart items are not taken from shopOrder because it knows nothing
        // about cart. Items are taken from cart directly. We just need to convert
        // hierarchical structure into plain one.
        $result['items'] = $this->formatCartItems($cart_items, $items_discount);
        $result['count'] = array_sum(waUtils::getFieldValues($result['items'], 'quantity', true));
        $result['count_html'] = _w('%d product', '%d products', $result['count']);

        // validate item counts against storefront stock
        $result['items'] = $this->validateStock($result['items']);

        // Total weight
        $result['total_weight'] = array_reduce($result['items'], function ($carry, $item) {
            return $carry + ifempty($item, 'product', 'weight', 0) * $item['quantity'];
        }, 0);
        $result['total_weight_html'] = $this->getTotalWeightHtml($result['total_weight']);

        return $result;
    }

    protected function cartCouponVars($data, $order)
    {
        if (!shopDiscounts::isEnabled('coupons')) {
            return array();
        }

        if (empty($data['coupon_code'])) {
            return array(
                'coupon_code' => '',
            );
        }

        return array(
            'coupon_code'          => $data['coupon_code'],
            'coupon_discount'      => ifset($order, 'params', 'coupon_discount', 0),
            'coupon_free_shipping' => ifset($order, 'params', 'coupon_free_shipping', 0),
        );
    }

    protected function cartAffiliateVars($data, $order)
    {
        if (!shopAffiliate::isEnabled()) {
            return array();
        }

        $affiliate_bonus = $affiliate_discount = 0;
        if (wa()->getUser()->isAuth()) {
            $customer_model = new shopCustomerModel();
            $customer = $customer_model->getById(wa()->getUser()->getId());
            $affiliate_bonus = $customer ? round($customer['affiliate_bonus'], 2) : 0;
        }

        $usage_percent = (float)wa()->getSetting('affiliate_usage_percent', 0, 'shop');
        $add_affiliate_bonus = shopAffiliate::calculateBonus($order);

        $affiliate_discount = 0;
        if (!empty($data['use_affiliate'])) {
            $affiliate_discount = shop_currency(
                shopAffiliate::convertBonus(ifset($order, 'params', 'affiliate_bonus', 0)),
                $this->getShopConfig()->getCurrency(true),
                null,
                false
            );
            if ($usage_percent) {
                $affiliate_discount = min($affiliate_discount, ($order['total'] + $affiliate_discount) * $usage_percent / 100.0);
            }
        } elseif ($affiliate_bonus) {
            $affiliate_discount = shop_currency(
                shopAffiliate::convertBonus($affiliate_bonus),
                $this->getShopConfig()->getCurrency(true),
                null,
                false
            );
            if ($usage_percent) {
                $affiliate_discount = min($affiliate_discount, $order['total'] * $usage_percent / 100.0);
            }
        }

        $template_vars = array(
            'affiliate' => array(
                'affiliate_bonus'      => $affiliate_bonus,
                'affiliate_discount'   => $affiliate_discount,
                'add_affiliate_bonus'  => round($add_affiliate_bonus, 2),
                'use_affiliate'        => !empty($data['use_affiliate']),
                'affiliate_percent'    => $usage_percent,
                'used_affiliate_bonus' => 0,
            ),
        );

        if (!empty($data['use_affiliate'])) {
            $template_vars['affiliate']['used_affiliate_bonus'] = ifset($order, 'params', 'affiliate_bonus', 0);
        }

        return $template_vars;
    }

    protected function validateStock($cart_items)
    {
        $code = ifset(ref(reset($cart_items)), 'code', null);
        if (!$code) {
            return $cart_items;
        }

        if (wa()->getSetting('ignore_stock_count')) {
            $check_count = false;
        } else {
            $check_count = true;
            if (wa()->getSetting('limit_main_stock') && waRequest::param('stock_id')) {
                $stock_id = waRequest::param('stock_id', null, 'string');
                $check_count = $stock_id;
            }
        }

        $cart_model = new shopCartItemsModel();
        $item_counts = $cart_model->checkAvailability($code, $check_count);

        return array_map(function ($item) use ($item_counts) {
            $item_data = ifset($item_counts, $item['id'], [
                'count'          => 0,
                'status'         => 0,
                'available'      => false,
                'can_be_ordered' => false,
            ]);
            $item['stock_count'] = $item_data['count'];
            $item['sku_available'] = (bool)$item_data['available'] && (bool)$item_data['sku_status'] && $item_data['status'] > 0;
            $item['can_be_ordered'] = (bool)$item_data['can_be_ordered'];
            if (!$item['can_be_ordered']) {
                $name = $item['name'];
                if ($item['sku_name']) {
                    $name .= ' ('.$item['sku_name'].')';
                }
                $name = htmlspecialchars($name);

                if ($item['sku_available']) {
                    if ($item['stock_count'] > 0) {
                        $item['error'] = sprintf(
                            _w('Only %d pcs of %s are available, and you already have all of them in your shopping cart.'),
                            $item['stock_count'],
                            $name
                        );
                    } else {
                        $item['error'] = sprintf(
                            _w('Oops! %s just went out of stock and is not available for purchase at the moment. We apologize for the inconvenience. Please remove this product from your shopping cart to proceed.'),
                            $name
                        );
                    }
                } else {
                    $item['error'] = sprintf(
                        _w('Oops! %s is not available for purchase at the moment. Please remove this product from your shopping cart to proceed.'),
                        $name
                    );
                }
            }
            return $item;
        }, $cart_items);
    }

    protected function formatCartItems($cart_items, $items_discount)
    {
        // Convert items from hierarchical into flat list
        $items = [];
        foreach ($cart_items as $item_id => $item) {
            if (isset($item['services'])) {
                $i = $item;
                unset($i['services']);
                $items[$item_id] = $i;
                foreach ($item['services'] as $s) {
                    $items[$s['id']] = $s;
                }
            } else {
                $items[$item_id] = $item;
            }
        }

        shopOrderItemsModel::sortItemsByGeneralSettings($items);

        // Insert per-item discounts
        foreach ($items as &$item) {
            $item['discount'] = ifset($items_discount, $item['id'], 0);
            if ($item['type'] == 'service' && isset($items[$item['parent_id']]['discount'])) {
                $items[$item['parent_id']]['discount'] += $item['discount'];
            }
        }
        unset($item);

        $service_model = new shopServiceModel();
        $items = $service_model->applyServicesInfoToCartItems($items);

        // Full price and compare (strike-out) price for each item, with services
        foreach ($items as $item_id => $item) {
            $services_price = array_sum(array_map(function ($s) use ($item) {
                if (empty($s['id'])) {
                    return 0;
                } elseif (isset($s['variants'])) {
                    return shop_currency($s['variants'][$s['variant_id']]['price'] * $item['quantity'], $s['currency'], null, false);
                } else {
                    return shop_currency($s['price'] * $item['quantity'], $s['currency'], null, false);
                }
            }, ifset($item, 'services', [])));

            $items[$item_id]['full_price'] = $services_price + shop_currency($item['price'] * $item['quantity'], $item['currency'], null, false);
            $items[$item_id]['full_compare_price'] = $services_price + shop_currency($item['compare_price'] * $item['quantity'], $item['currency'], null, false);
        }

        // Prepare product features, including weight
        $product_features_model = new shopProductFeaturesModel();
        foreach ($items as &$item) {
            $item['product']['features'] = $product_features_model->getValues($item['product_id'], $item['sku_id'], $item['product']['type_id'], $item['product']['sku_type'],
                false);
            $item['product']['weight'] = ifset($item, 'product', 'features', 'weight', null);
            $item['product']['weight_html'] = $item['product']['weight'];
            $item['total_weight'] = null;
            $item['total_weight_html'] = null;
            if ($item['product']['weight'] instanceof shopDimensionValue) {
                /** @var shopDimensionValue $weight */
                $weight = $item['product']['weight'];
                $item['product']['weight_html'] = $weight->format('@locale');
                $item['product']['weight'] = $weight->value_base_unit;
                $weight['value'] *= $item['quantity'];
                $weight['value_base_unit'] *= $item['quantity'];
                $item['total_weight_html'] = $weight->format('@locale');
                $item['total_weight'] = $weight->value_base_unit;
            } elseif (is_numeric($item['product']['weight'])) {
                $item['total_weight'] = $item['product']['weight'] * $item['quantity'];
                $item['total_weight_html'] = $item['total_weight'];
            }
            $item['weight'] = $item['product']['weight'];
            $item['weight_html'] = $item['product']['weight_html'];
        }
        unset($item);

        return $items;
    }

    //
    // Cart-related methods above this.
    // Form-related methods below this.
    //

    /**
     * Returns HTML rendering form block for new one-page checkout
     *
     * @param array
     * @return string
     */
    public function form($opts = array())
    {
        $template_path = wa()->getAppPath('templates/actions/frontend/FrontendOrderForm.html', 'shop');

        return $this->renderTemplate($template_path, $this->formVars() + array(
                'shop_checkout_include_path' => wa()->getAppPath('templates/actions/frontend/order/', 'shop'),
                'options'                    => $opts,
            ));
    }

    /**
     * Returns variables that $wa->shop->checkout()->form() assigns to its template.
     * @param bool $clear_cache
     * @return array
     */
    public function formVars($clear_cache = false)
    {
        static $result = null;
        if ($clear_cache || $result === null) {
            $old_is_template = waConfig::get('is_template');
            waConfig::set('is_template', null);

            // Get checkout order block data from session
            $session_checkout = wa()->getStorage()->get('shop/checkout');
            $session_input = (!empty($session_checkout['order']) && is_array($session_checkout['order'])) ? $session_checkout['order'] : [];

            /** флаг предназначен для шага Shipping */
            $session_input['fast_render'] = true;

            /** @var shopOrder $order */
            $order = (new shopFrontendOrderActions())->makeOrderFromCart();
            $process_data = shopCheckoutStep::processAll('form', $order, $session_input);

            $result = $this->prepareFormVars($process_data);
            $result['session_is_alive'] = !empty($session_checkout['order']);
            waConfig::set('is_template', $old_is_template);
        }
        return $result;
    }

    /**
     * Turns result of shopCheckoutStep::processAll() into list of variables
     * suited for FrontendOrderForm.html template.
     * Used in ::formVars() above, as well as in shopFrontendOrderActions for order/calculate
     */
    public function prepareFormVars($process_data)
    {
        $config = new shopCheckoutConfig(true);
        $result = array_merge([
            'config'  => $config,
            'contact' => ifset($process_data, 'contact', null),
        ], $process_data['result']);

        if (!isset($result['error_step_id'])) {
            $result['error_step_id'] = null;
        }
        if (!isset($result['errors'])) {
            $result['errors'] = null;
        }

        // Run all the checkout_render_* events
        // Also see other checkout_* events in shopCheckoutStep::processAll()
        foreach ($config->getCheckoutSteps() as $step) {
            if (empty($result['event_hook'][$step->getId()])) {
                $result['event_hook'][$step->getId()] = wa('shop')->event('checkout_render_'.$step->getId(), ref([
                    'step_id'       => $step->getId(),
                    'data'          => $process_data,
                    'error_step_id' => &$result['error_step_id'],
                    'errors'        => &$result['errors'],
                    'vars'          => &$result,
                ]));
            }
        }

        return $result;
    }

    protected function renderTemplate($template_path, $assign = array())
    {
        $view = wa('shop')->getView();
        $old_vars = $view->getVars();

        // Keep vars from parent template
        //$view->clearAllAssign();

        $view->assign($assign);
        $html = $view->fetch($template_path);
        $view->clearAllAssign();
        $view->assign($old_vars);
        return $html;
    }

    /**
     * Returns url to storefront checkout
     * @param bool $absolute
     * @return string
     *
     * NEW CHECKOUT: /shop/order/
     * OLD CHECKOUT: /shop/checkout/
     */
    public function url($absolute = false)
    {
        $route = wa()->getRouting()->getRoute();
        $app = ifset($route, 'app', null);
        if ($app !== 'shop') {
            $route = $this->getShopConfig()->getStorefrontRoute();
        }

        $checkout_version = ifset($route, 'checkout_version', 1);

        if ($checkout_version == 2) {
            return wa()->getRouteUrl('shop/frontend/order', [], $absolute);
        }

        return wa()->getRouteUrl('shop/frontend/checkout', [], $absolute);
    }

    /**
     * Returns url to storefront cart
     * @param bool $absolute
     * @return string
     *
     * NEW CHECKOUT: /shop/order/
     * OLD CHECKOUT: /shop/cart/
     */
    public function cartUrl($absolute = false)
    {
        $route = wa()->getRouting()->getRoute();
        $app = ifset($route, 'app', null);
        if ($app !== 'shop') {
            $route = $this->getShopConfig()->getStorefrontRoute();
        }

        $checkout_version = ifset($route, 'checkout_version', 1);

        if ($checkout_version == 2) {
            return wa()->getRouteUrl('shop/frontend/order', [], $absolute);
        }

        return wa()->getRouteUrl('shop/frontend/cart', [], $absolute);
    }

    /**
     * Returns HTML rendering cross-selling block for new one-page checkout
     *
     * @param array
     * @return string
     */
    public function crossSelling($opts = array())
    {
        try {
            $checkout_config = new shopCheckoutConfig(true);
            if (empty($checkout_config['recommendations']['used'])) {
                return '';
            }
        } catch (waException $e) {
            return '';
        }

        $template_path = wa()->getAppPath('templates/actions/frontend/order/cart/CrossSelling.html', 'shop');
        $vars = $this->crossSellingVars(false, shopViewHelper::CROSS_SELLING_IN_STOCK);
        if (empty($vars['products'])) {
            return '';
        }

        return $this->renderTemplate($template_path, $vars + [
                'shop_checkout_include_path' => wa()->getAppPath('templates/actions/frontend/order/', 'shop'),
                'options'                    => $opts + [
                        'adaptive' => true,
                    ],
            ]);
    }

    /**
     * Returns variables that $wa->shop->checkout()->crossSelling() assigns to its template.
     *
     * @param bool $clear_cache
     * @param bool $available_only
     * @return array
     */
    public function crossSellingVars($clear_cache = false, $available_only = false)
    {
        static $result = null;
        if ($clear_cache || $result === null) {
            $old_is_template = waConfig::get('is_template');
            waConfig::set('is_template', null);
            $vars = $this->cartVars();
            $related = wa()->getView()->getHelper()->shop->crossSelling($vars['cart']['items'], 5, $available_only, 'product_id');
            waConfig::set('is_template', $old_is_template);
            $result = [
                'products' => $related,
            ];
        }
        return $result;
    }


    /**
     * @return shopConfig
     */
    protected function getShopConfig()
    {
        /** @var shopConfig $config */
        $config = wa('shop')->getConfig();
        return $config;
    }
}
