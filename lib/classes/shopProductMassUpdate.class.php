<?php

class shopProductMassUpdate
{
    /**
     * Efficient mass update of skus, products and their stock counts.
     *
     * $raw_products is a list of arrays containing data to update in `shop_product` DB table.
     * 'id' is required in each array, everything else is optional. Some fields like
     * 'create_datetime', 'edit_datetime', 'sku_count' and 'sku_type' have special meaning
     * and will not be changed by this method. 'count' will be ignored, too;
     * it is calculated automatically and set according to SKU counts of the product.
     *
     * $raw_skus is a list of arrays containing data to update in `shop_product_skus` DB table.
     * 'id' is required in each array, everything else is optional. Some fields like
     * 'product_id' and 'virtual' have special meaning and will be ignored.
     * 'count' and 'stock' have special behavior described below.
     *
     * $raw_skus[i]['stock'] allows to update per-stock counts of a SKU.
     * It must be an array [stock_id => value]. Value may be an integer to set finite supply,
     * or null to set infinite. Unknown stock_id is an error. Missing stock_id
     * will be taken from DB and will not change.
     *
     * Only one of $raw_skus[i]['stock'] and $raw_skus[i]['count'] should be specified.
     * If 'stock' is specified, 'count' will be set automatically according to stock counts.
     * Otherwise, when 'count' is specified, all by-stock counts for the SKU will be deleted
     * and only a single total SKU count saved.
     * When neither 'stock' nor 'count' is specified, SKU counts will not change.
     *
     * @param array $raw_skus
     * @param array $raw_products
     */
    public static function update($raw_skus, $raw_products = array())
    {
        if (waConfig::get('is_template') || (!$raw_products && !$raw_skus)) {
            return;
        }

        // Stocks from DB
        $stock_model = new shopStockModel();
        $stocks = $stock_model->getAll('id');

        // Validate and sanitize SKU and product data from POST
        list($skus, $sku_stocks, $products) = self::sanitize($raw_skus, $raw_products, $stocks);
        list($sku_product_ids, $old_product_data) = self::getProductData($skus, $products);
        list($skus, $products) = self::prepareInputs($skus, $products, $sku_product_ids, $old_product_data, self::getTypeIdAccess($old_product_data));
        $old_product_data = array_intersect_key($old_product_data, $products);
        $sku_stocks = array_intersect_key($sku_stocks, $skus);
        unset($sku_product_ids);

        // Remember SKUs data before any changes are made
        $old_sku_data = self::getSkuData($old_product_data);
        $old_sku_stocks = self::getStockCounts(array_keys($products));
        $old_sku_stocks = self::fillEmptyStockCounts($old_sku_stocks, $old_sku_data, $stocks);

        // Prepare a copy of old data with modifications from POST, but do not update anything in DB yet.
        $new_sku_stocks = self::getUpdatedStockCounts($sku_stocks, $old_sku_stocks, $stocks);
        list($skus, $new_sku_data) = self::setSkuData($skus, $old_sku_data, $new_sku_stocks);
        $products = self::setProductData($products, $new_sku_data, $old_sku_data);

        // Update in DB
        self::updateStockCounts($skus, array_keys($sku_stocks), $new_sku_stocks);
        self::updateProducts($products);
        self::updateSkus($skus);

        // Stock change log in shop_product_stocks_log
        self::updateStockLog($old_sku_stocks, $new_sku_stocks, $old_sku_data, $new_sku_data);

        /**
         * @event product_mass_update
         * @param array
         * @return none
         */
        $params = array(
            'skus_changed' => $skus,
            'products_changed' => $products,
            'old_sku_data' => $old_sku_data,
            'new_sku_data' => $new_sku_data,
            'old_product_data' => $old_product_data,
            'old_sku_stocks' => $old_sku_stocks,
            'new_sku_stocks' => $new_sku_stocks,
            'stocks_changed_sku_ids' => array_keys($sku_stocks),
        );
        wa('shop')->event('product_mass_update', $params);

        // Trigger a `product_save` event when updating a single product.
        // This compromise makes this new class more friendly for old style plugins.
        if (count($products) == 1) {
            $product = reset($products);
            if (!empty($product['id'])) {
                $product = new shopProduct($product['id']);
                $product->save();
            }
        }
    }

    protected static function getStockCounts($product_ids)
    {
        if (!$product_ids) {
            return array();
        }
        $product_stock_counts = array();
        $product_stock_model = new shopProductStocksModel();
        $rows = $product_stock_model->where('product_id IN (?)', $product_ids)->query();
        foreach($rows as $row) {
            $product_stock_counts[$row['sku_id']][$row['stock_id']] = self::formatFloatValue($row['count']);
        }
        return $product_stock_counts;
    }

