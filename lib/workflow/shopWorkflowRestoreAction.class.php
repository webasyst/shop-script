<?php

class shopWorkflowRestoreAction extends shopWorkflowAction
{
    public function execute($oder_id = null)
    {
        // Restore previous state
        $log_model = new shopOrderLogModel();
        $params = array();
        $this->state_id = $log_model->getPreviousState($oder_id, $params);

        // Restore order.paid_*, customer.total_spent and customer.affiliation_bonus
        $paid_date = ifset($params['paid_date']);
        if ($paid_date) {
            $t = strtotime($paid_date);
            $result['update'] = array(
                    'paid_year' => date('Y', $t),
                    'paid_quarter' => floor((date('n', $t) - 1) / 3) + 1,
                    'paid_month' => date('n', $t),
                    'paid_date' => date('Y-m-d', $t),
            );
            return $result;
        }

        return true;
    }

    public function postExecute($order_id = null, $result = null)
    {
        $data = parent::postExecute($order_id, $result);

        if ($order_id != null) {

            $log_model = new waLogModel();
            $log_model->add('order_restore', $order_id);

            $order_model = new shopOrderModel();
            $app_settings_model = new waAppSettingsModel();

            if ($this->state_id != 'refunded') {
                
                // for logging changes in stocks
                shopProductStocksLogModel::setContext(
                        shopProductStocksLogModel::TYPE_ORDER,
                        'Order %s was restored',
                        array(
                            'order_id' => $order_id
                        )
                );
                
                $update_on_create = $app_settings_model->get('shop', 'update_stock_count_on_create_order');
                if ($update_on_create) {
                    $order_model->reduceProductsFromStocks($order_id);
                } else if (!$update_on_create && $this->state_id != 'new') {
                    $order_model->reduceProductsFromStocks($order_id);
                }
                
                shopProductStocksLogModel::clearContext();
                
            }

            $order = $order_model->getById($order_id);
            if ($order && $order['paid_date']) {
                shopAffiliate::applyBonus($order_id);
                shopCustomer::recalculateTotalSpent($order['contact_id']);
            }
        }
        return $data;
    }
}
