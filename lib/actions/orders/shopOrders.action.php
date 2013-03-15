<?php

class shopOrdersAction extends shopOrderListAction
{
    public function execute()
    {
        $config = $this->getConfig();

        $default_view = $config->getOption('orders_default_view');
        $view = waRequest::get('view', $default_view, waRequest::TYPE_STRING_TRIM);

        $orders = $this->getOrders(0, $this->getCount());

        $action_ids = array_flip(array('process', 'pay', 'ship', 'complete', 'delete'));
        $workflow = new shopWorkflow();
        $actions = array();
        foreach ($workflow->getAllActions() as $action) {
            if (isset($action_ids[$action->id])) {
                $actions[$action->id] = $action->name;
            }
        }

        $state_names = array();
        foreach ($workflow->getAvailableStates() as $state_id => $state) {
            $state_names[$state_id] = $state['name'];
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
            'count_new' => $this->model->getStateCounters('new'),
            'timeout' => $config->getOption('orders_update_list'),
            'actions' => $actions
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