    protected static function fillEmptyStockCounts($product_stock_counts, $old_sku_data, $stocks)
    {
        foreach($old_sku_data as $product_id => $product_skus) {
            if (array_intersect_key($product_skus, $product_stock_counts)) {
                $product_stock_counts += array_fill_keys(array_keys($product_skus), array());
            }
        }
        $empty_stock = array_fill_keys(array_keys($stocks), null);
        foreach(array_keys($product_stock_counts) as $sku_id) {
            $product_stock_counts[$sku_id] += $empty_stock;
        }
        return $product_stock_counts;
    }

    protected static function getUpdatedStockCounts($sku_stocks_change, $old_sku_stocks, $stocks)
    {
        $empty_stock_data = array_fill_keys(array_keys($stocks), 0);
        $new_sku_stocks = $old_sku_stocks;
        foreach($sku_stocks_change as $sku_id => $stock_counts) {
            // $stock_counts === null means that sku does not use detailed stock counts
            if ($stock_counts === null) {
                unset($new_sku_stocks[$sku_id]);
                continue;
            }
            // Update sku counts in memory
            $new_sku_stocks[$sku_id] = ifset($new_sku_stocks[$sku_id], array()) + $empty_stock_data;
            foreach($stock_counts as $stock_id => $count) {
                $new_sku_stocks[$sku_id][$stock_id] = self::formatFloatValue($count);
            }
        }

        return $new_sku_stocks;
    }

    protected static function getStockCountsValues($skus, $sku_ids_changed, $new_sku_stocks)
    {
        $values = array();
        foreach($sku_ids_changed as $sku_id) {
            if (empty($new_sku_stocks[$sku_id])) {
                continue;
            }
            foreach($new_sku_stocks[$sku_id] as $stock_id => $count) {
                $count = self::formatFloatValue($count);
                if ($count !== null) {
                    $values[] = array(
                        'sku_id' => $sku_id,
                        'product_id' => $skus[$sku_id]['product_id'],
                        'stock_id' => $stock_id,
                        'count' => $count,
                    );
                }
            }
        }
        return $values;
    }

    protected static function updateStockCounts($skus, $sku_ids_changed, $new_sku_stocks)
    {
        $values = self::getStockCountsValues($skus, $sku_ids_changed, $new_sku_stocks);
        $product_stocks_model = new shopProductStocksModel();
        $product_stocks_model->deleteByField('sku_id', $sku_ids_changed);
        while ($values) {
            $product_stocks_model->multipleInsert(array_splice($values, 0, 50));
        }
        return $new_sku_stocks;
    }

    protected static function getSkuData($old_product_data)
    {
        if (!$old_product_data) {
            return array();
        }
        $data = array();
        $product_skus_model = new shopProductSkusModel();
        $product_ids = array_keys($old_product_data);
        $rows = $product_skus_model->select('id,product_id,count,price,primary_price,available,status')->where('product_id IN (?)', array($product_ids))->query();
        foreach($rows as $row) {
            $count = self::formatFloatValue($row['count']);
            $data[$row['product_id']][$row['id']] = array(
                'id' => $row['id'],
                'product_id' => $row['product_id'],
                'primary_price' => $row['primary_price'],
                'price' => $row['price'],
                'count' => $count,
                'product_currency' => ifset($old_product_data, $row['product_id'], 'currency', null),
                'available' => $row['available'],
                'status' => $row['status'],
            );
        }
        return $data;
    }

    protected static function setSkuData($skus, $old_sku_data, $sku_stocks)
    {
        $new_sku_data = $old_sku_data;
        $default_currency = wa('shop')->getConfig()->getCurrency(true);

        // Update data in $new_sku_data from $skus
        foreach($skus as $sku_id => &$sku) {
            foreach($new_sku_data[$sku['product_id']][$sku_id] as $k => $v) {
                if (array_key_exists($k, $sku)) {
                    $new_sku_data[$sku['product_id']][$sku_id][$k] = $sku[$k];
                }
            }
        }
        unset($sku);

        // sku_id => product_id for all product skus,
        // not just from $skus which are only those being updated
        $sku_product_ids = array();
        foreach($new_sku_data as $product_id => $product_skus) {
            foreach($product_skus as $sku_id => $sku_data) {
                $sku_product_ids[$sku_id] = $product_id;
            }
        }

        // Update counts in $new_sku_data from $sku_stocks
        foreach($sku_stocks as $sku_id => $stock_counts) {
            $sku_count = 0;
            foreach($stock_counts as $stock_id => $count) {
                if ($count === null) {
                    $sku_count = null;
                    break;
                }
                $sku_count += $count;
            }

            $product_id = $sku_product_ids[$sku_id];
            $new_sku_data[$product_id][$sku_id]['count'] = $sku_count;
        }

        // Update counts in $skus
        foreach($new_sku_data as $product_id => $product_skus) {
            foreach($product_skus as $sku_id => $sku_data) {
                // Count
                $count = $sku_data['count'];
                if ($old_sku_data[$product_id][$sku_id]['count'] !== $count) {
                    if (empty($skus[$sku_id])) {
                        $skus[$sku_id] = array(
                            'id' => $sku_id,
                        );
                    }
                    $skus[$sku_id]['count'] = $count;
                } else if (isset($skus[$sku_id])) {
                    unset($skus[$sku_id]['count']);
                }
            }
        }


        return array($skus, $new_sku_data);
    }

