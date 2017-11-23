<?php

/**
 * Note: all prices in this table (price and purchase_price)
 * are stored in shop_order.currency, not the default shop currency.
 * shop_order.rate contains the currency rate valid at the time of the order.
 */
class shopOrderItemsModel extends waModel
{
    protected $table = 'shop_order_items';

    /**
     * @var string
     */
    private $primary_currency;

    /**
     * @var array
     */
    private $order;

    /*
     * Get items with correct order:
     *  product item, related services items, product items, related services items and so on
     * */
    private function getRawItems($order_id)
    {
        // important: order meters!
        $order_id = intval($order_id);
        $sql = <<<SQL
SELECT
  oi.*,
  p.image_id,
  p.image_filename,
  s.image_id sku_image_id,
  p.ext,
  s.file_name,
  s.file_size
FROM `{$this->table}` oi
  LEFT JOIN shop_product p ON oi.product_id = p.id
  LEFT JOIN shop_product_skus s ON oi.sku_id = s.id
WHERE order_id = {$order_id} AND type='product'
ORDER BY oi.id
SQL;

        $product_items = $this->query($sql)->fetchAll('id');

        $service_items = array();

        $sql = <<<SQL
SELECT *
FROM `{$this->table}`
WHERE order_id = {$order_id} AND type='service'
ORDER BY parent_id, id
SQL;

        $parent_id = 0;
        foreach ($this->query($sql) as $item) {
            if ($parent_id != $item['parent_id']) {
                $parent_id = $item['parent_id'];
                $service_items[$parent_id] = array();
            }
            $service_items[$parent_id][$item['id']] = $item;
        }

        $data = array();
        $image_ids = array();
        foreach ($product_items as $product_item) {
            if ($product_item['sku_image_id'] && ($product_item['sku_image_id'] != $product_item['image_id'])) {
                $image_ids[] = $product_item['sku_image_id'];
            }
            $data[$product_item['id']] = $product_item;
            if (!empty($service_items[$product_item['id']])) {
                $data += $service_items[$product_item['id']];
            }
        }
        if ($image_ids) {
            $image_model = new shopProductImagesModel();
            $images = $image_model->getById($image_ids);
            foreach ($data as $item_id => &$item) {
                if (!empty($item['sku_image_id']) && ($item['sku_image_id'] != $item['image_id']) && isset($images[$item['sku_image_id']])) {
                    $item['image_id'] = $item['sku_image_id'];
                    $item['ext'] = $images[$item['sku_image_id']]['ext'];
                    $item['image_filename'] = $images[$item['sku_image_id']]['filename'];
                }
            }
            unset($item);
        }

        return $data;
    }

