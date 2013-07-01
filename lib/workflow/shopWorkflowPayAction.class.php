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
            $order_params_model = new shopOrderParamsModel();
            $order_params_model->insert(array(
                'order_id' => $order_id,
                'name' => 'payment_transaction_id',
                'value' => $params['id']
            ));
        } else {
            $order_id = $params;
            $result['text'] = waRequest::post('text', '');
        }
        $order_model = new shopOrderModel();
        $order = $order_model->getById($order_id);
        if (!$order['paid_year']) {
            shopAffiliate::applyBonus($order_id);
            $result['update']= array(
                    'paid_year' => date('Y'),
                    'paid_quarter' => floor((date('n') - 1) / 3) + 1,
                    'paid_month' => date('n'),
                    'paid_date' => date('Y-m-d'),
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
        parent::postExecute($order_id, $result);

        $order_model = new shopOrderModel();
        if (is_array($order_id)) {
            $order = $order_id;
            $order_id = $order['id'];
        } else {
            $order = $order_model->getById($order_id);
        }

        shopCustomers::recalculateTotalSpent($order['contact_id']);
        if ($order !== null) {
            $order_model->recalculateProductsTotalSales($order_id);
        }

        $log_model = new shopOrderLogModel();
        $state_id = $log_model->getPreviousState($order_id);

        $app_settings_model = new waAppSettingsModel();
        $update_on_create   = $app_settings_model->get('shop', 'update_stock_count_on_create_order');

        if (!$update_on_create && $state_id == 'new') {
            // jump through 'processing' state - reduce
            $order_model = new shopOrderModel();
            $order_model->reduceProductsFromStocks($order_id);
        }
    }
}

