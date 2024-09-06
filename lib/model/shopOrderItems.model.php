<?php

/**
 * Note: all prices in this table (price and purchase_price)
 * are stored in shop_order.currency, not the default shop currency.
 * shop_order.rate contains the currency rate valid at the time of the order.
 */
class shopOrderItemsModel extends waModel implements shopOrderStorageInterface
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
        $order = $order_id;
        if (is_array($order)) {
            $order_id = ifset($order['id']);
        } elseif ($order instanceof shopOrder) {
            $order_id = $order->id;
        } elseif ($extend) {
            $order = $this->getOrder($order);
        }
        $items = $this->getRawItems($order_id);
        shopOrderItemsModel::sortItemsByGeneralSettings($items);

        if ($extend) {
            $items = $this->extendItems($items, $order);
        }

        return $items;
    }

    /**
     * @todo it extends items. why variable name is "product'? Need refactor
     * @param $items
     * @param $order_id
     * @return array
     */
    public function extendItems($items, $order_id)
    {
        $order = $this->getOrder($order_id);

        $data = array();
        $type = null;
        foreach ($items as $item) {
            if ($item['type'] == 'product') {
                $sku_id = $item['sku_id'];
                $product = &$data[];
                $product = $this->getProductInfo($item['product_id'], $sku_id, $order);

                if (empty($product)) {
                    # fake product
                    $product = array(
                        'id'       => $item['product_id'],
                        'fake'     => true,
                        'skus'     => array(),
                        'currency' => $order['currency'],
                    );
                }

                if (!isset($product['skus'][$sku_id])) {
                    unset($product['skus']);
                    $product['skus'] = array();
                    # fake sku
                    $product['skus'][$sku_id] = array(
                        'id'    => $sku_id,
                        'fake'  => true,
                        'price' => $item['price'],
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

                $current_product_name = '';
                if (!empty($product['name'])) {
                    $current_product_name = $product['name'];
                    if (!empty($product['skus'][$sku_id]['name'])) {
                        $current_product_name .= ' ('.$product['skus'][$sku_id]['name'].')';
                    }
                }

                $product['current_product_name'] = $current_product_name;
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

            //set sku image in items
            if (isset($sku_id) && !empty($product['skus'][$sku_id]['image_id'])) {
                $shop_product_images = new shopProductImagesModel();
                $image = $shop_product_images->getById($product['skus'][$sku_id]['image_id']);
                $product = array_merge($product, array(
                    'image_filename' => $image['filename'],
                    'ext'            => $image['ext'],
                    'image_id'       => $image['id'],
                ));
            }
        }
        return $data;
    }

    /**
     * @deprecated
     *
     * If you need to scale this method, remove it from the model.
     *
     * @param $product_id
     * @param null $sku_id
     * @param null $order_id
     * @param null $currency
     * @return array
     */
    private function getProductInfo($product_id, $sku_id = null, $order_id = null, $currency = null)
    {
        /**
         * @var $cache shopProduct[]
         */
        static $cache = array();

        if ($order_id) {
            $order = $this->getOrder($order_id);
        }

        if (is_array($product_id)) {
            $data = $product_id;
            if ($sku_id === null) {
                $sku_id = count($data['skus']) > 1 ? $data['sku_id'] : null;
            }
        } else {
            if (!isset($cache[$product_id])) {
                // Get product with storefront context
                $product_options = [];
                if (!empty($order['params']['storefront'])) {
                    $product_options['storefront_context'] = $order['params']['storefront'];
                }
                $cache[$product_id] = new shopProduct($product_id, $product_options);
            }
            $product = $cache[$product_id];
            $data = $product->getData();
            if (!$data) {
                return array();
            }
            $data['skus'] = $product->skus;
            if ($sku_id === null) {
                $sku_id = count($data['skus']) > 1 ? $product['sku_id'] : null;
            }
        }

        # get currency rate
        $rate = 1;
        $currency_model = $this->getModel('currency');
        if (!empty($order)) {
            $rate = $order['rate'];
        } elseif ($currency) {
            $rate = $currency_model->getRate($currency);
        }

        # convert currency
        $data['price'] = (float)$currency_model->convertByRate($data['price'], 1, $rate);
        $data['max_price'] = (float)$currency_model->convertByRate($data['max_price'], 1, $rate);
        $data['min_price'] = (float)$currency_model->convertByRate($data['min_price'], 1, $rate);

        foreach ($data['skus'] as &$sku) {
            $sku['price'] = (float)$currency_model->convertByRate($sku['primary_price'], 1, $rate);
        }
        unset($sku);

        if ($sku_id && isset($data['skus'][$sku_id])) {
            $sku_price = $data['skus'][$sku_id]['price'];
        } else {
            $sku_price = $data['price'];
        }

        $data['services'] = $this->getServices($data, $sku_id, $rate, $sku_price);
        return $data;
    }

    /**
     * @deprecated
     *
     * If you need to scale this method, remove it from the model.
     *
     * shopOrdersGetProductController->getServices analogue of this method
     *
     * @param $product
     * @param $sku_id
     * @param $rate
     * @param $sku_price
     * @return array
     */
    private function getServices($product, $sku_id, $rate, $sku_price)
    {
        $currency_model = $this->getModel('currency');

        $services = $this->getModel('service')->getAvailableServicesFullInfo($product, $sku_id);
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

    /**
     * @deprecated
     *
     * If you need to scale this method, remove it from the model.
     *
     * @param $product
     * @param null $order_id
     * @param null $currency
     */
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

    /**
     * @deprecated
     *
     * If you need to scale this method, remove it from the model.
     *
     * shopOrdersGetProductController->getServices analogue of this method
     *
     * @param $services
     * @param $order_id
     * @param $currency
     */
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

            // The search logic "default price" is deprecated
            // Previously, they wanted to show the service, even if there is no variant for it
            // Now the service always has the variants

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
            unset($variant);
            if ($default_price === null) {
                if (isset($service['variants'][$service['variant_id']])) {
                    $default_price = $service['variants'][$service['variant_id']]['price'];
                    if ($service['currency'] == '%') {
                        $default_percent_price = $service['variants'][$service['variant_id']]['percent_price'];
                    }
                } elseif (!empty($service['variants'])) {
                    reset($service['variants']);
                    $first = current($service['variants']);
                    $default_price = $first['price'];
                    if ($service['currency'] == '%') {
                        $default_percent_price = $first['percent_price'];
                    }
                }
            }
            if (!empty($service['variants'])) {
                $service['price'] = $default_price;
                if ($service['currency'] == '%') {
                    $service['percent_price'] = $default_percent_price;
                }
            }
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
        if (is_array($order_id) || ($order_id instanceof shopOrder)) {
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
     * @throws waException
     */
    public function update($items, $order_id)
    {
        $stocks = shopHelper::getStocks();
        $old_items = $this->getByField('order_id', $order_id, 'id');
        $add = array();
        $update = array();
        $sku_stock = array();

        $context = shopProductStocksLogModel::getContext();
        $return_stock_id = ifset($context, 'params', 'return_stock_id', null);

        // fetch product codes info from DB
        $product_codes = [];
        foreach ($items as $item) {
            if (isset($item['codes']) && is_array($item['codes'])) {
                foreach($item['codes'] as $c) {
                    if (isset($c['code'])) {
                        $product_codes[$c['code']] = null;
                    }
                }
            }
        }
        if ($product_codes) {
            $product_codes = (new shopProductCodeModel())->getByField('code', array_keys($product_codes), 'code');
        }

        $parent_id = null;
        $order_item_codes = [];
        foreach ($items as $item) {

            $codes = ifset($item, 'codes', null);
            unset($item['codes']);

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
                    $item['type'] = 'product';
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
                    $stock_id = (int)$item['stock_id'];

                    if ($stock_id && (!empty($return_stock_id))) {
                        $stock_id = (int)$return_stock_id;
                    }

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

            if ($codes && is_array($codes) && $item['type'] == 'product') {
                foreach(array_values($codes) as $sort => $c) {
                    if (!empty($c['value']) && !empty($c['code'])) {
                        $order_item_codes[] = [
                            'order_id' => $order_id,
                            'order_item_id' => $parent_id,
                            'code_id' => ifset($product_codes, $c['code'], 'id', null),
                            'code' => $c['code'],
                            'value' => $c['value'],
                            'sort' => $sort,
                        ];
                    }
                }
            }
        }

        foreach ($update as $item_id => $item) {
            $this->updateById($item_id, $item);
        }
        if ($add) {
            $this->multipleInsert($add);
        }
        if ($order_item_codes) {
            $order_item_codes_model = new shopOrderItemCodesModel();
            $order_item_codes_model->deleteByField([
                'order_id' => $order_id,
                'order_item_id' => array_map(function($c) {
                    return $c['order_item_id'];
                }, $order_item_codes),
                'code' => array_map(function($c) {
                    return $c['code'];
                }, $order_item_codes),
            ]);
            $order_item_codes_model->multipleInsert($order_item_codes);
        }
        if ($old_items) {
            foreach ($old_items as $old_item) {
                // check stock changes
                if ($old_item['type'] == 'product') {
                    $stock_id = (int)$old_item['stock_id'];
                    if ($stock_id && (!empty($return_stock_id))) {
                        $stock_id = (int)$return_stock_id;
                    }
                    $sku_id = $old_item['sku_id'];
                    if (!isset($sku_stock[$sku_id][$stock_id])) {
                        $sku_stock[$sku_id][$stock_id] = 0;
                    }
                    $sku_stock[$sku_id][$stock_id] += $old_item['quantity'];
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
     * @throws waException
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
                        'stock_id' => $stock_id,
                    ));

                    if (!$item) {
                        continue;
                    }
                    $product_stocks_model->set(array(
                        'sku_id'     => $sku_id,
                        'product_id' => $product_id,
                        'stock_id'   => $stock_id,
                        'count'      => $item['count'] + $count,
                    ));
                } else {
                    $before_count = $product_skus_model->select('count')->where('id=i:sku_id', array('sku_id' => $sku_id))->fetchField();
                    if ($before_count !== null) {
                        $log_data = array(
                            'product_id'   => $product_id,
                            'sku_id'       => $sku_id,
                            //'stock_id'     => $stock_id,
                            'before_count' => $before_count,
                            'after_count'  => $before_count + $count,
                            'diff_count'   => $count,
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

    public function getData(shopOrder $order)
    {
        $options = $order->options('items');
        $data = $this->getItems($order, !empty($options['extend']));
        return $data;
    }

    /**
     * Re-sort input items
     *
     * Be careful - input array must has correct consistence
     * Otherwise it will be shrink
     *
     * Correct consistence - input array must has products and related to them services
     * Services without product will be thrown out of result array
     *
     * @param array $items items (products and services)
     * @param string $type type of sorting
     * @return array
     */
    protected static function sortOrderItems($items, $type)
    {
        $tmp_services = $new_items = [];

        if ($type != 'user_cart') {
            foreach ($items as $i_key => $item) {
                if (isset($item['type']) && $item['type'] == 'service') {
                    $tmp_services[$item['parent_id']][$i_key] = $item;
                }
            }

            if ($type === 'sku_name') {
                uasort($items, array('shopOrderItemsModel', 'sortOrderItemsBySkuName'));
            }
            if ($type === 'sku_code') {
                uasort($items, array('shopOrderItemsModel', 'sortOrderItemsBySkuCode'));
            }

            foreach ($items as $p_key => $product) {
                if (isset($product['type']) && $product['type'] == 'service') {
                    continue;
                }
                $new_items[$p_key] = $product;
                $product_id = ifset($product, 'id', null);
                $tmp_item_services = ifset($tmp_services, $product_id, []);
                if (!empty($tmp_item_services)) {
                    foreach ($tmp_item_services as $s_key => $service) {
                        $new_items[$s_key] = $service;
                    }
                }
            }
        } else {
            $new_items = $items;
        }

        return $new_items;
    }


    public static function sortItemsByGeneralSettings(&$items)
    {
        $sort_order_items = wa()->getSetting('sort_order_items', '', 'shop');

        $sorted_items = self::sortOrderItems($items, $sort_order_items);

        if (waSystemConfig::isDebug() && defined('SHOP_ORDER_ITEMS_SORT_LOG')) {
            waLog::log("\nSTART IN \n".var_export($items, true)."\n OUT \n".var_export($sorted_items, true)."\n END", 'shop/sort_order_items.log');
        }

        if (isset($items[0])) {
            $items = array_values($sorted_items);
        } else {
            $items = $sorted_items;
        }
    }

    public static function sortOrderItemsBySkuName($a_arr, $b_arr)
    {
        $a_name = mb_strtolower(ifset($a_arr, 'name', ''));
        $b_name = mb_strtolower(ifset($b_arr, 'name', ''));

        return strncasecmp($a_name, $b_name, 255);
    }

    public static function sortOrderItemsBySkuCode($a_arr, $b_arr)
    {
        $a_sku_code = mb_strtolower(ifset($a_arr, 'sku_code', ''));
        $b_sku_code = mb_strtolower(ifset($b_arr, 'sku_code', ''));
        $a_name = mb_strtolower(ifset($a_arr, 'name', ''));
        $b_name = mb_strtolower(ifset($b_arr, 'name', ''));

        if (strlen($a_sku_code) && strlen($b_sku_code) === 0) {
            return -1;
        } elseif (strlen($a_sku_code) === 0 && strlen($b_sku_code)) {
            return 1;
        } else {
            $result = strcmp($a_sku_code, $b_sku_code);
            if ($result === 0) {
                $result = strcmp($a_name, $b_name);
            }
            return $result;
        }
    }
}
