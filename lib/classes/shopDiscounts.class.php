<?php

class shopDiscounts
{
    /**
     * Returns aggregate discount amount applicable to order.
     *
     * @param array $order Order data array
     * @param bool $apply Whether discount-related information must be added to order parameters (where appropriate)
     * @param string $description will be set to human-readable description of discount calculation
     * @return float Total discount value expressed in order currency
     */
    public static function calculate(&$order, $apply = false, &$description = null)
    {
        $currency = isset($order['currency']) ? $order['currency'] :  wa('shop')->getConfig()->getCurrency(false);
        $contact = self::getContact($order);
        $order = self::prepareOrderData($order, $contact);
        $discounts = array();

        // Discount by contact category applicable?
        if (self::isEnabled('category')) {
            $tmp = self::byCategory($order, $contact);
            if ($tmp) {
                $discounts[] = $tmp;
            }
        }

        // Discount by coupon applicable?
        if (self::isEnabled('coupons')) {
            $tmp = self::byCoupons($order, $contact, $apply);
            if ($tmp) {
                $discounts[] = $tmp;
            }
        }

        // Discount by order total applicable?
        if (self::isEnabled('order_total')) {
            $dbsm = new shopDiscountBySumModel();

            // Order total in default currency
            $order_total = (float) shop_currency($order['total'], $currency, wa('shop')->getConfig()->getCurrency(), false);
            $percent = (float) $dbsm->getDiscount('order_total', $order_total);
            $tmp = self::byPercent($order, $percent, sprintf_wp('By order total, %s%%', $percent));
            if ($tmp) {
                $discounts[] = $tmp;
            }
        }

        // Discount by customer total spent applicable?
        if (self::isEnabled('customer_total')) {
            $tmp = self::byCustomerTotal($order, $contact, $apply);
            if ($tmp) {
                $discounts[] = $tmp;
            }
        }

        /**
         * @event order_calculate_discount
         * @param array $params
         * @param array[string] $params['order'] order info array('total' => '', 'items' => array(...))
         * @param array[string] $params['contact'] contact info
         * @param bool[string] $params['apply'] calculate or apply discount
         * @return array[string] $return['description'] discount description to save in order log
         * @return array[float] $return['discount'] discount amount in order currency
         */
        $event_params = array('order' => &$order, 'contact' => $contact, 'apply' => $apply);
        $plugins_discounts = wa('shop')->event('order_calculate_discount', $event_params);
        foreach ($plugins_discounts as $plugin_id => $plugin_discount) {
            if (is_array($plugin_discount)) {
                $discounts[] = $plugin_discount;
            } else {
                $plugin_description = self::getPluginName($plugin_id).': '.shop_currency_html($plugin_discount, $currency, $currency);
                $discounts[] = array(
                    'discount' => $plugin_discount,
                    'description' => $plugin_description
                );
            }
        }

        // How do discounts combine: 'max' or 'sum' of all applicable discounts
        $discount_combine_type = waSystem::getSetting('discounts_combine', null, 'shop');

        // Process discounts of individual order items.
        $items_description = '';
        $total_item_discount = 0.0;
        foreach ($order['items'] as $item_id => $item) {
            $item_discount = 0;
            $item_discount_description = '';
            foreach ($discounts as $d) {
                if (!isset($d['items'][$item_id])) {
                    continue;
                }

                $discount_amount = $d['items'][$item_id]['discount'];
                $discount_description = $d['items'][$item_id]['description'].': '.shop_currency($discount_amount, $currency, $currency);

                if ($discount_combine_type == 'sum') {
                    $item_discount += $discount_amount;
                    $item_discount_description .= '<li>'.$discount_description.'</li>';
                } else {
                    if ($discount_amount > $item_discount) {
                        $item_discount = $discount_amount;
                        $item_discount_description = $discount_description;
                    }
                }
            }

            if ($item_discount) {
                $item_discount = min(max(0, $item_discount), shop_currency($item['price'], $item['currency'], $currency, false) * $item['quantity']);
                $order['items'][$item_id]['total_discount'] = $item_discount;
                $total_item_discount += $item_discount;

                $items_description .= $item['name'].' &minus; ';
                if ($discount_combine_type == 'sum') {
                    $items_description .= '<ul>'.$item_discount_description.'</ul>';
                } else {
                    $items_description .= $item_discount_description;
                }
                $items_description .= '<br>';
            }
        }

        // Process general order discounts, not tied to any item.
        $order_discount = 0;
        $order_discount_description = '';
        foreach ($discounts as $d) {
            if (empty($d['discount'])) {
                continue;
            }

            $d['description'] = $d['description'].': '.shop_currency($d['discount'], $currency, $currency);
            if ($discount_combine_type == 'sum') {
                $order_discount += $d['discount'];
                $order_discount_description .= '<li>'.$d['description'].'</li>';
            } else {
                if ($d['discount'] > $order_discount) {
                    $order_discount = $d['discount'];
                    $order_discount_description = $d['description'];
                }
            }
        }

        // Total discount and description
        $description = '';
        $discount = $total_item_discount + $order_discount;
        if ($discount) {
            if (wa('shop')->getConfig()->getOption('discount_description')) {
                $description = $items_description;
            }
            if ($discount_combine_type == 'sum') {
                if ($order_discount) {
                    $description .= '<ul>'.$order_discount_description.'</ul><br>';
                }
                $description .= sprintf_wp('Shop is set up to use sum of all discounts: %s', shop_currency_html($discount, $currency, $currency));
            } else {
                if ($order_discount) {
                    $description .= $order_discount_description.'<br>';
                }
                $description .= sprintf_wp('Shop is set up to use single largest of all discounts: %s', shop_currency_html($discount, $currency, $currency));
            }
        }

        // Discount based on affiliate bonus?
        if (shopAffiliate::isEnabled()) {
            $d = null;
            $amount = (float) shopAffiliate::discount($order, $contact, $apply, $discount, $d);
            $discount = $discount + $amount;
            $d && $amount > 0 && ($description .= "\n<br>".sprintf($d, shop_currency_html($amount, $currency, $currency)));
        }

        // Round the discount if set up to do so
        if ($discount && wa()->getEnv() == 'frontend' && waSystem::getSetting('round_discounts', '', 'shop') && $discount < ifset($order['total'], 0)) {
            $rounded_discount = shopRounding::roundCurrency($discount, $currency, true);
            if ($rounded_discount != $discount) {
                $discount = $rounded_discount;
                $description .= "\n<br>";
                $description .= sprintf_wp('Discount rounded to %s', shop_currency_html($discount, $currency, $currency));
            }
        }

        return min(max(0, $discount), ifset($order['total'], 0));
    }

