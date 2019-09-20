<?php

/**
 * Discount coupons settings form, and submit controller for that form.
 */
class shopMarketingDiscountsCouponsAction extends shopMarketingDiscountsViewAction
{
    protected $discount_type_id = 'coupons';

    public function execute()
    {
        $enabled = shopDiscounts::isEnabled('coupons');
        $this->view->assign('enabled', $enabled);
    }
}
