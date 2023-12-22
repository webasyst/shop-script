<?php
/**
 * shopAffiliate::discount()
 * - Calculate and/or apply discount when user spends affiliate bonus on an order.
 * - This is called as a part of shopDiscount::calculate() / ::apply() and should not be directly called by hand.
 * - When applying:
 *   - Decrease order total (by applying a discount).
 *   - Decrease customer's affiliate bonus.
 *   - Save amount of affiliate points used to order params `affiliate_bonus`.
 *
 * shopAffiliate::refundDiscount()
 * - Reverse of application of discount()
 * - Increase customer's affiliate bonus by amount previously saved in params `affiliate_bonus`
 *
 * shopAffiliate::reapplyDiscount()
 * - Re-apply ::discount() again e.g. when a deleted order is restored.
 * - Decrease customer's affiliate bonus by amount previously saved in params `affiliate_bonus`
 *
 *
 *
 * shopAffiliate::calculateBonus()
 * - Used everywhere to calculate amount of affiliation points to be credited
 *   to customer's account when they properly pay for a given order.
 * - Does not update anything, just performs the calculation.
 *
 * shopAffiliate::applyBonus()
 * - Increase customer's affiliation account after he properly paid for a given order.
 *
 * shopAffiliate::cancelBonus()
 * - Decrease customer's affiliation account after a previously paid order
 *   is refunded or deleted.
 *
 * shopAffiliate::convertBonus()
 * - Calculate how much does a given amount of affiliation points worth
 *   in terms of discount. Input is affiliation points, output is
 *   shop's main (default) currency.
 *
 */
class shopAffiliate
{
    public static function isEnabled()
    {
        return wa()->getSetting('affiliate', '', 'shop');
    }