    protected static function updateSkus($skus)
    {
        $product_skus_model = new shopProductSkusModel();
        foreach($skus as $sku) {
            $id = $sku['id'];
            unset(
                $sku['id'],
                $sku['product_id'],
                $sku['virtual']
            );
            $product_skus_model->updateById($id, $sku);
        }
    }

    protected static function setProductData($products, $new_sku_data, $old_sku_data)
    {
        // Update product.min_price and .max_price to match SKU prices accordingly
        foreach($old_sku_data as $product_id => $product_skus) {
            if (!$product_skus || empty($products[$product_id])) {
                continue;
            }
            $prices = array();
            foreach($product_skus as $sku) {
                if (isset($new_sku_data[$product_id][$sku['id']]['primary_price'])) {
                    $prices[] = $new_sku_data[$product_id][$sku['id']]['primary_price'];
                } else {
                    $prices[] = $sku['primary_price'];
                }
            }
            $products[$product_id]['min_price'] = min($prices);
            $products[$product_id]['max_price'] = max($prices);
        }

        // Update product.count to match counts of SKUs
        foreach ($new_sku_data as $product_id => $product_skus) {
            $count = 0;
            foreach ($product_skus as $sku) {
                if (!empty($sku['available']) && !empty($sku['status'])) {
                    if (is_null($sku['count'])) {
                        $count = null;
                        break;
                    } elseif ($sku['count'] < 0) {
                        continue;
                    } else {
                        $count += $sku['count'];
                    }
                }
            }
            $products[$product_id]['count'] = $count;
        }

        return $products;
    }

    protected static function updateProducts($products)
    {
        $product_model = new shopProductModel();
        foreach($products as $product_id => $product) {
            unset(
                $product['id'],
                $product['create_datetime'],
                $product['sku_count'],
                $product['sku_type']
            );
            $product['edit_datetime'] = date('Y-m-d H:i:s');
            $product_model->updateById($product_id, $product);
        }
    }

    protected static function getStockLogValues($old_sku_stocks, $new_sku_stocks, $old_sku_data, $new_sku_data)
    {
        $values = array();
        $datetime = date('Y-m-d H:i:s');

        $log_model_context_type = self::getStockLogContextType();

        foreach($new_sku_data as $product_id => $product_skus) {
            foreach(array_keys($product_skus) as $sku_id) {
                $old_data = $old_sku_data[$product_id][$sku_id];
                $new_data = $new_sku_data[$product_id][$sku_id];
                $old_stocks = ifset($old_sku_stocks[$sku_id]);
                $new_stocks = ifset($new_sku_stocks[$sku_id]);

                if ($new_stocks) {
                    if (!$old_stocks) {
                        // no stocks to multiple stocks: decrease old single value to 0 first
                        $new_count = 0;
                        $old_count = $old_data['count'];
                        if ($old_count !== $new_count) {
                            $values[] = array(
                                'sku_id' => $sku_id,
                                'stock_id' => null,
                                'product_id' => $product_id,
                                'datetime' => $datetime,
                                'type' => $log_model_context_type,
                                'diff_count' => $new_count === null ? null : $new_count - (int) $old_count,
                                'before_count' => $old_count,
                                'after_count' => $new_count,
                            );
                        }
                    }

                    // Change counts on each stock to new value
                    foreach($new_stocks as $stock_id => $new_count) {
                        if ($old_stocks && array_key_exists($stock_id, $old_stocks)) {
                            $old_count = $old_stocks[$stock_id];
                        } else {
                            $old_count = 0;
                        }
                        if ($old_count !== $new_count) {
                            $values[] = array(
                                'sku_id' => $sku_id,
                                'stock_id' => $stock_id,
                                'product_id' => $product_id,
                                'datetime' => $datetime,
                                'type' => $log_model_context_type,
                                'diff_count' => $new_count === null ? null : $new_count - (int) $old_count,
                                'before_count' => $old_count,
                                'after_count' => $new_count,
                            );
                        }
                    }
                } else {
                    $old_count = $old_data['count'];
                    if ($old_stocks) {
                        // multiple stocks to no stocks: decrease all stock counts to 0
                        foreach($old_stocks as $stock_id => $old_count) {
                            $new_count = 0;
                            if ($old_count !== $new_count) {
                                $values[] = array(
                                    'sku_id' => $sku_id,
                                    'stock_id' => $stock_id,
                                    'product_id' => $product_id,
                                    'datetime' => $datetime,
                                    'type' => $log_model_context_type,
                                    'diff_count' => $new_count === null ? null : $new_count - (int) $old_count,
                                    'before_count' => $old_count,
                                    'after_count' => $new_count,
                                );
                            }
                        }
                        $old_count = 0;
                    }

                    // Change a single sku count to new value
                    $new_count = $new_data['count'];
                    if ($old_count !== $new_count) {
                        $values[] = array(
                            'sku_id' => $sku_id,
                            'stock_id' => null,
                            'product_id' => $product_id,
                            'datetime' => $datetime,
                            'type' => $log_model_context_type,
                            'diff_count' => $new_count === null ? null : $new_count - (int) $old_count,
                            'before_count' => $old_count,
                            'after_count' => $new_count,
                        );
                    }
                }
            }
        }

        return $values;
    }

