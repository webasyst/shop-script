<?php

class shopOrdersPerformActionController extends waJsonController
{
    public function execute()
    {
        $action_id = waRequest::get('id', null, waRequest::TYPE_STRING_TRIM);
        if (!$action_id) {
            throw new waException('No action id given.');
        }

        $order_ids = waRequest::post('order_id', null, waRequest::TYPE_ARRAY_INT);
        if ($order_ids !== null) {
            $this->performAction($action_id, $order_ids);
        } else {
            $order_model = new shopOrderModel();

            $options = array();
            $filter_params = waRequest::post('filter_params', null);

            if ($filter_params !== null) {
                $total_count = 0;
                if ($filter_params) {
                    $options['where'] = $filter_params;
                    $total_count = $order_model->countByField($filter_params);
                } else {
                    $total_count = $order_model->countAll();
                }
                $limit = 100;
                for ($offset = 0; $offset < $total_count; $offset += $limit) {
                    $options['offset'] = $offset;
                    $options['limit'] = $limit;
                    $this->performAction($action_id, array_keys($order_model->getList('id', $options)));
                }
            }
        }
    }

    public function performAction($action_id, $order_ids)
    {
        foreach ($order_ids as $order_id) {
        }
    }
}