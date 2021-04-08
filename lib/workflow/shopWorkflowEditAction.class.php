<?php

class shopWorkflowEditAction extends shopWorkflowAction
{
    private $order_item = null;

    protected function price($value, $currency = null)
    {
        if (strpos($value, ',') !== false) {
            $value = str_replace(',', '.', $value);
        }
        if ($currency) {
            waCurrency::round($value, $currency);
        }
        return (double)$value;
    }

    public function isAvailable($order)
    {
        $available = true;
        if (is_array($order)) {
            $this->order_item = $order;
        } elseif ($order instanceof shopOrder) {
            $this->order_item = $order;
        }

        if ($order) {
            if (!($order instanceof shopOrder)) {
                $order = new shopOrder($order);
            }

            if ($order->auth_date && !$order->paid_date) {
                // We want to show button, but it will not work in this case.
                // Attempt to edit will show warning and bail because of $this->order_uneditable_reason
                $available = true;
            }
        }

        return $available && parent::isAvailable($order);
    }

    public function execute($data = null)
    {
        $return = true;
        $order = $this->order_model->getById($data['id']);

        # after editing every unsettled order became simple order
        if ($order['unsettled']) {
            $data['unsettled'] = 0;
        }

        $subtotal = 0;
        $services = $products = array();
        foreach ($data['items'] as $item) {
            if ($item['service_id']) {
                $services[] = $item['service_id'];
            } else {
                $products[] = $item['product_id'];
            }
        }
        $service_model = new shopServiceModel();
        $product_model = new shopProductModel();
        $services = $service_model->getById($services);
        $products = $product_model->getById($products);

        foreach ($data['items'] as &$item) {
            $item['currency'] = $order['currency'];
            $item['price'] = $this->price($item['price'], $order['currency']);

            if ($item['service_id']) {
                if (isset($services[$item['service_id']])) {
                    $item['service'] = $services[$item['service_id']];
                } else {
                    $item['service'] = array(
                        'name' => $item['name'],
                        'id'   => $item['service_id'],
                    );
                }
            } else {
                if (isset($products[$item['product_id']])) {
                    $item['product'] = $products[$item['product_id']];
                } else {
                    $item['product'] = array(
                        'name'   => $item['name'],
                        'id'     => $item['product_id'],
                        'sku_id' => $item['sku_id'],
                    );
                }
            }
            $subtotal += $item['price'] * $item['quantity'];
        }
        unset($item);

        foreach (array('shipping', 'discount') as $k) {
            if (!isset($data[$k])) {
                $data[$k] = 0;
            } else {
                $data[$k] = $this->price($data[$k], $order['currency']);
            }
        }
        $contact = new waContact($order['contact_id']);
        $shipping_address = $contact->getFirst('address.shipping');
        if (!$shipping_address) {
            $shipping_address = $contact->getFirst('address');
        }
        $shipping_address = $shipping_address ? $shipping_address['data'] : array();
        $billing_address = $contact->getFirst('address.billing');
        if (!$billing_address) {
            $billing_address = $contact->getFirst('address');
        }
        $billing_address = $billing_address ? $billing_address['data'] : array();

        $discount_rate = $subtotal ? ($data['discount'] / $subtotal) : 0;

        $taxes_params = array(
            'shipping'      => $shipping_address,
            'billing'       => $billing_address,
            'discount_rate' => $discount_rate,
        );

        if (!empty($data['params']['shipping_tax_id'])) {
            $data['items']['%shipping%'] = array(
                'type'     => 'shipping',
                'tax_id'   => $data['params']['shipping_tax_id'],
                'quantity' => 1,
                'price'    => $data['shipping'],
                'currency' => $order['currency'],
            );
        }

        $taxes = shopTaxes::apply($data['items'], $taxes_params, $order['currency']);

        if (isset($data['items']['%shipping%']['tax'])) {
            $data['params']['shipping_tax'] = $data['items']['%shipping%']['tax'];
            $data['params']['shipping_tax_percent'] = $data['items']['%shipping%']['tax_percent'];
            $data['params']['shipping_tax_included'] = $data['items']['%shipping%']['tax_included'];
        } else {
            $data['params']['shipping_tax'] = null;
            $data['params']['shipping_tax_percent'] = null;
            $data['params']['shipping_tax_included'] = null;
        }
        unset($data['items']['%shipping%']);
        $tax = $tax_included = 0;
        foreach ($taxes as $t) {
            if (isset($t['sum'])) {
                $tax += $t['sum'];
            }
            if (isset($t['sum_included'])) {
                $tax_included += $t['sum_included'];
            }
        }

        $data['tax'] = $tax_included + $tax;
        $data['total'] = $subtotal + $tax + $data['shipping'] - $data['discount'];

        // for logging changes in stocks
        shopProductStocksLogModel::setContext(
            shopProductStocksLogModel::TYPE_ORDER,
            /*_w*/ ('Order %s was edited'),
            array(
                'order_id'        => $data['id'],
                'return_stock_id' => ifset($data, 'params', 'return_stock', null),
            )
        );

        if (!empty($data['params']['shipping_id'])) {
            try {
                if ($shipping_plugin = shopShipping::getPlugin(null, $data['params']['shipping_id'])) {
                    $shipping_currency = $shipping_plugin->allowedCurrency();
                    if ($shipping_currency) {
                        $data['params']['shipping_currency'] = $shipping_currency;
                        $rate_model = new shopCurrencyModel();
                        if ($row = $rate_model->getById($shipping_currency)) {
                            $data['params']['shipping_currency_rate'] = str_replace(',', '.', $row['rate']);
                        }
                    }
                }

            } catch (waException $ex) {

            }
        }

        if (!empty($data['params']['refund_items'])) {
            // Write refund items as JSON into order log params.
            // This later becomes human-readable text during template rendering.
            $return = array(
                'params' => array(
                    'refund_items' => $data['params']['refund_items'],
                ),
            );
            unset($data['params']['refund_items']);
            $data['params']['auth_edit'] = date('Y-m-d H:i:s');
        }

        if (!empty($data['params']['is_delivery_cost_removed'])) {
            if (!is_array($return)) {
                $return = array();
            }
            $return['params']['is_delivery_cost_removed'] = $data['params']['is_delivery_cost_removed'];
        }

        if (!empty($data['text'])) {
            if (!is_array($return)) {
                $return = array();
            }
            $return['text'] = $data['text'];
            unset($data['text']);
        }

        $edited_sku_ids = array();
        foreach ($data['items'] as $item) {
            if (!empty($item['sku_id'])) {
                $edited_sku_ids[] = $item['sku_id'];
            }
        }
        $order_items_codes_model = new shopOrderItemCodesModel();
        $order_items_codes_model->clearValues($data['id'], $edited_sku_ids);

        // SAVE ORDER ITEMS
        $this->order_model->update($data, $data['id']);

        $this->waLog('order_edit', $data['id']);

        shopProductStocksLogModel::clearContext();

        if (!empty($data['params'])) {
            $this->order_params_model->set($data['id'], $data['params'], false);

            // Update shop customer source if this is the first order and source changed
            if (isset($data['params']['referer_host'])) {
                $scm = new shopCustomerModel();
                $shop_customer = $scm->getById($order['contact_id']);
                if (ifset($shop_customer, 'number_of_orders', null) == 1 && $shop_customer['source'] != $data['params']['referer_host']) {
                    $scm->updateById($order['contact_id'], array(
                        'source' => $data['params']['referer_host'],
                    ));
                }
            }
        }

        $this->marketingPromoWorkflowRun($data['id']);

        return $return;
    }

