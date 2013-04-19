<?php

class shopBackendAutocompleteController extends waController
{
    protected $limit = 10;
    public function execute()
    {
        $data = array();
        $q = waRequest::get('term', '', waRequest::TYPE_STRING_TRIM);
        if ($q) {
            $type = waRequest::get('type', 'product', waRequest::TYPE_STRING_TRIM);
            if ($type == 'sku') {
                $data = $this->skusAutocomplete($q);
            } else if ($type == 'order') {
                $data = $this->ordersAutocomplete($q);
            } else if ($type == 'customer') {
                $data = $this->customersAutocomplete($q);
            } else {
                $data = $this->productsAutocomplete($q);
            }
            $data = $this->formatData($data, $type);
        }
        echo json_encode($data);
    }

    private function formatData($data, $type)
    {
        if ($type == 'order') {
            shopHelper::workupOrders($data);
            foreach ($data as &$item) {
                $item['value'] = shopHelper::encodeOrderId($item['id']);
                $item['label'] = '';
                if (!empty($item['icon'])) {
                    $item['label'] .= "<i class='{$item['icon']}'></i>";
                }
                $item['label'] .= $item['value']." ".$item['total_str'];
                $item['label'] .= ' <span class="hint">'.htmlspecialchars($item['customer_name']).'</span>';
                $item = array(
                    'id' => $item['id'],
                    'value' => $item['value'],
                    'label' => $item['label'],
                );
            }
            return $data;
        }
        $with_counts = waRequest::get('with_counts', 0, waRequest::TYPE_INT) && $type == 'product';
        foreach ($data as &$item) {
            $item['label'] = htmlspecialchars($item['value']);
            if ($with_counts) {
                $item['label'] .= ' '.shopHelper::getStockCountIcon($item['count'], null, true);
            }
        }
        return $data;
    }

    public function skusAutocomplete($q)
    {
        $product_skus_model = new shopProductSkusModel();
        $q = $product_skus_model->escape($q, 'like');
        return $product_skus_model->
            select('id, name AS value')->
            where("name LIKE '{$q}%' OR sku LIKE '{$q}%'")-> // TODO: change name to full_name
            limit($this->limit)->
            fetchAll();
    }

    public function productsAutocomplete($q)
    {
        $product_model = new shopProductModel();
        $q = $product_model->escape($q, 'like');
        $fields = 'id, name AS value, price, count';

        $products = $product_model->select($fields)
            ->where("name LIKE '$q%'")
            ->limit($this->limit)
            ->fetchAll();
        $count = count($products);

        if ($count < $this->limit) {
            $product_skus_model = new shopProductSkusModel();
            $product_ids = array_keys($product_skus_model->select('id, product_id')
                ->where("sku LIKE '$q%'")
                ->limit($this->limit)
                ->fetchAll('product_id'));
            if ($product_ids) {
                $data = $product_model->select($fields)
                    ->where('id IN ('.implode(',', $product_ids).')')
                    ->limit($this->limit - $count)
                    ->fetchAll();
                $products = array_merge($products, $data);
            }
        }
        // try find with LIKE %query%
        if (!$products) {
            $products = $product_model->select($fields)
                ->where("name LIKE '%$q%'")
                ->limit($this->limit)
                ->fetchAll();
        }
        $currency = wa()->getConfig()->getCurrency();
        foreach ($products as &$p) {
            $p['price_str'] = wa_currency($p['price'], $currency);
        }
        return $products;
    }

    private function getOrders($q, $limit = null)
    {
        $order_model = new shopOrderModel();
        $limit = $limit ? $limit : $this->limit;
        $orders = $order_model->autocompleteById($q, $limit);
        if (!$orders) {
            return $order_model->autocompleteById($q, $limit, true);
        }
        return $orders;
    }

    public function ordersAutocomplete($q)
    {
        // first, assume $q is encoded $order_id, so decode
        $dq = shopHelper::decodeOrderId($q);
        if (!$dq) {
            $dq = self::decodeOrderId($q);
        }
        if ($dq) {
            $orders = $this->getOrders($dq);
        } else {
            $orders = array();
        }

        $cnt = count($orders);
        if ($cnt < $this->limit) {
            $orders = array_merge($orders, $this->getOrders($q, $this->limit - $cnt));
        }
        return $orders;
    }

    /**
     * Tries to decode order_id ignoring all non-digit characters in string.
     * Helps to implement human-intuitive searching over decoded IDs.
     */
    public static function decodeOrderId($encoded_id)
    {
        $format = wa('shop')->getConfig()->getOrderFormat();
        $format = str_replace('%', 'garbage', $format);
        $format = str_replace('{$order.id}', '%', $format);
        $format = preg_split('~[^0-9%]~', $format);
        foreach($format as $part) {
            if (strpos($part, '%')) {
                $format = $part;
                break;
            }
        }
        if (!is_string($format)) {
            return '';
        }

        $format = '/^'.str_replace('%', '(\d+)', preg_quote($format)).'$/';
        if (!preg_match($format, $encoded_id, $m)) {
            return '';
        }
        return $m[1];
    }


    public function customersAutocomplete($q)
    {
        $scm = new shopCustomerModel();
        $result = array();
        list($list, $total) = $scm->getList(null, $q, 0, $this->limit);
        foreach($list as $c) {
            $result[] = array(
                'value' => $c['name'],
                'id' => $c['id'],
            );
        }
        return $result;
    }
}