    public function getItems($order_id, $extend = false)
    {
        if (!$extend) {
            return $this->getRawItems($order_id);
        }

        $order = $this->getOrder($order_id);

        $data = array();
        $type = null;
        foreach ($this->getRawItems($order['id']) as $item) {
            if ($item['type'] == 'product') {
                $sku_id = $item['sku_id'];
                $product = &$data[];
                $product = $this->getProductInfo($item['product_id'], $sku_id, $order['id']);

                if (empty($product)) {
                    $product = array(
                        'id'       => $item['product_id'],
                        'fake'     => true,
                        'skus'     => array(),
                        'currency' => $order['currency'],
                    );
                }

                if (!isset($product['skus'][$sku_id])) {
                    unset($product['skus']);
                    $product['skus'][$sku_id] = array(
                        'id'   => $sku_id,
                        'fake' => true
                    );
                } else {
                    $product['skus'][$sku_id]['price'] = $item['price'];
                }
                $product['item'] = $this->formatItem($item);
                if (!empty($product['fake'])) {
                    $product['price'] = $product['item']['price'];
                }
                if (!empty($product['skus'][$sku_id]['fake'])) {
                    // parse string looks like this "ProductName (SkuName)"
                    if (preg_match('!\(([\s\S]+)\)$!', $product['item']['name'], $m)) {
                        // fake means deleted
                        $name_of_fake_sku = $m[1];
                        if ($product['item']['sku_code']) {
                            $name_of_fake_sku .= ' ('.$product['item']['sku_code'].')';
                        }
                    } else {
                        $name_of_fake_sku = $product['item']['sku_code'];
                    }
                    $product['skus'][$sku_id]['name'] = $name_of_fake_sku;
                }
            }
            $service_id = $item['service_id'];
            $service_variant_id = $item['service_variant_id'];

            if ($item['type'] == 'service') {
                if (!isset($product['services'][$service_id]['variants'][$service_variant_id])) {
                    $product['services'][$service_id] = array(
                        'id'         => $service_id,
                        'name'       => '',
                        'fake'       => true,
                        'variant_id' => '',
                        'variants'   => array(),
                        'currency'   => $order['currency'],
                    );
                }
                $product['services'][$service_id]['item'] = $this->formatItem($item);
                if (!empty($product['services'][$service_id]['fake'])) {
                    $product['services'][$service_id]['price'] = $product['services'][$service_id]['item']['price'];
                }
            }
            if (empty($product['fake'])) {
                $this->workupProduct($product, $order);
            }
        }
        return $data;
    }

    private function getProductInfo($product_id, $sku_id = null, $order_id = null, $currency = null)
    {
        $product = new shopProduct($product_id);
        $data = $product->getData();
        if (!$data) {
            return array();
        }

        $rate = 1;
        $currency_model = $this->getModel('currency');
        if ($order_id) {
            $order = $this->getOrder($order_id);
            $rate = $order['rate'];
        } elseif ($currency) {
            $rate = $currency_model->getRate($currency);
        }

        $data['price'] = (float)$currency_model->convertByRate($data['price'], 1, $rate);
        $data['max_price'] = (float)$currency_model->convertByRate($data['max_price'], 1, $rate);
        $data['min_price'] = (float)$currency_model->convertByRate($data['min_price'], 1, $rate);
        $data['skus'] = $product->skus;

        foreach ($data['skus'] as &$sku) {
            $sku['price'] = (float)$currency_model->convertByRate($sku['primary_price'], 1, $rate);
        }
        unset($sku);

        if ($sku_id === null) {
            $sku_id = count($data['skus']) > 1 ? $product->sku_id : null;
        }

        if ($sku_id && isset($data['skus'][$sku_id])) {
            $sku_price = $data['skus'][$sku_id]['price'];
        } else {
            $sku_price = $data['price'];
        }

        $data['services'] = $this->getServices($product_id, $sku_id, $rate, $sku_price);
        return $data;
    }

    private function getServices($product_id, $sku_id, $rate, $sku_price)
    {
        $currency_model = $this->getModel('currency');

        $services = $this->getModel('service')->getAvailableServicesFullInfo($product_id, $sku_id);
        foreach ($services as &$service) {
            if ($service['currency'] == '%') {
                $service['percent_price'] = $service['price'];
                $service['price'] = (float)($service['price'] / 100) * $sku_price;
            } else {
                $service['price'] = (float)$currency_model->convertByRate($service['price'], 1, $rate);
            }
            foreach ($service['variants'] as &$variant) {
                if ($service['currency'] == '%') {
                    $variant['percent_price'] = $variant['price'];
                    $variant['price'] = (float)($variant['price'] / 100) * $sku_price;
                } else {
                    $variant['price'] = (float)$currency_model->convertByRate($variant['primary_price'], 1, $rate);
                }
            }
            unset($variant);
        }
        unset($service);

        return $services;
    }

    public function getProduct($product_id, $order_id = null, $currency = null)
    {
        $data = $this->getProductInfo($product_id, null, $order_id, $currency);
        $this->workupProduct($data, $order_id, $currency);
        return $data;
    }

