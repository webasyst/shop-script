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
                $description .= sprintf('<h5>%s</h5>', _wp('Discount applicable to individual items:'));
                $description .= $items_description;
            }
            if (self::getDiscountCombineType() == 'sum') {
                if ($order_discount || strlen($order_discount_description)) {
                    if (strlen($description)) {
                        $icon = <<<HTML
<i class="icon16 ss orders-all"></i>
HTML;
                        $description .= sprintf('<h5>%s%s</h5>', $icon, _wp('Discount applicable to entire order:'));
                    }
                    $description .= '<ul>'.$order_discount_description.'</ul>';
                }
                $description .= <<<HTML
<i class="icon16  ss discounts-bw fas fa-tags text-gray custom-mr-4"></i>
HTML;
                $description .= sprintf_wp('Shop is set up to use sum of all discounts: %s', shop_currency_html($discount, $currency, $currency));
            } else {
                if ($order_discount || strlen($order_discount_description)) {
                    if (strlen($description)) {
                        $icon = <<<HTML
<i class="icon16 ss orders-all"></i>
HTML;

                        $description .= sprintf('<h5>%s%s</h5>', $icon, _wp('Discount applicable to entire order:'));
                    }
                    $description .= $order_discount_description.'<br>';
                }

                $description .= <<<HTML
<i class="icon16 ss discounts-bw fas fa-tags text-gray custom-mr-4"></i>
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
            // Work for test cases and API
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
        $items = self::getItemsForCouponHash($order, $coupon);

        $total_items_cost = 0;
        foreach ($items as $i => $item) {
            if ($item['type'] === 'service') {
                unset($items[$i]);
                continue;
            }
            $total_items_cost += $item['price'] * $item['quantity'];
        }

        // Flat value in currency
        $discount = max(0.0, (float)$coupon['value']);
        if ($order['currency'] != $coupon['type']) {
            $crm = new shopCurrencyModel();
            $discount = (float)$crm->convert($discount, $coupon['type'], $order['currency']);
        }

        if ($total_items_cost < $discount) {
            $discount = $total_items_cost;
        }

        if (!$items || $discount <= 0) {
            return [];
        }

        $result = [
            'coupon_discount' => $discount,
            'items'           => [],
        ];

        // Distribute coupon discount between items it applies to
        $discount_ratio = $discount / $total_items_cost;
        foreach ($items as $item_id => $item) {
            $result['items'][$item_id] = array(
                'discount'    => $item['price'] * $item['quantity'] * $discount_ratio,
                'description' => $description,
            );
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
        $coupon_model = new shopCouponModel();
        $coupon = self::getCoupon($order);
        $result = [];

        if (!$coupon && $apply) {
            $coupon_model->setUnused($order['id']);
        }
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
            if ($apply) {
                $coupon_model->setUnused($order['id']);
                $coupon_model->useOne($coupon['id']);
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
            if ($discount && (!empty($discount['discount']) || strlen(ifset($discount['description'], '')))) {
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
        if ($discount === null) {
            $discount =& $order['discount'];
        }

        if (empty($currency)) {
            $currency = $order['currency'];
        }

        $items_price = 0;
        foreach ($order['items'] as $item) {
            $items_price += $item['price'] * $item['quantity'];
        }
        if ($discount > $items_price) {
            $discount = $items_price;
            $description .= _w('The discount amount was reduced so as not to exceed the cost of all products.');
        }

        // Currency precision is how many cents in 1 unit of currency.
        // For all sane options this is 100.
        $currency_precision = 100;
        if (!empty($order['__currency_precision']) && $order['__currency_precision'] % 10 == 0) {
            // This is used in unit tests.
            $currency_precision = $order['__currency_precision'];
        } else if (($info = waCurrency::getInfo($currency)) && isset($info['precision'])) {
            $currency_precision = pow(10, max(0, $info['precision']));
        }

        //
        // Keep everything as is, when both:
        // 1) All discount is assigned to items, no leftovers.
        // 2) All item discounts are evenly divisible by item quantity.
        //
        // This is required when order is modified after partial refund or partial capture:
        // we do not want to mess with discount distribution in these cases.
        //
        try {
            $total_items_discount_cents = 0;
            foreach ($order['items'] as $item) {
                if (!empty($item['quantity']) && floatval($item['quantity'])) {
                    $total_item_discount_cents = round(ifset($item['total_discount'], 0) * $currency_precision);
                    // Quantity may be fractional. PHP's % operator only works with integers.
                    // Multiply by 1000 as a workaround.
                    if (($total_item_discount_cents*1000) % ($item['quantity']*1000)) {
                        throw new waException(); // see catch() below
                    }
                    $total_items_discount_cents += $total_item_discount_cents;
                }
            }
            $total_discount_cents = round($discount * $currency_precision);
            if ($total_discount_cents == $total_items_discount_cents) {
                return; // order is good, do not change anything
            }
        } catch (waException $e) {
            // one of item's discounts is not divisible by its quantity, continue
        }

        // Currency rounding setting affects discount batching, see below
        if (!empty($order['__currency_rounding'])) {
            // This is used in unit tests or by very brave shop customizator. Don't use that.
            $currency_rounding = $order['__currency_rounding'];
        } else {
            if (shopRounding::isEnabled('discounts') && wa('shop')->getSetting('discount_distrbution_rounding', 1)) {
                $currencies = wa('shop')->getConfig()->getCurrencies();
                $currency_rounding = ifset($currencies, $currency, 'rounding', 1/$currency_precision);
            } else {
                $currency_rounding = 1/$currency_precision;
            }
        }

        // Shop setting: whether allowed to split a single order item into two
        // rather than modify total order discount value
        if (isset($order['__discount_distrbution_split'])) {
            $discount_distrbution_split = (bool) $order['__discount_distrbution_split'];
        } else {
            $discount_distrbution_split = wa('shop')->getSetting('discount_distrbution_split');
        }

        // Do not allow to split order items when at least one item has fractional quantity
        foreach ($order['items'] as $item) {
            if (!wa_is_int($item['quantity'])) {
                $discount_distrbution_split = false;
                break;
            }
        }
        unset($item);

        // Vars with `_cents` postfix are cents, always round()'ed or floor()'ed to integers.
        // We calculate using integer math to avoid rounding errors.
        // (They still have PHP type float though.)

        // Discount batching is how many cents we're not allowed to split between different order items.
        // E.g. this can be 10 so that 20 cent discount can be put into one item, or split
        // into two items 10+10, but never 11+9. This function can only operate with discounts
        // in these batches - that is, split or increase.
        // Discount must be divisible by this number, or discount will increase to nearest divisible.
        $discount_batch_cents = (int) self::getDiscountBatch($currency_rounding, $currency_precision);

        // Order subtotal: simple sum(price*quantity) for all items
        $subtotal = 0;

        // This is total discount already distributed among order items
        $total_items_discount_cents = 0;

        foreach ($order['items'] as &$item) {
            if (!empty($item['quantity']) && floatval($item['quantity'])) {
                $total_item_discount_cents = round(ifset($item['total_discount'], 0) * $currency_precision);
                if (!$discount_distrbution_split) {
                    // When not allowed to split order item into two, make sure initial item
                    // discounts are all rounded according to currency rouding settings
                    // (that is, divisible by $discount_batch_cents).
                    // This ensures that $order_discount_cents below will be divisible by $discount_batch_cents
                    // as long as total $discount value is rounded to currency settings.
                    $total_item_discount_cents = ceil($total_item_discount_cents / $discount_batch_cents) * $discount_batch_cents;
                }

                $item['total_discount'] = $total_item_discount_cents / $currency_precision;

                $total_items_discount_cents += $total_item_discount_cents;
                $item['_total'] = ($item['price'] * $item['quantity']) - $item['total_discount'];
                $subtotal += $item['_total'];
            } else {
                $item['total_discount'] = 0;
                $item['_total'] = 0;
            }
        }
        unset($item);
        if (!$subtotal) {
            // Unable to distribute discount among zero-priced items
            return;
        }

        // This is order discount we have to distribute among order items
        $order_discount_cents = round($discount * $currency_precision) - $total_items_discount_cents;

        // Adjust discount precision if discount is not divisible by it for some reason.
        // It happens when admin specifies discount by hand. Maybe also in some unforeseen cases.
        if (!$discount_batch_cents || $order_discount_cents % $discount_batch_cents) {
            $discount_batch_cents = 1;
        }

        $order_discount_percent = $order_discount_cents/$subtotal;

        foreach ($order['items'] as $item_id => &$item) {
            if (empty($item['quantity']) || !floatval($item['quantity'])) {
                $item['smashed_discount_cents'] = 0;
                $item['discount_batch_cents'] = 0;
                $item['total_discount'] = 0;
                continue;
            }

            // Discount we want to add to this item proportional to its value relative to the whole order
            $delta_cents = floor($order_discount_percent * $item['_total']);

            // Calculate new item discount, then adjust according to divisibility:
            // Total item discount (in cents) must always be divisible by item quantity, as well as by discount batch size.
            $total_item_discount_cents = round($item['total_discount'] * $currency_precision) + $delta_cents;
            if (wa_is_int($item['quantity'])) {
                // When quantity is integer, we want to preserve discount precision (i.e. respect $discount_batch_cents)
                $item['discount_batch_cents'] = waUtils::lcm($item['quantity'], $discount_batch_cents);
            } else {
                // When quantity is not integer, we don't care for $discount_batch_cents, but still need total item
                // discount to be divisible by quanity (even if fractional).
                $item['discount_batch_cents'] = $item['quantity']*1000 / waUtils::gcd($item['quantity']*1000, 1000);
            }
            $total_item_discount_cents = $item['discount_batch_cents'] * floor($total_item_discount_cents / $item['discount_batch_cents']);

            // Modify item data
            $delta_cents = $total_item_discount_cents - round($item['total_discount'] * $currency_precision);
            $item['total_discount'] = $total_item_discount_cents / $currency_precision;
            $item['smashed_discount_cents'] = $delta_cents;

            // Modify vars that store totals
            $order_discount_cents -= $delta_cents;
            $total_items_discount_cents += $delta_cents;

            unset($total_item_discount_cents, $delta_cents, $item['_total'], $item);
        }
        unset($item);

        // Still have leftovers to distribute?
        if ($order_discount_cents != 0) {

            // Items sorted by discount batch, desc
            $order_items = array_map(function($item_id, $i) {
                return ['id'=>$item_id] + $i;
            }, array_keys($order['items']), $order['items']);
            array_multisort(array_column($order_items, 'discount_batch_cents'), SORT_DESC, $order_items);

            // Loop over items once, larger discount batch first.
            // Attempt to modify item discount to decrease `abs($order_discount_cents)` if possible.
            foreach ($order_items as $item) {
                $item_id = $item['id'];
                if (empty($item['quantity']) || !floatval($item['quantity'])) {
                    continue;
                }

                // Move discount from order to item, one batch at a time
                while (abs($order_discount_cents) >= $item['discount_batch_cents']) {
                    if ($order_discount_cents < 0) {
                        $item_discount_cents = -$item['discount_batch_cents'];
                    } else {
                        $item_discount_cents = $item['discount_batch_cents'];
                    }

                    $new_item_total_discount = $order['items'][$item_id]['total_discount'] + $item_discount_cents / $currency_precision;

                    // item discount may not be negative or exceed price - abort if it does
                    if ($new_item_total_discount < 0 || $item['price']*$item['quantity'] < $new_item_total_discount) {
                        break;
                    }

                    $order['items'][$item_id]['total_discount'] = $new_item_total_discount;
                    $order['items'][$item_id]['smashed_discount_cents'] += $item_discount_cents;
                    $total_items_discount_cents += $item_discount_cents;
                    $order_discount_cents -= $item_discount_cents;
                }
            }

            // Make sure order total never increases (add minimal available discount if it is about to)
            if ($order_discount_cents > 0) {

                // Loop over items once, smaller discount batch first.
                // Use first item available to fix order discount into negative value.
                $order_items = array_reverse($order_items);
                foreach ($order_items as $item) {
                    if (empty($item['quantity']) || !floatval($item['quantity'])) {
                        continue;
                    }
                    $item_id = $item['id'];
                    $item_discount_cents = $item['discount_batch_cents'];
                    $new_item_total_discount = $order['items'][$item_id]['total_discount'] + $item_discount_cents / $currency_precision;

                    // item discount may not be negative or exceed price - only apply discount to this item if possible
                    if (!($new_item_total_discount < 0 || $item['price']*$item['quantity'] < $new_item_total_discount)) {
                        $order['items'][$item_id]['total_discount'] = $new_item_total_discount;
                        $order['items'][$item_id]['smashed_discount_cents'] += $item_discount_cents;
                        $total_items_discount_cents += $item_discount_cents;
                        $order_discount_cents -= $item_discount_cents;
                        break;
                    }
                }
            }
        }

        // Remove internal vars from items, unless asked not to. Used in unit-tests.
        if (empty($order['__keep_internal_vars'])) {
            foreach ($order['items'] as &$item) {
                unset(
                    $item['smashed_discount_cents'],
                    $item['discount_batch_cents']
                );
            }
            unset($item);
        }

        // This modifies $discount and $order in outer function scope by link
        // by dropping $order_discount_cents part and only keeping $total_items_discount_cents part.
        $old_discount = $discount;
        $discount = $total_items_discount_cents / $currency_precision;

        if ($order_discount_cents != 0) {

            // When item total price is lower than discount batch, we can't guarantee
            // that discount only increases. This is dealt with at higher level.
            if ($order_discount_cents > 0) {
                // Notify caller discount has decreased. Used in shopOrder.
                $order['discount_decreased_by'] = $order_discount_cents / $currency_precision;
            }

            // Notify caller that discount has changed. This is used in shopOrder.
            $order['discount_increased_by'] = -$order_discount_cents / $currency_precision;

            if (!$discount_distrbution_split) {
                // This modifies $description in outer function scope by link
                $description .= sprintf(
                    '<h5>%s</h5><b>%s</b>',
                    _w('Total discount value was adjusted.'),
                    sprintf_wp(
                        'Before: %s, after: %s',
                        shop_currency($old_discount, $currency, $currency, 'h'),
                        shop_currency($discount, $currency, $currency, 'h')
                    )
                );
            }
        }
    }

    public static function getDiscountBatch($currency_rounding, $currency_precision=100)
    {
        list($round_unit, $shift, $precision) = shopRounding::getRoundingVars($currency_rounding);
        if ($shift > 0) {
            $result = $shift*$currency_precision;
        } else if ($round_unit) {
            $result = $round_unit*$currency_precision;
        }
        return max($result, 1);
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
            // modify discount, making sure discount per one item is a whole number of cents,
            // unless shop is set up to split one of the order items into two in such cases
            if ($item['quantity'] > 1 && !wa()->getSetting('discount_distrbution_split', 0)) {
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

                // Never decrease discount, only allowed to increase it
                if ($round_discount < $item['discount']) {
                    $round_discount += 0.01 * $item['quantity'];
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
