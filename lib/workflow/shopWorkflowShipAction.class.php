<?php

class shopWorkflowShipAction extends shopWorkflowEditshippingdetailsAction
{
    public function postExecute($params = null, $result = null)
    {
        if (is_array($params)) {
            $order_id = $params['order_id'];
        } else {
            $order_id = $params;
        }

        $data = shopWorkflowAction::postExecute($order_id, $result);

        $this->waLog('order_ship', $order_id);


        $app_settings_model = new waAppSettingsModel();
        if (!$app_settings_model->get('shop', 'disable_stock_count')) {
            // for logging changes in stocks
            shopProductStocksLogModel::setContext(
                shopProductStocksLogModel::TYPE_ORDER,
                'Order %s was shipped',
                array('order_id' => $order_id)
            );

            $this->order_model->reduceProductsFromStocks($order_id);

            shopProductStocksLogModel::clearContext();
        }

        $params = array(
            'shipping_data' => waRequest::post('shipping_data'),
            'log'           => true,
        );
        $this->setPackageState(waShipping::STATE_READY, $order_id, $params);


        return $data;
    }

    public function getButton()
    {
        // Cancel special behaviour of Editshippingdetails action
        return shopWorkflowAction::getButton();
    }

    public function getHTML($order_id)
    {
        $controls = $this->getShippingFields($order_id, waShipping::STATE_READY);
        $this->getView()->assign('shipping_controls', $controls);
        return parent::getHTML($order_id);
    }
}