    private function workupProduct(&$product, $order_id = null, $currency = null)
    {
        if (!$currency) {
            $currency = $this->getCurrency();
        }
        if ($order_id) {
            $order = $this->getOrder($order_id);
            $order_id = $order;
            $currency = $order['currency'];
        }

        if ($product['min_price'] == $product['max_price']) {
            $product['price_str'] = wa_currency($product['min_price'], $currency);
            $product['price_html'] = wa_currency_html($product['min_price'], $currency);
        } else {
            $product['price_str'] = wa_currency($product['min_price'], $currency).'...'.wa_currency($product['max_price'], $currency);
            $product['price_html'] = wa_currency_html($product['min_price'], $currency).'...'.wa_currency_html($product['max_price'], $currency);
        }
        if (!empty($product['skus']) && is_array($product['skus'])) {
            foreach ($product['skus'] as &$sku) {
                if (isset($sku['price'])) {
                    $sku['price_str'] = wa_currency($sku['price'], $currency);
                    $sku['price_html'] = wa_currency_html($sku['price'], $currency);
                }
            }
            unset($sku);
        }
        if (!empty($product['services'])) {
            $this->workupServices($product['services'], $order_id, $currency);
        }
    }

    private function workupServices(&$services, $order_id, $currency)
    {
        if ($order_id) {
            $order = $this->getOrder($order_id);
            $currency = $order['currency'];
        }
        foreach ($services as &$service) {
            $default_price = null;
            $default_percent_price = null;
            if (!isset($service['currency'])) {
                $service['currency'] = $currency;
            }
            foreach ($service['variants'] as &$variant) {
                $variant['price'] = waCurrency::round($variant['price'], $currency);
                $variant['price_str'] = ($variant['price'] >= 0 ? '+' : '-').wa_currency($variant['price'], $currency);
                $variant['price_html'] = ($variant['price'] >= 0 ? '+' : '-').wa_currency_html($variant['price'], $currency);
                if ($variant['status'] == shopProductServicesModel::STATUS_DEFAULT) {
                    $default_price = $variant['price'];
                    if ($service['currency'] == '%') {
                        $default_percent_price = $variant['percent_price'];
                    }
                }
            }
            if ($default_price === null) {
                if (isset($service['variants'][$service['variant_id']])) {
                    $default_price = $service['variants'][$service['variant_id']]['price'];
                    if ($service['currency'] == '%') {
                        $default_percent_price = $service['variants'][$service['variant_id']]['percent_price'];
                    }
                } else {
                    reset($service['variants']);
                    $first = current($service['variants']);
                    $default_price = $first['price'];
                    if ($service['currency'] == '%') {
                        $default_percent_price = $first['percent_price'];
                    }
                }
            }
            $service['price'] = $default_price;
            if ($service['currency'] == '%') {
                $service['percent_price'] = $default_percent_price;
            }
            unset($variant);
        }
        unset($service);
    }

    public function getSku($sku_id, $order_id = null, $currency = null)
    {
        $currency_model = $this->getModel('currency');
        if ($order_id) {
            $order = $this->getOrder($order_id);
            $rate = $order['rate'];
            $currency = $order['currency'];
        } else {
            if (!$currency) {
                $currency = $this->getCurrency();
            }
            $rate = $currency_model->getRate($currency);
        }

        $data = $this->getModel('sku')->getSku($sku_id);
        $data['price'] = (float)$currency_model->convertByRate($data['primary_price'], 1, $rate);
        $data['price_str'] = wa_currency($data['price'], $currency);
        $data['price_html'] = wa_currency_html($data['price'], $currency);

        $data['services'] = $this->getServices($data['product_id'], $sku_id, $rate, $data['price']);
        $this->workupServices($data['services'], $order_id, $currency);

        return $data;
    }

