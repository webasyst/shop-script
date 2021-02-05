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

        } else {
            $order_id = $options;
            $options = array();
        }

        # use $options['action_options'] for tests
        $refund = ifset($options, 'action_options', 'refund', waRequest::post('refund'));
        $refund_amount = ifset($options, 'action_options', 'refund_amount', waRequest::post('refund_amount'));
        $refund_mode = ifset($options, 'action_options', 'refund_mode', waRequest::post('refund_mode'));
        $return_stock = intval(ifset($options, 'action_options', 'return_stock', waRequest::post('return_stock')));

        $refund_items = ifset($options, 'action_options', 'refund_items', waRequest::post('refund_items'));

        if ($refund) {
            $plugin = $this->getPaymentPlugin($order_id);
        } else {
            $plugin = null;
        }

        if ($refund_mode === 'partial') {
            $order_options = array(
                'ignore_stock_validate' => true,
                'return_stock'          => $return_stock,
            );
            $order = new shopOrder($order_id, $order_options);

            if ($refund_items !== true) {
                #Don't change state after action execute
                $this->state_id = null;
            }

            $refund_items = $order->edit($refund_items, waPayment::OPERATION_REFUND);
            $refund_items = $this->workupOrderItems($order, $plugin, $refund_items);
            $refund_amount = 0.0;
            foreach ($refund_items as $refund_item) {
                $refund_amount += ($refund_item['price'] * $refund_item['quantity']) - ifset($refund_item['total_discount'], 0);
            }
        } elseif ($refund_amount === null) {
            $refund_amount = true;
            $refund_items = null;
        } else {
            if ($refund_amount !== true) {
                $refund_amount = max(0, floatval($refund_amount));
            }
            $refund_items = null;
        }

        if ($refund && !empty($plugin)) {
            $result = $this->refundPayment($plugin, $order_id, $refund_amount, $refund_items);
        } elseif ($refund_items) {
            $result = array(
                'params' => array(
                    'refund_amount' => $refund_amount,
                    'refund_items'  => $refund_items,
                ),
            );
        }

        if ($result) {
            if (!empty($order)) {
                shopAffiliate::cancelBonus($order->id);
                $order->save();
            }
            if ($return_stock) {
                if (!is_array($result)) {
                    $result = array();
                }
                $result['params']['return_stock'] = intval($return_stock);
            }
            $text = nl2br(htmlspecialchars(trim(waRequest::post('text', '')), ENT_QUOTES, 'utf-8'));
            if (strlen($text)) {
                if (!is_array($result)) {
                    $result = array();
                }
                $result['text'] = $text;
            }

            if ($refund_mode !== 'partial') {
                if (!is_array($result)) {
                    $result = array();
                }
                $result['params']['auth_edit'] = null;
            }
        }

        return $result;
    }

    /**
     * @param waPayment|waIPaymentRefund $plugin
     * @param int                        $order_id
     * @param float                      $refund_amount
     * @param array[]                    $refund_items
     * @return array|bool
     */
    protected function refundPayment($plugin, $order_id, $refund_amount, $refund_items)
    {
        if ($transaction = shopPayment::isRefundAvailable($order_id, $plugin)) {
            try {

                if (isset($transaction['amount']) && ($refund_amount > $transaction['amount'])) {
                    throw new waException('Specified amount exceeds transaction amount');
                }

                if (empty($refund_items)
                    &&
                    (!empty($transaction['refunded_amount']) || ($transaction['state'] == waPayment::STATE_PARTIAL_REFUNDED))
                ) {
                    $order = new shopOrder($order_id);
                    $refund_items = $order->items;
                    if ($order->shipping > 0) {
                        $refund_items['%shipping%'] = array(
                            'name'         => $order->shipping_name,
                            'type'         => 'shipping',
                            'quantity'     => 1,
                            'price'        => $order->shipping,
                            'tax_percent'  => $order->params['shipping_tax_percent'],
                            'tax_included' => $order->params['shipping_tax_included'],
                        );
                    }
                    $refund_items = $this->workupOrderItems($order, $plugin, $refund_items);
                }

                $response = $plugin->refund(compact('transaction', 'refund_amount', 'refund_items'));

                if (empty($response)) {
                    $result = false;
                } else {
                    if (($response['result'] !== 0)) {
                        throw new waException('Transaction error: '.$response['description']);
                    }

                    $template = _w('Refunded %s via %s payment gateway.');
                    if ($refund_amount === true) {
                        $refund_amount_html = shop_currency_html($transaction['amount'], $transaction['currency_id']);
                        $refund_amount = $transaction['amount'];
                    } else {
                        $refund_amount_html = shop_currency_html($refund_amount, $transaction['currency_id']);
                    }

                    unset($refund_items['%shipping%']);
                    $result = array(
                        'params' => array(
                            'refund_amount' => $refund_amount,
                            'refund_items'  => $refund_items,
                        ),
                        'text'   => sprintf($template, $refund_amount_html, $plugin->getName()),
                    );
                }
            } catch (waException $ex) {
                $result = false;
                $data = compact('transaction', 'refund_amount', 'refund_items');
                if (!empty($response)) {
                    $data['response'] = $response;
                }
                $message = sprintf(
                    "Error during refund order #%d: %s\nDATA:%s",
                    $order_id,
                    $ex->getMessage(),
                    var_export($data, true)
                );
                waLog::log($message, 'shop/workflow/refund.error.log');
            }
        } else {
            $result = false;
            $message = sprintf(
                "Refund order #%d not available\nDATA:%s",
                $order_id,
                var_export(compact('transaction', 'refund_amount', 'refund_items'), true)
            );
            waLog::log($message, 'shop/workflow/refund.error.log');
        }

        return $result;
    }

    public function postExecute($order_id = null, $result = null)
    {
        $data = parent::postExecute($order_id, $result);

        if (!empty($data)) {
            if ($order_id != null) {
                $order = $this->getOrder($order_id);
                if ($this->state_id) {
                    $this->waLog('order_refund', $order_id);
                    $this->order_model->updateById($order_id, array(
                        'paid_date'    => null,
                        'paid_year'    => null,
                        'paid_month'   => null,
                        'paid_quarter' => null,

                        'auth_date' => null,
                    ));



                    // for logging changes in stocks
                    shopProductStocksLogModel::setContext(
                        shopProductStocksLogModel::TYPE_ORDER,
                        'Order %s was refunded',
                        array(
                            'order_id' => $order_id,
                            'return_stock_id' => ifempty($result, 'params', 'return_stock', null),
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
                } else {
                    #partial refund
                    $this->waLog('order_partial_refund', $order_id);
                    $order['items'] = $result['params']['refund_items'];

                    shopAffiliate::applyBonus($order);

                    $this->order_model->recalculateProductsTotalSales($order_id);
                    shopCustomer::recalculateTotalSpent($order['contact_id']);

                    $this->setPackageState(waShipping::STATE_DRAFT, $order);
                }
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
        $shipping_controls = $this->getShippingFields($order_id, waShipping::STATE_CANCELED);

        $partial_refund = $transaction_data ? $plugin->getProperties('partial_refund') : false;
        $order = new shopOrder($order_id);

        $button_class = $this->getOption('button_class');

        $currency_id = ($transaction_data && $plugin) ? $plugin->allowedCurrency() : $order->currency;
        if (is_array($currency_id) && in_array($order->currency, $currency_id)) {
            $currency_id = $order->currency;
        }
        $currency = $this->getConfig()->getCurrencies($currency_id);
        $currency = reset($currency);

        $locale_info = waLocale::getInfo(wa()->getLocale());

        $currency_info = array(
            'code'             => $currency['code'],
            'fraction_divider' => ifset($locale_info, 'decimal_point', '.'),
            'fraction_size'    => ifset($currency, 'precision', 2),
            'group_divider'    => ifset($locale_info, 'thousands_sep', ''),
            'group_size'       => 3,

            'pattern_html' => str_replace('0', '%s', waCurrency::format('%{h}', 0, $currency['code'])),
            'pattern_text' => str_replace('0', '%s', waCurrency::format('%{s}', 0, $currency['code'])),
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

        $order_items_count = 0;
        $order_items = $order->edit(true, waPayment::OPERATION_REFUND);
        $order_items = $this->workupOrderItems($order, $transaction_data ? $plugin : null, $order_items);
        foreach ($order_items as &$item) {
            $order_items_count += intval($item['quantity']);
            if ((float)$item['quantity']) {
                $item['price_with_discount'] = $item['price'] - $item['total_discount'] / $item['quantity'];
            } else {
                $item['price_with_discount'] = $item['price'];
            }
        }

        $uncorrected_refund_plugins = $this->checkSupportedFiscalizationPlugins();

        $this->getView()->assign(
            compact(
                'transaction_data',
                'partial_refund',
                'shipping_controls',
                'uncorrected_refund_plugins',
                'button_class',
                'order_items',
                'order_items_count',
                'order',
                'currency_info',
                'stocks'
            )
        );

        $this->setOption('html', true);
        return parent::getHTML($order_id);
    }

    protected function workupOrderItems(shopOrder $order, waPayment $plugin = null, $items = null)
    {
        if (!$items) {
            $items = $order->items;
        }

        if ($plugin) {

            $currency_id = $plugin->allowedCurrency();
            if (is_array($currency_id)) {
                if (in_array($order->currency, $currency_id)) {
                    $currency_id = $order->currency;
                } else {
                    $currency_id = reset($currency_id);
                }
            }

            $refund_options = array(
                'currency'       => $currency_id,
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

    public function getButton()
    {
        return parent::getButton('data-container="#workflow-content"');
    }
}
