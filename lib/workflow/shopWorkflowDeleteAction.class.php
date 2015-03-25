<?php

class shopWorkflowDeleteAction extends shopWorkflowAction
{
    public function execute($order_id = null)
    {
        $om = new shopOrderModel();
        $order = $om->getById($order_id);
        shopAffiliate::refundDiscount($order);
        if ($order['paid_year']) {
            shopAffiliate::cancelBonus($order);
        }
        return true;
    }

    public function postExecute($order_id = null, $result = null)
    {
        $data = parent::postExecute($order_id, $result);
        if ($order_id != null) {

            $log_model = new waLogModel();
            $log_model->add('order_delete', $order_id);

            $order_model = new shopOrderModel();
            $app_settings_model = new waAppSettingsModel();

            if ($data['before_state_id'] != 'refunded') {
                $update_on_create = $app_settings_model->get('shop', 'update_stock_count_on_create_order');
                
                // for logging changes in stocks
                shopProductStocksLogModel::setContext(
                        shopProductStocksLogModel::TYPE_ORDER,
                        /*_w*/('Order %s was deleted'),
                        array(
                            'order_id' => $order_id
                        )
                );
                
                if ($update_on_create) {
                    $order_model->returnProductsToStocks($order_id);
                } else if (!$update_on_create && $data['before_state_id'] != 'new') {
                    $order_model->returnProductsToStocks($order_id);
                }
                
                shopProductStocksLogModel::clearContext();
                
            }

            $order = $order_model->getById($order_id);
            if ($order && $order['paid_date']) {
                // Remember paid_date in log params for Restore action
                $olpm = new shopOrderLogParamsModel();
                $olpm->insert(array(
                    'name' => 'paid_date',
                    'value' => $order['paid_date'],
                    'order_id' => $order_id,
                    'log_id' => $data['id'],
                ));

                // Empty paid_date and update stats so that deleted orders do not affect reports
                $order_model->updateById($order_id, array(
                    'paid_date' => null,
                    'paid_year' => null,
                    'paid_month' => null,
                    'paid_quarter' => null,
                ));
                $order_model->recalculateProductsTotalSales($order_id);
                shopCustomer::recalculateTotalSpent($order['contact_id']);
            }
        }
        return $data;
    }

    public function getButton()
    {
        return parent::getButton('data-confirm="'._w('This order will be cancelled. Are you sure?').'"');
    }
}