    private function getOrder($order_id)
    {
        if (is_array($order_id)) {
            $this->order = $order_id;
        } else {
            if ($this->order === null || $this->order['id'] != $order_id) {
                $this->order = $this->getModel('order')->getById($order_id);
            }
        }
        return $this->order;
    }

    /**
     * @param $name
     * @return shopOrderItemsModel|shopCurrencyModel|shopOrderModel|shopProductModel|shopProductServicesModel|shopProductSkusModel
     */
    private function getModel($name)
    {
        if ($name == 'product') {
            return new shopProductModel();
        } elseif ($name == 'sku') {
            return new shopProductSkusModel();
        } elseif ($name == 'service') {
            return new shopProductServicesModel();
        } elseif ($name == 'order') {
            return new shopOrderModel();
        } elseif ($name == 'currency') {
            return new shopCurrencyModel();
        } else {
            return $this;
        }
    }

    /**
     * Get actual order items stock counts
     * @param $items
     * @return array
     */
    private function getItemsStocks($items)
    {
        $items_stocks = array();

        $sku_ids = array();
        foreach ($items as $item) {
            if (!empty($item['sku_id'])) {
                $sku_ids[] = $item['sku_id'];
            }
        }

        #get actual stock data
        if ($sku_ids) {

            $m = new shopProductStocksModel();
            $stocks = $m->getBySkuId($sku_ids);

            foreach ($stocks as $sku_id => $item_stocks) {
                if (!isset($items_stocks[$sku_id])) {
                    $items_stocks[$sku_id] = array();
                }
                foreach ($item_stocks as $stock_id => $stock) {
                    $items_stocks[$sku_id][$stock_id] = (($stock['count'] === null) || ($stock['count'] > 0));
                }
            }

            foreach ($items_stocks as &$item_stocks) {
                asort($item_stocks);
                unset($item_stocks);
            }
        }
        return $items_stocks;
    }

    public function correctItemsStocks($items, $order_id, $increment = false)
    {
        $order_params_model = new shopOrderParamsModel();
        $stock_id = $order_params_model->getOne($order_id, 'stock_id');

        $items_stocks = $this->getItemsStocks($items);

        $sku_stock = array();

        foreach ($items as $item) {

            #checking stock integrity
            $item_stocks = ifset($items_stocks[$item['sku_id']], array());
            if ($item_stocks) {
                if (!$item['stock_id'] || !isset($item_stocks[$item['stock_id']])) {

                    //counts was moved
                    if ($stock_id && isset($item_stocks[$stock_id])) {
                        $item['stock_id'] = $stock_id;
                    } else {
                        //TODO use stock rules
                        $item['stock_id'] = key($item_stocks);
                    }
                    $this->updateById($item['id'], array('stock_id' => $item['stock_id']));
                }
            } elseif ($item['stock_id']) {
                // stock was deleted
                $item['stock_id'] = 0;
                $this->updateById($item['id'], array('stock_id' => $item['stock_id']));
            }

            #set stock delta
            if (!isset($sku_stock[$item['sku_id']][$item['stock_id']])) {
                $sku_stock[$item['sku_id']][$item['stock_id']] = 0;
            }
            if ($increment) {
                $sku_stock[$item['sku_id']][$item['stock_id']] += $item['quantity'];
            } else {
                $sku_stock[$item['sku_id']][$item['stock_id']] -= $item['quantity'];
            }
        }
        return $sku_stock;
    }

