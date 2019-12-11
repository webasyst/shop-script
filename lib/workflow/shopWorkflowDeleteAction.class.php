<?php

class shopWorkflowDeleteAction extends shopWorkflowAction
{
    public function execute($order_id = null)
    {
        $order = $this->getOrder($order_id);
        shopAffiliate::refundDiscount($order);
        if ($order['paid_year']) {
            shopAffiliate::cancelBonus($order);
        }


        $return_stock = ifset($options, 'action_options', 'return_stock', waRequest::post('return_stock'));
        if ($return_stock) {
            $result = array(
                'params' => array(
                    'return_stock' => $return_stock,
                ),
            );

        } else {
            $result = true;
        }


        $text = nl2br(htmlspecialchars(trim(waRequest::post('text', '')), ENT_QUOTES, 'utf-8'));
        if (strlen($text)) {
            if (!is_array($result)) {
                $result = array();
            }
            $result['text'] = $text;
        }

        return $result;
    }

    public function postExecute($order_id = null, $result = null)
    {
        $data = parent::postExecute($order_id, $result);
        if ($data && ($order_id != null)) {

            $this->waLog('order_delete', $order_id);

            if ($data['before_state_id'] != 'refunded') {
                // for logging changes in stocks
                shopProductStocksLogModel::setContext(
                    shopProductStocksLogModel::TYPE_ORDER,
                    /*_w*/ ('Order %s was deleted'),
                    array(
                        'order_id' => $order_id,
                        'return_stock_id' => ifempty($result, 'params', 'return_stock', null),
                    )
                );

                // was reducing in past?
                $reduced = $this->order_params_model->getOne($order_id, 'reduced');
                if ($reduced) {
                    $return_stock = $this->order_model->returnProductsToStocks($order_id);
                    if (empty($return_stock)) {
                        unset($data['params']['return_stock']);
                    }
                }

                shopProductStocksLogModel::clearContext();
            }

            $order = $this->order_model->getById($order_id);
            if ($order && $order['paid_date']) {
                // Remember paid_date in log params for Restore action
                $order_log_params_model = new shopOrderLogParamsModel();
                $order_log_params_model->insert(array(
                    'name'     => 'paid_date',
                    'value'    => $order['paid_date'],
                    'order_id' => $order_id,
                    'log_id'   => $data['id'],
                ));

                // Empty paid_date and update stats so that deleted orders do not affect reports
                $this->order_model->updateById($order_id, array(
                    'paid_date'    => null,
                    'paid_year'    => null,
                    'paid_month'   => null,
                    'paid_quarter' => null,
                    'auth_date'    => null,
                ));
                $this->order_model->recalculateProductsTotalSales($order_id);
                shopCustomer::recalculateTotalSpent($order['contact_id']);
            }

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
        $data = array();
        if ($shipping_controls = $this->getShippingFields($order_id, waShipping::STATE_CANCELED)) {
            $data['shipping_controls'] = $shipping_controls;
        }
        if ($reduced = $this->order_params_model->getOne($order_id, 'reduced')) {
            $model = new shopStockModel();
            $stocks = $model->getAll();
            if (count($stocks) > 1) {
                $data['stocks'] = $stocks;
            }
        }

        if ($data) {
            $this->getView()->assign($data);
        }

        $this->setOption('html', true);
        return parent::getHTML($order_id);
    }

    public function getButton()
    {
        return parent::getButton();
        //'data-confirm="'._w('This order will be cancelled. Are you sure?').'"';
    }
}
