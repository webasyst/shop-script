<?php

class shopOrderSaveItemMethod extends shopApiMethod
{
    protected $method = 'POST';

    protected $max_count;

    public function execute()
    {
        $item_id = waRequest::post('item_id', null, waRequest::TYPE_INT);
        $quantity = waRequest::post('quantity', null, waRequest::TYPE_INT);

        if (!$item_id) {
            throw new waAPIException('invalid_param', 'Order not found', 404);
        }

        $soim = new shopOrderItemsModel();
        $change_item = $soim->getById($item_id);

        if ($change_item) {
            $data = $this->getData($change_item, $quantity);
        } else {
            throw new waAPIException('invalid_param', 'Item not found', 404);
        }

        $errors = $this->validate($data, $change_item, $quantity);

        if (!$errors) {
            $this->save($data);
        }

        $this->response = $this->createResponse($errors);
    }

    protected function validate($data, $change_item, $quantity)
    {
        $errors = array();

        if (!wa('shop')->getSetting('ignore_stock_count', null, 'shop')) {
            $validate_stocks_count = $this->validateExceedStocksCount($change_item, $quantity);
            if ($validate_stocks_count) {
                $errors[] = $validate_stocks_count;
            }
        };

        $product_count = null;
        foreach ($data['items_validate'] as $item) {
            if ($item['type'] == 'product') {
                $product_count += 1;
            }
        }
        unset($data['items_validate']);

        if ($product_count < 2 && $quantity == 0) {
            $errors[] = 'You can not delete the last product';
        }

        return $errors;
    }

    /**
     * @param $errors
     * @return array
     */
    protected function createResponse($errors)
    {
        $result = array(
            'status' => 'ok',
            'errors' => $errors,
        );

        if ($errors) {
            $result['status'] = 'fail';
        }

        if ($this->max_count) {
            $result['max_count'] = $this->max_count;
        }

        return $result;
    }

    /**
     * Make sure sum of item discounts does not exceed total discount
     * @param $data
     * @return mixed
     */
    protected function compareDiscount($data)
    {
        $tmp_discount = 0;
        foreach ($data['items'] as $item) {
            $tmp_discount += round(ifset($item['total_discount'], 0), 4);
        }
        if (round($tmp_discount, 4) > round($data['discount'], 4)) {
            waLog::log(sprintf('Discount for items reset because it [%f] more then total discount [%f] for order %d', $tmp_discount, $data['discount'], $data['order_id']),
                'shop/order.log');
            foreach ($data['items'] as &$item) {
                $item['total_discount'] = 0;
            }
            unset($item);
        }

        return $data;
    }

    /**
     * @param $data
     */
    protected function save($data)
    {
        // Save order
        $workflow = new shopWorkflow();
        $workflow->getActionById('edit')->run($data);
        $discount_description = sprintf_wp(
            'Changed in mobile app by user: %s',
            wa()->getUser()->getLogin()
        );

        $order_log_model = new shopOrderLogModel();
        $order_log_model->add(array(
            'order_id'        => $data['id'],
            'contact_id'      => wa()->getUser()->getId(),
            'before_state_id' => $data['state_id'],
            'after_state_id'  => $data['state_id'],
            'text'            => $discount_description,
            'action_id'       => '',
        ));
    }

    private function getData($change_item, $quantity)
    {
        $soim = new shopOrderItemsModel();
        $som = new shopOrderModel();
        $sopm = new shopOrderParamsModel();

        $data = $som->getById($change_item['order_id']);
        $data['params'] = $sopm->get($change_item['order_id']);
        $data['items'] = $soim->getByField(array('order_id' => $change_item['order_id']), true);
        $data['items_validate'] = $data['items']; //for validate

        foreach ($data['items'] as $key => &$item) {
            if ($change_item['type'] == 'service') {
                if ($item['id'] == $change_item['id']) {
                    unset($data['items'][$key]);
                    break;
                }
            } else {
                if (($item['id'] == $change_item['id'] || ifset($item,'parent_id', null) == $change_item['id'])
                    && $quantity == 0) {
                    unset($data['items'][$key]);
                    continue;
                }
                if ($item['id'] == $change_item['id']) {
                    $item['quantity'] = $quantity;
                    unset($item['total_discount']);
                }
            }
        }

        unset($item);

        $data['total'] = $this->calcTotal($data);

        $data['comment'] = waRequest::post('comment', null, waRequest::TYPE_STRING_TRIM);
        $data['discount'] = shopDiscounts::reapply($data);
        $data['tax'] = 0;
        $data['total'] = $this->calcTotal($data);
        $data['shipping'] = $this->calcShipping($data);

        $data['params']['storefront'] = ifempty($data['params']['storefront']);

        $data['total'] = $data['total'] - $data['discount'] + $data['shipping'];

        if ($data['total'] <= 0) {
            $data['total'] = 0;
        }

        if (isset($data['discount'])) {
            $data = $this->compareDiscount($data);
        }

        return $data;
    }

