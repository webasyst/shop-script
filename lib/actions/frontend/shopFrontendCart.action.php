<?php

class shopFrontendCartAction extends shopFrontendAction
{
    public function execute()
    {
        // In case new checkout is enabled and current design theme is not adapted to use it properly
        // (i.e. set up to use template from default theme), this controller must redirect to new cart url.
        // We only redirect for non-XHRs because themes use XHRs here to fetch cart data.
        if (!waRequest::isXMLHttpRequest() && !waRequest::request('forcenoredirect')) {
            $route = wa()->getRouting()->getRoute();
            $checkout_version = ifset($route, 'checkout_version', 1);
            if ($checkout_version == 2 && isset($route['checkout_storefront_id'])) {
                $checkout_config = new shopCheckoutConfig($route['checkout_storefront_id']);
                if (ifempty($checkout_config, 'design', 'custom', null) === true) {
                    $this->redirect(wa()->getRouteUrl('shop/frontend/order'));
                }
            }
        }

        $this->getResponse()->addHeader("Cache-Control", "no-store, no-cache, must-revalidate");
        $this->getResponse()->addHeader("Expires", date("r"));

        if (waRequest::method() == 'post') {
            $data = wa()->getStorage()->get('shop/checkout', array());
            if ($coupon_code = waRequest::post('coupon_code')) {
                $data['coupon_code'] = $coupon_code;
            } elseif (isset($data['coupon_code'])) {
                unset($data['coupon_code']);
            }

            if (($use = waRequest::post('use_affiliate')) !== null) {
                if ($use) {
                    $data['use_affiliate'] = 1;
                } elseif (isset($data['use_affiliate'])) {
                    unset($data['use_affiliate']);
                }
            }

            wa()->getStorage()->set('shop/checkout', $data);
            wa()->getStorage()->remove('shop/cart');
        }

        $cart = new shopCart();
        $code = $cart->getCode();

        $errors = array();
        $cart_model = new shopCartItemsModel();
        //$items = $cart_model->where('code= ?', $code)->order('parent_id')->fetchAll('id');
        $items = $cart->items(false);
        shopOrderItemsModel::sortItemsByGeneralSettings($items);
        $total = $cart->total(false);
        $order = array(
            'currency' => wa()->getConfig()->getCurrency(false),
            'total'    => $total,
            'items'    => $items
        );
        $discount_description = '';
        $order['discount'] = $discount = shopDiscounts::calculate($order, false, $discount_description);
        $order['total'] = $total = $total - $order['discount'];

        if (isset($order['discount_decreased_by'])) {

            $discount += $order['discount_decreased_by'];
            $total -= $order['discount_decreased_by'];
            $order['discount'] = $discount;
            $order['total'] = $total;

            foreach ($items as &$row) {
                $row['error'] = _w('Unable to create an order with the specified discount. Please contact the store’s support team.');
                break;
            }
            unset($row);

        } else if (waRequest::post('checkout')) {
            $saved_quantity = $cart_model->select('id,quantity')->where("type='product' AND code = s:code", array('code' => $code))->fetchAll('id');
            $quantity = waRequest::post('quantity');
            foreach ($quantity as $id => $q) {
                if (isset($saved_quantity[$id]) && ($q != $saved_quantity[$id])) {
                    $cart->setQuantity($id, $q);
                }
            }

            if (wa()->getSetting('ignore_stock_count')) {
                $check_count = false;
            } else {
                $check_count = true;
                if (wa()->getSetting('limit_main_stock') && waRequest::param('stock_id')) {
                    $check_count = waRequest::param('stock_id');
                }
            }
            $not_available_items = $cart_model->getNotAvailableProducts($code, $check_count);
            self::validateNotAvailableProducts($not_available_items, $errors);

            foreach ($items as &$row) {
                if (!$row['quantity'] && !isset($errors[$row['id']])) {
                    $errors[$row['id']] = null;
                }
                $row['error'] = (isset($errors[$row['id']]) ? $errors[$row['id']] : null);
            }
            unset($row);
            if (!$errors) {
                $this->redirect(wa()->getRouteUrl('/frontend/checkout'));
            }
        }

        $this->setThemeTemplate('cart.html');

        $service_model = new shopServiceModel();
        $items = $service_model->applyServicesInfoToCartItems($items);

        // Calculate full price
        foreach ($items as $item_id => $item) {
            $price = shop_currency($item['price'] * $item['quantity'], $item['currency'], null, false);
            if (isset($item['services'])) {
                foreach ($item['services'] as $s) {
                    if (!empty($s['id'])) {
                        if (isset($s['variants'])) {
                            $price += shop_currency($s['variants'][$s['variant_id']]['price'] * $item['quantity'], $s['currency'], null, false);
                        } else {
                            $price += shop_currency($s['price'] * $item['quantity'], $s['currency'], null, false);
                        }
                    }
                }
            }
            $items[$item_id]['full_price'] = $price;
        }

        $data = wa()->getStorage()->get('shop/checkout');
        $this->view->assign('cart', array(
            'items' => $items,
            'total' => $total,
            'count' => $cart->count()
        ));

        $this->view->assign('coupon_code', isset($data['coupon_code']) ? $data['coupon_code'] : '');
        if (!empty($data['coupon_code']) && !empty($order['params']['coupon_discount'])) {
            $this->view->assign('coupon_discount', $order['params']['coupon_discount']);
        }
        if (!empty($data['coupon_code']) && isset($order['shipping']) && $order['shipping'] === 0) {
            $this->view->assign('coupon_free_shipping', true);
        }
        if (shopAffiliate::isEnabled()) {
            $affiliate_bonus = $affiliate_discount = $potential_affiliate_discount = 0;
            if ($this->getUser()->isAuth()) {
                $customer_model = new shopCustomerModel();
                $customer = $customer_model->getById($this->getUser()->getId());
                $affiliate_bonus = $customer ? round($customer['affiliate_bonus'], 2) : 0;
            }
            $this->view->assign('affiliate_bonus', $affiliate_bonus);

            $use = !empty($data['use_affiliate']);
            $this->view->assign('use_affiliate', $use);
            $usage_percent = (float)wa()->getSetting('affiliate_usage_percent', 0, 'shop');
            $this->view->assign('affiliate_percent', $usage_percent);
            $affiliate_discount = self::getAffiliateDiscount($affiliate_bonus, $order);
            $potential_affiliate_discount = self::getAffiliateDiscount($affiliate_bonus, $order);
            $this->view->assign('potential_affiliate_discount', $potential_affiliate_discount);

            if ($use) {
                $discount -= $affiliate_discount;
                $this->view->assign('used_affiliate_bonus', $order['params']['affiliate_bonus']);
            }

            /**
             * If the new order currency differs from the default currency, then convert the bonus discount to the default currency.
             * This is done because in the cart.html template the currency is converted into the order currency
             */
            if ($order['currency'] != wa('shop')->getConfig()->getCurrency(true)) {
                $currencies = wa('shop')->getConfig()->getCurrencies($order['currency']);
                $affiliate_discount = $affiliate_discount * $currencies[$order['currency']]['rate'];
            }

            $this->view->assign('affiliate_discount', $affiliate_discount);

            $add_affiliate_bonus = shopAffiliate::calculateBonus($order);
            $this->view->assign('add_affiliate_bonus', round($add_affiliate_bonus, 2));
        }
        $this->view->assign('discount', $discount);

        /**
         * @event frontend_cart
         * @return array[string]string $return[%plugin_id%] html output
         */
        $this->view->assign('frontend_cart', wa()->event('frontend_cart'));

        $this->getResponse()->setTitle(_w('Cart'));

        $checkout_flow = new shopCheckoutFlowModel();
        $checkout_flow->add(array(
            'code'        => $code,
            'step'        => 0,
            'description' => null /* TODO: Error message here if exists */
        ));

    }

