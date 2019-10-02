<?php

abstract class shopMarketingDiscountsViewAction extends shopMarketingSettingsViewAction
{
    protected $discount_type_id = 'coupons';

    public function __construct($params = null)
    {
        parent::__construct($params);
        $sidebar_action = new shopMarketingDiscountsSidebarAction();
        $sidebar_action->setTypeId($this->discount_type_id);
        $sidebar = $sidebar_action->display(false);
        $this->view->assign('discounts_sidebar', $sidebar);
    }
}