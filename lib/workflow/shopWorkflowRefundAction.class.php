<?php

class shopWorkflowRefundAction extends shopWorkflowAction
{
    public function postExecute($order_id = null, $result = null)
    {
        $data = parent::postExecute($order_id, $result);

        $order_model = new shopOrderModel();
        if (is_array($order_id)) {
            $order = $order_id;
            $order_id = $order['id'];
        } else {
            $order = $order_model->getById($order_id);
        }

        shopCustomer::recalculateTotalSpent($order['contact_id']);

        if ($order_id != null) {
            $log_model = new waLogModel();
            $log_model->add('order_refund', $order_id);
            $order_model = new shopOrderModel();
            $order_model->updateById($order_id, array(
                'paid_date' => null,
                'paid_year' => null,
                'paid_month' => null,
                'paid_quarter' => null,
            ));

            // for logging changes in stocks
            shopProductStocksLogModel::setContext(
                    shopProductStocksLogModel::TYPE_ORDER,
                    'Order %s was refunded',
                    array(
                        'order_id' => $order_id
                    )
            );

            $order_model->returnProductsToStocks($order_id);
            shopAffiliate::refundDiscount($order);
            shopAffiliate::cancelBonus($order);
            $order_model->recalculateProductsTotalSales($order_id);
        }

        return $data;
    }
}
