<?php

class shopWorkflowCallbackAction extends shopWorkflowAction
{
    public function execute($params = null)
    {
        $result = array();
        $data = empty($params['view_data']) ? '' : ($params['view_data'].' - ');
        $result['text'] = $params['plugin'].' '.$params['state'].' ('.$data.$params['amount'].' '.$params['currency_id'].')';
        $result['params'] = array();
        if (isset($params['id'])) {
            $result['params']['payment_transaction_id'] = $params['id'];
        }

        return $result;
    }

    public function postExecute($params = null, $result = null)
    {
        if (is_array($params)) {
            $order_id = $params['order_id'];
        } else {
            $order_id = $params;
        }
        return parent::postExecute($order_id, $result);
    }
}