    /**
     * If set context use it. Otherwise use default shopProductStocksLogModel::TYPE_IMPORT
     * Context set there wa-apps/shop/lib/actions/products/shopProductsMassUpdate.controller.php
     * @return string
     */
    protected static function getStockLogContextType()
    {
        $log_model_context = shopProductStocksLogModel::getContext();

        if (!empty($log_model_context['type'])) {
            $type = $log_model_context['type'];
        } else {
            $type = shopProductStocksLogModel::TYPE_IMPORT;
        }

        return $type;
    }

    protected static function updateStockLog($old_sku_stocks, $new_sku_stocks, $old_sku_data, $new_sku_data)
    {
        $values = self::getStockLogValues($old_sku_stocks, $new_sku_stocks, $old_sku_data, $new_sku_data);
        $product_stocks_log_model = new shopProductStocksLogModel();
        while ($values) {
            $product_stocks_log_model->multipleInsert(array_splice($values, 0, 50));
        }
    }

    protected static function sanitize($raw_skus, $raw_products, $stocks)
    {
        // Sanitize skus data
        $skus = array();
        $sku_stocks = array();
        $product_skus_model = new shopProductSkusModel();
        $valid_fields = $product_skus_model->getMetadata();
        foreach($raw_skus as $sku) {
            if (!isset($sku['id'])) {
                continue;
            }
            if (!wa_is_int($sku['id'])) {
                throw new waException('sku.id must be integer: '.htmlspecialchars($sku['id']), 400);
            }
            if (!empty($sku['stock'])) {
                if (!is_array($sku['stock'])) {
                    throw new waException('sku.stocks must be an array', 400);
                }

                $sku_stocks[$sku['id']] = array();
                foreach($sku['stock'] as $stock_id => $count) {
                    if (!wa_is_int($stock_id)) {
                        throw new waException('sku.stocks stock_ids must be integers: '.htmlspecialchars($stock_id), 400);
                    }
                    $formatted_count = self::formatFloatValue($count, false);
                    if ($formatted_count === false) {
                        throw new waException('sku.stocks counts must be integers, fractional or empty strings: '.htmlspecialchars($count), 400);
                    }
                    if (!isset($stocks[$stock_id])) {
                        throw new waException('stock_id does not exist: '.htmlspecialchars($stock_id), 400);
                    }
                    $sku_stocks[$sku['id']][$stock_id] = $formatted_count;
                }
                unset($sku['count']);
            } else if (array_key_exists('count', $sku)) {
                $sku_stocks[$sku['id']] = null;
                $formatted_count = self::formatFloatValue($sku['count'], false);
                if ($formatted_count === false) {
                    throw new waException('sku.count must be integer, fractional or empty string: '.htmlspecialchars($sku['count']), 400);
                } else {
                    $sku['count'] = $formatted_count;
                }
            }
            $skus[$sku['id']] = array_intersect_key($sku, $valid_fields);
        }

        // Sanitize products data
        $products = array();
        $product_model = new shopProductModel();
        $valid_fields = $product_model->getMetadata();
        foreach($raw_products as $product) {
            if (!isset($product['id'])) {
                continue;
            }
            if (!wa_is_int($product['id'])) {
                throw new waException('product.id must be integer: '.htmlspecialchars($product['id']), 400);
            }
            unset($product['count'], $product['create_datetime']);
            $products[$product['id']] = array_intersect_key($product, $valid_fields);
        }

        return array($skus, $sku_stocks, $products);
    }