    public static function validateNotAvailableProducts($not_available_items, &$errors)
    {
        foreach ($not_available_items as $row) {
            if ($row['sku_name']) {
                $row['name'] .= ' ('.$row['sku_name'].')';
            }
            if ($row['available'] && $row['sku_status'] && $row['status'] > 0) {
                if ($row['count'] > 0) {
                    $message = _w('Only %d pcs of %s are available, and you already have all of them in your shopping cart.');
                    $errors[$row['id']] = sprintf($message, $row['count'], $row['name']);
                } else {
                    $message = _w('Oops! %s just went out of stock and is not available for purchase at the moment. We apologize for the inconvenience. Please remove this product from your shopping cart to proceed.');
                    $errors[$row['id']] = sprintf($message, $row['name']);
                }
            } else {
                $message = _w('Oops! %s is not available for purchase at the moment. Please remove this product from your shopping cart to proceed.');
                $errors[$row['id']] = sprintf($message, $row['name']);
            }
        }
    }

    public static function getAffiliateDiscount($affiliate_bonus, $order)
    {
        $data = wa()->getStorage()->get('shop/checkout');
        $use = !empty($data['use_affiliate']);
        $usage_percent = (float)wa()->getSetting('affiliate_usage_percent', 0, 'shop');
        if (!$usage_percent) {
            $usage_percent = 100;
        }
        $affiliate_discount = 0;
        if ($use) {
            $affiliate_discount = shop_currency(shopAffiliate::convertBonus($order['params']['affiliate_bonus']), wa('shop')->getConfig()->getCurrency(true), null, false);
            if ($usage_percent) {
                $affiliate_discount = min($affiliate_discount, ($order['total'] + $affiliate_discount) * $usage_percent / 100.0);
            }
        } elseif ($affiliate_bonus) {
            $affiliate_discount = shop_currency(shopAffiliate::convertBonus($affiliate_bonus), wa('shop')->getConfig()->getCurrency(true), null, false);
            if ($usage_percent) {
                $affiliate_discount = min($affiliate_discount, $order['total'] * $usage_percent / 100.0);
            }
        }
        return $affiliate_discount;
    }

}
