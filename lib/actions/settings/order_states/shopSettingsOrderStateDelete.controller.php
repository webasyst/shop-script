<?php

class shopSettingsOrderStateDeleteController extends waJsonController
{
    public function execute()
    {
        $id = waRequest::post('id');
        if (!$id) {
            $this->errors = _w("Unknown state");
            return;
        }

        $order_model = new shopOrderModel();
        if ($order_model->countByField('state_id', $id)) {
            $this->errors = _w("Cannot delete order status while there are active orders in this status");
            return;
        }

        $config = shopWorkflow::getConfig();
        if (isset($config['states'][$id])) {
            unset($config['states'][$id]);
        }
        shopWorkflow::setConfig($config);
    }
}