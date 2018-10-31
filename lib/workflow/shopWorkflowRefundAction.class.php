<?php

class shopWorkflowRefundAction extends shopWorkflowAction
{
    public function isAvailable($order)
    {
        if (!empty($order['id']) && $this->getPaymentPlugin($order['id'])) {
            $this->setOption('html', true);
        }

        return parent::isAvailable($order);
    }

    public function execute($options = null)
    {
        $result = true;
        if (is_array($options)) {
            # it's payment plugin callback
            $order_id = $options['order_id'];
            //@todo add refund details

        } else {
            $order_id = $options;
            if (waRequest::post('refund')) {
                $plugin = $this->getPaymentPlugin($order_id);
                if ($plugin && ($transaction = shopPayment::isRefundAvailable($order_id, $plugin))) {
                    try {
                        $refund_amount = waRequest::post('refund_amount');
                        if ($refund_amount === null) {
                            $refund_amount = true;
                        } else {
                            $refund_amount = max(0, floatval($refund_amount));
                            if (isset($transaction['amount']) && ($refund_amount > $transaction['amount'])) {
                                throw new waException('Specified amount exceeds transaction amount');
                            }
                        }

                        $response = $plugin->refund(compact('transaction', 'refund_amount'));
                        if (empty($response)) {
                            $result = false;
                        } else {
                            if (($response['result'] !== 0)) {
                                throw new waException('Transaction error: '.$response['description']);
                            }

                            $template = _w('Refunded %s via %s payment gateway.');
                            if ($refund_amount === true) {
                                $refund_amount_html = shop_currency_html($transaction['amount'], $transaction['currency_id']);
                            } else {
                                $refund_amount_html = shop_currency_html($refund_amount, $transaction['currency_id']);
                            }
                            $result = array(
                                'params' => array(
                                    'refund_amount' => $refund_amount === true ? 'all' : $refund_amount,
                                ),
                                'text'   => sprintf($template, $refund_amount_html, $plugin->getName()),
                            );
                        }
                    } catch (waException $ex) {
                        $result = false;
                        $message = sprintf(
                            "Error during refund order #%d: %s\nDATA:%s",
                            $order_id,
                            $ex->getMessage(),
                            var_export(compact('transaction', 'refund_amount'), true)
                        );
                        waLog::log($message, 'shop/workflow/refund.error.log');
                    }
                }
            }
        }

        return $result;
    }

    public function postExecute($order_id = null, $result = null)
    {
        $data = parent::postExecute($order_id, $result);

        if ($data !== null) {

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
                        'order_id' => $order_id,
                    )
                );

                // refund, so return
                $this->order_model->returnProductsToStocks($order_id);
                shopProductStocksLogModel::clearContext();

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
        }

        return $data;
    }

    public function getHTML($order_id)
    {
        $order_id = intval($order_id);

        /** @var waPayment|null $plugin */
        $plugin = $this->getPaymentPlugin($order_id);

        $transaction_data = $plugin ? shopPayment::isRefundAvailable($order_id, $plugin) : false;

        $controls = $this->getShippingFields($order_id, waShipping::STATE_CANCELED);

        if ($controls || $transaction_data) {
            $button_class = $this->getOption('button_class');

            if (false) { // once it will be work
                $partial_refund = $plugin->getProperties('partial_refund');
            }
            $this->getView()->assign(compact('transaction_data', 'partial_refund', 'shipping_controls', 'button_class'));
            $this->setOption('html', true);
        }

        return parent::getHTML($order_id);
    }
}
