<?php

class shopDiscounts
{
    /**
     * Returns aggregate discount amount applicable to order.
     *
     * @param array  $order       Order data array
     * @param bool   $apply       Whether discount-related information must be added to order parameters (where appropriate)
     * @param string $description will be set to human-readable description of discount calculation
     * @return float Total discount value expressed in order currency
     * @throws waException
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
        $total_items_discount = 0.0;
        foreach ($order['items'] as $item_id => $item) {
            $items_description .= self::formatItemDiscount($order, $item_id, $discounts, $total_items_discount);
        }

        // Process general order discounts, not tied to any item.
        $order_discount = 0;
        $order_discount_description = self::formatOrderDiscount($discounts, $order_discount);

        // Total discount and description
        $description = '';
        $discount = $total_items_discount + $order_discount;
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
        if ($discount
            && (wa()->getEnv() == 'frontend' || !empty($order['discount_rounding']))
            && waSystem::getSetting('round_discounts', '', 'shop')
            && ($discount < ifset($order['total'], 0))
        ) {
            $rounded_discount = shopRounding::roundCurrency($discount, $currency, true);
            if ($rounded_discount != $discount) {
                $discount = $rounded_discount;
                $description .= "\n<br>";
                $description .= sprintf_wp('Discount rounded to %s', shop_currency_html($discount, $currency, $currency));
            }
        }


        static::correctOrderDiscount($order, $description, $discount, $currency);

        return min(max(0, $discount), ifset($order['total'], 0));
    }

    /**
     * @return string
     * @throws waException
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
     * @throws waException
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
     * @throws waException
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
     * @throws waDbException
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
                $item_discount = shop_currency($item_discount * $item['quantity'], ifempty($item, 'currency', $order['currency']), $order['currency'], false);
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
     * @param array     $order
     * @param waContact $contact
     * @param boolean   $apply
     * @return mixed
     * @throws waDbException
     * @throws waException
     */
    protected static function byCustomerTotal($order, $contact, $apply)
    {
        if (!$contact || !$contact->getId()) {
            return 0;
        }
        $customer_model = new shopCustomerModel();
        $customer = $customer_model->getById($contact->getId());
        if ($customer && ($customer['total_spent'] > 0)) {
            $discount_by_sum_model = new shopDiscountBySumModel();
            $percent = (float)$discount_by_sum_model->getDiscount('customer_total', $customer['total_spent']);

            $shop_config = wa('shop')->getConfig();
            /** @var shopConfig $shop_config */
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
     * @throws waDbException
     * @throws waException
     */
    protected static function byOrderTotal($order)
    {
        // Order total in default currency
        $shop_config = wa('shop')->getConfig();
        /** @var shopConfig $shop_config */
        $shop_currency = $shop_config->getCurrency(true);
        $currency = $order['currency'];

        $order_total = (float)shop_currency($order['total'], $currency, $shop_currency, false);

        $discount_by_sum_model = new shopDiscountBySumModel();
        $percent = (float)$discount_by_sum_model->getDiscount('order_total', $order_total);

        $description = sprintf_wp('By order total, %s%%', $percent);

        return self::byPercent($order, $percent, $description);
    }

    /**
     * Discounts by contact category implementation.
     * @param array     $order
     * @param waContact $contact
     * @return array
     * @throws waDbException
     * @throws waException
     */
    protected static function byCategory($order, $contact)
    {
        if (!$contact) {
            return null;
        }
        $contact_category_discount_model = new shopContactCategoryDiscountModel();
        $percent = $contact_category_discount_model->getByContact($contact->getId());
        if ($percent != 0) {
            $format = 'By customer category, %s%%';
            $description = sprintf_wp($format, $percent);
            return self::byPercent($order, $percent, $description);
        }
        return null;
    }


    protected static function getCoupon($order)
    {
        $result = [];
        $cm = new shopCouponModel();

        if (!empty($order['id'])) {
            // If the order has already been created, look for the saved coupon.

            if (!isset($order['params']) || !isset($order['params']['coupon_id'])) {
                $order_params_model = new shopOrderParamsModel();
                $order['params'] = ifset($order, 'params', []) + $order_params_model->get($order['id']);
            }
            $coupon_id = ifset($order, 'params', 'coupon_id', null);

            if ($coupon_id) {
                $result = $cm->getById((int)$coupon_id);
            }
        } elseif (wa()->getEnv() === 'frontend') {
            // If this is a new order, look for a coupon in the session.
            $checkout_data = wa('shop')->getStorage()->read('shop/checkout');
            $coupon_code = ifset($checkout_data, 'coupon_code', null);

            if ($coupon_code) {
                $coupon = $cm->getByField('code', $coupon_code);

                // Coupon must be available for use.
                if ($coupon && shopCouponModel::isEnabled($coupon)) {
                    $result = $coupon;
                }
            }
        } elseif (!empty($order['params']['coupon_id'])) {
            // Work for test cases
            $result = $cm->getById((int)$order['params']['coupon_id']);
        }

        return $result;
    }

    /**
     * Apply Free Shipping Coupon
     * @param $order
     * @param $description
     * @return array
     */
    protected static function getFreeShippingByCoupons(&$order, &$description)
    {
        // Zero cost of delivery in the order
        $order['shipping'] = 0;
        $description .= ': '._w('Free shipping');

        $result = [
            'coupon_discount' => 0,
            'discount'        => 0,
            'description'     => $description,
        ];

        return $result;
    }

    /**
     * Apply discount coupon in percent
     *
     * @param $order
     * @param $coupon
     * @param $description
     * @return array
     * @throws waException
     */
    protected static function getPercentDiscountByCoupons($order, $coupon, $description)
    {
        // If there is a hash, then you need to apply only to certain products.
        if (!empty($coupon['products_hash'])) {
            $result = self::getPercentDiscountByProductType($order, $coupon, $description);
        } else {
            $percent = (float)$coupon['value'];
            $result = self::byPercent($order, $percent, $description);
            $result['coupon_discount'] = max(0.0, min(100.0, $percent)) * $order['total'] / 100.0;
        }

        return $result;
    }

    /**
     * Applies an integer coupon discount.
     * Applies to the entire order
     *
     * @param $order
     * @param $coupon
     * @param $description
     * @return array
     * @throws waException
     */
    protected static function getIntegerDiscountByCoupons($order, $coupon, $description)
    {
        $result = [];

        $items = self::getItemsForCouponHash($order, $coupon);

        if ($items) {
            // Flat value in currency
            $discount = max(0.0, (float)$coupon['value']);
            if ($order['currency'] != $coupon['type']) {
                $crm = new shopCurrencyModel();
                $discount = (float)$crm->convert($discount, $coupon['type'], $order['currency']);
            }

            if (!empty($coupon['products_hash'])) {
                $total_items_cost = 0;
                foreach ($items as $item) {
                    // Skip service
                    if ($item['type'] === 'service') {
                        continue;
                    }

                    $total_items_cost += $item['price'] * $item['quantity'];
                }

                // The discount can not be more than the prices of goods to which it applies.
                if ($total_items_cost < $discount) {
                    $discount = $total_items_cost;
                }
            }

            $result = [
                'coupon_discount' => $discount,
                'discount'        => $discount,
                'description'     => $description,
            ];
        }

        return $result;
    }

    /**
     * Apply discount coupons
     *
     * @param $order
     * @param $contact object unused
     * @param $apply
     * @return array
     * @throws waException
     */
    protected static function byCoupons(&$order, $contact, $apply)
    {
        $coupon = self::getCoupon($order);
        $result = [];

        // If there is no coupon or there are no items in the order, do not apply the coupon
        if (!$coupon || empty($order['items'])) {
            return $result;
        }

        $description = _w('Coupon code').' '.$coupon['code'];

        switch ($coupon['type']) {
            case '$FS':
                $result = self::getFreeShippingByCoupons($order, $description);
                break;
            case '%':
                $result = self::getPercentDiscountByCoupons($order, $coupon, $description);
                break;
            default:
                $result = self::getIntegerDiscountByCoupons($order, $coupon, $description);
                break;
        }

        if ($result) {
            if ($apply && !$order['id']) {
                (new shopCouponModel())->useOne($coupon['id']);
            }

            $order['params']['coupon_id'] = $coupon['id'];
            // Record total coupon discount
            $order['params']['coupon_discount'] = $result['coupon_discount'];
            // Remove from result as unnecessary
            unset($result['coupon_discount']);
            //Say that free shipping has been applied
            if ($coupon['type'] == '$FS') {
                $order['params']['coupon_free_shipping'] = 1;
            }
        }

        return $result;
    }

    /**
     * @param $order
     * @param $coupon
     * @param $description
     * @return array
     * @throws waException
     */
    protected static function getPercentDiscountByProductType($order, $coupon, $description)
    {
        $items = self::getItemsForCouponHash($order, $coupon);
        $result = [];

        if (!$items) {
            return $result;
        }

        $percent = $coupon['value'];
        $total_discount = 0;
        foreach ($items as $item_id => $item) {
            // Skip service
            if ($item['type'] === 'service') {
                continue;
            }
            $item_discount = $percent * $item['price'] / 100.0;
            $item_discount = shop_currency($item_discount * $item['quantity'], ifempty($item, 'currency', $order['currency']), $order['currency'], false);
            $total_discount += $item_discount;

            $result['items'][$item_id] = array(
                'discount'    => $item_discount,
                'description' => $description,
            );
        }

        if ($order['total'] < $total_discount) {
            $result = [];
        } else {
            // Save total discount on items
            $result['coupon_discount'] = $total_discount;
        }

        return $result;
    }

    /**
     * Takes the hash of the coupon and returns the products that are in the order and in the search results for the hash
     *
     * @param $order
     * @param $coupon
     * @return array
     * @throws waException
     */
    protected static function getItemsForCouponHash($order, $coupon)
    {
        $hash = $coupon['products_hash'];
        $result = [];
        $items = $order['items'];

        // If there is no hash, then there is no need to search for goods.
        // If there is a hash, then you need to make sure that there are goods under it
        if (empty($hash)) {
            return $items;
        }

        $products_id = [];
        foreach ($items as $item) {
            $products_id[] = $item['product_id'];
        }

        if ($products_id) {
            $collection = new shopProductsCollection($hash);
            $collection->addWhere('p.id IN ('.implode(',', $products_id).')');
            $products = $collection->getProducts('id');

            if ($products) {
                foreach ($items as $index => $item) {
                    if (!isset($products[$item['product_id']])) {
                        unset($items[$index]);
                    }
                }
                $result = $items;
            }
        }

        return $result;
    }

    /** Helper for apply() and calculate() to get customer's waContact from order data. May return null for new customers.
     * @param array $order
     * @return waContact
     * @throws waException
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
            /** @var shopConfig $shop_config */
            $order['currency'] = $shop_config->getCurrency(false);
        }

        $order['contact'] = $contact;
        if (empty($order['params'])) {
            unset($order['params']);
        }

        foreach ($order['items'] as &$item) {
            if (empty($item['currency'])) {
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

    /**
     * Distributes order discount between items.
     * @param array  $order
     * @param string $description
     * @param float  $discount
     * @param string $currency
     */
    public static function correctOrderDiscount(&$order, &$description, &$discount = null, $currency = null)
    {
        if (empty($currency)) {
            $currency = $order['currency'];
        }
        $currency_precision = 100;
        if (($info = waCurrency::getInfo($currency)) && isset($info['precision'])) {
            $currency_precision = pow(10, max(0, $info['precision']));
        }

        if ($discount === null) {
            $discount =& $order['discount'];
        }

        // Vars with `_cents` postfix are cents, always round()'ed or floor()'ed to integers.
        // We calculate using integer math to avoid rounding errors.
        // (They still have PHP type float though.)

        // Order subtotal: simple sum(price*quantity) for all items
        $subtotal = 0;

        // This is total discount already distributed among order items
        $total_items_discount_cents = 0;

        foreach ($order['items'] as &$item) {
            if ($item['quantity']) {
                $total_item_discount_cents = round(ifset($item['total_discount'], 0) * $currency_precision);

                $item['total_discount'] = $total_item_discount_cents / $currency_precision;

                $total_items_discount_cents += $total_item_discount_cents;
                $item['_total'] = ($item['price'] * $item['quantity']) - $item['total_discount'];
                $subtotal += $item['_total'];
            }
            unset($item);
        }
        if (!$subtotal) {
            // Unable to distribute discount among zero-priced items
            return;
        }

        // This is order discount we have to distribute among order items
        $order_discount_cents = round($discount * $currency_precision) - $total_items_discount_cents;

        // Need to distribute something?
        if ($order_discount_cents) {

            $order_discount_percent = $order_discount_cents/$subtotal;

            foreach ($order['items'] as $item_id => &$item) {
                if (!empty($item['quantity'])) {
                    // paranoid

                    // Discount we want to add to this item proportional to its value in whole order
                    $delta_cents = floor($order_discount_percent * $item['_total']);

                    // Calculate new item discount, then adjust according to item quantity:
                    // total item discount (in cents) must always be divisible by item quantity.
                    $total_item_discount_cents = round($item['total_discount'] * $currency_precision) + $delta_cents;
                    $total_item_discount_cents = $item['quantity'] * round($total_item_discount_cents / $item['quantity']);

                    // Modify item data
                    $delta_cents = $total_item_discount_cents - round($item['total_discount'] * $currency_precision);
                    $item['total_discount'] = $total_item_discount_cents / $currency_precision;
                    $item['smashed_discount_cents'] = $delta_cents;

                    // Modify vars that store totals
                    $order_discount_cents -= $delta_cents;
                    $total_items_discount_cents += $delta_cents;

                }
                unset($total_item_discount_cents, $delta_cents, $item['_total'], $item);
            }

            // Still have leftovers to distribute?
            if ($order_discount_cents != 0) {

                // item_id => minimal step (in cents) we can add or remove discount from this item
                // minimal step depends on item quantity.
                // We'll loop over them in order greater to smaller.
                $discount_map_cents = waUtils::getFieldValues($order['items'], 'quantity', true);
                krsort($discount_map_cents, SORT_NUMERIC);

                $min_quantity_item_id = array_keys($discount_map_cents)[count($discount_map_cents) - 1];
                $min_item_discount_cents = $discount_map_cents[$min_quantity_item_id];

                do {
                    foreach ($discount_map_cents as $item_id => $item_discount_cents) {
                        if ($item_discount_cents > abs($order_discount_cents)) {
                            if ($min_quantity_item_id != $item_id || $order_discount_cents < 0) {
                                // Only change discount of an item if the change (i.e. $item_discount_cents)
                                // is less than discount left to distribute (i.e. $order_discount_cents).
                                // BUT the overall discount can not decrease, so we still want
                                // $order_discount_cents to become <0 at last step.
                                continue;
                            }
                        }
                        if ($order_discount_cents < 0) {
                            $item_discount_cents *= -1;
                        }

                        $order_discount_cents -= $item_discount_cents;
                        $total_items_discount_cents += $item_discount_cents;
                        $order['items'][$item_id]['total_discount'] += $item_discount_cents / $currency_precision;
                        $order['items'][$item_id]['smashed_discount_cents'] += $item_discount_cents;
                    }
                    if(!$order_discount_cents) {
                        break;
                    }
                } while ($min_item_discount_cents < abs($order_discount_cents));
            }
        }

        // This modifies $discount and $order in outer function scope by link
        // by dropping $order_discount_cents part and only keeping $total_items_discount_cents part.
        $discount = $total_items_discount_cents / $currency_precision;

        if ($order_discount_cents != 0) {
            // This modifies $description in outer function scope by link
            $description .= sprintf(
                '<h5>%s</h5><b>%s</b>',
                _w('total discount value was adjusted:'),
                shop_currency($discount, $currency, $currency, 'h')
            );
        }
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
            $item['discount'] = min(max(0, $item['discount']), shop_currency($item['price'], ifempty($item, 'currency', $currency), $currency, false) * $item['quantity']);
            // round discount per one item
            if ($item['quantity'] > 1) {
                $round_discount = $item['quantity'] * shop_currency($item['discount'] / $item['quantity'], $currency, $currency, false);
                if (($round_discount != $item['discount']) && waSystemConfig::isDebug()) {
                    $log = array(
                        'name'           => $item['name'],
                        'discount'       => $item['discount'],
                        'currency'       => $currency,
                        'price'          => $item['price'],
                        'round_discount' => $round_discount,
                    );
                    waLog::log(var_export($log, true), 'shop/discounts_per_item.rounding.log');
                }
                $item['discount'] = $round_discount;
            }

            $order['items'][$item_id]['total_discount'] = $item['discount'];
            $total_items_discount += $item['discount'];

            switch ($item['type']) {
                case 'service':
                    $items_description .= <<<HTML
<i class="icon16 ss service"></i>
HTML;
                    break;

            }
            $items_description .= htmlentities($item['name'], ENT_NOQUOTES, 'utf-8');
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
     * @param        $component_discount
     * @param string $plugin_id
     * @param string $currency
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
                    $name = htmlentities($plugin_id ? self::getPluginName($plugin_id) : '', ENT_NOQUOTES, 'utf-8');
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