    /**
     * @param $value
     * @param bool $return_correct_value
     * @return int|float|null|false
     */
    protected static function formatFloatValue($value, $return_correct_value = true)
    {
        if (wa_is_int($value)) {
            $value = (int)$value;
        } else {
            if (is_string($value) && strpos($value, ',') !== false) {
                $value = str_replace(',', '.', $value);
            }
            if (is_numeric($value)) {
                $value = (float)$value;
            } elseif (!$value || $return_correct_value) {
                $value = null;
            } else {
                $value = false;
            }
        }

        return $value;
    }

    protected static function getProductData($skus, $products)
    {
        $sku_product_ids = array();
        if ($skus) {
            $product_skus_model = new shopProductSkusModel();
            $sku_product_ids = $product_skus_model->select('id, product_id')->where('id IN (?)', array(array_keys($skus)))->fetchAll('id', true);
        }
        $product_ids = array_keys($products + array_flip($sku_product_ids));
        $product_data = array();
        if ($product_ids) {
            $product_model = new shopProductModel();
            $product_data = $product_model->select('id, type_id, sku_id, currency, price, min_price, max_price')->where('id IN (?)', array($product_ids))->fetchAll('id');
        }
        return array($sku_product_ids, $product_data);
    }

    protected static function prepareInputs($skus, $products, $sku_product_ids, $product_data, $has_access)
    {
        // Add products that are missing in raw inputs
        foreach($product_data as $product) {
            if (empty($products[$product['id']])) {
                $products[$product['id']] = array(
                    'id' => $product['id'],
                );
            }
        }

        // Check that sku exists, its product exists, and user has access rights to modify them.
        $default_currency = wa('shop')->getConfig()->getCurrency(true);
        foreach($skus as $sku) {
            // Unknown sku_id?
            if (empty($sku_product_ids[$sku['id']])) {
                unset($skus[$sku['id']]);
                continue;
            }

            // No product for some reason?
            $product_id = $sku_product_ids[$sku['id']];
            if (!isset($product_data[$product_id])) {
                unset($skus[$sku['id']]);
                continue;
            }

            // No access to product?
            $product = $product_data[$product_id];
            if (!isset($has_access[$product['type_id']])) {
                unset($skus[$sku['id']]);
                continue;
            }

            // All fine
            $skus[$sku['id']]['product_id'] = $product_id;

            // update SKU primary_price if price changed (or vice versa)
            if (array_key_exists('price', $sku)) {
                $sku['primary_price'] = $skus[$sku['id']]['primary_price'] = shop_currency($sku['price'], $product['currency'], $default_currency, false);
            } else if (array_key_exists('primary_price', $sku)) {
                $skus[$sku['id']]['price'] = shop_currency($sku['primary_price'], $default_currency, $product['currency'], false);
            }

            // Update product prices according to main SKU price
            if (isset($sku['primary_price']) && $product['sku_id'] == $sku['id']) {
                $products[$product_id]['price'] = $sku['primary_price'];
            }
            if (isset($sku['compare_price']) && $product['sku_id'] == $sku['id']) {
                $products[$product_id]['compare_price'] = shop_currency($sku['compare_price'], $product['currency'], $default_currency, false);
            }
        }

        // Check that products exist, and user has access rights to modify them.
        foreach($products as $product) {
            // Unknown product id?
            if (!isset($product_data[$product['id']])) {
                unset($products[$product['id']]);
                continue;
            }

            // No access to product?
            $type_id = $product_data[$product['id']]['type_id'];
            if (!isset($has_access[$type_id])) {
                unset($products[$product['id']]);
                continue;
            }
        }

        return array($skus, $products);
    }

    // Type ids current user has write access to
    protected static function getTypeIdAccess($product_data)
    {
        $has_access = array();
        if (wa()->getUser()->isAdmin('shop')) {
            foreach($product_data as $p) {
                $has_access[$p['type_id']] = true;
            }
        } else {
            foreach(wa()->getUser()->getRights('shop', 'type.%') as $type_id => $level) {
                if ($level >= 2) {
                    $has_access[$type_id] = true;
                }
            }
        }
        return $has_access;
    }
}
