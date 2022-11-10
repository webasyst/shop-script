<?php

class shopOrdersPerformActionController extends waJsonController
{
    public function execute()
    {
        $action_id = waRequest::get('id', null, waRequest::TYPE_STRING_TRIM);
        if (!$action_id) {
            throw new waException('No action id given.');
        }

        $chunk_size = $this->getChunkSize();
        $offset = (int) waRequest::get('offset');
        
        $workflow = new shopWorkflow();

        // collect orders under which has performed actions
        $updated_orders_ids = array();
        
        $hash = $this->getHash();
        if ($hash === null) {
            return;
        }

        $collection = new shopOrdersCollection($hash);
        $total_count = $collection->count();
        $orders = $collection->getOrders('*', $offset, $chunk_size);
        foreach ($orders as $order) {
            /** @var waWorkflowAction[] $actions */
            $actions = $workflow->getStateById($order['state_id'])->getActions();
            if (isset($actions[$action_id])) {
                $actions[$action_id]->run($order['id']);
                $updated_orders_ids[] = $order['id'];
            }
        }
        
        if (!$updated_orders_ids) {
            return;
        }

        // How many orders stayed in the this collection?
        // That orders that stayed in collection we not touch again and offset updated on count of stayed updated orders
        // If after run action all updated orders moved from this collection ($stayed_count == 0) then offset stay the same

        $stayed_count = $this->calcStayedCount($hash, $updated_orders_ids);

        $this->response = array(
            'offset'      => $offset + $stayed_count,
            'total_count' => $total_count,
        );

        $order_model = new shopOrderModel();
        $state_counters = $order_model->getStateCounters();
        $pending_count =
            (!empty($state_counters['new'])        ? $state_counters['new'] : 0) +
            (!empty($state_counters['processing']) ? $state_counters['processing'] : 0) +
            (!empty($state_counters['paid'])       ? $state_counters['paid'] : 0);
        $this->response['state_counters'] = $state_counters;
        $this->response['pending_count'] = $pending_count;
        
        $collection = new shopOrdersCollection('id/'.implode(',', $updated_orders_ids));
        $total_count = $collection->count();
        $orders = $collection->getOrders('*,contact', 0, $total_count);
        // orders for update items in table
        shopHelper::workupOrders($orders);
        $this->response['orders'] = array_values($orders);
        
    }
    
    public function getHash()
    {
        $order_ids = waRequest::post('order_id', null, waRequest::TYPE_ARRAY_INT);
        if ($order_ids !== null) {
            if ($order_ids) {
                return 'id/'.implode(',', $order_ids);
            } else {
                return null;
            }
        }
        
        $filter_params = waRequest::post('filter_params', null);
        if ($filter_params === null) {
            return null;
        }
        
        $hash = '';
        if ($filter_params) {
            if (count($filter_params) == 1) {
                $k = key($filter_params);
                $v = $filter_params[$k];
                if (is_array($v)) {
                    $v = implode("||", $v);
                }
                if ($k == 'storefront') {
                    $k = 'params.'.$k;
                    if (substr($v, -1) == '*') {
                        $v = substr($v, 0, -1);
                    }
                }
                $hash = "search/{$k}={$v}";
            }
        }
        if (!$hash) {
            $hash_param = waRequest::get('hash', '', waRequest::TYPE_STRING_TRIM);
            if ($hash_param) {
                $collection = new shopOrdersCollection($hash_param);
                if ($collection->isPluginHash()) {
                    $hash = $hash_param;
                } else {
                    $order_model = new shopOrderModel();
                    $number_selected_orders = $collection->count();
                    $number_all_orders = $order_model->countAll();
                    // if a hash is set and there are all products in the collection, then something went wrong
                    // paranoia mode
                    if ($number_selected_orders >= $number_all_orders) {
                        return null;
                    }
                }
            }
        }

        return $hash;
    }

    /**
     * @param $hash
     * @param $updated_orders_ids
     * @return int
     */
    protected function calcStayedCount($hash, $updated_orders_ids)
    {
        if (!$updated_orders_ids) {
            return 0;
        }

        $collection = new shopOrdersCollection($hash);

        $updated_orders_ids_str = join(',', $updated_orders_ids);

        $collection->addWhere("id IN($updated_orders_ids_str)");

        // How many orders stayed in the this collection?
        return $collection->count();
    }

    protected function getChunkSize()
    {
        $orders_per_page = $this->getConfig()->getOption('orders_per_page');
        $view = 'table';
        if (is_array($orders_per_page)) {
            if (isset($orders_per_page[$view])) {
                $count = $orders_per_page[$view];
            } else {
                $count = reset($orders_per_page);
            }
        } else {
            $count = $orders_per_page;
        }
        return $count;
    }
}