    /**
     * Update items of order
     * Also update stock counts if it's necessary
     *
     * @param array $items
     * @param int $order_id
     */
    public function update($items, $order_id)
    {
        $stocks = shopHelper::getStocks();
        $old_items = $this->getByField('order_id', $order_id, 'id');
        $add = array();
        $update = array();
        $sku_stock = array();

        $parent_id = null;
        foreach ($items as $item) {

            // new item insert
            if (empty($item['id']) || empty($old_items[$item['id']])) {

                $item['order_id'] = $order_id;
                if ($item['type'] == 'product') {
                    $parent_id = $this->insert($item);
                } else {
                    $item['parent_id'] = $parent_id;
                    $add[] = $item;
                }

                // stock count
                if ($item['type'] == 'product') {
                    if (!isset($sku_stock[$item['sku_id']][$item['stock_id']])) {
                        $sku_stock[$item['sku_id']][$item['stock_id']] = 0;
                    }
                    $sku_stock[$item['sku_id']][$item['stock_id']] -= $item['quantity'];
                }

            } else {

                // edit old item
                $item_id = $item['id'];
                $old_item = $old_items[$item_id];
                if ($old_item['type'] == 'product') {
                    $parent_id = $item_id;
                } else {
                    $item['parent_id'] = $parent_id;
                }
                $item['price'] = $this->castValue('float', $item['price']);
                $old_item['price'] = (float)$old_item['price'];
                $diff = array_diff_assoc($item, $old_item);

                // check stock changes
                if ($item['type'] == 'product') {

                    $sku_id = $item['sku_id'];
                    $stock_id = $item['stock_id'];

                    // Reset virtualstock_id to NULL if stock_id changed to something not included in original virtual stock
                    if (array_key_exists('stock_id', $diff)
                        && $old_item['virtualstock_id']
                        && !array_key_exists('virtualstock_id', $diff)
                    ) {
                        $diff['virtualstock_id'] = null;
                        $stock = ifset($stocks['v'.$old_item['virtualstock_id']]);
                        if ($diff['stock_id']
                            && $stock
                            && !empty($stock['substocks'])
                            && in_array($diff['stock_id'], $stock['substocks'])
                        ) {
                            unset($diff['virtualstock_id']);
                        }
                    }
                    if (isset($diff['stock_id']) || isset($diff['sku_id'])) {

                        if (($old_item['stock_id'] == null) || isset($stocks[$old_item['stock_id']])) { #stock was not deleted
                            $old_sku_id = $old_item['sku_id'];

                            if (!isset($sku_stock[$old_sku_id][$old_item['stock_id']])) {
                                $sku_stock[$old_sku_id][$old_item['stock_id']] = 0;
                            }
                            $sku_stock[$old_sku_id][$old_item['stock_id']] += $old_item['quantity'];
                        }

                        if (!isset($sku_stock[$sku_id][$stock_id])) {
                            $sku_stock[$sku_id][$stock_id] = 0;
                        }
                        $sku_stock[$sku_id][$stock_id] -= $item['quantity'];
                    } elseif (isset($diff['quantity'])) {
                        if (!isset($sku_stock[$sku_id][$stock_id])) {
                            $sku_stock[$sku_id][$stock_id] = 0;
                        }
                        $sku_stock[$sku_id][$stock_id] += $old_item['quantity'] - $item['quantity'];
                    }
                }

                if (!empty($diff)) {
                    $update[$item_id] = $diff;
                }
                unset($old_items[$item_id]);
            }
        }

        foreach ($update as $item_id => $item) {
            $this->updateById($item_id, $item);
        }
        if ($add) {
            $this->multipleInsert($add);
        }
        if ($old_items) {
            foreach ($old_items as $old_item) {
                // check stock changes
                if ($old_item['type'] == 'product') {
                    if (!isset($sku_stock[$old_item['sku_id']][$old_item['stock_id']])) {
                        $sku_stock[$old_item['sku_id']][$old_item['stock_id']] = 0;
                    }
                    $sku_stock[$old_item['sku_id']][$old_item['stock_id']] += $old_item['quantity'];
                }
            }
            $this->deleteById(array_keys($old_items));
        }

        // was reducing in past?
        $order_params_model = new shopOrderParamsModel();
        $reduced = $order_params_model->getOne($order_id, 'reduced');
        if ($reduced) {
            foreach ($sku_stock as &$stocks) {
                $stocks = array_filter($stocks);
                unset($stocks);
            }

            if ($sku_stock = array_filter($sku_stock)) {
                $this->updateStockCount($sku_stock);
            }
        }
    }

