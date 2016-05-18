<?php

class shopOrderTrackingController extends waJsonController
{
    public function execute()
    {
        $model = new shopOrderParamsModel();
        $fields = array(
            'order_id' => waRequest::get('order_id'),
            'name'     => array('shipping_id', 'tracking_number'),
        );

        $params = $model->getByField($fields, 'name');

        foreach ($params as &$param) {
            $param = $param['value'];
        }

        $this->response['params'] = $params;
        $tracking = '';
        if (!empty($params['shipping_id'])) {
            try {
                $plugin = shopShipping::getPlugin(null, $params['shipping_id']);
                if (!empty($params['tracking_number'])) {
                    $tracking = $plugin->tracking($params['tracking_number']);
                }
            } catch (waException $ex) {
                $tracking = $ex->getMessage();
            }
        }
        $this->response['tracking'] = $tracking;
    }
}
