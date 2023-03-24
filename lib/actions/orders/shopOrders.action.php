<?php

class shopOrdersAction extends shopOrderListAction {
    public function execute() {
        /** @var shopConfig $config */
        $config = $this->getConfig();

        $default_view = $config->getOption('orders_default_view');
        $view = waRequest::get('view', $default_view, waRequest::TYPE_STRING_TRIM);

        $orders = [];

        $forbidden = array_fill_keys(['edit', 'message', 'comment', 'editshippingdetails', 'editcode'], true);

        $workflow = new shopWorkflow();

        $count = $this->getCount();
        $available_states = $workflow->getAvailableStates();
        if ($view == 'kanban' && (!isset($this->filter_params['state_id']) || is_array($this->filter_params['state_id']))) {
            if (!empty($this->filter_params['state_id'])) {
                $available_states = array_intersect_key($available_states, array_flip($this->filter_params['state_id']));
            }
            foreach ($available_states as $state_id => $state) {
                $temp_where = "o.state_id = '$state_id'";
                $this->collection->addWhere($temp_where);
                $orders += $this->getOrders(0, $count);
                $this->collection->deleteTempWhere($temp_where);
            }
        } else {
            $orders = $this->getOrders(0, $count);
        }
        $this->formatOrders($orders);

        $actions = [];

        // get user rights
        $user = wa()->getUser();

        if ($user->isAdmin('shop')) {
            $rights = true;
        } else {
            $rights = $user->getRights('shop', 'workflow_actions.%');
            if (!empty($rights['all'])) {
                $rights = true;
            }
        }
        $state_names = [];
        if (!empty($rights)) {
            foreach ($workflow->getAvailableActions() as $action_id => $action) {
                if (!isset($forbidden[$action_id])
                    && empty($action['internal'])
                    && (($rights === true) || !empty($rights[$action_id]))
                ) {
                    $actions[$action_id] = [
                        'name'                 => ifset($action['name'], ''),
                        'style'                => ifset($action['options']['style']),
                        'available_for_states' => [],       // for what states action is available
                    ];
                }
            }


            foreach ($available_states as $state_id => $state) {
                $state_names[$state_id]['name'] = waLocale::fromArray($state['name']);
                $state_names[$state_id]['options'] = $state['options'];

                if (isset($state['available_actions']) && is_array($state['available_actions'])) {
                    foreach ($state['available_actions'] as $action_id) {
                        if (isset($actions[$action_id])) {
                            $actions[$action_id]['available_for_states'][] = $state_id; // for this state this action is available
                        }
                    }
                }

            }
        }

        $counters = [
            'state_counters' => [
                'new' => $this->model->getStateCounters('new'),
            ],
        ];

        $filter_params = $this->getFilterParams();
        if (isset($filter_params['state_id'])) {
            $filter_params['state_id'] = (array)$filter_params['state_id'];
            sort($filter_params['state_id']);
            if ($filter_params['state_id'] == ['new', 'paid', 'processing']) {
                $total = 0;
                foreach ($filter_params['state_id'] as $st) {
                    $total += (int)$this->model->getStateCounters($st);
                }
                $counters['common_counters'] = [
                    'pending' => $total,
                ];
            } else {
                foreach ($filter_params['state_id'] as $st) {
                    $counters['state_counters'][$st] = (int)$this->model->getStateCounters($st);
                }
            }
        } else if (isset($filter_params['storefront'])) {
            $counters['storefront_counters'][$filter_params['storefront']] = count($orders);
        } else {
            $counters['common_counters'] = [
                'all' => $this->model->countAll(),
            ];
        }

        // for define which actions available for whole order list
        // need for apply action on order list in table view (see $.order_list)
        // if not used, not query it and must be NULL (not empty array) for distinguish cases
        $all_order_state_ids = null;
        if ($view === 'table') {
            $all_order_state_ids = $this->getDistinctOrderFieldValues('state_id');
        }

        $state_counters = null;
        if ($view === 'kanban') {
            $state_counters = (new shopOrderModel())->getStateCounters();
        }

        $this->assign([
            'orders'              => array_values($orders),
            'total_count'         => $this->getTotalCount(),
            'count'               => count($orders),
            'order'               => $this->getOrder($orders),
            'currency'            => $config->getCurrency(),
            'state_names'         => $state_names,
            'plugin_hash'         => waRequest::get('hash', '', waRequest::TYPE_STRING_TRIM),
            'params'              => $this->getFilterParams(),
            'params_str'          => $this->getFilterParams(true),
            'view'                => $view,
            'timeout'             => $config->getOption('orders_update_list'),
            'actions'             => $actions,
            'counters'            => $counters,
            'sort'                => $this->getSort(),
            'all_order_state_ids' => $all_order_state_ids,
            'state_counters'      => $state_counters,
        ]);
    }

    public function getOrders($offset, $limit) {
        return $this->collection->getOrders("*,products,contact,params,courier,order_icon", $offset, $limit);
    }

    protected function formatOrders(&$orders) {
        self::extendContacts($orders);
        shopHelper::workupOrders($orders);
    }

    public function getOrder($orders) {
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
