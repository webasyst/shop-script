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
        $orders_collection = new shopOrdersCollection('search/contact_id='.$id);
        $orders = $orders_collection->getOrders('*,items,params', 0, 500);
        shopHelper::workupOrders($orders);
        foreach($orders as &$o) {
            $o['total_formatted'] = waCurrency::format('%{h}', $o['total'], $o['currency']);
            $o['shipping_name'] = ifset($o['params']['shipping_name'], '');
            $o['payment_name'] = ifset($o['params']['payment_name'], '');
        }

        $config = wa()->getConfig('shop');

        $this->view->assign('orders_default_view', $config->getOption('orders_default_view'));
        $this->view->assign('orders',  $orders);
        $this->view->assign('contact', $contact);
        $this->view->assign('def_cur_tmpl', str_replace('0', '%s', waCurrency::format('%{h}', 0, wa()->getConfig()->getCurrency())));
    }
}

