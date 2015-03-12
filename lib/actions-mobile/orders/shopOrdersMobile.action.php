<?php

/**
 * List of all orders in mobile backend.
 */
class shopOrdersMobileAction extends shopMobileViewAction
{
    public function execute()
    {
        if (wa()->getUser()->getRights('shop', 'orders')) {
            $collection = new shopOrdersCollection('');
            $orders = $collection->getOrders("*,contact,params", 0, 30);
            shopOrderListAction::extendContacts($orders);
            shopHelper::workupOrders($orders);
            $this->view->assign('orders', $orders);
        } else {
            $this->view->assign('orders', false);
        }

        wa()->getResponse()->setTitle(_w('Orders'));
        parent::execute();
    }
}

