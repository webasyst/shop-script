<?php

class shopAffiliate
{
    public static function isEnabled()
    {
        return wa()->getSetting('affiliate');
    }

    /**
     * Amount of affiliation points given order worths.
     * @param array|int $order_or_id
     */
    public static function calculateBonus($order_or_id)
    {
        if (!self::isEnabled()) {
            return 0;
        }

        $credit_rate = wa()->getSetting('affiliate_credit_rate', 0, 'shop');
        if (!$credit_rate) {
            return 0;
        }

        if (int_ok($order_or_id)) {
            $order_id = $order_or_id;
            $om = new shopOrderModel();
            $order = $om->getOrder($order_id);
        } else {
            $order = $order_or_id;
            //$order_id = $order['id'];
        }

        // Convert order total from order currency to default currency
        $curm = new shopCurrencyModel();
        $order_currency = isset($order['currency']) ? $order['currency'] : null;
        $def_cur = wa('shop')->getConfig()->getCurrency(true);
        $affiliatable_total = $curm->convert($order['total'], ifempty($order_currency, $def_cur), $def_cur);

        $product_types = wa()->getSetting('affiliate_product_types', '', 'shop');
        if (!empty($product_types)) {

            //
            // When affiliation program is enabled for certain product types only,
            // we need to calculate total afiliatable amount from order items.
            //

            $product_types = array_fill_keys(explode(',', $product_types), true);

            // Make sure order data contains items
            if (empty($order['items']) && !empty($order['id'])) {
                $oim = new shopOrderItemsModel();
                $order['items'] = $oim->getItems($order['id']);
            }
            if (empty($order['items']) || !is_array($order['items'])) {
                return 0;
            }

            // Fetch product info
            $product_ids = array();
            foreach($order['items'] as $i) {
                $product_ids[$i['product_id']] = true;
            }
            $pm = new shopProductModel();
            $products = $pm->getById(array_keys($product_ids));

            // Calculate total value of affiliatable order items
            $items_total = 0;
            foreach($order['items'] as $i) {
                $p = $products[$i['product_id']];
                $type_id = $p['type_id'];
                if ($i['type'] == 'product' && $type_id && !empty($product_types[$type_id])) {
                    $items_total += $curm->convert($i['price'] * $i['quantity'], ifempty($p['currency'], $def_cur), $def_cur);
                }
            }

            if ($affiliatable_total > $items_total) {
                $affiliatable_total = $items_total;
            }
        }

        return $affiliatable_total / $credit_rate;
    }

    public static function applyBonus($order_or_id)
    {
        if (int_ok($order_or_id)) {
            $order_id = $order_or_id;
            $om = new shopOrderModel();
            $order = $om->getOrder($order_id);
        } else {
            $order = $order_or_id;
            $order_id = $order['id'];
        }
        if (!$order['contact_id']) {
            return 0;
        }
        $cm = new shopCustomerModel();
        $customer = $cm->getById($order['contact_id']);
        if (!$customer) {
            return 0;
        }

        $atm = new shopAffiliateTransactionModel();
        $atm->applyBonus(
            $order['contact_id'],
            self::calculateBonus($order),
            $order_id,
            sprintf_wp('Bonus for the order %s totalling %s',
                shopHelper::encodeOrderId($order_id),
                waCurrency::format('%{s}', $order['total'], ifempty($order['currency'], wa()->getConfig()->getCurrency()))
            )
        );
    }

    public static function cancelBonus($order_or_id)
    {
        if (int_ok($order_or_id)) {
            $order_id = $order_or_id;
            $om = new shopOrderModel();
            $order = $om->getOrder($order_id);
        } else {
            $order = $order_or_id;
            $order_id = $order['id'];
        }
        if (!$order['contact_id']) {
            return;
        }
        $cm = new shopCustomerModel();
        $customer = $cm->getById($order['contact_id']);
        if (!$customer) {
            return;
        }

        $atm = new shopAffiliateTransactionModel();
        $atm->applyBonus($order['contact_id'], -self::calculateBonus($order), $order_id);
    }

    /** Convert affiliate bonus into default currency. */
    public static function convertBonus($points)
    {
        $usage_rate = (float) wa()->getSetting('affiliate_usage_rate', 0, 'shop');
        return $points * $usage_rate;
    }

    public static function discount(&$order, $contact, $apply, $other_discounts)
    {
        if (!$contact || !$contact->getId()) {
            return 0;
        }
        $checkout_data = wa()->getStorage()->read('shop/checkout');
        if (empty($checkout_data['use_affiliate'])) {
            return 0; // !!! Will this fail when recalculating existing order?
        }
        $usage_rate = (float) wa()->getSetting('affiliate_usage_rate', 0, 'shop');
        if ($usage_rate <= 0) {
            return 0;
        }

        $cm = new shopCustomerModel();
        $customer = $cm->getById($contact->getId());
        if (!$customer || $customer['affiliate_bonus'] <= 0) {
            return 0;
        }

        $order_total = $order['total'] - $other_discounts;
        $max_bonus = $customer['affiliate_bonus'];
        if (!empty($order['params']['affiliate_bonus'])) {
            // Recalculating existing order: take old discount into account
            $max_bonus += $order['params']['affiliate_bonus'];
        }

        $crm = new shopCurrencyModel();
        $discount = (float) $crm->convert(
            $max_bonus*$usage_rate,
            wa()->getConfig()->getCurrency(true),
            wa()->getConfig()->getCurrency(false)
        );
        if ($discount > $order_total) {
            $discount = $order_total;
        }

        if ($discount < $order_total) {
            $bonus_used = $max_bonus;
        } else {
            $bonus_used = ((float) $crm->convert(
                $discount,
                wa()->getConfig()->getCurrency(false),
                wa()->getConfig()->getCurrency(true)
            )) / $usage_rate;
        }

        if (empty($order['params'])) {
            $order['params'] = array();
        }
        $order['params']['affiliate_bonus'] = $bonus_used;

        if ($apply) {
            $balance_change = $max_bonus - $bonus_used - $customer['affiliate_bonus'];
            if (abs($balance_change) > 0.0001) {
                if (!empty($order['params']['affiliate_bonus'])) {
                    $message = sprintf_wp('Recalculation of order total, new discount: %s', waCurrency::format('%{s}', $discount, wa()->getConfig()->getCurrency()));
                } else {
                    $message = sprintf_wp('Discount of %s', waCurrency::format('%{s}', $discount, wa()->getConfig()->getCurrency()));
                }
                $atm = new shopAffiliateTransactionModel();
                $atm->applyBonus($contact->getId(), $balance_change, ifset($order['id']), $message);
            }
        }

        return $discount;
    }
}

