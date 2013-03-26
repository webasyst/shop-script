<?php

class shopWorkflowRefundAction extends shopWorkflowAction
{
    public function postExecute($order_id = null, $result = null)
    {
        parent::postExecute($order_id, $result);

        $order_model = new shopOrderModel();
        if (is_array($order_id)) {
            $order = $order_id;
            $order_id = $order['id'];
        } else {
            $order = $order_model->getById($order_id);
        }

        shopCustomers::recalculateTotalSpent($order['contact_id']);

        if ($order_id != null) {
            $order_model = new shopOrderModel();
            $order_model->updateById($order_id, array(
                'paid_date' => null,
                'paid_year' => null,
                'paid_month' => null,
                'paid_quarter' => null,
            ));
            $order_model->returnProductsToStocks($order_id);
            shopAffiliate::cancelBonus($order_id);
            $order_model->recalculateProductsTotalSales($order_id);
        }
    }
}
