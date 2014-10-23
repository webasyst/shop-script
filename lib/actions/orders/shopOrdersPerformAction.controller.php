<?php

class shopOrdersPerformActionController extends waJsonController
{
    public function execute()
    {
        $action_id = waRequest::get('id', null, waRequest::TYPE_STRING_TRIM);
        if (!$action_id) {
            throw new waException('No action id given.');
        }

        $chunk_size = 100;
        $offset = (int) waRequest::get('offset');
        
        $workflow = new shopWorkflow();
        $order_model = new shopOrderModel();
        
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
            $actions = $workflow->getStateById($order['state_id'])->getActions();
            if (isset($actions[$action_id])) {
                $actions[$action_id]->run($order['id']);
                $updated_orders_ids[] = $order['id'];
            }
        }
        
        if (!$updated_orders_ids) {
            return;
        }

        $this->response = array(
            'offset' => $offset + count($orders),
            'total_count' => $total_count                    
        );

        // sidebar counters
        if ($this->response['offset'] >= $this->response['total_count']) {
            $order_model = new shopOrderModel();
            $state_counters = $order_model->getStateCounters();
            $pending_count =
                (!empty($state_counters['new'])        ? $state_counters['new'] : 0) +
                (!empty($state_counters['processing']) ? $state_counters['processing'] : 0) +
                (!empty($state_counters['paid'])       ? $state_counters['paid'] : 0);            
            $this->response['state_counters'] = $state_counters;
            $this->response['pending_count'] = $pending_count;
        }
        
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
                return 'id/'.implode(',',$order_ids);
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
        return $hash;
    }
}