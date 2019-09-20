<?php

/**
 * Settings form for discounts by total amount spent by customer,
 * and submit controller for that form.
 */
class shopMarketingDiscountsCustomerTotalAction extends shopMarketingDiscountsOrderTotalAction
{
    protected $discount_type_id = 'customer_total';

    public function execute()
    {
        $this->execByType('customer_total');
    }
}