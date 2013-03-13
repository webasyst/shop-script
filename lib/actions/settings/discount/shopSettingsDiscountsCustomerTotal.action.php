<?php

/**
 * Settings form for discounts by total amount spent by customer,
 * and submit controller for that form.
 */
class shopSettingsDiscountsCustomerTotalAction extends shopSettingsDiscountsOrderTotalAction
{
    public function execute()
    {
        $this->execByType('customer_total');
    }
}

