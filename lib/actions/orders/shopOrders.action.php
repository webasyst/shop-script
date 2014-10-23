<?php

class shopOrdersAction extends shopOrderListAction
{
    public function execute()
    {
        $config = $this->getConfig();

        $default_view = $config->getOption('orders_default_view');
        $view = waRequest::get('view', $default_view, waRequest::TYPE_STRING_TRIM);

        $orders = $this->getOrders(0, $this->getCount());

        $action_ids = array_flip(array('process', 'pay', 'ship', 'complete', 'delete', 'restore'));
        $workflow = new shopWorkflow();
        $actions = array();
        foreach ($workflow->getAllActions() as $action) {
            if (isset($action_ids[$action->id])) {
                $actions[$action->id] = array(
                    'name' => $action->name,
                    'style' => $action->getOption('style')
                );
            }
        }
        
        $state_names = array();
        foreach ($workflow->getAvailableStates() as $state_id => $state) {
            $state_names[$state_id] = $state['name'];
        }
        
        $counters = array(
            'state_counters' => array(
                'new' => $this->model->getStateCounters('new')
            )
        );
        
        $filter_params = $this->getFilterParams();
        if (isset($filter_params['state_id'])) {
            $filter_params['state_id'] = (array) $filter_params['state_id'];
            sort($filter_params['state_id']);
            if ($filter_params['state_id'] == array('new', 'paid', 'processing')) {
                $total = 0;
                foreach ($filter_params['state_id'] as $st) {
                    $total += (int) $this->model->getStateCounters($st);
                }
                $counters['common_counters'] = array(
                    'pending' => $total
                );
            } else {
                foreach ($filter_params['state_id'] as $st) {
                    $counters['state_counters'][$st] = (int) $this->model->getStateCounters($st);
                }
            }
        } else {
            $counters['common_counters'] = array(
                'all' => $this->model->countAll()
            );
        }
        
        $this->assign(array(
            'orders' => array_values($orders),
            'total_count' => $this->getTotalCount(),
            'count' => count($orders),
            'order' => $this->getOrder($orders),
            'currency' => $this->getConfig()->getCurrency(),
            'state_names' => $state_names,
            'params' => $this->getFilterParams(),
            'params_str' => $this->getFilterParams(true),
            'view' => $view,
            'timeout' => $config->getOption('orders_update_list'),
            'actions' => $actions,
            'counters' => $counters
        ));
    }

    public function getOrder($orders)
    {
        $order_id = waRequest::get('id', null, waRequest::TYPE_INT);
        if ($order_id) {
            if (isset($orders[$order_id])) {
                return $orders[$order_id];
            } else {
                $item = $this->model->getById($order_id);
                if (!$item) {
                    throw new waException("Unknown order", 404);
                }
                return $item;
            }
        } else if (!empty($orders)) {
            reset($orders);
            return current($orders);
        }
        return null;
    }
}