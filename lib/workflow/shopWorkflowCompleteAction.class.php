<?php

class shopWorkflowCompleteAction extends shopWorkflowAction
{
    public function execute($order_id = null)
    {
        $order = $this->getOrder($order_id);

        // update orders counter of a courier this order is assigned to
        $params = $this->order_params_model->get($order_id);
        $courier_id = ifset($params['courier_id'], '');
        if ($courier_id) {
            $courier_model = new shopApiCourierModel();
            $courier_model->incrOrdersProcessed($courier_id);
        }

        if ($order['paid_year']) {
            return true;
        } else {
            if ($this->getConfig()->getOption('order_paid_date') == 'create') {
                $time = strtotime($order['create_datetime']);
            } else {
                $time = time();
            }
            shopAffiliate::applyBonus($order_id);
            $result = array(
                'update' => array(
                    'paid_year'     => date('Y', $time),
                    'paid_quarter'  => floor((date('n', $time) - 1) / 3) + 1,
                    'paid_month'    => date('n', $time),
                    'paid_date'     => date('Y-m-d', $time),
                    'paid_datetime' => date('Y-m-d H:i:s', $time),
                )
            );
            if (!$this->order_model->where("contact_id = ? AND paid_date IS NOT NULL", $order['contact_id'])->limit(1)->fetch()) {
                $result['update']['is_first'] = 1;
            }
            return $result;
        }
    }

    public function postExecute($order_id = null, $result = null)
    {
        $data = parent::postExecute($order_id, $result);

        $this->waLog('order_complete', $order_id);

        $order = $this->getOrder($order_id);

        shopCustomer::recalculateTotalSpent($order['contact_id']);
        if ($order !== null) {

            $state_id = $this->order_log_model->getPreviousState($order_id);

            $app_settings_model = new waAppSettingsModel();
            $update_on_create = $app_settings_model->get('shop', 'update_stock_count_on_create_order');

            if (!$app_settings_model->get('shop', 'disable_stock_count') && !$update_on_create && $state_id == 'new') {
                // jump through 'processing' state - reduce

                // for logging changes in stocks
                shopProductStocksLogModel::setContext(
                    shopProductStocksLogModel::TYPE_ORDER,
                    /*_w*/('Order %s was completed'),
                    array(
                        'order_id' => $order_id
                    )
                );

                $this->order_model->reduceProductsFromStocks($order_id);

                shopProductStocksLogModel::clearContext();
            }

            $this->order_model->recalculateProductsTotalSales($order_id);
        }
        return $data;
    }
}
