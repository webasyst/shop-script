<?php

class shopWorkflowRestoreAction extends shopWorkflowAction
{
    public function execute($oder_id = null)
    {
        $log_model = new shopOrderLogModel();
        $this->state_id = $log_model->getPreviousState($oder_id);

        $om = new shopOrderModel();
        $order = $om->getById($oder_id);
        if ($order['paid_year']) {
            shopAffiliate::applyBonus($order_id);
        }

        return true;
    }

    public function postExecute($order_id = null, $result = null) {
        parent::postExecute($order_id, $result);

        if ($order_id != null) {
            $order_model = new shopOrderModel();
            $app_settings_model = new waAppSettingsModel();
            $update_on_create = $app_settings_model->get('shop', 'update_stock_count_on_create_order');
            if ($update_on_create) {
                $order_model->reduceProductsFromStocks($order_id);
            } else if (!$update_on_create && $this->state_id != 'new') {
                $order_model->reduceProductsFromStocks($order_id);
            }
        }
    }
}