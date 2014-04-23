<?php

/**
 * List of all orders in mobile backend.
 */
class shopOrdersMobileAction extends shopMobileViewAction
{
    public function execute()
    {
        if (wa()->getUser()->getRights('shop', 'orders')) {
            $om = new shopOrderModel();
            $orders = $om->order('id DESC')->limit(30)->fetchAll('id');
            shopHelper::workupOrders($orders);
            $this->view->assign('orders', $orders);
        } else {
            $this->view->assign('orders', false);
        }

        wa()->getResponse()->setTitle(_w('Orders'));
        parent::execute();
    }
}

