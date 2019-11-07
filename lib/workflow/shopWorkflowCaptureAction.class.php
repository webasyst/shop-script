<?php

class shopWorkflowCaptureAction extends shopWorkflowPayAction
{
    public function getDefaultOptions()
    {
        $options = parent::getDefaultOptions();
        unset($options['html']);
        return $options;
    }

    public function isAvailable($order)
    {
        if (!empty($order['id'])) {
            $plugin = null;
            $transactions = $this->getPaymentTransactions($plugin, $order['id']);
            if (isset($transactions[waPayment::TRANSACTION_CANCEL])) {
                return parent::isAvailable($order);
            }
        }
        return false;
    }

    public function execute($params = null)
    {
        $result = array();
        // plugin callback
        if (is_array($params)) {
            $callback = true;
            $order_id = $params['order_id'];
            if (isset($params['plugin'])) {
                $result['text'] = $params['plugin'].' (';
                if (!empty($params['view_data'])) {
                    $result['text'] .= $params['view_data'].' - ';
                }
                $result['text'] .= $params['amount'].' '.$params['currency_id'].')';
                $result['update']['params'] = array(
                    'payment_transaction_id' => $params['id'],
                );
            } else {
                if (isset($params['text'])) {
                    $result['text'] = $params['text'];
                }
                if (isset($params['update'])) {
                    $result['update'] = $params['update'];
                }
            }
        } else {
            $callback = false;
            $order_id = $params;
        }

        if (!$callback) {
            $plugin = $this->getPaymentPlugin($order_id);
            if ($plugin
                && ($plugin instanceof waIPaymentCapture)
                && ($transactions = $this->getPaymentTransactions($plugin, ($order_id)))
                && (isset($transactions[waPayment::TRANSACTION_CAPTURE]))
            ) {
                $transaction = $transactions[waPayment::TRANSACTION_CAPTURE];
                try {
                    $response = $plugin->capture(compact('transaction'));
                } catch (waException $ex) {
                    $message = sprintf(
                        "Error during capture order #%d: %s\nDATA:%s",
                        $order_id,
                        $ex->getMessage(),
                        var_export(compact('transaction'), true)
                    );
                    waLog::log($message, 'shop/workflow/capture.error.log');
                    throw new waException(_w('TODO: Error occurred during capture order. For details see error log'));
                }

                if (empty($response)) {
                    $result = false;
                } else {
                    if (($response['result'] === 0)) {
                        $amount = ifset($response, 'data', 'amount', $transaction['amount']);
                        $currency_id = ifset($response, 'data', 'currency_id', $transaction['currency_id']);

                        $capture_amount_html = shop_currency_html($amount, $currency_id);

                        $template = _w('Capture %s via %s payment gateway.');

                        $result = array(
                            'params' => array(
                                'capture_amount' => $amount,
                            ),
                            'text'   => sprintf($template, $capture_amount_html, $plugin->getName()),
                        );
                    } else {
                        throw new waException(sprintf(_w('TODO: Transaction error: %s'), $response['description']));
                    }
                }

            }
        }

        $order = $this->order_model->getById($order_id);

        if ($callback) {
            $this->waLog('order_capture_callback', $order_id, $order['contact_id']);
        } else {
            $this->waLog('order_capture', $order_id);
        }

        $this->preparePayData($result, $order);

        return $result;
    }

    public function postExecute($order_id = null, $result = null)
    {
        if (!$result) {
            return null;
        }
        if (is_array($order_id)) {
            $params = $order_id;
            $order_id = $params['order_id'];
        }

        return parent::postExecute($order_id, $result);
    }
}
