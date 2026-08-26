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
            throw new waException(_w("Unknown product"));
        }

        // chunk size
        $count = $this->getConfig()->getOption('product_orders_per_page');

        // offset
        $offset = waRequest::get('offset', 0, waRequest::TYPE_INT);

        $orders_collection = new shopOrdersCollection('search/items.product_id='.$id);
        $total_count = $this->getTotalCount($orders_collection);
        $orders_collection->orderBy('create_datetime', 'DESC');
        $orders = $orders_collection->getOrders('*,params,order_icon', $offset, $count);
        $this->workupOrders($orders);

        $default_view = $this->getConfig()->getOption('orders_default_view');
        $view = waRequest::get('view', $default_view, waRequest::TYPE_STRING_TRIM);

        $this->view->assign(array(
            'product_id'   => $id,
            'orders'       => $orders,
            'offset'       => $offset,
            'count'        => count($orders),
            'total_count'  => $total_count,
            'lazy'         => waRequest::get('lazy', false),
            'view' => $view,
        ));

    }

    public function workupOrders(&$orders)
    {
        $contact_ids = array();
        foreach ($orders as $o) {
            $contact_ids[] = $o['contact_id'];
        }
        $contact_ids = array_unique($contact_ids);
        $col = new waContactsCollection('id/'.implode(',', $contact_ids ? $contact_ids : array(0)));
        $limit = !empty($contact_ids) ? count($contact_ids) : 0;

        shopHelper::workupOrders($orders);

        $contacts = $col->getContacts('id,name,firstname,lastname,middlename', 0, $limit);
        $state_names = $this->getStateNames();
        foreach ($orders as &$o) {
            $o['contact'] = ifset($contacts[$o['contact_id']], array(
                'id'   => $o['contact_id'],
                'name' => sprintf(_w('Contact deleted: %d'), $o['contact_id'])
            ));
            $o['contact']['name'] = waContactNameField::formatName($o['contact']);

            if (isset($o['state_id'])) {
                $o['state_name'] = $state_names[$o['state_id']]['name'];
                $o['state_color'] = $state_names[$o['state_id']]['color'];
            }
            $o['total_formatted'] = waCurrency::format('%{h}', $o['total'], $o['currency']);
            $o['shipping_name'] = ifset($o['params']['shipping_name'], '');
            $o['payment_name'] = ifset($o['params']['payment_name'], '');
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

    protected function getStateNames()
    {
        $workflow = new shopWorkflow();
        $available_states = $workflow->getAvailableStates();

        $state_names = [];
        foreach ($available_states as $state_id => $state) {
            $state_names[$state_id]['name'] = waLocale::fromArray($state['name']);
            $state_names[$state_id]['color'] = $state['options']['style']['color'];
        }

        return $state_names;
    }
}