    /**
     * Amount of affiliation points given order worths.
     * @param array|int $order_or_id
     * @param float $credit_rate
     * @param bool $check_product_types
     * @return float
     */
    public static function calculateBonus($order_or_id, $credit_rate = null, $check_product_types = true)
    {
        if (!self::isEnabled()) {
            return 0;
        }

        if ($credit_rate === null) {
            $credit_rate = wa()->getSetting('affiliate_credit_rate', 0, 'shop');
        }
        if (!$credit_rate) {
            return 0;
        }

        if (wa_is_int($order_or_id)) {
            $om = new shopOrderModel();
            $order = $om->getOrder($order_or_id);
        } else {
            $order = $order_or_id;
        }

        // Convert order total from order currency to default currency
        $curm = new shopCurrencyModel();
        $order_currency = isset($order['currency']) ? $order['currency'] : null;
        $def_cur = wa('shop')->getConfig()->getCurrency(true);
        $affiliatable_total = $curm->convert(ifset($order['total'], 0) - ifset($order['shipping'], 0), ifempty($order_currency, $def_cur), $def_cur);

        $product_types = wa()->getSetting('affiliate_product_types', '', 'shop');
        if (!empty($product_types) && $check_product_types) {

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
            foreach ($order['items'] as $i) {
                $product_ids[$i['product_id']] = true;
            }
            $pm = new shopProductModel();
            $products = $pm->getById(array_keys($product_ids));

            // Calculate total value of affiliatable order items
            $items_total = 0;
            foreach ($order['items'] as $i) {
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

        $is_rounding = wa()->getSetting('affiliate_rounding', 0, 'shop');

        $final_bonus = $affiliatable_total / $credit_rate;
        if ($is_rounding) {
            $final_bonus = floor($final_bonus);
        }

        return $final_bonus;
    }

    public static function applyBonus($order_or_id)
    {
        if (wa_is_int($order_or_id)) {
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

        if (empty($order['currency'])) {
            $shop_config = wa('shop')->getConfig();
            /**
             * @var shopConfig $shop_config
             */
            $currency = $shop_config->getCurrency();
        } else {
            $currency = $order['currency'];
        }

        $atm = new shopAffiliateTransactionModel();
        $atm->applyBonus(
            $order['contact_id'],
            self::calculateBonus($order),
            $order_id,
            sprintf_wp(
                'Bonus for the order %s totalling %s',
                shopHelper::encodeOrderId($order_id),
                waCurrency::format('%{s}', $order['total'], $currency)
            )
        );
    }

    public static function refundDiscount($order_or_id)
    {
        if (wa_is_int($order_or_id)) {
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

        $order_params_model = new shopOrderParamsModel();
        $params = ifset($order['params'], array()) + $order_params_model->get($order['id']);
        if (empty($params['affiliate_bonus'])) {
            return;
        }

        $atm = new shopAffiliateTransactionModel();
        $atm->applyBonus(
            $order['contact_id'],
            $params['affiliate_bonus'],
            $order_id,
            sprintf_wp('Refund bonus used to get discount for order %s', shopHelper::encodeOrderId($order_id)),
            shopAffiliateTransactionModel::TYPE_ORDER_CANCEL
        );
    }

    public static function cancelBonus($order_or_id)
    {
        if (wa_is_int($order_or_id)) {
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
        $atm->applyBonus(
            $order['contact_id'],
            -self::calculateBonus($order),
            $order_id,
            '',
            shopAffiliateTransactionModel::TYPE_ORDER_CANCEL
        );
    }

    /** Convert affiliate bonus into default currency. */
    public static function convertBonus($points)
    {
        $usage_rate = (float) wa()->getSetting('affiliate_usage_rate', 0, 'shop');
        return $points * $usage_rate;
    }

    /**
     * @param mixed[string] $order
     * @param waContact $contact
     * @param bool $apply
     * @param float $other_discounts
     * @param string $d description
     * @return float
     */
    public static function discount(&$order, $contact, $apply, $other_discounts, &$d = null)
    {
        // Make sure affiliation program is enabled, set up properly and applicable for given order
        if (!$contact || !$contact->getId()) {
            return 0;
        }
        $usage_rate = (float) wa()->getSetting('affiliate_usage_rate', 0, 'shop');
        $usage_percent = (float) wa()->getSetting('affiliate_usage_percent', 0, 'shop');
        if ($usage_rate <= 0) {
            return 0;
        }
        $checkout_data = wa()->getStorage()->read('shop/checkout');
        $force_affiliate = !empty($order['params']['force_affiliate']);
        if (empty($order['id']) && empty($checkout_data['use_affiliate']) && !$force_affiliate) {
            return 0;
        }
        $cm = new shopCustomerModel();
        $customer = $cm->getById($contact->getId());
        if (!$customer || (empty($order['id']) && $customer['affiliate_bonus'] <= 0)) {
            return 0;
        }

        // When recalculating existing order, take into account affiliation bonus used originally.
        // If not used at all, don't apply it this time, too.
        $prev_affiliate_bonus = 0;
        if (!empty($order['id'])) {
            if (!empty($order['params']['affiliate_bonus'])) {
                $prev_affiliate_bonus = $order['params']['affiliate_bonus'];
            } else {
                $order_params_model = new shopOrderParamsModel();
                $params = ifset($order['params'], array()) + $order_params_model->get($order['id']);
                $prev_affiliate_bonus = ifempty($params['affiliate_bonus'], 0);
            }
            if (!$prev_affiliate_bonus) {
                return 0;
            }
        }

        $order_total = $order['total'] - $other_discounts;
        if ($usage_percent) {
            $order_total = $usage_percent * $order_total / 100.0;
        }
        $max_bonus = $customer['affiliate_bonus'] + $prev_affiliate_bonus;

        $shop_config = wa('shop')->getConfig();
        /**
         * @var shopConfig $shop_config
         */

        $default_currency = $shop_config->getCurrency(true);
        if (empty($order['currency'])) {
            $order_currency = $shop_config->getCurrency(false);
        } else {
            $order_currency = $order['currency'];
        }

        // Calculate discount
        $crm = new shopCurrencyModel();
        $discount = (float) $crm->convert(
            $max_bonus*$usage_rate,
            $default_currency,
            $order_currency
        );

        // Make sure discount does not exceed order total
        if ($discount < $order_total) {
            $bonus_used = $max_bonus;
        } else {
            $discount = $order_total;
            $bonus_used = ((float) $crm->convert(
                $discount,
                $order_currency,
                $default_currency
            )) / $usage_rate;
        }

        $d = sprintf_wp('Affiliate bonus (%s) is used for additional discount', round($bonus_used, 2)).': %s';

        // Change customer's affiliation balance
        if ($apply) {
            unset($order['params']['force_affiliate']);
            $balance_change = $max_bonus - $bonus_used - $customer['affiliate_bonus'];

            if (abs($balance_change) >= 0.0001) {
                if ($prev_affiliate_bonus) {
                    $message = sprintf_wp('Recalculation of order total, new discount: %s', waCurrency::format('%{s}', $discount, $order_currency));
                } else {
                    $message = sprintf_wp('Discount of %s', waCurrency::format('%{s}', $discount, $order_currency));
                }
                $atm = new shopAffiliateTransactionModel();
                $atm->applyBonus($contact->getId(), $balance_change, ifset($order['id']), $message);
            }
        }

        // Save bonus used to order params
        if (empty($order['params'])) {
            $order['params'] = array();
        }
        $order['params']['affiliate_bonus'] = $bonus_used;

        return $discount;
    }

    public static function reapplyDiscount($order_or_id)
    {
        if (wa_is_int($order_or_id)) {
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

        $bonus = ifset($order['params']['affiliate_bonus']) ? $order['params']['affiliate_bonus'] : null;

        $atm = new shopAffiliateTransactionModel();
        $atm->applyBonus(
            $order['contact_id'],
            -$bonus,
            $order_id,
            _w('Recalculation of discount after order is restored')
        );
    }
}
