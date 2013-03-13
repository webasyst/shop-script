<?php

class shopCustomers
{
    public static function recalculateTotalSpent($customer_id)
    {
        if (!$customer_id) {
            return; // being paranoid
        }
        $m = new shopCustomerModel();
        $sql = "SELECT SUM(total*rate)
                FROM shop_order
                WHERE contact_id=:cid
                    AND paid_date IS NOT NULL";
        $m->updateById($customer_id, array(
            'total_spent' => (float) $m->query($sql, array('cid' => $customer_id))->fetchField(),
        ));
    }
}
