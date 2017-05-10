<?php

class shopWorkflowRefundAction extends shopWorkflowAction
{
    public function postExecute($order_id = null, $result = null)
    {
        $data = parent::postExecute($order_id, $result);

        $order = $this->getOrder($order_id);

        if ($order_id != null) {
            $this->waLog('order_refund', $order_id);
            $this->order_model->updateById($order_id, array(
                'paid_date'    => null,
                'paid_year'    => null,
                'paid_month'   => null,
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

            // refund, so return
            $this->order_model->returnProductsToStocks($order_id);

            shopAffiliate::refundDiscount($order);
            shopAffiliate::cancelBonus($order);
            $this->order_model->recalculateProductsTotalSales($order_id);
            shopCustomer::recalculateTotalSpent($order['contact_id']);

            $params = array(
                'shipping_data' => waRequest::post('shipping_data'),
                'log'           => true,
            );
            $this->setPackageState(waShipping::STATE_CANCELED, $order, $params);
        }

        return $data;
    }

    public function getHTML($order_id)
    {
        if ($controls = $this->getShippingFields($order_id, waShipping::STATE_CANCELED)) {
            $this->getView()->assign('shipping_controls', $controls);
            $this->setOption('html', true);
        }
        return parent::getHTML($order_id);
    }
}