    protected static function getPluginName($plugin_id)
    {
        if (wa()->appExists($plugin_id)) {
            $apps = wa()->getApps();
            $d = ifset($apps[$plugin_id]['name'], $plugin_id);
        } else {
            $d = null;
            $pid = str_replace('-plugin', '', $plugin_id);
            try {
                $d = wa('shop')->getPlugin($pid)->getName();
            } catch (Exception $e) { }
            $d = ifempty($d, $plugin_id);
        }
        return $d;
    }

    /**
     * Returns aggregate discount amount applicable to order and adds discount-related information to order parameters where appropriate.
     *
     * @param array $order Order data array
     * @return float Total discount value expressed in order currency
     */
    public static function apply(&$order, &$description=null)
    {
        return self::calculate($order, true, $description);
    }

    /**
     * Same as ::apply(), but used after an existing order is modified.
     */
    public static function reapply(&$order, &$description=null)
    {
        return self::calculate($order, 'reapply', $description);
    }

    /**
     * Determines whether specified discount type is enabled in store settings field 'discount_%type%'.
     *
     * @param string $discount_type Discount type id
     * @return bool
     */
    public static function isEnabled($discount_type)
    {
        return !empty($discount_type) && waSystem::getSetting('discount_'.$discount_type, null, 'shop');
    }

    protected static function byPercent($order, $percent, $description)
    {
        $result = array();
        $percent = max(0.0, min(100.0, $percent));
        $discount = $percent * $order['total'] / 100.0;
        foreach ($order['items'] as $item_id => $item) {
            $item_discount = $percent * $item['price'] / 100.0;
            $item_discount = shop_currency($item_discount, $item['currency'], $order['currency'], false) * $item['quantity'];
            $result['items'][$item_id] = array(
                'discount' => $item_discount,
                'description' => $description
            );
            $discount -= $item_discount;
        }
        $discount = round($discount, 4);
        if ($discount) {
            $result['discount'] = $discount;
            $result['description'] = $description;
        }
        return $result;
    }

