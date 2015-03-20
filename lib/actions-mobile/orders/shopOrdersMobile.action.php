<?php
/**
 * List of all orders in mobile backend.
 */
class shopOrdersMobileAction extends shopMobileViewAction
{
    public function execute()
    {
        $orders = false;
        $start = waRequest::request('start', 0, 'int');
        $has_more_rows = false;
        $limit = 50;

        if (wa()->getUser()->getRights('shop', 'orders')) {

            $collection = new shopOrdersCollection('');
            $orders = $collection->getOrders("*,contact,params", $start, 50);
            $has_more_rows = count($orders) == $limit;

            shopOrderListAction::extendContacts($orders);
            shopHelper::workupOrders($orders);
        }

        if (!waRequest::request('nolayout')) {
            wa()->getResponse()->setTitle(_w('Orders'));
            $this->setLayout(new shopMobileLayout());
        }

        $this->view->assign(array(
            'has_more_rows' => $has_more_rows,
            'orders' => $orders,
            'start' => $start,
        ));
    }
}

