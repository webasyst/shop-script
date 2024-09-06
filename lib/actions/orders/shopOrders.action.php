<?php

class shopOrdersAction extends shopOrderListAction {
    public function execute() {
        /** @var shopConfig $config */
        $config = $this->getConfig();

        $view = $this->getOrdersView();

        $orders = [];

        $forbidden = array_fill_keys(['edit', 'message', 'comment', 'editshippingdetails', 'editcode'], true);

        $workflow = new shopWorkflow();

        $count = $this->getCount();
        $counters = ['state_counters' => []];
        $available_states = $workflow->getAvailableStates();
        $filter_state_id = $this->getStateId();
        if ($view == 'kanban') {
            if (wa()->getUser()->getRights('shop', 'orders') == shopRightConfig::RIGHT_ORDERS_COURIER) {
                throw new waException(_w('Access denied'), 403);
            }
            if ($filter_state_id) {
                $available_states = array_intersect_key($available_states, array_flip($filter_state_id));
            }
            foreach ($available_states as $state_id => $state) {
                $temp_where = "o.state_id = '$state_id'";
                $this->collection->addWhere($temp_where);
                $orders += $this->getOrders(0, $count);
                $counters['state_counters'][$state_id] = $this->collection->count(true);
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

        $filter_params = $this->getFilterParams();
        if ($view != 'kanban') {
            $counters['state_counters']['new'] = $this->model->getStateCounters('new');
            if ($filter_state_id) {
                $filter_params['state_id'] = $filter_state_id;
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

            if ($view === 'split' && !empty($filter_params['viewpos'])) {
                $sales_stats_action = new shopOrdersSalesStatsAction([
                    'date' => $filter_params['viewpos'],
                ]);
                $orders_sales_html = $sales_stats_action->display();
            }
        }

        // for define which actions available for whole order list
        // need for apply action on order list in table view (see $.order_list)
        // if not used, not query it and must be NULL (not empty array) for distinguish cases
        $all_order_state_ids = null;
        if ($view === 'table') {
            $all_order_state_ids = $this->getDistinctOrderFieldValues('state_id');
        }

        $state_counters = null;
        $state_transitions = null;
        if ($view === 'kanban') {
            $state_counters = $this->model->getStateCounters();
            $state_transitions = $this->getStateTransitions($workflow, array_keys($state_counters));
        }

        list($show_mobile_ad, $show_premium_ad) = $this->shouldShowAds();

        $currency =  $config->getCurrency();
        $total_processing = wa_currency_html($this->model->getTotalSalesByInProcessingStates(), $currency, '%k{h}');
        $this->assign([
            'orders'               => array_values($orders),
            'total_count'          => $this->getTotalCount(),
            'count'                => count($orders),
            'order'                => $this->getOrder($orders),
            'currency'             => $currency,
            'state_names'          => $state_names,
            'plugin_hash'          => waRequest::get('hash', '', waRequest::TYPE_STRING_TRIM),
            'params'               => $filter_params,
            'params_str'           => $this->getFilterParams(true),
            'params_extended'      => $this->getFilterParamsExtended($filter_params, $orders),
            'view'                 => $view,
            'timeout'              => $config->getOption('orders_update_list'),
            'actions'              => $actions,
            'counters'             => $counters,
            'sort'                 => $this->getSort(),
            'all_order_state_ids'  => $all_order_state_ids,
            'state_counters'       => $state_counters,
            'state_transitions'    => $state_transitions,
            'last_update_datetime' => $this->getLastUpdateDatetime($orders),
            'total_processing'     => $total_processing,
            'show_mobile_ad'       => $show_mobile_ad,
            'show_premium_ad'      => $show_premium_ad,
            'is_orders_empty'      => !($this->model->countAll()),
            'orders_sales_html'    => ifset($orders_sales_html),
        ]);
    }

    protected function shouldShowAds()
    {
        // Mobile ad has priority; show if required
        list($show_mobile_ad, $when_mobile_add_hidden) = $this->shouldShowMobileAd();
        if ($show_mobile_ad) {
            return [$show_mobile_ad, false];
        }
        // three days after mobile ad is hidden, show no ads
        if (time() - $when_mobile_add_hidden <= 3*24*3600) {
            return [false, false];
        }
        // show premium ad unless closed by user
        return [false, $this->shouldShowPremiumAd()];
    }

    protected function shouldShowMobileAd()
    {
        $hide_mobile_ad_till = wa()->getUser()->getSettings('shop', 'hide_mobile_ad_till', null);
        if ($hide_mobile_ad_till && strtotime($hide_mobile_ad_till) > time()) {
            return [false, strtotime($hide_mobile_ad_till) - 30*24*3600];
        }

        $api_tokens_model = new waApiTokensModel();
        $row = $api_tokens_model->getByField([
            'client_id' => ['com.webasyst.shopscript', 'com.webasyst.shopscript.android'],
        ], false);
        if ($row) {
            return [false, strtotime($row['create_datetime'])];
        }

        return [true, null];
    }

    protected function shouldShowPremiumAd()
    {
        if (shopLicensing::isPremium()) {
            return false;
        }
        $hide_premium_ad_till = wa()->getUser()->getSettings('shop', 'hide_premium_ad_till', null);
        if ($hide_premium_ad_till && strtotime($hide_premium_ad_till) > time()) {
            return false;
        }
        return true;
    }

    protected function getLastUpdateDatetime($orders)
    {
        if (!$orders) {
            return date('Y-m-d H:i:s', time() - 3600*24);
        }
        return max(array_column($orders, 'update_datetime'));
    }

    protected function getStateTransitions($workflow, $state_ids)
    {
        $actions_data = $workflow->getAvailableActions();

        $state_transitions = [];
        foreach($state_ids as $state_id) {
            $state = $workflow->getStateById($state_id);
            foreach($state->getActions() as $action) {
                $to_state_id = ifset($actions_data, $action->getId(), 'state', null);
                if ($to_state_id) {
                    $state_transitions[$state->getId()][$to_state_id] = true;
                }
            }
        }

        $state_transitions['auth']['paid'] = true;
        $state_transitions['auth']['refunded'] = true;

        return $state_transitions;
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

    protected function getFilterParamsExtended($filter_params, $orders)
    {
        $result = [];
        if (isset($filter_params['product_id'])) {
            $result['product_id'] = [
                'id' => $filter_params['product_id'],
                'name' => '',
            ];

            if ($orders) {
                $order = reset($orders);
                if (isset($order['items'])) {
                    foreach($order['items'] as $item) {
                        if (ifset($item, 'product', 'id', null) == $filter_params['product_id']) {
                            $result['product_id']['name'] = ifset($item, 'product', 'name', '');
                            break;
                        }
                    }
                }
            }

            if (empty($result['product_id']['name'])) {
                $product_model = new shopProductModel();
                $product = $product_model->getById($filter_params['product_id']);
                if ($product) {
                    $result['product_id']['name'] = $product['name'];
                }
            }
        }

        if (isset($filter_params['contact_id'])) {
            $result['contact_id'] = [
                'id' => $filter_params['contact_id'],
                'name' => '',
            ];

            $contact_model = new waContactModel();
            $contact_name = $contact_model->getName($filter_params['contact_id']);
            if ($contact_name) {
                $result['contact_id']['name'] = $contact_name;
            }
        }

        $payment_id = ifset($filter_params, 'payment_id', null);
        $shipping_id = ifset($filter_params, 'shipping_id', null);
        if ($payment_id || $shipping_id) {

            $plugins = [];
            foreach($orders as $o) {
                if ($payment_id && ifempty($o, 'params', 'payment_id', null) == $payment_id) {
                    $plugins[$payment_id] = ['name' => ifempty($o, 'params', 'payment_name', '')];
                    break;
                }
                if ($shipping_id && ifempty($o, 'params', 'shipping_id', null) == $shipping_id) {
                    $plugins[$shipping_id] = ['name' => ifempty($o, 'params', 'shipping_name', '')];
                    break;
                }
            }

            if (!$plugins) {
                $plugin_model = new shopPluginModel();
                $plugins = $plugin_model->getByField([
                    'id' => array_filter([$payment_id, $shipping_id]),
                ], 'id');
            }

            foreach(['shipping_id', 'payment_id'] as $key) {
                if (isset($filter_params[$key]) && !empty($plugins[$filter_params[$key]]['name'])) {
                    $result[$key] = [
                        'id' => $filter_params[$key],
                        'name' => $plugins[$filter_params[$key]]['name'],
                    ];
                }
            }
        }

        return $result;
    }

    protected function getOrdersView()
    {
        $default_view = wa('shop')->getConfig()->getOption('orders_default_view');
        $view = waRequest::get('view', $default_view, waRequest::TYPE_STRING_TRIM);
        if (!in_array($view, ['split','table','kanban'])) {
            return $default_view;
        }
        return $view;
    }
}
