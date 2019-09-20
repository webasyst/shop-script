<?php

/**
 * Settings form for discounts by order total amount,
 * and submit controller for that form.
 *
 * Also used as a superclass by DiscountsCustomerTotalAction.
 */
class shopMarketingDiscountsOrderTotalAction extends shopMarketingDiscountsViewAction
{
    protected $discount_type_id = 'order_total';

    public function execute()
    {
        $this->execByType('order_total');
    }

    public function execByType($type)
    {
        $dbsm = new shopDiscountBySumModel();

        $enabled = shopDiscounts::isEnabled($type);
        $def_cur = waCurrency::getInfo(wa()->getConfig()->getCurrency());

        $rates = $dbsm->getByType($type);
        foreach($rates as &$r) {
            $r['sum'] = (float) $r['sum'];
            $r['discount'] = (float) $r['discount'];
        }

        $this->view->assign('rates', $rates);
        $this->view->assign('enabled', $enabled);
        $this->view->assign('def_cur_sym', ifset($def_cur['sign_html'], ifset($def_cur['sign'], wa()->getConfig()->getCurrency())));
    }
}

