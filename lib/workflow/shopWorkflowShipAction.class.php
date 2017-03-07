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

        $log_model = new waLogModel();
        $log_model->add('order_ship', $order_id);


        // for logging changes in stocks
        shopProductStocksLogModel::setContext(
            shopProductStocksLogModel::TYPE_ORDER,
            'Order %s was shipped',
            array('order_id' => $order_id)
        );

        $order_model = new shopOrderModel();
        $order_model->reduceProductsFromStocks($order_id);

        shopProductStocksLogModel::clearContext();

        return $data;
    }

    public function getButton()
    {
        // Cancel special behaviour of Editshippingdetails action
        return shopWorkflowAction::getButton();
    }
}
