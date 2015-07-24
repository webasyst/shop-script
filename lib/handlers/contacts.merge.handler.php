<?php

/**
 * Update all tables when several contacts are merged into one.
 */
class shopContactsMergeHandler extends waEventHandler
{
    public function execute(&$params)
    {
        $master_id = $params['id'];
        $merge_ids = $params['contacts'];
        $all_ids = array_merge($merge_ids, array($master_id));

        if (!$merge_ids) {
            return null;
        }

        $m = new waModel();

        //
        // All the simple cases: update contact_id in tables
        //
        foreach(array(
            array('shop_cart_items', 'contact_id'),
            array('shop_checkout_flow', 'contact_id'),
            array('shop_order', 'contact_id'),
            array('shop_order_log', 'contact_id'),
            array('shop_product', 'contact_id'),
            array('shop_product_reviews', 'contact_id'),
            array('shop_affiliate_transaction', 'contact_id'), // also see below

            // No need to do this since users are never merged into other contacts
            //array('shop_coupon', 'create_contact_id'),
            //array('shop_page', 'create_contact_id'),
            //array('shop_product_pages', 'create_contact_id'),
        ) as $pair)
        {
            list($table, $field) = $pair;
            $sql = "UPDATE $table SET $field = :master WHERE $field in (:ids)";
            $m->exec($sql, array('master' => $master_id, 'ids' => $merge_ids));
        }

        //
        // shop_affiliate_transaction
        //
        $balance = 0.0;
        $sql = "SELECT * FROM shop_affiliate_transaction WHERE contact_id=? ORDER BY id";
        foreach($m->query($sql, $master_id) as $row) {
            $balance += $row['amount'];
            if ($row['balance'] != $balance) {
                $m->exec("UPDATE shop_affiliate_transaction SET balance=? WHERE id=?", $balance, $row['id']);
            }
        }
        $affiliate_bonus = $balance;

        //
        // shop_customer
        //

        // Make sure it exists
        $cm = new shopCustomerModel();
        $cm->createFromContact($master_id);

        $sql = "SELECT SUM(number_of_orders) FROM shop_customer WHERE contact_id IN (:ids)";
        $number_of_orders = $m->query($sql, array('ids' => $all_ids))->fetchField();

        $sql = "SELECT MAX(last_order_id) FROM shop_customer WHERE contact_id IN (:ids)";
        $last_order_id = $m->query($sql, array('ids' => $all_ids))->fetchField();

        $sql = "UPDATE shop_customer SET number_of_orders=?, last_order_id=?, affiliate_bonus=? WHERE contact_id=?";
        $m->exec($sql, ifempty($number_of_orders, 0), ifempty($last_order_id, null), ifempty($affiliate_bonus, 0), $master_id);

        if ($number_of_orders) {
            shopCustomer::recalculateTotalSpent($master_id);
        }

        wa('shop')->event('customers_merge', $params);

        return null;
    }
}

