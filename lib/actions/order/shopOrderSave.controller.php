<?php

class shopOrderSaveController extends waJsonController
{
    private $models = array();
    private $shipping_address;
    private $billing_address;

    public function execute()
    {
        // Existing order id
        $id = waRequest::get('id', null, waRequest::TYPE_INT);

        // null             - don't add/edit contact info
        // not zero numeric - edit existing contact
        // zero numeric     - add contact
        $customer_id = waRequest::post('customer_id', null, waRequest::TYPE_INT);
        if ($customer_id && !shopHelper::getContactRights($customer_id)) {
            $customer_id = null;
        }
        if ($customer_id === null && !$id) {
            $customer_id = 0;
        }

        // Validation for customer form
        if ($customer_id !== null) {
            $contact = new waContact($customer_id);
            $form = shopHelper::getCustomerForm($customer_id);
            $customer_validation_disabled = wa()->getSetting('disable_backend_customer_form_validation');
            if (!$customer_validation_disabled) {
                if (!$form->isValid($contact)) {
                    $this->errors['customer']['html'] = $form->html();
                }
            }
        }

        // Validation for order form
        if ($data = $this->getData($id)) {
            $this->validate($data, $id);
        }

        if ($this->errors) {
            return;
        }

        $params_model = new shopOrderParamsModel();
        $params = $params_model->get($id);

        // Save customer
        if ($customer_id !== null) {

            foreach((array)$form->post() as $fld_id => $fld_data) {
                if (!$fld_data) {
                    continue;
                }
                if ($fld_id == 'address.shipping') {
                    $this->shipping_address = $fld_data;
                    $this->setAddress($contact, $params, 'shipping');
                    if (isset($this->shipping_address[0])) {
                        $this->shipping_address = $this->shipping_address[0];
                    }
                    continue;
                } elseif ($fld_id == 'address.billing') {
                    $this->billing_address = $fld_data;
                    $this->setAddress($contact, $params, 'billing');
                    if (isset($this->billing_address[0])) {
                        $this->billing_address = $this->billing_address[0];
                    }
                    continue;
                }
                if (is_array($fld_data) && !empty($fld_data[0])) {
                    $contact[$fld_id] = array();
                    foreach($fld_data as $v) {
                        $contact->set($fld_id, $v, true);
                    }
                } else {
                    $contact[$fld_id] = $fld_data;
                }
            }

            if ($customer_validation_disabled) {
                $contact->save();
            } else {
                $errors = $contact->save(array(), true);
                if ($errors) {

                    // Only consider errors from visible fields
                    $errors = array_intersect_key($errors, $form->fields);

                    if ($errors) {
                        $this->errors['customer'] = $errors;
                        return;
                    } else {
                        // No errors from visible fields: save anyway
                        $contact->save();
                    }
                }
            }
            $data['contact'] = $contact;
        }

        if ($this->errors) {
            return;
        }

        // Remember previous discount to only write discount description to order log
        // if something actually changed
        $previous_discount = 0.0;

        // Unless discount for existing order is specified by hand, recalculate it again,
        // allowing plugins such as affiliation to modify its data if needed.
        $discount_description = waRequest::request('discount_description', '', 'string');
        if ($id && !empty($data['discount'])) {
            // Non-empty description means that the discount were not specified by hand.
            if (!empty($discount_description)) {
                $data['shipping'] = $this->cast($data['shipping']);
                $data = array(
                    'total' => $data['total'] + $this->cast($data['discount']) - $data['shipping'],
                    'discount' => 0,
                    'params' => ifempty($data['params'], array()) + $params,
                ) + $data;

                // Add keys from shop_order not present in $data
                $o = array_diff_key($this->getModel()->getOrder($id), $data);
                $data += $o;

                // Calculate discounts
                $discount_description = null;
                $data['discount'] = shopDiscounts::reapply($data, $discount_description);
                $data['total'] = $data['total'] - $data['discount'] + $data['shipping'];
                if ($data['total'] <= 0) {
                    $data['total'] = 0;
                }

                // Remove keys from shop_order previously not present in $data
                $data = array_diff_key($data, $o);
            }

            $previous_discount = (float) $this->getModel()->select('discount')->where('id=?', $id)->fetchField();
        }

        // Fill in shipping and payment-specific params
        $this->getParams($data, $id);

        // Save order
        $workflow = new shopWorkflow();
        if (!$id) {
            $just_created = true;
            $data['skip_description'] = true;
            $id = $workflow->getActionById('create')->run($data);
        } else {
            $data['id'] = $id;
            $just_created = false;
            $workflow->getActionById('edit')->run($data);
        }

        // Load nice and clean order data from DB
        $this->response['order'] = $order = $this->workupOrder($this->getModel()->getOrder($id));
        $order['discount'] = (float) $order['discount'];

        // Save discount description to order log
        if ($previous_discount != $order['discount']) {
            if (empty($discount_description)) {
                if ($just_created) {
                    $discount_description = sprintf_wp('Discount specified manually during order creation: %s', shop_currency($order['discount'], $order['currency'], $order['currency']));
                } else {
                    $discount_description = sprintf_wp('Discount modified manually via backend editor: %s', shop_currency($order['discount'], $order['currency'], $order['currency']));
                }
            }

            $order_log_model = new shopOrderLogModel();
            $order_log_model->add(array(
                'order_id' => $id,
                'contact_id' => wa()->getUser()->getId(),
                'before_state_id' => $order['state_id'],
                'after_state_id' => $order['state_id'],
                'text' => $discount_description,
                'action_id' => '',
            ));
        }
    }

