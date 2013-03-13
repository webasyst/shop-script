<?php

/**
 * List of all orders in mobile backend.
 */
class shopOrdersMobileAction extends shopMobileViewAction
{
    public function execute()
    {
        $om = new shopOrderModel();
        $orders = $om->order('id DESC')->limit(30)->fetchAll('id');
        shopHelper::workupOrders($orders);
        $this->view->assign('orders', $orders);

        wa()->getResponse()->setTitle(_w('Orders'));
        parent::execute();
    }
}

