<?php

class shopWorkflowDeleteAction extends shopWorkflowAction
{
    public function execute($order_id = null)
    {
        $om = new shopOrderModel();
        $order = $om->getById($order_id);
        if ($order['paid_year']) {
            shopAffiliate::cancelBonus($order_id);
        }
        return true;
    }

    public function postExecute($order_id = null, $result = null) {
        $data = parent::postExecute($order_id, $result);
        if ($order_id != null) {
            $order_model = new shopOrderModel();
            $app_settings_model = new waAppSettingsModel();
            $update_on_create = $app_settings_model->get('shop', 'update_stock_count_on_create_order');
            if ($update_on_create) {
                $order_model->returnProductsToStocks($order_id);
            } else if (!$update_on_create && $data['before_state_id'] != 'new') {
                $order_model->returnProductsToStocks($order_id);
            }

        }
        return $data;
    }

    public function getButton()
    {
        return parent::getButton('data-confirm="'._w('This order will be cancelled. Are you sure?').'"');
    }
}