    protected function setAddress(waContact $contact, $params, $ext)
    {
        $address = shopHelper::getOrderAddress($params, $ext);
        if ($contact['address.'.$ext]) {
            $flag = false;
            foreach ($contact['address.'.$ext] as $i => $a) {
                $flag = true;
                foreach ($address as $k => $v) {
                    $old_v = isset($a['data'][$k]) ? $a['data'][$k] : '';
                    if ($v !== $old_v) {
                        $flag = false;
                        break;
                    }
                }
                if ($flag) {
                    break;
                }
            }
            if ($flag) {
                $tmp = $contact['address.'.$ext];
                $tmp[$i] = ($ext == 'shipping') ? $this->shipping_address : $this->billing_address;
                $contact['address.'.$ext] = $tmp;
            }
        } else {
            $tmp = ($ext == 'shipping') ? $this->shipping_address : $this->billing_address;
            if ($tmp) {
                $contact['address.'.$ext] = $tmp;
            }
        }
    }

    private function getOrderItems($items, $weight_unit)
    {
        $product_ids = array();
        foreach ($items as $item) {
            $product_ids[] = $item['product_id'];
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

        $m = null;
        if ($weight_unit) {
            $dimension = shopDimension::getInstance()->getDimension('weight');
            if ($weight_unit != $dimension['base_unit']) {
                $m = $dimension['units'][$weight_unit]['multiplier'];
            }
        }

        foreach ($items as &$item) {
            if ($item['type'] == 'product') {
                if (isset($values['skus'][$item['sku_id']])) {
                    $w = $values['skus'][$item['sku_id']];
                } else {
                    $w = isset($values[$item['product_id']]) ? $values[$item['product_id']] : 0;
                }
                if ($m !== null) {
                    $w = $w / $m;
                }
                $item['weight'] = $w;
            } else {
                $item['weight'] = 0;
            }
        }
        unset($item);
        return $items;

    }

    private function getParams(&$data, $id)
    {
        $model = new shopPluginModel();

        $shipping_address = array();
        if (!empty($data['contact'])) {
            $address = $data['contact']->getFirst('address.shipping');
            if (!$address) {
                $address = $data['contact']->getFirst('address');
            }
            if (!empty($address['data'])) {
                $shipping_address = $address['data'];
            }
        }
        $empty_address = false;
        // shipping
        if ($shipping_id = waRequest::post('shipping_id')) {
            $shipping_parts = explode('.', $shipping_id);
            $shipping_id = $shipping_parts[0];
            $rate_id = isset($shipping_parts[1]) ? $shipping_parts[1] : '';
            $data['params']['shipping_id'] = $shipping_id;
            $data['params']['shipping_rate_id'] = $rate_id;

            $plugin_info = $model->getById($shipping_id);
            $plugin = shopShipping::getPlugin($plugin_info['plugin'], $shipping_id);
            $rates = $plugin->getRates($this->getOrderItems($data['items'], $plugin->allowedWeightUnit()), $shipping_address);
            $empty_address = $plugin->allowedAddress() === false;
            $data['params']['shipping_plugin'] = $plugin->getId();
            $data['params']['shipping_name'] = $plugin_info['name'];

            if ($rates && is_array($rates)) {
                if (!$rate_id) {
                    $rate = reset($rates);
                    $data['params']['shipping_rate_id'] = key($rates);
                } else if (!empty($rates[$rate_id])) {
                    $rate = $rates[$rate_id];
                }
                if (!empty($rate['est_delivery'])) {
                    $data['params']['shipping_est_delivery'] = $rate['est_delivery'];
                }
                if (!empty($rate['name'])) {
                    $data['params']['shipping_name'] .= ' ('.$rate['name'].')';
                }
            }

            if (waRequest::post('shipping'.$shipping_id)) {
                foreach (waRequest::post('shipping_'.$shipping_id) as $k => $v) {
                    $data['params']['shipping_params_'.$k] = $v;
                }
            }
        } else {
            foreach (array('id', 'rate_id', 'plugin', 'name', 'est_delivery') as $k) {
                $data['params']['shipping_'.$k] = null;
            }
        }
        // payment
        if ($payment_id = waRequest::post('payment_id')) {
            $data['params']['payment_id'] = $payment_id;
            $plugin_info = $model->getById($payment_id);
            $data['params']['payment_plugin'] = $plugin_info['plugin'];
            $data['params']['payment_name'] = $plugin_info['name'];

            if (waRequest::post('payment_'.$payment_id)) {
                foreach (waRequest::post('payment_'.$payment_id) as $k => $v) {
                    $data['params']['payment_params_'.$k] = $v;
                }
            }
        }

        // shipping and billing addreses
        if (!empty($data['contact'])) {
            // Make sure all old address data is removed
            if ($id) {
                $opm = new shopOrderParamsModel();
                foreach($opm->get($id) as $k => $v) {
                    if (preg_match('~^(billing|shipping)_address\.~', $k)) {
                        $data['params'][$k] = null;
                    }
                }
            }
            if (!$empty_address && $this->shipping_address) {
                foreach ($this->shipping_address as $k => $v) {
                    $data['params']['shipping_address.'.$k] = $v;
                }
            }

            if ($this->billing_address) {
                foreach ($this->billing_address as $k => $v) {
                    $data['params']['billing_address.'.$k] = $v;
                }
            }
        }
    }

    private function getData($id)
    {
        $data = $id ? $this->getEditData($id) : $this->getAddData();
        if (!$data) {
            return array();
        }
        $data['comment'] = waRequest::post('comment', null, waRequest::TYPE_STRING_TRIM);
        $data['shipping'] = waRequest::post('shipping', 0);
        $data['discount'] = waRequest::post('discount', 0);
        $data['tax'] = 0;
        $data['total'] = $this->calcTotal($data);
        $data['params']['storefront'] = waRequest::post('storefront', null, waRequest::TYPE_STRING_TRIM);
        $data['params']['storefront'] = ifempty($data['params']['storefront']);
        return $data;
    }

    private function post($name = null, $default = null, $ns = null, $type = null)
    {
        $data = waRequest::post($name, $default, $type);
        if ($ns === null) {
            return $data;
        }
        if (isset($data[$ns])) {
            return $data[$ns];
        }
        return array();
    }

    private function validate($data, $order_id)
    {
        if (empty($data['items'])) {
            $this->errors['order']['common'] = _w('Please add at least one product to save this order');
        }

        if (!wa('shop')->getSetting('ignore_stock_count')) {
            $this->validateExceedStocksCount($data, $order_id);
        }

        return empty($this->errors);
    }

    private function validateExceedStocksCount($data, $order_id)
    {
        $sku_ids = array();

        // calc currenct quantity usage
        $usage = array();
        foreach ($data['items'] as $i) {
            if ($i['type'] == 'product') {
                if ($i['stock_id']) {
                    if (!isset($usage[$i['sku_id']][$i['stock_id']])) {
                        $usage[$i['sku_id']][$i['stock_id']] = 0;
                    }
                    $usage[$i['sku_id']][$i['stock_id']] += $i['quantity'];
                } else {
                    if (!isset($usage[$i['sku_id']])) {
                        $usage[$i['sku_id']] = 0;
                    }
                    $usage[$i['sku_id']] += $i['quantity'];
                }
                $sku_ids[] = $i['sku_id'];
            }
        }

        // calc old quantity usage of this order (if order is new, than array will be empty)
        $old_usage = array();
        foreach ($this->getModel('order_items')->getByField(
                array('order_id' => $order_id, 'type' => 'product'),
                'id'
        ) as $i)
        {
            if ($i['stock_id']) {
                if (!isset($old_usage[$i['sku_id']][$i['stock_id']])) {
                    $old_usage[$i['sku_id']][$i['stock_id']] = 0;
                }
                $old_usage[$i['sku_id']][$i['stock_id']] += $i['quantity'];
            } else {
                if (!isset($old_usage[$i['sku_id']])) {
                    $old_usage[$i['sku_id']] = 0;
                }
                $old_usage[$i['sku_id']] += $i['quantity'];
            }
            $sku_ids[] = $i['sku_id'];
        }

        $sku_ids = array_unique($sku_ids);

        // calc stock counts
        $sku_stocks = $this->getSkuStocks($sku_ids);
        $skus = $this->getModel('product_skus')->getByField('id', $sku_ids, 'id');
        $counts = array();
        foreach ($sku_stocks as $sku_id => &$stock) {
            if (empty($stock)) {
                if (isset($skus[$sku_id]['count'])) {
                    $counts[$sku_id] = $skus[$sku_id]['count'];
                }
            } else {
                foreach ($stock as $stock_id => $st) {
                    $counts[$sku_id][$stock_id] = $st['count'];
                }
            }
        }

        // summarize stock counts with old usage as if temporary return items to stocks
        foreach ($old_usage as $sku_id => $ou) {
            if (!isset($counts[$sku_id])) {
                continue;
            }
            if (!is_array($counts[$sku_id])) {
                $cnt = array_sum((array)$ou);
                if ($counts[$sku_id] !== null) {
                    $counts[$sku_id] += $cnt;
                }
            } else {
                if (is_array($ou)) {
                    foreach ($ou as $stock_id => $cnt) {
                        if (isset($counts[$sku_id][$stock_id])) {
                            $counts[$sku_id][$stock_id] += $cnt;
                        }
                    }
                } else {
                    $stock_ids = array_keys($counts[$sku_id]);
                    $first_stock_id = reset($stock_ids);
                    $counts[$sku_id][$first_stock_id] += $ou;
                }
            }
        }

        // AND NOW check CURRENT USAGE does not exceed COUNTs in stocks
        $error_sku_id = null;
        foreach ($usage as $sku_id => $u) {
            if (!isset($counts[$sku_id])) {
                continue;
            }
            if (is_array($u)) {
                foreach ($u as $stock_id => $cnt) {
                    if (isset($old_usage[$sku_id][$stock_id]) && $old_usage[$sku_id][$stock_id] == $cnt) {
                        continue;
                    }
                    if (isset($counts[$sku_id][$stock_id]) && $cnt > $counts[$sku_id][$stock_id]) {
                        $error_sku_id = $sku_id;
                        break 2;
                    }
                }
            } else {
                if ($counts[$sku_id] !== null && $u > $counts[$sku_id]) {
                    $error_sku_id = $sku_id;
                    break;
                }
            }
        }

        // Error for some sku
        if ($error_sku_id) {
            $sku = $this->getModel('product_skus')->getById($error_sku_id);
            $product_id = $sku['product_id'];
            $this->errors['order']['product'][$product_id]['sku_id'] = $error_sku_id;
            $this->errors['order']['product'][$product_id]['quantity'] = _w('The number of items your can add to the order is limited by the stock level');
        }

    }

    private function validateEditDataStocks()
    {
        $skus   = $this->post('sku', array(), 'edit');
        $stocks = $this->post('stock', array(), 'edit');
        $items  = $this->post('item', array(), 'edit');

        $sku_ids = array();
        foreach ($items as $index => $item_id) {
            $sku_ids[] = $skus[$item_id];
        }

        $sku_stocks = $this->getSkuStocks($sku_ids);
        foreach ($items as $index => $item_id) {
            $sku_id   = $skus[$item_id];
            $stock_id = $stocks[$item_id];
            if (empty($stock_id) && !empty($sku_stocks[$sku_id])) {
                $this->errors['order']['items'][$index]['stock_id'] = _w('Select stock');
            }
        }

        return empty($this->errors);
    }

    private function getEditData($order_id)
    {
        if (!$this->validateEditDataStocks()) {
            return array();
        }

        $items      = $this->post('item', array(), 'edit');
        $products   = $this->post('product', array(), 'edit');
        $skus       = $this->post('sku', array(), 'edit');
        $services   = $this->post('service', array(), 'edit');
        $variants   = $this->post('variant', array(), 'edit');
        $names      = $this->post('name', array(), 'edit');
        $prices     = $this->post('price', array(), 'edit');
        $quantities = $this->post('quantity', array(), 'edit');
        $stocks     = $this->post('stock', array(), 'edit');


        $product_ids = array();
        $sku_ids     = array();
        $service_ids = array();
        $variant_ids = array();
        $quantity = 0;

        $data = array(
            'items' => array()
        );

        foreach ($items as $index => $item_id) {
            $product_ids[] = $products[$item_id];
            $sku_ids[] = $skus[$item_id];

            $quantity = $quantities[$item_id];
            $data['items'][] = array(
                'id' => $item_id,
                'product_id' => $products[$item_id],
                'sku_id' => $skus[$item_id],
                'type' => 'product',
                'service_id' => null,
                'service_variant_id' => null,
                'price' => $prices[$item_id],
                'quantity' => $quantities[$item_id],
                'stock_id' => !empty($stocks[$item_id]) ? $stocks[$item_id] : null,
            );

            if (!empty($services[$index])) {
                foreach ($services[$index] as $group => $services_grouped) {
                    foreach ($services_grouped as $k => $service_id) {
                        $service_ids[] = $service_id;
                        $pitem = &$data['items'][];
                        $pitem = array(
                            'product_id' => $products[$item_id],
                            'sku_id' => $skus[$item_id],
                            'type' => 'service',
                            'service_id' => $service_id,
                            'price' => $prices[$group][$k],
                            'quantity' => $quantity,
                            'service_variant_id' => null
                        );
                        if ($group == 'item') {        // it's item for update: $k is ID of item
                            $pitem['id'] = $k;
                        } else {
                            $pitem['parent_id'] = $item_id;
                            $pitem['type'] = 'service';
                        }

                        if (!empty($variants[$index][$service_id])) {
                            $variant_ids[] = $variants[$index][$service_id];
                            $pitem['service_variant_id'] = $variants[$index][$service_id];
                        }
                        unset($pitem);
                    }
                }
            }
        }

        if ($product_ids) {
            $products = $this->getFields($product_ids, 'product', 'name,tax_id, currency');
            $skus     = $this->getFields($sku_ids, 'product_skus', 'name, sku, purchase_price');
            $services = $this->getFields($service_ids, 'service', 'name,tax_id');
            $variants = $this->getFields($variant_ids, 'service_variants');
            $order_currency = $this->getModel()->select('currency')->where('id=?', $order_id)->fetchField();
            foreach ($data['items'] as &$item) {
                // items with id mean for updating (old items)
                if (isset($item['id'])) {
                    if ($item['service_id']) {
                        if (isset($names[$item['id']])) {
                            $item['name'] = $names[$item['id']];
                        } else {
                            if ($variants[$item['service_variant_id']]['name']) {
                                $item['name'] = "{$services[$item['service_id']]['name']} ({$variants[$item['service_variant_id']]['name']})";
                            } else {
                                $item['name'] = "{$services[$item['service_id']]['name']}";
                            }
                        }
                        if (isset($services[$item['service_id']])) {
                            $item['tax_id'] = $services[$item['service_id']]['tax_id'];
                        } else {
                            $item['tax_id'] = null;
                        }
                        continue;
                    }

                    if (isset($names[$item['id']])) {
                        $item['name'] = $names[$item['id']];
                    } else {
                        $item['name'] = $products[$item['product_id']]['name'];
                        if ($skus[$item['sku_id']]['name']) {
                            $item['name'] .= ' ('.$skus[$item['sku_id']]['name'].')';
                        }
                    }
                    if (isset($products[$item['product_id']])) {
                        $item['tax_id'] = $products[$item['product_id']]['tax_id'];
                    } else {
                        $item['tax_id'] = null;
                    }
                    if (isset($skus[$item['sku_id']])) {
                        $item['sku_code'] = $skus[$item['sku_id']]['sku'];
                        if (isset($products[$item['product_id']])) {
                            $item['purchase_price'] = shop_currency($skus[$item['sku_id']]['purchase_price'], $products[$item['product_id']]['currency'], $order_currency, false);
                        }
                    }
                } else {
                    if ($item['service_id']) {
                        if ($variants[$item['service_variant_id']]['name']) {
                            $item['name'] = "{$services[$item['service_id']]['name']} ({$variants[$item['service_variant_id']]['name']})";
                        } else {
                            $item['name'] = "{$services[$item['service_id']]['name']}";
                        }
                        if (isset($services[$item['service_id']])) {
                            $item['tax_id'] = $services[$item['service_id']]['tax_id'];
                        } else {
                            $item['tax_id'] = null;
                        }
                    } else {
                        $item['sku_code'] = $skus[$item['sku_id']]['sku'];
                        $item['purchase_price'] = shop_currency($skus[$item['sku_id']]['purchase_price'], $products[$item['product_id']]['currency'], $order_currency, false);
                    }
                }
            }
            unset($item);
        }
        $data['items'] = array_merge($data['items'], $this->getItems($order_currency));
        return $data;
    }

    private function getAddData()
    {
        $currency = waRequest::post('currency');
        if (!$currency) {
            $currency = $this->getConfig()->getCurrency();
            $rate = 1;
        } else {
            $currency_model = new shopCurrencyModel();
            $rate = $currency_model->getRate($currency);
        }
        return array(
            'currency' => $currency,
            'rate' => $rate,
            'items' => $this->getItems($currency),
        );
    }

    private function getItems($order_currency)
    {
        $data = array();
        $products   = $this->post('product', array(), 'add');
        if (!$products) {
            return $data;
        }

        $skus       = $this->post('sku', array(), 'add');
        $prices     = $this->post('price', array(), 'add');
        $quantities = $this->post('quantity', array(), 'add');
        $services   = $this->post('service', array(), 'add');
        $variants   = $this->post('variant', array(), 'add');
        $stocks     = $this->post('stock', array(), 'add');

        $product_ids = array();
        $sku_ids     = array();
        $service_ids = array();
        $variant_ids = array();
        $quantity = 0;

        foreach ($products as $index => $product_id) {
            $product_ids[] = (int)$product_id;

            $sku_id = $skus[$index];
            $sku_ids[] = (int)$sku_id;
            $quantity = $quantities[$index]['product'];
            $data[] = array(
                'name' => '',
                'product_id' => $product_id,
                'sku_id' => $sku_id,
                'type' => 'product',
                'service_id' => null,
                'price' => $prices[$index]['product'],
                'currency' => '',
                'quantity' => $quantity,
                'service_variant_id' => null,
                'stock_id' => !empty($stocks[$index]['product']) ? $stocks[$index]['product'] : null
            );
            if (!empty($services[$index])) {
                foreach ($services[$index] as $service_id) {
                    $service_ids[] = (int)$service_id;
                    $item = array(
                        'name' => '',
                        'product_id' => $product_id,
                        'sku_id' => $skus[$index],
                        'type' => 'service',
                        'service_id' => $service_id,
                        'price' => $prices[$index]['service'][$service_id],
                        'currency' => '',
                        'quantity' => $quantity,
                        'service_variant_id' => null,
                        'stock_id' => null
                    );
                    if (!empty($variants[$index][$service_id])) {
                        $variant_ids[] = (int)$variants[$index][$service_id];
                        $item['service_variant_id'] = $variants[$index][$service_id];
                    }
                    $data[] = $item;
                }
            }
        }

        $products = $this->getFields($product_ids, 'product', 'name, tax_id, currency');
        $skus = $this->getFields($sku_ids, 'product_skus', 'name, sku, purchase_price');
        $services = $this->getFields($service_ids, 'service', 'name, tax_id');
        $variants = $this->getFields($variant_ids, 'service_variants');

        foreach ($data as &$item) {
            if ($item['service_id']) {
                //$item['tax_id'] = $services[$item['service_id']]['tax_id'];
                $name = $services[$item['service_id']]['name'];
                if ($item['service_variant_id']) {
                    if ($variants[$item['service_variant_id']]['name']) {
                        $name .= " ({$variants[$item['service_variant_id']]['name']})";
                    }
                }
                $item['tax_id'] = $services[$item['service_id']]['tax_id'];
            } else {
                $name = $products[$item['product_id']]['name'];
                if ($skus[$item['sku_id']]['name']) {
                    $name .= ' ('.$skus[$item['sku_id']]['name'].')';
                }
                $item['sku_code'] = $skus[$item['sku_id']]['sku'];
                $item['purchase_price'] = shop_currency($skus[$item['sku_id']]['purchase_price'], $products[$item['product_id']]['currency'], $order_currency, false);
                $item['tax_id'] = $products[$item['product_id']]['tax_id'];
            }
            $item['name'] = $name;
        }
        unset($item);
        return $data;
    }

    public function calcTotal($data)
    {
        $total = 0;
        foreach ($data['items'] as $item) {
            $total += $this->cast($item['price'])*(int)$item['quantity'];
        }
        if ($total == 0) {
            return $total;
        }
        return $total - $this->cast($data['discount']) + $this->cast($data['shipping']);
    }

    private function cast($value)
    {
        if (strpos($value, ',') !== false) {
            $value = str_replace(',', '.', $value);
        }
        return str_replace(',', '.', (double)$value);
    }

    public function getCustomerData()
    {
        return waRequest::post('customer');
    }

    public function getSkuStocks($sku_ids)
    {
        if (!$sku_ids) {
            return array();
        }
        $sku_stocks = $this->getModel('product_stocks')->
            select('*')->
            where("sku_id IN (".implode(',', $sku_ids).")")->
            order('sku_id')->
            fetchAll();

        $data = array();
        foreach ($sku_ids as $sku_id) {
            $data[$sku_id] = array();
        }

        $sku_id = null;
        foreach ($sku_stocks as $item) {
            if ($item['sku_id'] != $sku_id) {
                $sku_id = $item['sku_id'];
            }
            $data[$sku_id][$item['stock_id']] = $item;
        }
        return $data;
    }

    public function getFields(array $ids, $model_name, $fields = 'name')
    {
        if (!$ids) {
            return array();
        }
        return $this->getModel($model_name)->select('id, '.$fields)->where("id IN (".implode(',', $ids).")")->fetchAll('id');
    }

    public function getCurrencies()
    {
        return $this->getModel('currency')->getCurrencies();
    }

    /**
     * @param string $name
     * @return shopOrderModel
     */
    public function getModel($name = 'order')
    {
        if (!isset($this->models[$name])) {
            if ($name == 'product') {
                $this->models[$name] = new shopProductModel();
            } else if ($name == 'product_skus') {
                $this->models[$name] = new shopProductSkusModel();
            } else if ($name == 'product_stocks') {
                $this->models[$name] = new shopProductStocksModel();
            } else if ($name == 'currency') {
                $this->models[$name] = new shopCurrencyModel();
            } else if ($name == 'order_items') {
                $this->models[$name] = new shopOrderItemsModel();
            } else if ($name == 'service') {
                $this->models[$name] = new shopServiceModel();
            } else if ($name == 'service_variants') {
                $this->models[$name] = new shopServiceVariantsModel();
            } else {
                $this->models[$name] = new shopOrderModel();
            }
        }
        return $this->models[$name];
    }

    public function workupOrder($order)
    {
        if (!empty($order['items'])) {
            foreach ($order['items'] as &$item) {
                $item['name'] = htmlspecialchars($item['name']);
                unset($item);
            }
        }
        $order['contact']['name'] = htmlspecialchars($order['contact']['name']);
        $orders = array($order);
        shopHelper::workupOrders($orders);
        return $orders[0];
    }
}
