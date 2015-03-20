<?php

class shopWorkflowCompleteAction extends shopWorkflowAction
{
    public function execute($order_id = null)
    {
        $order_model = new shopOrderModel();
        $order = $order_model->getById($order_id);
        if ($order['paid_year']) {
            return true;
        } else {
            if (wa('shop')->getConfig()->getOption('order_paid_date') == 'create') {
                $time = strtotime($order['create_datetime']);
            } else {
                $time = time();
            }
            shopAffiliate::applyBonus($order_id);
            $result = array(
                'update' => array(
                    'paid_year' => date('Y', $time),
                    'paid_quarter' => floor((date('n', $time) - 1) / 3) + 1,
                    'paid_month' => date('n', $time),
                    'paid_date' => date('Y-m-d', $time),
                )
            );
            if (!$order_model->where("contact_id = ? AND paid_date IS NOT NULL", $order['contact_id'])->limit(1)->fetch()) {
                $result['update']['is_first'] = 1;
            }
            return $result;
        }
    }

    public function postExecute($order_id = null, $result = null)
    {
        $data = parent::postExecute($order_id, $result);

        $log_model = new waLogModel();
        $log_model->add('order_complete', $order_id);

        $order_model = new shopOrderModel();
        if (is_array($order_id)) {
            $order = $order_id;
            $order_id = $order['id'];
        } else {
            $order = $order_model->getById($order_id);
        }

        shopCustomer::recalculateTotalSpent($order['contact_id']);
        if ($order !== null) {

            $log_model = new shopOrderLogModel();
            $state_id = $log_model->getPreviousState($order_id);

            $app_settings_model = new waAppSettingsModel();
            $update_on_create   = $app_settings_model->get('shop', 'update_stock_count_on_create_order');

            if (!$update_on_create && $state_id == 'new') {
                // jump through 'processing' state - reduce
                
                // for logging changes in stocks
                shopProductStocksLogModel::setContext(
                        shopProductStocksLogModel::TYPE_ORDER,
                        /*_w*/('Order %s was completed'),
                        array(
                            'order_id' => $order_id
                        )
                );
                
                $order_model = new shopOrderModel();
                $order_model->reduceProductsFromStocks($order_id);
                
                shopProductStocksLogModel::clearContext();
            }

            $order_model->recalculateProductsTotalSales($order_id);
        }
        return $data;
    }
}
