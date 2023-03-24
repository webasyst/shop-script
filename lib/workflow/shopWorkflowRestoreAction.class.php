<?php

class shopWorkflowRestoreAction extends shopWorkflowAction
{
    public function execute($order_id = null)
    {
        // Restore previous state
        $params = array();
        $this->state_id = $this->order_log_model->getPreviousState($order_id, $params);
        shopAffiliate::reapplyDiscount($order_id);
        // Restore order.paid_*, order.auth_date, customer.total_spent and customer.affiliation_bonus
        $update = array();

        if ($paid_date = ifset($params['paid_date'])) {
            $t = strtotime($paid_date);
            $paid_datetime = null;
            if (isset($params['paid_datetime'])) {
                $paid_datetime = date('Y-m-d H:i:s', strtotime($params['paid_datetime']));
            }
            $update += array(
                'paid_year'     => date('Y', $t),
                'paid_quarter'  => floor((date('n', $t) - 1) / 3) + 1,
                'paid_month'    => date('n', $t),
                'paid_date'     => date('Y-m-d', $t),
                'paid_datetime' => $paid_datetime,
            );
        }

        if ($auth_date = ifset($params['auth_date'])) {
            $t = strtotime($auth_date);
            $update += array(
                'auth_date' => date('Y-m-d', $t),
            );
        }

        return $update ? compact('update') : true;
    }

    public function postExecute($order_id = null, $result = null)
    {
        $data = parent::postExecute($order_id, $result);

        if ($order_id != null) {

            $this->waLog('order_restore', $order_id);

            if ($this->state_id != 'refunded') {

                // for logging changes in stocks
                shopProductStocksLogModel::setContext(
                    shopProductStocksLogModel::TYPE_ORDER,
                    'Order %s was restored',
                    array(
                        'order_id' => $order_id
                    )
                );

                // Check was reducing in past?
                // If yes, it means that order has been deleted (and stock returned)
                // and so it's needed to reduce again
                if ($this->order_params_model->getReduceTimes($order_id) > 0) {
                    $this->order_model->reduceProductsFromStocks($order_id);
                }

                shopProductStocksLogModel::clearContext();
                
            }

            $order = $this->order_model->getById($order_id);
            if ($order && $order['paid_date']) {
                shopAffiliate::applyBonus($order_id);
                shopCustomer::recalculateTotalSpent($order['contact_id']);
            }

            $this->setPackageState(waShipping::STATE_DRAFT, $order_id, array('log' => true));
        }
        return $data;
    }
}
