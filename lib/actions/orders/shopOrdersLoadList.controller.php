<?php

class shopOrdersLoadListController extends shopOrderListAction
{
    private $offset;

    public function execute()
    {

        $updated_orders = [];

        // id is order id
        if (!waRequest::get('id', 0, waRequest::TYPE_INT)) {
            if (waRequest::get('search')) {
                $updated_orders = array_values($this->getUpdatedOrders());
            }

            if (waRequest::get('counters')) {
                $datas['counters'] = $this->getListCounters();
            }

            $datas['updated_orders'] = $updated_orders;

            $this->assign($datas);
        }

        // if 'id' is passed

        $offset = $this->getOffset();
        if ($offset === false) {
            $this->setError(_w("Unknown offset"));
        }
        $count = $this->getCount();
        if ($count === false) {
            $this->setError(_w("Unknown count"));
        }

        $total_count = $this->getTotalCount();
        $orders = $this->getOrders($offset, $count);

        if (waRequest::get('search')) {
            $updated_orders = array_values($this->getUpdatedOrders());
        }

        $count = count($orders);

        // set response
        $this->assign(
            // basic info
            array(
                'orders'         => array_values($orders),
                'updated_orders' => $updated_orders,
                'total_count'    => $total_count,
                'current_offset' => $offset,
                'count'          => $count,
                'loaded'         => $offset + $count,
                'progress'       => array(
                    'loaded' => _w('%d order', '%d orders', $offset + $count),
                    'of'     => sprintf(_w('of %d'), $total_count),
                    'chunk'  => _w('%d order', '%d orders', max(0, min($total_count - ($offset + $count), $count))),
                ),
            )
            +
            (
                // extend info
                waRequest::get('counters') ? array('counters' => $this->getListCounters()) : array()
            )
        );
    }

    public function getCount()
    {
        if (waRequest::get('lt')) {
            return $this->getOrderOffset(waRequest::get('id', 0, waRequest::TYPE_INT));
        } else {
            return parent::getCount();
        }
    }

    public function getOffset()
    {
        if ($this->offset === null) {
            if (waRequest::get('lt')) {
                $this->offset = 0;
            } else {
                $offset = $this->getOrderOffset(waRequest::get('id', 0, waRequest::TYPE_INT));
                if ($offset === false) {
                    return false;
                }
                $this->offset = $offset + 1;
            }
        }
        return $this->offset;
    }

    /**
     * Get offset in list by ID
     * @param number $id
     * @return boolean|number If false than error occurred else int offset
     */
    private function getOrderOffset($id)
    {
        static $offset;
        if ($offset === null) {
            if (!$id) {
                return false;
            }
            $offset = $this->collection->getOrderOffset($id);
            if ($offset === false) {
                return false;
            }
        }
        return (int)$offset;
    }

    public function getListCounters()
    {
        $view = waRequest::get('view', '', waRequest::TYPE_STRING_TRIM);
        $counters = [];
        if ($view == 'kanban') {
            $workflow = new shopWorkflow();
            $available_states = $workflow->getAvailableStates();
            $filter_state_id = $this->getStateId();
            $conditions = explode('&', $this->getHash());
            foreach ($conditions as $key => $condition) {
                if (mb_strpos($condition, 'search/update_datetime') === 0 || mb_strpos($condition, 'state_id') === 0 || mb_strpos($condition, 'update_datetime') === 0) {
                    unset($conditions[$key]);
                }
            }
            $collection = new shopOrdersCollection(implode('&', $conditions));
            $counters['state_counters'] = array_fill_keys(array_keys($available_states), 0);
            if ($filter_state_id) {
                $available_states = array_intersect_key($available_states, array_flip($filter_state_id));
            }
            foreach ($available_states as $state_id => $state) {
                $temp_where = "o.state_id = '$state_id'";
                $collection->addWhere($temp_where);
                $counters['state_counters'][$state_id] = $collection->count(true);
                $collection->deleteTempWhere($temp_where);
            }
        } else {
            $counters['state_counters'] = $this->model->getStateCounters();
        }

        $pending_counters = intval(
            ifset($counters['state_counters']['new'], 0) +
            ifset($counters['state_counters']['auth'], 0) +
            ifset($counters['state_counters']['processing'], 0) +
            ifset($counters['state_counters']['paid'], 0)
        );
        $counters['common_counters'] = ['pending_counters' => $pending_counters];

        $prev_count_pending = waRequest::get('prev_pending', 0, waRequest::TYPE_INT);
        if ($pending_counters && $prev_count_pending !== $pending_counters) {
            /** @var shopConfig $config */
            $config = $this->getConfig();
            $counters['total_processing'] = wa_currency_html($this->model->getTotalSalesByInProcessingStates(), $config->getCurrency(), '%k{h}');
        }

        if ($view == 'split') {
            $counters['all_count'] = intval($this->model->countAll());
        }

        return $counters;
    }

    public function assign($data)
    {
        echo json_encode(array('status' => 'ok', 'data' => $data));
        exit;
    }

    public function setError($msg)
    {
        echo json_encode(array('status' => 'fail', 'errors' => array($msg)));
        exit;
    }
}
