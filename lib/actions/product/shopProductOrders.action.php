<?php

class shopProductOrdersAction extends waViewAction
{
    public function execute()
    {
        if (!$this->getRights('orders')) {
            throw new waRightsException(_ws('Access denied'));
        }
        $id = waRequest::get('id', null, waRequest::TYPE_INT);
        $product_model = new shopProductModel();
        $product = $product_model->getById($id);
        if (!$product) {
            throw new waException(_w("Unkown product"));
        }

        // order
        $order = waRequest::get('order', null, waRequest::TYPE_STRING_TRIM);
        if ($order != 'asc' && $order != 'desc') {
            $order = 'desc';
        }

        // chunk size
        $count = $this->getConfig()->getOption('product_orders_per_page');

        // offset
        $offset = waRequest::get('offset', 0, waRequest::TYPE_INT);

        $orders_collection = new shopOrdersCollection('search/items.product_id='.$id);
        $total_count = $this->getTotalCount($orders_collection);
        $orders_collection->orderBy('create_datetime', 'DESC');
        $orders = $orders_collection->getOrders('*,params', $offset, $count);
        $this->workupOrders($orders);

        $this->view->assign(array(
            'product_id' => $id,
            'orders' => $orders,
            'offset' => $offset,
            'count' => count($orders),
            'total_count' => $total_count,
            'lazy' => waRequest::get('lazy', false)
        ));

    }

    public function workupOrders(&$orders)
    {
        $contact_ids = array();
        foreach ($orders as $o) {
            $contact_ids[] = $o['contact_id'];
        }
        $contact_ids = array_unique($contact_ids);
        $col = new waContactsCollection('id/' . implode(',', $contact_ids ? $contact_ids : array(0)));
        $contacts = $col->getContacts('id,name,firstname,lastname,middlename');
        foreach ($orders as &$o) {
            $o['contact'] = ifset($contacts[$o['contact_id']], array(
                'id' => $o['contact_id'],
                'name' => sprintf(_w('Contact deleted: %d'), $o['contact_id'])
            ));
            $o['contact']['name'] = waContactNameField::formatName($o['contact']);
        }
        unset($o);
        shopHelper::workupOrders($orders);
        foreach($orders as &$o) {
            $o['total_formatted'] = waCurrency::format('%{h}', $o['total'], $o['currency']);
            $o['shipping_name'] = ifset($o['params']['shipping_name'], '');
            $o['payment_name'] = ifset($o['params']['payment_name'], '');
            // !!! TODO: shipping and payment icons
        }
        unset($o);
    }

    public function getTotalCount(shopOrdersCollection $collection)
    {
        $total_count = waRequest::get('total_count', null, waRequest::TYPE_INT);
        if (!$total_count) {
            $total_count = $collection->count();
        }
        return $total_count;
    }

}