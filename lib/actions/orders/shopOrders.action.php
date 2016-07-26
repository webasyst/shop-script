<?php

class shopOrdersAction extends shopOrderListAction
{

    public function __construct($params = null)
    {
        parent::__construct($params);
        $sort = $this->getSort();
        $order_by = array($sort[0] => $sort[1]);
        if ($sort[0] !== 'create_datetime') {
            $order_by['create_datetime'] = 'desc';
        }
        $this->collection->orderBy($order_by);
    }

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
            'counters' => $counters,
            'sort' => $this->getSort()
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

    public function getSort()
    {
        $sort = (array) wa()->getRequest()->request('sort');
        $sort_field = (string) ifset($sort[0]);
        $sort_order = (string) ifset($sort[1]);

        $csm = new waContactSettingsModel();

        if (!$sort_field) {
            $sort = $csm->getOne(wa()->getUser()->getId(), 'shop', 'order_list_sort');
            $sort = explode('/', $sort, 2);
            $sort_field = (string) ifset($sort[0]);
            $sort_order = (string) ifset($sort[1]);
        }

        if (!in_array($sort_field, array('create_datetime', 'updated', 'paid_date'))) {
            $sort_field = 'create_datetime';
            $sort_order = 'desc';
        }
        $sort_order = strtolower($sort_order) === 'desc' ? 'desc' : 'asc';

        $csm->set(wa()->getUser()->getId(), 'shop', 'order_list_sort', "{$sort_field}/{$sort_order}");

        return array($sort_field, $sort_order);
    }
}