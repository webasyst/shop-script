<?php

class shopMarketingDiscountsCustomerTotalSaveController extends shopMarketingDiscountsOrderTotalSaveController
{
    public function execute()
    {
        $this->saveByType('customer_total');
    }
}