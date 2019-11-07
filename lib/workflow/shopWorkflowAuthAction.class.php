<?php

class shopWorkflowAuthAction extends shopWorkflowAction
{
    public function execute($params = null)
    {
        $result = array(
            'update' => array(),
        );
        // from auth callback
        $order_id = $params['order_id'];
        if (isset($params['plugin'])) {
            $result['text'] = $params['plugin'].' (';
            if (!empty($params['view_data'])) {
                $result['text'] .= $params['view_data'].' - ';
            }
            $result['text'] .= $params['amount'].' '.$params['currency_id'].')';
            $result['update']['params'] = array(
                'payment_transaction_id' => $params['id'],
            );
        } else {
            if (isset($params['text'])) {
                $result['text'] = $params['text'];
            }
            if (isset($params['update'])) {
                $result['update'] = $params['update'];
            }
        }

        $order = $this->order_model->getById($order_id);

        $this->waLog('order_auth_callback', $order_id, $order['contact_id']);


        if (!$order['auth_date']) {
            $time = time();
            $result['update'] = array_merge(array(
                'auth_date' => date('Y-m-d', $time),
            ), $result['update']);

        }

        $fields = array('total', 'currency');
        $changes = array();
        foreach ($fields as $field) {
            if (isset($result['update'][$field]) && ($result['update'][$field] != $order[$field])) {
                $change = sprintf(_w('Field %s changed %s → %s'), $field, $order[$field], $result['update'][$field]);
                $changes[] = sprintf('<li>%s</li>', $change);
            }
        }
        if ($changes) {
            if (!isset($result['text'])) {
                $result['text'] = '';
            }
            $result['text'] .= '<ul class="menu-v">'.implode($changes).'</ul>';
        }

        return $result;
    }

    public function postExecute($order_id = null, $result = null)
    {
        if (is_array($order_id)) {
            $params = $order_id;
            $order_id = $params['order_id'];
        }
        return parent::postExecute($order_id, $result);
    }
}
