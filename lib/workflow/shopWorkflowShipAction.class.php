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
        $text = array();
        $params = array();

        if ( ( $tracking = waRequest::post('tracking_number', waRequest::param('tracking_number', '', waRequest::TYPE_STRING), 'string'))) {
            $text[] = _w('Tracking number').': '.htmlspecialchars($tracking);
            $params['tracking_number'] = $tracking;
        }
        if ( ( $courier_id = waRequest::post('courier_id', waRequest::param('courier_id', null, waRequest::TYPE_INT), 'int'))) {
            $courier_model = new shopApiCourierModel();
            $courier = $courier_model->getById($courier_id);
            if ($courier) {
                $text[] = _w('Courier').': '.htmlspecialchars(ifempty($courier['name'], '('.$courier_id.')'));
                $params['courier_id'] = $courier_id;
            }
        }

        if ($text || $params) {
            return array(
                'text' => join("\n", $text),
                'params' => $params,
                'update' => array(
                    'params' => $params,
                ),
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

    public function getHTML($order_id)
    {
        $order_params_model = new shopOrderParamsModel();
        $params = $order_params_model->get($order_id);
        $storefront = ifset($params['storefront'], '');
        if ($storefront) {
            $storefront = rtrim($storefront, '/*');
            if (false !== strpos($storefront, '/')) {
                $storefront .= '/';
            }
        }

        $courier_model = new shopApiCourierModel();
        $all_couriers = $couriers = $courier_model->getEnabled();
        if ($storefront) {
            $couriers = $courier_model->getByStorefront($storefront, $couriers);
        }

        $this->getView()->assign(array(
            'other_couriers_exist' => count($all_couriers) > count($couriers),
            'storefront' => $storefront,
            'couriers' => $couriers,
        ));
        return parent::getHTML($order_id);
    }
}
