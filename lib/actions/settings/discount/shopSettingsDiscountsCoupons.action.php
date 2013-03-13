<?php

/**
 * Discount coupons settings form, and submit controller for that form.
 */
class shopSettingsDiscountsCouponsAction extends waViewAction
{
    public function execute()
    {
        $enabled = shopDiscounts::isEnabled('coupons');
        $this->view->assign('enabled', $enabled);
    }
}
