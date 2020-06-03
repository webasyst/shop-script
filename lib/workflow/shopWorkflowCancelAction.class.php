<?php

class shopWorkflowCancelAction extends shopWorkflowDeleteAction
{
    public function isAvailable($order)
    {
        if (!empty($order['id']) && !empty($order['auth_date']) && empty($order['paid_date'])) {
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
        $result = true;

        if (!is_array($params)) {
            $order_id = $params;
            $plugin = null;
            $transactions = $this->getPaymentTransactions($plugin, $order_id);
            if ($plugin
                && ($plugin instanceof waIPaymentCancel)
                && (isset($transactions[waPayment::TRANSACTION_CANCEL]))
            ) {
                try {
                    $transaction = $transactions[waPayment::TRANSACTION_CANCEL];

                    $response = $plugin->cancel(compact('transaction'));
                    if (empty($response)) {
                        $result = false;
                    } else {
                        if (($response['result'] !== 0)) {
                            throw new waException('Transaction error: '.$response['description']);
                        }

                        $amount = ifset($response, 'data', 'amount', $transaction['amount']);
                        $currency_id = ifset($response, 'data', 'currency_id', $transaction['currency_id']);

                        $cancel_amount_html = shop_currency_html($amount, $currency_id);

                        $template = _w('Cancel %s via payment gateway %s.');

                        $result = array(
                            'params' => array(
                                'cancel_amount' => $amount,
                            ),
                            'text'   => sprintf($template, $cancel_amount_html, $plugin->getName()),
                            'update' => array(
                                'auth_date' => null,
                            ),
                        );
                    }
                } catch (waException $ex) {
                    $result = false;
                    $message = sprintf(
                        "Error during cancel order #%d: %s\nDATA:%s",
                        $order_id,
                        $ex->getMessage(),
                        var_export(compact('transaction'), true)
                    );
                    waLog::log($message, 'shop/workflow/cancel.error.log');
                }
            }
        } else {
            $order_id = $params['order_id'];
            if (isset($params['plugin'])) {
                if (!is_array($result)) {
                    $result = array();
                }
                $result['text'] = $params['plugin'].' (';
                if (!empty($params['view_data'])) {
                    $result['text'] .= $params['view_data'].' - ';
                }
                $result['text'] .= $params['amount'].' '.$params['currency_id'].')';
                $result['update'] = array(
                    'auth_date' => null,
                    'params'    => array(
                        'payment_transaction_id' => $params['id'],
                    ),
                );
            } else {
                if (isset($params['text'])) {
                    if (!is_array($result)) {
                        $result = array();
                    }
                    $result['text'] = $params['text'];
                }
                if (isset($params['update'])) {
                    if (!is_array($result)) {
                        $result = array();
                    }
                    $result['update'] = $params['update'];
                }
            }
        }

        if ($result) {
            $parent_result = parent::execute($order_id);
            if (is_array($parent_result)) {
                if (!is_array($result)) {
                    $result = array();
                }
                $result = array_merge_recursive($result, $parent_result);
            }
        }

        return $result;
    }

    public function postExecute($order_id = null, $result = null)
    {
        if (is_array($order_id)) {
            $params = $order_id;
            $order_id = $params['order_id'];
        }
        return parent::postExecute($order_id, $result);
    }
}
