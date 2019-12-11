<?php

class shopWorkflowSettleAction extends shopWorkflowAction
{
    private $order_item;

    protected function init()
    {
        $this->state_id = 'deleted';
    }

    public function getDefaultOptions()
    {
        $options = parent::getDefaultOptions();
        $options['html'] = true;
        return $options;
    }

    public function execute($params = null)
    {
        if (is_array($params)) {
            $result = $params;

            $this->state_id = $params['state_id'];
        } else {
            $order_id = $params;
            $master_id = waRequest::post('master_id', null, waRequest::TYPE_INT);

            if (empty($master_id) && ('custom' == waRequest::post('master_id'))) {
                $master_id = waRequest::post('master_id_custom', null, waRequest::TYPE_INT);
            }

            if (empty($master_id)) {
                $master_id = $order_id;
            }

            if ($master_id != $order_id) {
                $text = sprintf(_w('Order was merged (settled) with %s'), $this->getOrderLink($master_id));
            } else {
                $text = null;
            }

            $result = array(
                'params' => array(
                    'workflow.settle_target_id' => $master_id,
                ),
                'text'   => $text,
                'update' => array(
                    'unsettled' => 0,
                ),
            );
        }
        return $result;
    }

    public function postExecute($params = null, $result = null)
    {
        if ($params != null) {
            if (is_array($params)) {
                $order_id = ifset($params['order_id']);
            } else {
                $order_id = $params;
            }
            if ($order_id) {
                $order = $this->order_model->getById($order_id);
            } else {
                $order = null;
            }

            $target_order_id = ifset($result['params']['workflow.settle_target_id']);
            if ($order && $target_order_id && ($order['id'] != $target_order_id)) {

                //TODO return error if order already deleted or cancelled
                $data = parent::postExecute($order, $result);

                if ($target_order_id) {
                    $this->waLog('order_delete', $order_id);

                    $text = sprintf(_w('Order was merged (settled) with %s'), $this->getOrderLink($order_id));
                    $text .= sprintf('(%s %s)', $order['total'], $order['currency']);

                    $update = array(
                        'total'    => $order['total'],
                        'currency' => $order['currency'],
                        'rate'     => $order['rate'],
                        'params'   => array(
                            'workflow.settle_parent_id' => $order_id,
                        ),
                    );
                    if (!empty($order['paid_date'])) {
                        $update['paid_year'] = $order['paid_year'];
                        $update['paid_quarter'] = $order['paid_quarter'];
                        $update['paid_month'] = $order['paid_month'];
                        $update['paid_date'] = $order['paid_date'];
                    }

                    if (!empty($order['auth_date'])) {
                        $update['auth_date'] = $order['auth_date'];
                    }

                    $params = array(
                        'order_id' => $target_order_id,
                        'update'   => $update,
                        'text'     => $text,
                    );

                    if ($order['state_id'] == 'paid') {
                        $this->workflow->getActionById('pay')->run($params);
                    } else {
                        $params['state_id'] = $order['state_id'];
                        $this->workflow->getActionById('settle')->run($params);
                    }

                    if ($order && $order['paid_date']) {
                        // Empty paid_date and update stats so that deleted orders do not affect reports
                        $this->order_model->updateById($order_id, array(
                            'paid_date'    => null,
                            'paid_year'    => null,
                            'paid_month'   => null,
                            'paid_quarter' => null,
                        ));

                        shopCustomer::recalculateTotalSpent($order['contact_id']);
                    }
                }
            } else {
                if ($target_order_id && ($order['id'] == $target_order_id)) {
                    $this->state_id = $order_id['state_id'];
                }
                if (!empty($result['update'])) {
                    $fields = array('total', 'currency');
                    $changes = array();
                    foreach ($fields as $field) {
                        if (isset($result['update'][$field]) && ($result['update'][$field] != $order[$field])) {
                            $change = sprintf(_w('%s was updated: %s → %s'), $field, $order[$field], $result['update'][$field]);
                            $changes[] = sprintf('<li>%s</li>', $change);
                        }
                    }
                    if ($changes) {
                        if (!isset($result['text'])) {
                            $result['text'] = '';
                        }
                        $result['text'] .= '<ul class="menu-v">'.implode($changes).'</ul>';
                    }
                }
                $data = parent::postExecute($order, $result);
            }
        } else {
            $data = null;
        }
        return $data;
    }

    public function isAvailable($order)
    {
        $this->order_item = $order;
        //TODO check
        return !empty($order['unsettled']);
    }

    public function getHTML($order_id)
    {
        $order_id = intval($order_id);

        $filter = array(
            'state_id!=completed||deleted||refunded||auth||paid',
            'id!='.$order_id,
            'unsettled=0',
            'params.workflow.settle_target_id=NULL',
            'params.workflow.settle_parent_id=NULL',
        );

        $hash = 'search/'.implode('&', $filter);

        if (true) { #filter by date
            $hash .= '&create_datetime>'.date('Y-m-d H:i:s', strtotime('-30 days'));
        }

        $collection = new shopOrdersCollection($hash);

        $order_by = array(
            'create_datetime' => 'DESC',
        );
        if ($this->order_item) {
            $total = $this->order_item['total'] * $this->order_item['rate'];
            $order_by[sprintf('amount:%f', $total)] = 'ASC';
            $collection->orderBy($order_by);
            $order = $this->order_item;
        }
        $unsettled_suggest_orders = $collection->getOrders('id,state,create_datetime,rate,total,currency,contact', 0, 5);
        shopHelper::workupOrders($unsettled_suggest_orders);


        $filter = http_build_query(compact('filter'));

        $order_by = http_build_query(compact('order_by'));

        $this->getView()->assign(compact('unsettled_suggest_orders', 'order', 'filter', 'order_by'));
        return parent::getHTML($order_id);
    }

    private function getOrderLink($id)
    {
        $id_str = htmlentities(shopHelper::encodeOrderId($id), ENT_QUOTES, 'utf-8');
        return sprintf('<a href="#/order/%d/" class="inline-link">%s</a>', $id, $id_str);
    }
}
