<?php
/**
 * Note: all prices in this table (price and purchase_price)
 * are stored in shop_order.curency, not the default shop currency.
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
     *  prodct item, related services items, product items, related services items and so on
     * */
    private function _getItems($order_id)
    {
        $items = array();

        // important: order metters!

        $product_items = $this->query("
            SELECT oi.*, p.image_id, p.image_filename, s.image_id sku_image_id, p.ext, s.file_name, s.file_size FROM `{$this->table}` oi
            LEFT JOIN shop_product p ON oi.product_id = p.id
            LEFT JOIN shop_product_skus s ON oi.sku_id = s.id
            WHERE order_id = ".(int)$order_id." AND type='product'
            ORDER BY oi.id
        ")->fetchAll('id');

        $service_items = array();

        $parent_id = 0;
        foreach ($this->query("
            SELECT * FROM `{$this->table}`
            WHERE order_id = ".(int)$order_id." AND type='service'
            ORDER BY parent_id, id
        ") as $item) {
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
            return $this->_getItems($order_id);
        }

        $order = $this->getOrder($order_id);

        $data = array();
        $type = null;
        foreach ($this->_getItems($order_id) as $item) {
            if ($item['type'] == 'product') {
                $sku_id = $item['sku_id'];
                $product = &$data[];
                $product = $this->getProductInfo($item['product_id'], $sku_id, $order_id);

                if (empty($product)) {
                    $product = array(
                        'id'   => $item['product_id'],
                        'fake' => true,
                        'skus' => array()
                    );
                }

                if (!isset($product['skus'][$sku_id])) {
                    unset($product['skus']);
                    $product['skus'][$sku_id] = array(
                        'id' => $sku_id,
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
                        'id' => $service_id,
                        'name' => '',
                        'fake' => true,
                        'variant_id' => '',
                        'variants' => array()
                    );
                }
                $product['services'][$service_id]['item'] = $this->formatItem($item);
                if (!empty($product['services'][$service_id]['fake'])) {
                    $product['services'][$service_id]['price'] = $product['services'][$service_id]['item']['price'];
                }
            }
            if (empty($product['fake'])) {
                $this->workupProduct($product, $order_id);
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

        $data['price']     = (float) $currency_model->convertByRate($data['price'], 1, $rate);
        $data['max_price'] = (float) $currency_model->convertByRate($data['max_price'], 1, $rate);
        $data['min_price'] = (float) $currency_model->convertByRate($data['min_price'], 1, $rate);
        $data['skus']      = $product->skus;

        foreach ($data['skus'] as &$sku) {
            $sku['price'] = (float) $currency_model->convertByRate($sku['primary_price'], 1, $rate);
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
                $service['price'] = (float) ($service['price']/100) * $sku_price;
            } else {
                $service['price'] = (float) $currency_model->convertByRate($service['price'], 1, $rate);
            }
            foreach ($service['variants'] as &$variant) {
                if ($service['currency'] == '%') {
                    $variant['percent_price'] = $variant['price'];
                    $variant['price'] = (float) ($variant['price']/100) * $sku_price;
                } else {
                    $variant['price'] = (float) $currency_model->convertByRate($variant['primary_price'], 1, $rate);
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
            $order    = $this->getOrder($order_id);
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
            $order    = $this->getOrder($order_id);
            $currency = $order['currency'];
        }
        foreach ($services as &$service) {
            $default_price = null;
            $default_percent_price = null;
            foreach ($service['variants'] as &$variant) {
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
            $order    = $this->getOrder($order_id);
            $rate     = $order['rate'];
            $currency = $order['currency'];
        } else {
            if (!$currency) {
                $currency = $this->getCurrency();
            }
            $rate = $currency_model->getRate($currency);
        }

        $data = $this->getModel('sku')->getSku($sku_id);
        $data['price']     = (float) $currency_model->convertByRate($data['primary_price'], 1, $rate);
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

    private function getModel($name)
    {
        if ($name == 'product') {
            return new shopProductModel();
        } else if ($name == 'sku') {
            return new shopProductSkusModel();
        } else if ($name == 'service') {
            return new shopProductServicesModel();
        } else if ($name == 'order') {
            return new shopOrderModel();
        } else if ($name == 'currency') {
            return new shopCurrencyModel();
        } else {
            return $this;
        }
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
                    if (isset($diff['stock_id']) || isset($diff['sku_id'])) {
                        if (!isset($sku_stock[$old_item['sku_id']][$old_item['stock_id']])) {
                            $sku_stock[$old_item['sku_id']][$old_item['stock_id']] = 0;
                            $sku_stock[$item['sku_id']][$item['stock_id']] = 0;
                        }
                        $sku_stock[$old_item['sku_id']][$old_item['stock_id']] += $old_item['quantity'];
                        $sku_stock[$item['sku_id']][$item['stock_id']] -= $item['quantity'];
                    } else if (isset($diff['quantity'])) {
                        if (!isset($sku_stock[$item['sku_id']][$item['stock_id']])) {
                            $sku_stock[$item['sku_id']][$item['stock_id']] = 0;
                        }
                        $sku_stock[$item['sku_id']][$item['stock_id']] += $old_item['quantity'] - $item['quantity'];
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
                $sku_stock[$old_item['sku_id']][$old_item['stock_id']] = $old_item['quantity'];
            }
            $this->deleteById(array_keys($old_items));
        }


        // Update stock count, but take into account 'update_stock_count_on_create_order'-setting and order state
        $order_model = new shopOrderModel();
        $order = $order_model->getById($order_id);
        $app_settings_model = new waAppSettingsModel();
        $update_on_create = $app_settings_model->get('shop', 'update_stock_count_on_create_order');
        if ($order['state_id'] != 'new' || $update_on_create) {
            $this->updateStockCount($sku_stock);
        }
    }

    public function updateStockCount($data)
    {
        if (!$data) {
            return;
        }
        $product_skus_model   = new shopProductSkusModel();
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
            $sku_id = (int) $sku_id;
            if (!isset($skus[$sku_id]['product_id'])) {
                continue;
            }
            $product_id = $skus[$sku_id]['product_id'];
            foreach ($sku_stock as $stock_id => $count) {
                $stock_id = (int) $stock_id;
                if ($stock_id) {
                    $item = $product_stocks_model->getByField(array(
                        'sku_id' => $sku_id,
                        'stock_id' => $stock_id
                    ));
                    if (!$item) {
                        continue;
                    }
                    $product_stocks_model->set(array(
                        'sku_id' => $sku_id,
                        'product_id' => $product_id,
                        'stock_id' => $stock_id,
                        'count' => $item['count'] + $count
                    ));
                } else {

                    $old_count = $product_skus_model->select('count')->where('id=i:sku_id', array('sku_id' => $sku_id))->fetchField();
                    if ($old_count !== null) {
                        $log_data = array(
                            'product_id' => $product_id,
                            'sku_id' => $sku_id,
                            'before_count' => $old_count,
                            'after_count' => $old_count + $count,
                            'diff_count' => $count
                        );
                        $stocks_log_model->insert($log_data);
                    }
                    $this->exec(
                        "UPDATE `shop_product_skus` SET count = count + ({$count})
                        WHERE id = $sku_id"
                    );

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
        $sql = "
            UPDATE `shop_product_skus` sk JOIN (
                SELECT sk.id, SUM(st.count) AS count FROM `shop_product_skus` sk
                JOIN `shop_product_stocks` st ON sk.id = st.sku_id
                WHERE sk.id IN(".implode(',', $sku_ids).")
                GROUP BY sk.id
                ORDER BY sk.id
            ) r ON sk.id = r.id
            SET sk.count = r.count
            WHERE sk.count IS NOT NULL";
        $this->exec($sql);

        // correct product counters
        $sql = "
            UPDATE `shop_product` p JOIN (
                SELECT p.id, SUM(sk.count) AS count FROM `shop_product` p
                JOIN `shop_product_skus` sk ON p.id = sk.product_id
                WHERE p.id IN(".implode(',', array_unique($product_ids)).") AND sk.available = 1
                GROUP BY p.id
                ORDER BY p.id
            ) r ON p.id = r.id
            SET p.count = r.count
            WHERE p.count IS NOT NULL";
        $this->exec($sql);
    }

    public function getCurrency()
    {
        if ($this->primary_currency === null) {
            $this->primary_currency = wa('shop')->getConfig()->getCurrency();
        }
        return $this->primary_currency;
    }

    public function formatItem($item)
    {
        $item['price'] = (float)$item['price'];
        return $item;
    }
}