    /** Discounts by amount of money previously spent by this customer. */
    protected static function byCustomerTotal($order, $contact, $apply)
    {
        if (!$contact || !$contact->getId()) {
            return 0;
        }
        $cm = new shopCustomerModel();
        $customer = $cm->getById($contact->getId());
        if ($customer && $customer['total_spent'] > 0) {
            $dbsm = new shopDiscountBySumModel();
            $description = _w('By customers overall purchases').' ('.shop_currency_html($customer['total_spent'], null, wa('shop')->getConfig()->getCurrency(true)).')';
            $percent = (float) $dbsm->getDiscount('customer_total', $customer['total_spent']);
            return self::byPercent($order, $percent, $description);
        }
        return null;
    }

    /**
     * Discounts by category implementation.
     * @param array $order
     * @param array $contact
     * @return array
     */
    protected static function byCategory($order, $contact)
    {
        if (!$contact) {
            return null;
        }
        $ccdm = new shopContactCategoryDiscountModel();
        $percent = $ccdm->getByContact($contact->getId());
        if ($percent != 0) {
            $description = sprintf_wp('By customer category, %s%%', $percent);
            return self::byPercent($order, $percent, $description);
        }
        return null;
    }

    /** Coupon discounts implementation. */
    protected static function byCoupons(&$order, $contact, $apply)
    {
        $d = '';
        $currency = isset($order['currency']) ? $order['currency'] : wa('shop')->getConfig()->getCurrency(false);

        $cm = new shopCouponModel();
        $checkout_data = wa('shop')->getStorage()->read('shop/checkout');
        if (!empty($order['id'])) {
            // Recalculating existing order: take coupon code from order params
            $order_params_model = new shopOrderParamsModel();
            $order['params'] = ifset($order['params'], array()) + $order_params_model->get($order['id']);
            if (empty($order['params']['coupon_id'])) {
                return 0;
            }
            $coupon_id = $order['params']['coupon_id'];

            $coupon = $cm->getById($coupon_id);
            $coupon_code = ifset($coupon['code'], 'coupon_id='.$coupon_id);
            $discount = ifset($order['params']['coupon_discount'], 0);
            $d = _w('Coupon code').' '.$coupon_code.': %s';
            if (ifset($coupon['type']) == '$FS') {
                $d = sprintf($d, _w('Free shipping'));
            }
            return array(
                'discount' => $discount,
                'description' => $d
            );
        } else if (empty($checkout_data['coupon_code'])) {
            return null;
        }

        $coupon = $cm->getByField('code', $checkout_data['coupon_code']);
        if (!$coupon || !shopCouponsAction::isEnabled($coupon)) {
            return 0;
        }

        $d = _w('Coupon code').' '.$coupon['code'];

        switch ($coupon['type']) {
            case '$FS':
                $order['shipping'] = 0;
                $discount = 0;
                $result = array(
                    'discount' => $discount,
                    'description' => $d.': '._w('Free shipping')
                );
                break;
            case '%':
                $percent = (float) $coupon['value'];
                $discount = max(0.0, min(100.0, $percent)) * $order['total'] / 100.0;
                $result = self::byPercent($order, $percent, $d);
                break;
            default:
                // Flat value in currency
                $discount = max(0.0, (float) $coupon['value']);
                if ($currency != $coupon['type']) {
                    $crm = new shopCurrencyModel();
                    $discount = (float) $crm->convert($discount, $coupon['type'], $currency);
                }
                $result = array(
                    'discount' => $discount,
                    'description' => $d
                );
                break;
        }

        if ($apply) {
            $cm->useOne($coupon['id']);
            if (empty($order['params'])) {
                $order['params'] = array();
            }
            $order['params']['coupon_id'] = $coupon['id'];
        }
        $order['params']['coupon_discount'] = $discount;

        return $result;
    }

    /** Helper for apply() and calculate() to get customer's waContact from order data. May return null for new customers. */
    protected static function getContact($order)
    {
        if (isset($order['contact']) && $order['contact'] instanceof waContact) {
            return $order['contact'];
        } elseif (!empty($order['contact_id'])) {
            return new waContact($order['contact_id']);
        } elseif (wa()->getEnv() == 'frontend') {
            return wa()->getUser();
        }
        return null;
    }

    protected static function prepareOrderData($order, $contact)
    {
        $order['id'] = ifempty($order['id']);
        $order['total'] = ifset($order['total'], 0);
        $order['currency'] = ifset($order['currency'], wa('shop')->getConfig()->getCurrency(false));
        $order['contact'] = $contact;
        if (empty($order['params'])) {
            unset($order['params']);
        }

        foreach($order['items'] as &$item) {
            if (!isset($item['currency'])) {
                $item['currency'] = $order['currency'];
            }
        }
        unset($item);

        return $order;
    }
}
