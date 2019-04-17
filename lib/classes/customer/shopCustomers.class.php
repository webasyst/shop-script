<?php

/**
 * Don't use. It's @deprecated
 */
class shopCustomers
{
    /**
     * @deprecated
     * @param int $customer_id
     */
    public static function recalculateTotalSpent($customer_id)
    {
        shopCustomer::recalculateTotalSpent($customer_id);
    }
}