    /**
     * @param array $data
     * @param int [int][int] $data[$sku_id][$stock_id]
     */
    public function updateStockCount($data)
    {
        if (!$data) {
            return;
        }

        $product_skus_model = new shopProductSkusModel();
        $product_stocks_model = new shopProductStocksModel();
        $stocks_log_model = new shopProductStocksLogModel();

        $sku_ids = array_map('intval', array_keys($data));
        if (!$sku_ids) {
            return;
        }

        $skus = $product_skus_model->select('id,product_id')->where("id IN(".implode(',', $sku_ids).")")->fetchAll('id');
        $sku_ids = array_keys($skus);
        if (!$sku_ids) {
            return;
        }
        $product_ids = array();

        foreach ($data as $sku_id => $sku_stock) {
            $sku_id = (int)$sku_id;
            if (!isset($skus[$sku_id]['product_id'])) {
                continue;
            }
            $product_id = $skus[$sku_id]['product_id'];
            foreach ($sku_stock as $stock_id => $count) {
                $stock_id = (int)$stock_id;

                if ($stock_id) {
                    $item = $product_stocks_model->getByField(array(
                        'sku_id'   => $sku_id,
                        'stock_id' => $stock_id
                    ));

                    if (!$item) {
                        continue;
                    }
                    $product_stocks_model->set(array(
                        'sku_id'     => $sku_id,
                        'product_id' => $product_id,
                        'stock_id'   => $stock_id,
                        'count'      => $item['count'] + $count
                    ));
                } else {
                    $before_count = $product_skus_model->select('count')->where('id=i:sku_id', array('sku_id' => $sku_id))->fetchField();
                    if ($before_count !== null) {
                        $log_data = array(
                            'product_id'   => $product_id,
                            'sku_id'       => $sku_id,
                            'before_count' => $before_count,
                            'after_count'  => $before_count + $count,
                            'diff_count'   => $count
                        );
                        $stocks_log_model->insert($log_data);
                    }
                    $sql = <<<SQL
UPDATE `shop_product_skus`
SET count = count + ({$count})
WHERE id = {$sku_id}
SQL;
                    $this->exec($sql);

                }
                if (isset($skus[$sku_id]['product_id'])) {
                    $product_ids[] = $product_id;
                }
            }
        }

        if (!$product_ids) {
            return;
        }

        // correct sku counters
        $skus = implode(',', $sku_ids);
        $sql = <<<SQL
UPDATE `shop_product_skus` sk
  JOIN (
         SELECT
           sk.id,
           SUM(st.count) AS count
         FROM `shop_product_skus` sk
           JOIN `shop_product_stocks` st ON sk.id = st.sku_id
         WHERE sk.id IN ({$skus})
         GROUP BY sk.id
         ORDER BY sk.id
       ) r ON sk.id = r.id
SET sk.count = r.count
WHERE sk.count IS NOT NULL
SQL;
        $this->exec($sql);

        // correct product counters
        $product_ids = array_unique($product_ids);
        $pm = new shopProductModel();
        foreach ($product_ids as $product_id) {
            $pm->correct($product_id);
        }
    }

    public function getCurrency()
    {
        if ($this->primary_currency === null) {
            $config = wa('shop')->getConfig();
            /**
             * @var shopConfig $config
             */
            $this->primary_currency = $config->getCurrency();
        }
        return $this->primary_currency;
    }

    public function formatItem($item)
    {
        $item['price'] = (float)$item['price'];
        return $item;
    }

    public function deleteStock($stock_id, $target_stock_id = null)
    {
        $data = array(
            'stock_id' => $target_stock_id,
        );
        $this->updateByField('stock_id', $stock_id, $data);
    }
}