    /**
     * @param $change_item
     * @param $quantity
     * @return string|null
     */
    private function validateExceedStocksCount($change_item, $quantity)
    {
        $ssm = new shopProductStocksModel();

        $stock_count = $ssm->getByField(array(
            'sku_id'   => $change_item['sku_id'],
            'stock_id' => $change_item['stock_id']
        ));

        if ($stock_count && $stock_count['count'] < $quantity && $quantity > 0) {
            $this->max_count = $stock_count['count'];
            return '!!! Required quantity more than is in stock';
        }
        return null;
    }

    /**
     * @param $data
     * @return int
     * @throws waException
     */
    public function calcShipping($data)
    {
        $product_ids = array();
        $shipping_id = null;
        foreach ($data['items'] as $i) {
            $product_ids[] = $i['product_id'];
        }
        $product_ids = array_unique($product_ids);
        $feature_model = new shopFeatureModel();
        $f = $feature_model->getByCode('weight');
        if (!$f) {
            $values = array();
        } else {
            $values_model = $feature_model->getValuesModel($f['type']);
            $values = $values_model->getProductValues($product_ids, $f['id']);
        }

        $contact = $this->getContact($data['contact_id']);
        $shipping_address = $contact->getFirst('address.shipping');
        if ($shipping_address) {
            $shipping_address = $shipping_address['data'];
        }

        $shipping_items = array();
        foreach ($data['items'] as &$i) {
            if ($i['type'] == 'product') {
                if (isset($values['skus'][$i['sku_id']])) {
                    $i['weight'] = $values['skus'][$i['sku_id']];
                } else {
                    $i['weight'] = isset($values[$i['product_id']]) ? $values[$i['product_id']] : 0;
                }
                $i['price'] = shop_currency($i['price'], $data['currency'], $data['currency'], false);
                $shipping_items[] = array(
                    'name'     => '',
                    'price'    => $i['price'],
                    'quantity' => $i['quantity'],
                    'weight'   => $i['weight'],
                );
            }
            unset($i);
        }

        $method_params = array(
            'currency'        => $data['currency'],
            'total_price'     => $data['total'] - $data['discount'],
            'no_external'     => true,
            'custom_html'     => false,
            'shipping_params' => array(),
        );

        if ($data['params'] && !empty($data['params']['shipping_id'])) {
            $shipping_id = $data['params']['shipping_id'];
            $method_params['allow_external_for'] = array('shipping_id' => $shipping_id);

            foreach ($data['params'] as $name => $value) {
                if (preg_match('@^shipping_params_(.+)$@', $name, $matches)) {
                    if (!isset($method_params['shipping_params'][$shipping_id])) {
                        $method_params['shipping_params'][$shipping_id] = array();
                    }
                    $method_params['shipping_params'][$shipping_id][$matches[1]] = $value;
                }
            }
        }

        $shipping_methods = shopHelper::getShippingMethods($shipping_address, $shipping_items, $method_params);

        if ($shipping_id) {
            if (!empty($data['params']['shipping_rate_id'])) {
                $shipping_id .= '.'.$data['params']['shipping_rate_id'];
            }
            $order_shipping = ifset($shipping_methods, $shipping_id, null);

            return ifset($order_shipping, 'rate', 0);
        }

        return 0;
    }

    /**
     * @param $data
     * @return float|int|mixed
     */
    public function calcTotal(&$data)
    {
        $total = 0;
        $fields = array('price');
        foreach ($data['items'] as &$item) {
            foreach ($fields as $field) {
                if (isset($item[$field])) {
                    $item[$field] = $this->cast($item[$field], $data['currency']);
                }
            }
            $total += $item['price'] * (int)$item['quantity'];
            unset($item);
        }
        if ($total == 0) {
            return $total;
        }
        $data['discount'] = $this->cast($data['discount'], $data['currency']);
        $data['shipping'] = $this->cast($data['shipping'], $data['currency']);

        return $total - $data['discount'] + $data['shipping'];
    }

    private function cast($value, $currency = null)
    {
        if (strpos($value, ',') !== false) {
            $value = str_replace(',', '.', $value);
        }
        if (!empty($currency)) {
            return waCurrency::round($value, $currency);
        }
        return str_replace(',', '.', (double)$value);
    }

    protected function getContact($contact_id = null)
    {
        if ($contact_id) {
            return new waContact($contact_id);
        }
        return new waContact();
    }
}
