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
        $contact = self::getContact($order);
        $order = self::prepareOrderData($order, $contact);
        $currency = $order['currency'];
        $discounts = array();

        // Discount by contact category applicable?
        if (self::isEnabled('category')) {
            $discounts[] = self::byCategory($order, $contact);
        }

        // Discount by coupon applicable?
        if (self::isEnabled('coupons')) {
            $discounts[] = self::byCoupons($order, $contact, $apply);
        }

        // Discount by order total applicable?
        if (self::isEnabled('order_total')) {
            $discounts[] = self::byOrderTotal($order);
        }

        // Discount by customer total spent applicable?
        if (self::isEnabled('customer_total')) {
            $discounts[] = self::byCustomerTotal($order, $contact, $apply);
        }

        foreach ($discounts as &$discount_component) {
            $discount_component = self::castComponentDiscount($discount_component, $currency);
            unset($discount_component);
        }

        /**
         * @event order_calculate_discount
         * @param array $params
         * @param array [string] $params['order'] order info array('total' => '', 'items' => array(...))
         * @param array [string] $params['contact'] contact info
         * @param bool [string] $params['apply'] calculate or apply discount
         * @return array[string] $return['description'] discount description to save in order log
         * @return array[float] $return['discount'] discount amount in order currency
         * @return array[float] $return['items'] discount amount in order currency
         */
        $event_params = array(
            'order'   => &$order,
            'contact' => $contact,
            'apply'   => $apply,
        );
        $plugins_discounts = wa('shop')->event('order_calculate_discount', $event_params);

        foreach ($plugins_discounts as $plugin_id => $plugin_discount) {
            $discounts[] = self::castComponentDiscount($plugin_discount, $currency, $plugin_id);
        }

        // Process discounts of individual order items.
        $items_description = '';
        $total_item_discount = 0.0;
        foreach ($order['items'] as $item_id => $item) {
            $items_description .= self::formatItemDiscount($order, $item_id, $discounts, $total_item_discount);
        }

        // Process general order discounts, not tied to any item.
        $order_discount = 0;
        $order_discount_description = self::formatOrderDiscount($discounts, $order_discount);

        // Total discount and description
        $description = '';
        $discount = $total_item_discount + $order_discount;
        if ($discount || strlen($order_discount_description)) {
            if (wa('shop')->getConfig()->getOption('discount_description') && strlen($items_description)) {
                $description .= sprintf('<h5>%s</h5>', _wp('applicable to individual items:'));
                $description .= $items_description;
            }
            if (self::getDiscountCombineType() == 'sum') {
                if ($order_discount || strlen($order_discount_description)) {
                    if (strlen($description)) {
                        $icon = <<<HTML
<i class="icon16 ss orders-all"></i>
HTML;
                        $description .= sprintf('<h5>%s%s</h5>', $icon, _wp('applicable to entire order:'));
                    }
                    $description .= '<ul>'.$order_discount_description.'</ul>';
                }
                $description .= <<<HTML
<i class="icon16  ss discounts-bw"></i>
HTML;
                $description .= sprintf_wp('Shop is set up to use sum of all discounts: %s', shop_currency_html($discount, $currency, $currency));
            } else {
                if ($order_discount || strlen($order_discount_description)) {
                    if (strlen($description)) {
                        $icon = <<<HTML
<i class="icon16 ss orders-all"></i>
HTML;

                        $description .= sprintf('<h5>%s%s</h5>', $icon, _wp('applicable to entire order:'));
                    }
                    $description .= $order_discount_description.'<br>';
                }

                $description .= <<<HTML
<i class="icon16 ss discounts-bw"></i>
HTML;
                $description .= sprintf_wp('Shop is set up to use single largest of all discounts: %s', shop_currency_html($discount, $currency, $currency));
            }
        }

        // Discount based on affiliate bonus?
        if (shopAffiliate::isEnabled()) {
            $d = null;
            $amount = (float)shopAffiliate::discount($order, $contact, $apply, $discount, $d);
            $discount = $discount + $amount;
            if ($d && $amount > 0) {
                $description .= "\n<br>".sprintf($d, shop_currency_html($amount, $currency, $currency));
            }
        }

        // Round to currency precision
        $discount = shop_currency($discount, $currency, $currency, false);
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

    /**
     * @return string
     */
    protected static function getDiscountCombineType()
    {
        static $discount_combine_type = null;
        if ($discount_combine_type === null) {
            // How do discounts combine: 'max' or 'sum' of all applicable discounts
            $discount_combine_type = waSystem::getSetting('discounts_combine', null, 'shop');
        }
        return $discount_combine_type;
    }

    protected static function getPluginName($plugin_id)
    {
        static $cache = array();
        if (!isset($cache[$plugin_id])) {
            if (wa()->appExists($plugin_id)) {
                $apps = wa()->getApps();
                $d = ifset($apps[$plugin_id]['name'], $plugin_id);
            } else {
                $d = null;
                $pid = str_replace('-plugin', '', $plugin_id);
                try {
                    $d = wa('shop')->getPlugin($pid)->getName();
                } catch (Exception $e) {

                }
                $d = ifempty($d, $plugin_id);
            }
            $cache[$plugin_id] = $d;
        }
        return $cache[$plugin_id];
    }

    /**
     * Returns aggregate discount amount applicable to order and adds discount-related information to order parameters where appropriate.
     *
     * @param array $order Order data array
     * @param array $description
     * @return float Total discount value expressed in order currency
     */
    public static function apply(&$order, &$description = null)
    {
        return self::calculate($order, true, $description);
    }

    /**
     * Same as ::apply(), but used after an existing order is modified.
     * @param array $order Order data array
     * @param array $description
     * @return float
     */
    public static function reapply(&$order, &$description = null)
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
        if ($percent > 0.0) {
            $discount = $percent * $order['total'] / 100.0;
            foreach ($order['items'] as $item_id => $item) {
                $item_discount = $percent * $item['price'] / 100.0;
                $item_discount = shop_currency($item_discount * $item['quantity'], $item['currency'], $order['currency'], false);
                $result['items'][$item_id] = array(
                    'discount'    => $item_discount,
                    'description' => $description,
                );
                $discount -= $item_discount;
            }
            $discount = shop_currency(max(0, $discount), $order['currency'], $order['currency'], false);
            if ($discount) {
                $result['discount'] = $discount;
                $result['description'] = $description;
            }
        }
        return $result;
    }

    /**
     * Discounts by amount of money previously spent by this customer.
     * @param array $order
     * @param waContact $contact
     * @param boolean $apply
     * @return mixed
     */
    protected static function byCustomerTotal($order, $contact, $apply)
    {
        if (!$contact || !$contact->getId()) {
            return 0;
        }
        $cm = new shopCustomerModel();
        $customer = $cm->getById($contact->getId());
        if ($customer && $customer['total_spent'] > 0) {
            $dbsm = new shopDiscountBySumModel();
            $percent = (float)$dbsm->getDiscount('customer_total', $customer['total_spent']);

            $shop_config = wa('shop')->getConfig();
            /**
             * @var shopConfig $shop_config
             */
            $currency = $shop_config->getCurrency(true);
            $total_spent = shop_currency_html($customer['total_spent'], null, $currency);

            $format = _w('By customers overall purchases').' (%s)';
            $description = sprintf($format, $total_spent);

            return self::byPercent($order, $percent, $description);
        }
        return null;
    }

    /**
     * Discounts by amount current order
     * @param array $order
     * @return mixed
     */
    protected static function byOrderTotal($order)
    {
        // Order total in default currency
        $shop_config = wa('shop')->getConfig();
        /**
         * @var shopConfig $shop_config
         */
        $shop_currency = $shop_config->getCurrency(true);
        $currency = $order['currency'];

        $order_total = (float)shop_currency($order['total'], $currency, $shop_currency, false);

        $dbsm = new shopDiscountBySumModel();
        $percent = (float)$dbsm->getDiscount('order_total', $order_total);

        $description = sprintf_wp('By order total, %s%%', $percent);

        return self::byPercent($order, $percent, $description);
    }

    /**
     * Discounts by category implementation.
     * @param array $order
     * @param waContact $contact
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
            $format = 'By customer category, %s%%';
            $description = sprintf_wp($format, $percent);
            return self::byPercent($order, $percent, $description);
        }
        return null;
    }

    /** Coupon discounts implementation.
     * @param array $order
     * @param waContact $contact
     * @param boolean $apply
     * @return mixed
     */
    protected static function byCoupons(&$order, $contact, $apply)
    {
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
            $description = _w('Coupon code').' '.$coupon_code;
            if (ifset($coupon['type']) == '$FS') {
                $order['shipping'] = 0;
                $description .= sprintf(': %s', _w('Free shipping'));
            }
            return array(
                'discount'    => $discount,
                'description' => $description,
            );
        } elseif (empty($checkout_data['coupon_code'])) {
            return null;
        }

        $coupon = $cm->getByField('code', $checkout_data['coupon_code']);
        if (!$coupon || !shopCouponsAction::isEnabled($coupon)) {
            return 0;
        }

        $description = _w('Coupon code').' '.$coupon['code'];

        switch ($coupon['type']) {
            case '$FS':
                $order['shipping'] = 0;
                $discount = 0;
                $result = array(
                    'discount'    => $discount,
                    'description' => $description.': '._w('Free shipping'),
                );
                break;
            case '%':
                $percent = (float)$coupon['value'];
                $discount = max(0.0, min(100.0, $percent)) * $order['total'] / 100.0;
                $result = self::byPercent($order, $percent, $description);
                break;
            default:
                // Flat value in currency
                $discount = max(0.0, (float)$coupon['value']);
                if ($order['currency'] != $coupon['type']) {
                    $crm = new shopCurrencyModel();
                    $discount = (float)$crm->convert($discount, $coupon['type'], $order['currency']);
                }
                $result = array(
                    'discount'    => $discount,
                    'description' => $description,
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

    /** Helper for apply() and calculate() to get customer's waContact from order data. May return null for new customers.
     * @param array $order
     * @return waContact
     */
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
        if (!isset($order['currency'])) {
            $shop_config = wa('shop')->getConfig();
            /**
             * @var shopConfig $shop_config
             */
            $order['currency'] = $shop_config->getCurrency(false);
        }

        $order['contact'] = $contact;
        if (empty($order['params'])) {
            unset($order['params']);
        }

        foreach ($order['items'] as &$item) {
            if (!isset($item['currency'])) {
                $item['currency'] = $order['currency'];
            }
        }
        unset($item);

        return $order;
    }

    protected static function formatOrderDiscount($discounts, &$order_discount)
    {
        $order_discount_description = '';
        foreach ($discounts as $discount) {
            if ($discount && (!empty($discount['discount']) || strlen(ifset($discount['description'])))) {
                if (self::getDiscountCombineType() == 'sum') {
                    $order_discount += $discount['discount'];
                    $order_discount_description .= '<li>'.$discount['description'].'</li>';
                } else {
                    if ($discount['discount'] > $order_discount) {
                        $order_discount = $discount['discount'];
                        $order_discount_description = $discount['description'];
                    }
                }
            }
        }
        return $order_discount_description;
    }

    protected static function formatItemDiscount(&$order, $item_id, $discounts, &$total_items_discount)
    {
        $items_description = '';
        $item = $order['items'][$item_id];
        $count = 0;
        $currency = $order['currency'];
        $item['discount'] = 0;
        $item['discount_description'] = '';
        foreach ($discounts as $plugin_id => $d) {
            if (!$d || !isset($d['items'][$item_id])) {
                continue;
            }

            if (is_int($plugin_id)) {
                $plugin_id = null;
            }

            $item_discount = self::castComponentDiscount($d['items'][$item_id], $currency, $plugin_id);

            if (self::getDiscountCombineType() == 'sum') {
                ++$count;
                $item['discount'] += $item_discount['discount'];
                $item['discount_description'] .= '<li>'.$item_discount['description'].'</li>';
            } elseif ($item_discount['discount'] > $item['discount']) {
                $item['discount'] = $item_discount['discount'];
                $item['discount_description'] = $item_discount['description'];
            }
        }

        if ($item['discount']) {
            $item['discount'] = min(max(0, $item['discount']), shop_currency($item['price'], $item['currency'], $currency, false) * $item['quantity']);
            $order['items'][$item_id]['total_discount'] = $item['discount'];
            $total_items_discount += $item['discount'];

            switch ($item['type']) {
                case 'service':
                    $items_description .= <<<HTML
<i class="icon16 ss service"></i>
HTML;
                    break;

            }
            $items_description .= $item['name'];
            if (self::getDiscountCombineType() == 'sum') {
                $items_description .= '<ul>'.$item['discount_description'].'</ul>';
                if ($count > 1) {
                    $format = _w('Total discount for this order item: %s.');

                    $_item_discount = shop_currency_html($item['discount'], $currency, $currency);
                    $items_description .= sprintf($format, $_item_discount);
                }
            } else {
                $items_description .= ' &minus; ';
                $items_description .= $item['discount_description'];
            }
            $items_description .= '<br>';
        }
        return $items_description;
    }

    /**
     * @param $component_discount
     * @param $plugin_id
     * @param $currency
     * @return array
     */
    protected static function castComponentDiscount($component_discount, $currency, $plugin_id = null)
    {
        if (is_array($component_discount)) {
            $discount = $component_discount;
            $discount += array(
                'discount' => null,
            );
        } elseif ($component_discount) {
            $discount = array(
                'discount' => $component_discount,
            );
        } else {
            $discount = null;
        }

        if ($discount) {
            if (!empty($discount['discount'])) {
                $discount['discount'] = waCurrency::round($discount['discount'], $currency);
                if (!isset($discount['description'])) {
                    $name = $plugin_id ? self::getPluginName($plugin_id) : '';
                    $description = $name.(strlen($name) ? ': ' : '').shop_currency_html($discount['discount'], $currency, $currency);
                    $discount += compact('description');
                } else {
                    $discount['description'] .= ': '.shop_currency_html($discount['discount'], $currency, $currency);
                }
            }

            if (!isset($discount['items'])) {
                $discount['items'] = array();
            }
        }
        return $discount;
    }
}
