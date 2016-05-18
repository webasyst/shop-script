<?php

class shopOrderSettleController extends waJsonController
{
    public function execute()
    {
        $id = waRequest::post('id', null, waRequest::TYPE_INT);
        $master_id = waRequest::post('master_id', null, waRequest::TYPE_INT);

        if (empty($master_id) && ('custom' == waRequest::post('master_id'))) {
            $master_id = waRequest::post('master_id_custom', null, waRequest::TYPE_INT);
        }

        if ($id && $master_id) {
            $order_model = new shopOrderModel();
            $orders = $order_model->getById(array($id, $master_id));
            $order = ifset($orders[$id]);
            $master_order = ifset($orders[$master_id]);
            unset($orders);

            if ($order && !empty($order['unsettled']) && !empty($master_order)) {
                $workflow = new shopWorkflow();

                $fields = array(
                    'total',
                    'currency',//XXX check and convert it!!!
                    'rate',
                );

                $changes = array();
                $logs = array();

                $pay = false;

                if (empty($master_order['paid_date'])
                    && ($master_order['state_id'] != 'refunded')
                ) {
                    $fields = array_merge($fields, array(
                        'paid_date',
                        'paid_year',
                        'paid_quarter',
                        'paid_month',
                    ));
                    $pay = true;
                }

                $update_order = array();
                foreach ($fields as $field) {
                    if (($master_order[$field] != $order[$field])
                        && (strpos($field, 'paid_') !== 0)
                    ) {
                        $change = sprintf(_w('Field %s changed %s → %s'), $field, $master_order[$field], $order[$field]);
                        $changes[] = sprintf('<li>%s</li>', $change);
                    }
                    $update_order[$field] = $order[$field];
                }
                $order_model->updateById($master_id, $update_order);

                $id_str = htmlentities(shopHelper::encodeOrderId($id), ENT_QUOTES, 'utf-8');

                $link = sprintf('<a href="#/orders/state_id=deleted&id=%d" class="inline-link">%s</a>', $id, $id_str);
                $text = sprintf(_w('Order was updated via merge by order %s'), $link);
                if ($changes) {
                    $text .= '<ul class="menu-v">'.implode($changes).'</ul>';
                }
                $logs[] = array(
                    'order_id'        => $master_id,
                    'action_id'       => '',
                    'before_state_id' => $master_order['state_id'],
                    'after_state_id'  => $master_order['state_id'],
                    'text'            => $text,
                    'params'          => array('unsettled_order_id' => $id),
                );

                //copy new order params
                $params_model = new shopOrderParamsModel();
                $params = $params_model->getByField('order_id', $id, true);

                $params[] = array(
                    'order_id' => $id,
                    'name'     => 'merged_order_id',
                    'value'    => $master_id,
                );

                $params[] = array(
                    'order_id' => $master_id,
                    'name'     => 'unsettled_order_id',
                    'value'    => $id,
                );
                foreach ($params as $param) {
                    $param['order_id'] = $master_id;
                    $params_model->insert($param, waModel::INSERT_IGNORE);
                }

                $master_id_str = htmlentities(shopHelper::encodeOrderId($master_id), ENT_QUOTES, 'utf-8');
                $link = sprintf('<a href="#/orders/state_id=paid&id=%d" class="inline-link">%s</a>', $master_id, $master_id_str);

                $text = sprintf(_w('Order data was merged into %s'), $link);
                $logs[] = array(
                    'order_id'        => $id,
                    'action_id'       => '',
                    'before_state_id' => $order['state_id'],
                    'after_state_id'  => $order['state_id'],
                    'text'            => $text,
                    'params'          => array('merged_order_id' => $master_id),
                );

                #add log records
                $log_model = new shopOrderLogModel();
                foreach ($logs as $log) {
                    $log_model->add($log);
                }

                $workflow->getActionById('delete')->run($id);
                $order_model->updateById($id, array('unsettled' => 0));
                if ($pay) {
                    $workflow->getActionById('pay')->run($master_id);
                }

                $this->redirect(sprintf('./#/orders/state=paid&id=%d/', $master_id));
            } else {
                $this->redirect(sprintf('./#/orders/id=%d/', $id));
            }
        } else {
            throw new waException(_w('Order not found'), 404);
        }
    }
}