    public function postExecute($order = null, $result = null)
    {
        $order_id = $order['id'];
        $data = $this->setPackageState(waShipping::STATE_DRAFT, $order);
        if ($data) {
            if (is_array($result)) {
                $result += $data;
            } else {
                $result = $data;
            }
        }
        return parent::postExecute($order_id, $result);
    }

    public function getButton()
    {
        $attrs = '';
        if (!empty($this->order_item)) {
            $order_mode = shopOrderMode::getMode($this->order_item);
            if ($order_mode['mode'] == shopOrderMode::MODE_DISABLED) {
                $message = $order_mode['message'];
                $attrs = sprintf('data-action-unavailable-reason="%s"', htmlentities($message, ENT_QUOTES, 'utf-8'));
            } elseif ($this->order_item->auth_date && !$this->order_item->paid_date && is_array($this->order_item)) {
                if (!empty($this->order_item['unsettled'])) {
                    $total = wa_currency($this->order_item['total'], $this->order_item['currency']);
                    $confirm = _w("Editing this order will mark it as settled and will re-calculate its current amount (%s) based on the changed order content. Are you sure you want to settle this order and to reset the order amount?");
                    $confirm = sprintf($confirm, $total);
                    $attrs = sprintf('data-confirm="%s"', htmlentities($confirm, ENT_QUOTES, 'utf-8'));
                }
            }
        }

        return <<<HTML
<a href="#" class="s-edit-order show-alert" {$attrs}>
    <i class="icon16 edit"></i><span>{$this->getName()}</span>
    <i class="icon16 loading" style="margin-left: 4px; display:none;"></i>
</a>
HTML;
    }

    /**
     * Runner marketing promo workflow
     * @param int $order_id
     * @throws waException
     */
    private function marketingPromoWorkflowRun($order_id)
    {
        $order = new shopOrder($order_id);
        $workflow = new shopMarketingPromoWorkflow($order);
        $workflow->run();
    }
}
