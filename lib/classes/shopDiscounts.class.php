<?php

class shopDiscounts
{
    /**
     * @param array $order items, total
     * @param bool $apply
     * @return float total discount value in currency of the order
     */
    public static function calculate(&$order, $apply = false)
    {
        $applicable_discounts = array();
        $contact = self::getContact($order);

        // Discount by contact category applicable?
        if (self::isEnabled('category')) {
            $applicable_discounts[] = self::byCategory($order, $contact, $apply);
        }

        // Discount by coupon applicable?
        if (self::isEnabled('coupons')) {
            $applicable_discounts[] = self::byCoupons($order, $contact, $apply);
        }

        // Discount by order total applicable?
        if (self::isEnabled('order_total')) {
            $crm = new shopCurrencyModel();
            $dbsm = new shopDiscountBySumModel();

            // Order total in default currency
            $order_total = (float) $crm->convert($order['total'], wa()->getConfig()->getCurrency(false), wa()->getConfig()->getCurrency());
            $applicable_discounts[] = max(0.0, min(100.0, (float) $dbsm->getDiscount('order_total', $order_total))) * $order['total'] / 100.0;
        }

        // Discount by customer total spent applicable?
        if (self::isEnabled('customer_total')) {
            $applicable_discounts[] = self::byCustomerTotal($order, $contact, $apply);
        }

        /**
         * @event order_calculate_discount
         * @param array $params
         * @param array[string] $params['order'] order info array('total' => '', 'items' => array(...))
         * @param array[string] $params['contact'] contact info
         * @param array[string] $params['apply'] calculate or apply discount
         * @return float discount
         */
        $event_params = array('order' => &$order, 'contact' => $contact, 'apply' => $apply);
        $plugins_discounts = wa()->event('order_calculate_discount', $event_params);
        foreach ($plugins_discounts as $plugin_discount) {
            $applicable_discounts[] = $plugin_discount;
        }

        // Select max discount or sum depending on global setting.
        $discount = 0.0;
        if ( ( $applicable_discounts = array_filter($applicable_discounts, 'is_numeric'))) {
            if (wa()->getSetting('discounts_combine') == 'sum') {
                $discount = (float) array_sum($applicable_discounts);
            } else {
                $discount = (float) max($applicable_discounts);
            }
        }

        // Discount based on affiliate bonus?
        if (shopAffiliate::isEnabled()) {
            $discount = $discount + (float) shopAffiliate::discount($order, $contact, $apply, $discount);
        }

        return min(max(0, $discount), ifset($order['total'], 0));
    }

    /**
     * @param array $order
     * @return float total discount value in currency of the order
     */
    public static function apply(&$order)
    {
        return self::calculate($order, true);
    }

    public static function isEnabled($discount_type)
    {
        return !empty($discount_type) && wa()->getSetting('discount_'.$discount_type);
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
            return max(0.0, min(100.0, (float) $dbsm->getDiscount('customer_total', $customer['total_spent']))) * $order['total'] / 100.0;
        }
        return 0.0;
    }

    /** Discounts by category implementation. */
    protected static function byCategory($order, $contact, $apply)
    {
        if (!$contact) {
            return 0;
        }

        $ccdm = new shopContactCategoryDiscountModel();
        return max(0.0, min(100.0, $ccdm->getByContact($contact->getId()))) * $order['total'] / 100.0;
    }

    /** Coupon discounts implementation. */
    protected static function byCoupons(&$order, $contact, $apply)
    {
        $checkout_data = wa()->getStorage()->read('shop/checkout');
        if (empty($checkout_data['coupon_code'])) {
            return 0; // !!! Will this fail when recalculating existing order?
        }

        $cm = new shopCouponModel();
        $coupon = $cm->getByField('code', $checkout_data['coupon_code']);
        if (!$coupon || !shopCouponsAction::isEnabled($coupon)) {
            return 0;
        }

        switch ($coupon['type']) {
            case '$FS':
                $order['shipping'] = 0;
                $result = 0;
                break;
            case '%':
                $result = max(0.0, min(100.0, (float) $coupon['value'])) * $order['total'] / 100.0;
                break;
            default:
                // Flat value in currency
                $result = max(0.0, (float) $coupon['value']);
                if (wa()->getConfig()->getCurrency(false) != $coupon['type']) {
                    $crm = new shopCurrencyModel();
                    $result = (float) $crm->convert($result, $coupon['type'], wa()->getConfig()->getCurrency(false));
                }
                break;
        }

        if ($apply) {
            $cm->useOne($coupon['id']);
            if (empty($order['params'])) {
                $order['params'] = array();
            }
            $order['params']['coupon_id'] = $coupon['id'];
            $order['params']['coupon_discount'] = $result;
        }

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
}
