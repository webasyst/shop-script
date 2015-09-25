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

        $descriptions = array();
        $applicable_discounts = array();
        $contact = self::getContact($order);

        // Discount by contact category applicable?
        if (self::isEnabled('category')) {
            $d = null;
            $amount = self::byCategory($order, $contact, $apply, $d);
            $amount > 0 && ($descriptions[] = sprintf($d, shop_currency_html($amount, $currency, $currency)));
            $applicable_discounts[] = $amount;
        }

        // Discount by coupon applicable?
        if (self::isEnabled('coupons')) {
            $d = null;
            $amount = self::byCoupons($order, $contact, $apply, $d);
            $d && ($descriptions[] = sprintf($d, shop_currency_html($amount, $currency, $currency)));
            $applicable_discounts[] = $amount;
        }

        // Discount by order total applicable?
        if (self::isEnabled('order_total')) {
            $dbsm = new shopDiscountBySumModel();

            // Order total in default currency
            $order_total = (float) shop_currency($order['total'], $currency, wa('shop')->getConfig()->getCurrency(), false);
            $percent = (float) $dbsm->getDiscount('order_total', $order_total);

            $amount = max(0.0, min(100.0, $percent)) * $order['total'] / 100.0;
            $amount > 0 && ($descriptions[] = sprintf_wp('By order total, %s%%', $percent).': '.shop_currency_html($amount, $currency, $currency));
            $applicable_discounts[] = $amount;
        }

        // Discount by customer total spent applicable?
        if (self::isEnabled('customer_total')) {
            $d = null;
            $amount = self::byCustomerTotal($order, $contact, $apply, $d);
            $d && $amount > 0 && ($descriptions[] = sprintf($d, shop_currency_html($amount, $currency, $currency)));
            $applicable_discounts[] = $amount;
        }

        /**
         * @event order_calculate_discount
         * @param array $params
         * @param array[string] $params['order'] order info array('total' => '', 'items' => array(...))
         * @param array[string] $params['contact'] contact info
         * @param bool[string] $params['apply'] calculate or apply discount
         * @return string[string] $return['description'] discount description to save in order log
         * @return float[string] $return['discount'] discount amount in order currency
         */
        $order = self::prepareOrderData($order, $contact);
        $event_params = array('order' => &$order, 'contact' => $contact, 'apply' => $apply);
        $plugins_discounts = wa('shop')->event('order_calculate_discount', $event_params);
        foreach ($plugins_discounts as $plugin_id => $plugin_discount) {
            if (is_array($plugin_discount)) {
                $amount = ifset($plugin_discount['discount'], 0);
                $d = ifset($plugin_discount['description']);
                $d && $amount != 0 && ($descriptions[] = sprintf($d, shop_currency_html($amount, $currency, $currency)));
            } else {
                $amount = $plugin_discount;
                if ($amount != 0) {
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

                    $descriptions[] = $d.': '.shop_currency_html($amount, $currency, $currency);
                }
            }
            $applicable_discounts[] = $amount;
        }

        if ($descriptions) {
            $description = _w('The following discounts are applicable for this order:')."\n<ul>\n<li>";
            $description .= join("</li>\n<li>", $descriptions);
            $description .= "</li>\n</ul>\n";
        }

        // Select max discount or sum depending on global setting.
        $discount = 0.0;
        if ( ( $applicable_discounts = array_filter($applicable_discounts, 'is_numeric'))) {
            if ($descriptions) {
                if (waSystem::getSetting('discounts_combine', null, 'shop') == 'sum') {
                    $discount = (float) array_sum($applicable_discounts);
                    $description .= sprintf_wp('Shop is set up to use sum of all discounts: %s', shop_currency_html($discount, $currency, $currency));
                } else {
                    $discount = (float) max($applicable_discounts);
                    $description .= sprintf_wp('Shop is set up to use single largest of all discounts: %s', shop_currency_html($discount, $currency, $currency));
                }
            }
        }

        // Discount based on affiliate bonus?
        if (shopAffiliate::isEnabled()) {
            $d = null;
            $amount = (float) shopAffiliate::discount($order, $contact, $apply, $discount, $d);
            $discount = $discount + $amount;
            $d && $amount > 0 && ($description .= "\n<br>".sprintf($d, shop_currency_html($amount, $currency, $currency)));
        }

        return min(max(0, $discount), ifset($order['total'], 0));
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

    /** Discounts by amount of money previously spent by this customer. */
    protected static function byCustomerTotal($order, $contact, $apply, &$d=null)
    {
        if (!$contact || !$contact->getId()) {
            return 0;
        }

        $cm = new shopCustomerModel();
        $customer = $cm->getById($contact->getId());
        if ($customer && $customer['total_spent'] > 0) {
            $dbsm = new shopDiscountBySumModel();
            $d = _w('By customers overall purchases').' ('.shop_currency_html($customer['total_spent'], null, wa('shop')->getConfig()->getCurrency(true)).'): %s';
            return max(0.0, min(100.0, (float) $dbsm->getDiscount('customer_total', $customer['total_spent']))) * $order['total'] / 100.0;
        }
        return 0.0;
    }

    /** Discounts by category implementation. */
    protected static function byCategory($order, $contact, $apply, &$d = null)
    {
        if (!$contact) {
            return 0;
        }

        $ccdm = new shopContactCategoryDiscountModel();
        $percent = $ccdm->getByContact($contact->getId());
        if ($percent != 0) {
            $d = sprintf_wp('By customer category, %s%%%%', $percent).': %s';
        }

        return max(0.0, min(100.0, $percent)) * $order['total'] / 100.0;
    }

    /** Coupon discounts implementation. */
    protected static function byCoupons(&$order, $contact, $apply, &$d=null)
    {
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

            return $discount;
        } else if (empty($checkout_data['coupon_code'])) {
            return 0;
        }

        $coupon = $cm->getByField('code', $checkout_data['coupon_code']);
        if (!$coupon || !shopCouponsAction::isEnabled($coupon)) {
            return 0;
        }

        $d = _w('Coupon code').' '.$coupon['code'].': %s';

        switch ($coupon['type']) {
            case '$FS':
                $order['shipping'] = 0;
                $result = 0;
                $d = sprintf($d, _w('Free shipping'));
                break;
            case '%':
                $result = max(0.0, min(100.0, (float) $coupon['value'])) * $order['total'] / 100.0;
                break;
            default:
                // Flat value in currency
                $result = max(0.0, (float) $coupon['value']);
                if ($currency != $coupon['type']) {
                    $crm = new shopCurrencyModel();
                    $result = (float) $crm->convert($result, $coupon['type'], $currency);
                }
                break;
        }

        if ($apply) {
            $cm->useOne($coupon['id']);
            if (empty($order['params'])) {
                $order['params'] = array();
            }
            $order['params']['coupon_id'] = $coupon['id'];
        }
        $order['params']['coupon_discount'] = $result;

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
