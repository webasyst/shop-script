<?php

class shopOrdersLoadListController extends shopOrderListAction
{
    private $errors = array();
    private $offset;

    public function execute()
    {
        // id is order id
        if (!waRequest::get('id', 0, waRequest::TYPE_INT)) {
            $this->assign(waRequest::get('counters') ? array('counters' => $this->getListCounters()) : array());
        }

        // if 'id' is passed

        $offset = $this->getOffset();
        if ($offset === false) {
            $this->setError(_w("Unkown offset"));
        }
        $count = $this->getCount();
        if ($count === false) {
            $this->setError(_w("Unkown count"));
        }

        $total_count = $this->getTotalCount();
        $orders = $this->getOrders($offset, $count);

        $count = count($orders);

        // set response
        $this->assign(
            // basic info
            array(
                'orders' => array_values($orders),
                'total_count' => $total_count,
                'current_offset' => $offset,
                'count' => $count,
                'loaded' => $offset + $count,
                'progress' => array(
                    'loaded' => _w('%d order','%d orders', $offset + $count),
                    'of' => sprintf(_w('of %d'), $total_count),
                    'chunk' => _w('%d order','%d orders', max(0, min($total_count - ($offset + $count), $count))),
                )
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
     * @return boolean|number If false than error occured else int offset
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
        return array(
            'state_counters'   => $this->model->getStateCounters()
        );
    }

    public function assign($data)
    {
        echo json_encode(array('status' => 'ok', 'data' => $data)); exit;
    }

    public function setError($msg)
    {
        echo json_encode(array('status' => 'fail', 'errors' => array($msg))); exit;
    }
}