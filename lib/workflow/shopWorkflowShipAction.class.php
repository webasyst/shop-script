<?php

class shopWorkflowShipAction extends shopWorkflowAction
{
    public function getDefaultOptions()
    {
        $options = parent::getDefaultOptions();
        $options['html'] = true;
        return $options;
    }

    public function execute($params = null)
    {
        if ($tracking = waRequest::post('tracking_number')) {
            return array(
                'text' => 'Tracking Number: '.$tracking,
                'params' => array(
                    'tracking_number' => $tracking
                ),
                'update' => array(
                    'params' => array(
                        'tracking_number' => $tracking
                    )
                )
            );
        } else {
            return true;
        }
    }

    public function postExecute($params = null, $result = null)
    {
        if (is_array($params)) {
            $order_id = $params['order_id'];
        } else {
            $order_id = $params;
        }
        $data = parent::postExecute($order_id, $result);

        $log_model = new waLogModel();
        $log_model->add('order_ship', $order_id);

        $log_model = new shopOrderLogModel();
        $state_id = $log_model->getPreviousState($order_id);

        $app_settings_model = new waAppSettingsModel();
        $update_on_create   = $app_settings_model->get('shop', 'update_stock_count_on_create_order');

        if (!$update_on_create && $state_id == 'new') {
            
            // for logging changes in stocks
            shopProductStocksLogModel::setContext(
                    shopProductStocksLogModel::TYPE_ORDER,
                    'Order %s was shipped',
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
