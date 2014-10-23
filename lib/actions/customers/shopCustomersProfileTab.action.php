<?php

/**
 * List of orders for Contacts profile tab
 */
class shopCustomersProfileTabAction extends waViewAction
{
    public function execute()
    {
        $id = waRequest::request('id', null, waRequest::TYPE_INT);

        $contact = new waContact($id);
        $contact->getName();

        // Customer orders
        $im = new shopOrderItemsModel();
        $orders_collection = new shopOrdersCollection('search/contact_id='.$id);
        $orders = $orders_collection->getOrders('*,items,params', 0, 500);
        shopHelper::workupOrders($orders);
        foreach($orders as &$o) {
            $o['total_formatted'] = waCurrency::format('%{s}', $o['total'], $o['currency']);
            $o['shipping_name'] = ifset($o['params']['shipping_name'], '');
            $o['payment_name'] = ifset($o['params']['payment_name'], '');
        }

        $this->view->assign('orders',  $orders);
        $this->view->assign('contact', $contact);
        $this->view->assign('def_cur_tmpl', str_replace('0', '%s', waCurrency::format('%{s}', 0, wa()->getConfig()->getCurrency())));
    }
}

