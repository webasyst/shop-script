<?php

class shopWorkflowPayAction extends shopWorkflowAction
{

    public function getDefaultOptions()
    {
        $options = parent::getDefaultOptions();
        $options['html'] = true;
        return $options;
    }

    public function execute($params = null)
    {
        $result = array();
        // from payment callback
        if (is_array($params)) {
            $order_id = $params['order_id'];
            $result['text'] = $params['plugin'].' ('.$params['view_data'].' - '.$params['amount'].' '.$params['currency_id'].')';
            $result['update']['params'] = array(
                'payment_transaction_id' => $params['id'],
            );
        } else {
            $order_id = $params;
            $result['text'] = nl2br(htmlspecialchars(waRequest::post('text', '')));
        }
        $order_model = new shopOrderModel();
        $order = $order_model->getById($order_id);

        $log_model = new waLogModel();
        if (wa()->getEnv() == 'backend') {
            $log_model->add('order_pay', $order_id);
        } else {
            $log_model->add('order_pay_callback', $order_id, $order['contact_id']);
        }

        if (!$order['paid_year']) {
            shopAffiliate::applyBonus($order_id);
            if (wa('shop')->getConfig()->getOption('order_paid_date') == 'create') {
                $time = strtotime($order['create_datetime']);
            } else {
                $time = time();
            }
            $result['update'] = array(
                    'paid_year' => date('Y', $time),
                    'paid_quarter' => floor((date('n', $time) - 1) / 3) + 1,
                    'paid_month' => date('n', $time),
                    'paid_date' => date('Y-m-d', $time),
            );
            if (!$order_model->where("contact_id = ? AND paid_date IS NOT NULL", $order['contact_id'])->limit(1)->fetch()) {
                $result['update']['is_first'] = 1;
            }
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
        $data = parent::postExecute($order_id, $result);

        $order_model = new shopOrderModel();
        if (is_array($order_id)) {
            $order = $order_id;
            $order_id = $order['id'];
        } else {
            $order = $order_model->getById($order_id);
        }

        shopCustomer::recalculateTotalSpent($order['contact_id']);
        if ($order !== null) {
            $order_model->recalculateProductsTotalSales($order_id);
        }

        $log_model = new shopOrderLogModel();
        $state_id = $log_model->getPreviousState($order_id);

        $app_settings_model = new waAppSettingsModel();
        $update_on_create   = $app_settings_model->get('shop', 'update_stock_count_on_create_order');

        if (!$update_on_create && $state_id == 'new') {
            
            // for logging changes in stocks
            shopProductStocksLogModel::setContext(
                    shopProductStocksLogModel::TYPE_ORDER,
                    _w('Order %s was paid'),
                    array(
                        'order_id' => $order_id
                    )
            );
            
            // jump through 'processing' state - reduce
            $order_model = new shopOrderModel();
            $order_model->reduceProductsFromStocks($order_id);
            
            shopProductStocksLogModel::clearContext();
            
        }

        return $data;
    }
}

