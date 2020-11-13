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
        if (!empty($order['id']) && !empty($order['auth_date']) && empty($order['paid_date'])) {
            $plugin = $this->getPaymentPlugin($order['id']);
            if (!$plugin) {
                return false;
            }
            $transactions = $this->getPaymentTransactions($plugin, $order['id']);

            if ($plugin->getProperties('partial_capture')) {
                $this->setOption('html', true);
            }

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
                $plugin_supports_partial_capture = $plugin->getProperties('partial_capture');
                $partial_capture = $plugin_supports_partial_capture && (waRequest::post('capture_mode') === 'partial');

                $return_stock = intval(ifset($options, 'action_options', 'return_stock', waRequest::post('return_stock')));
                $order_options = array(
                    'ignore_stock_validate' => true,
                    'return_stock'          => $return_stock,
                );
                $order = new shopOrder($order_id, $order_options);

                if ($partial_capture) {
                    $change_items = ifset($options, 'action_options', 'capture_items', waRequest::post('capture_items'));
                    $capture_shipping_cost = waRequest::post('capture_shipping_cost', 0, waRequest::TYPE_INT);
                    $order->edit($change_items, waPayment::OPERATION_CAPTURE, null, $capture_shipping_cost);

                    $text = nl2br(htmlspecialchars(trim(waRequest::post('text', '')), ENT_QUOTES, 'utf-8'));
                    if (!strlen($text)) {
                        $text = null;
                    }

                    $order->save($text);
                    $order_data = shopPayment::getOrderData($order_id, $plugin);
                } else {

                    $amount_on_hold = $order['amount_on_hold'];
                    if ($amount_on_hold < $order['total']) {
                        // Amount on hold is not enough to cover order total. Bail.
                        $message = sprintf(
                            "Unable to capture money for order #%d because order total exceeds amount on hold.",
                            $order_id
                        );
                        waLog::log($message, 'shop/workflow/capture.error.log');
                        throw new waException(_w('An error occurred during the order capture. See error log for details.'));
                    }


                    if ($amount_on_hold > $order['total'] && !$plugin_supports_partial_capture) {
                        // Can not do full capture because order total changed and does not match amount on hold.
                        // Can not do partial capture because plugin does not support it. Bail.
                        $message = sprintf(
                            "Unable to capture money for order #%d because order was modified.",
                            $order_id
                        );
                        waLog::log($message, 'shop/workflow/capture.error.log');
                        throw new waException(_w('An error occurred during the order capture. See error log for details.'));
                    }

                    $params = $order['params'];
                    if ($amount_on_hold > $order['total'] || !empty($params['auth_edit'])) {
                        // if order changed since money auth, have to perform operation in partial mode
                        $order_data = shopPayment::getOrderData($order_id, $plugin);
                    }

                }

                try {
                    // Perform capture operation via payment plugin
                    $response = $plugin->capture(compact('transaction', 'order_data'));
                } catch (waException $ex) {
                    $message = sprintf(
                        "Unable to perform money capture for order #%d: %s\nDATA:%s",
                        $order_id,
                        $ex->getMessage(),
                        var_export(compact('transaction', 'order_data'), true)
                    );
                    waLog::log($message, 'shop/workflow/capture.error.log');
                    throw new waException(_w('An error occurred during the order capture. See error log for details.'));
                }

                if (empty($response)) {
                    $result = false;
                } else {
                    if (($response['result'] === 0)) {
                        $amount = ifset($response, 'data', 'amount', $transaction['amount']);
                        $is_delivery_cost_removed = ifset($response, 'data', 'is_delivery_cost_removed', false);
                        $currency_id = ifset($response, 'data', 'currency_id', $transaction['currency_id']);

                        $capture_amount_html = shop_currency_html($amount, $currency_id);

                        if ($partial_capture) {
                            $template = _w('Partial capture %s via payment gateway %s.');
                        } else {
                            $template = _w('Capture %s via payment gateway %s.');
                        }

                        $result = array(
                            'params' => array(
                                'capture_amount' => $amount,
                                'is_delivery_cost_removed' => $is_delivery_cost_removed,
                            ),
                            'text'   => sprintf($template, $capture_amount_html, $plugin->getName()),
                        );


                    } else {
                        throw new waException(sprintf(_w('Transaction error: %s'), $response['description']));
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

    public function getHTML($order_id)
    {
        $order_id = intval($order_id);

        /** @var waPayment|null $plugin */
        $plugin = $this->getPaymentPlugin($order_id);
        if ($plugin
            && ($plugin instanceof waIPaymentCapture)
            && ($transactions = $this->getPaymentTransactions($plugin, ($order_id)))
            && (isset($transactions[waPayment::TRANSACTION_CAPTURE]))
        ) {
            $transaction_data = $transactions[waPayment::TRANSACTION_CAPTURE];
        } else {
            $transaction_data = null;
        }

        $shipping_controls = $this->getShippingFields($order_id, waShipping::STATE_DRAFT);

        $partial_capture = $transaction_data ? $plugin->getProperties('partial_capture') : false;

        $order = new shopOrder($order_id);

        $button_class = $this->getOption('button_class');

        $currency_id = ($transaction_data && $plugin) ? $plugin->allowedCurrency() : $order->currency;
        $currency = $this->getConfig()->getCurrencies($currency_id);
        $currency = reset($currency);

        $locale_info = waLocale::getInfo(wa()->getLocale());

        $currency_info = array(
            'code'             => $currency['code'],
            'fraction_divider' => ifset($locale_info, 'decimal_point', '.'),
            'fraction_size'    => ifset($currency, 'precision', 2),
            'group_divider'    => ifset($locale_info, 'thousands_sep', ''),
            'group_size'       => 3,

            'pattern_html' => str_replace('0', '%s', waCurrency::format('%{h}', 0, $currency_id)),
            'pattern_text' => str_replace('0', '%s', waCurrency::format('%{s}', 0, $currency_id)),
        );

        $app_settings_model = new waAppSettingsModel();
        if (!$app_settings_model->get('shop', 'disable_stock_count')) {
            $model = new shopStockModel();
            $stocks = $model->getAll();
            if (count($stocks) <= 1) {
                $stocks = array();
            }
        } else {
            $stocks = array();
        }


        $order->edit(true, waPayment::OPERATION_CAPTURE);
        $order_items = $this->workupOrderItems($order, $plugin);
        foreach ($order_items as &$item) {
            if ($item['quantity']) {
                $item['price_with_discount'] = $item['price'] - $item['total_discount'] / $item['quantity'];
            } else {
                $item['price_with_discount'] = $item['price'];
            }
        }

        $uncorrected_capture_plugins = $this->checkSupportedFiscalizationPlugins();

        $this->getView()->assign(
            compact(
                'partial_capture',
                'shipping_controls',
                'uncorrected_capture_plugins',
                'button_class',
                'order_items',
                'order',
                'currency_info',
                'stocks'
            )
        );

        $this->setOption('html', true);
        return parent::getHTML($order_id);
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

    protected function workupOrderItems(shopOrder $order, waPayment $plugin = null, $items = null)
    {
        if (!$items) {
            $items = $order->items;
        }

        if ($plugin) {
            $refund_options = array(
                'currency'       => $plugin->allowedCurrency(),
                'order_currency' => $order->currency,
            );
            foreach ($items as $id => $item) {
                if (empty($item['quantity'])) {
                    unset($items[$id]);
                }
            }
            $items = shopHelper::workupOrderItems($items, $refund_options);
        }

        return $items;

    }
}
