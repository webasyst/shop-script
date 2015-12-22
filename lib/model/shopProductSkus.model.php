<?php

/**
 * Note the currencies for different price fields:
 *
 * - shop_product_skus.primary_price    primary currency
 * - shop_product_skus.price            shop_product.currency
 * - shop_product_skus.purchase_price   shop_product.currency
 * - shop_product_skus.compare_price    shop_product.currency
 */
class shopProductSkusModel extends shopSortableModel implements shopProductStorageInterface
{
    protected $table = 'shop_product_skus';
    protected $context = 'product_id';

    /**
     * @var shopCurrencyModel
     */
    protected $currency_model;

    /**
     * @var string
     */
    protected $primary_currency;

    /**
     * @param int[] $product_ids
     * @return bool
     */
    public function deleteByProducts(array $product_ids)
    {
        $result = $this->deleteByField('product_id', $product_ids);
        foreach ((array)$product_ids as $product_id) {
            $file_path = shopProduct::getPath($product_id, "sku_file");
            waFiles::delete($file_path);
        }

        return $result;
    }

    public function getPrices($ids)
    {
        $sql = "SELECT id, primary_price FROM ".$this->table." WHERE id IN (i:ids)";

        return $this->query($sql, array('ids' => $ids))->fetchAll('id', true);
    }


    /**
     * Delete sku of product by id with taking into account foreign relations and some other nuances
     *
     * @param int $sku_id
     * @return boolean
     */
    public function delete($sku_id)
    {
        $sku_id = (int)$sku_id;
        $sku = $this->getById($sku_id);

        if (!$sku) {
            return false;
        }

        $product_model = new shopProductModel();
        $product = $product_model->getById($sku['product_id']);
        if ($file_path = self::getPath($sku)) {
            waFiles::delete($file_path);
        }

        if (!$product) {
            $this->deleteById($sku_id); // product doesn't exist, but sku exists, so kill hanging sku
            return false;
        }

        // get aggregated info of skus for this product
        $sql = <<<SQL
        SELECT
  COUNT(`id`) AS `cnt`,
  MAX(`price`) AS `max_price`,
  MIN(`price`) AS `min_price`,
  SUM(IF((`count` < 0) OR (`available` != 1), 0, `count`)) `count`
FROM `{$this->table}`
WHERE
  (`product_id` = {$product['id']})
  AND
  (`id` != {$sku_id})
SQL;
        $data = $this->query($sql)->fetchAssoc();

        if (!$data) {
            return false; // something is wrong
        }

        if (!$data['cnt']) {
            return true; // can't remove single sku
        }

        $primary = wa('shop')->getConfig()->getCurrency();
        $currency = $primary;
        if ($product['currency'] && $product['currency'] != $primary) {
            $currency = $product['currency'];
        }
        // info for updating product when sku will be deleted
        $update = array(
            'max_price' => $currency != $primary ? $this->convertPrice($data['max_price'], $currency) : $data['max_price'],
            'min_price' => $currency != $primary ? $this->convertPrice($data['min_price'], $currency) : $data['min_price'],
            'sku_count' => $data['cnt']
        );
        if ($product['sku_id'] == $sku_id) {
            $item = $this->query("SELECT id AS sku_id, price FROM `{$this->table}` WHERE product_id = {$product['id']} AND id != {$sku_id} LIMIT 1")->fetchAssoc();
            if (!$item) {
                return false;
            }
            if ($currency != $primary) {
                $item['price'] = $this->convertPrice($item['price'], $currency);
            }
            $update += $item;
        }

        if (($sku['count'] !== null) && ($product['count'] !== null)) {
            $update['count'] = $product['count'] - $sku['count']; // increase count if it's possible
        } elseif (($product['count'] === null) && ($sku['count'] === null)) {
            $update['count'] = $data['count'];
        }

        $diff = array_diff_assoc($update, $product);
        if ($diff) {
            $product_model->updateById($product['id'], $diff); // we'll have difference after sku's deleting, so up product info
        }

        /**
         * @event product_sku_delete
         * @param array $sku
         */
        wa('shop')->event('product_sku_delete', $sku);
        
        if (!$this->deleteById($sku_id)) { // delete sku
            return false;
        }

        $this->deleteFromStocks($product['id'], $sku_id); // take info account stocks
        $this->deleteStocksLog($product['id'], $sku_id);
        $this->deleteServices($product['id'], $sku_id);

        $product_features_model = new shopProductFeaturesModel();
        $product_features_model->deleteByField('sku_id', $sku_id);

        return true;
    }

    /**
     * @param int $product_id
     * @param int $sku_id
     * @return bool
     */
    public function deleteFromStocks($product_id, $sku_id)
    {
        $product_stocks_model = new shopProductStocksModel();

        return $product_stocks_model->deleteByField(array('product_id' => $product_id, 'sku_id' => $sku_id));
    }

    /**
     * @param int $product_id
     * @param int $sku_id
     * @return bool
     */
    public function deleteStocksLog($product_id, $sku_id)
    {
        $model = new shopProductStocksLogModel();

        return $model->deleteByField(array('product_id' => $product_id, 'sku_id' => $sku_id));
    }

    public function deleteServices($product_id, $sku_id)
    {
        $product_services_model = new shopProductServicesModel();

        return $product_services_model->deleteByField(array('product_id' => $product_id, 'sku_id' => $sku_id));
    }

    public function deleteJoin($table, $product_id, $where)
    {
        $where['product_id'] = $product_id;
        $sql = "DELETE t FROM ".$table." t JOIN shop_product_skus s ON t.sku_id = s.id WHERE ".$this->getWhereByField($where, 's');

        return $this->exec($sql);
    }

    public function getSku($sku_id)
    {
        $sku = $this->getById($sku_id);
        if (!$sku) {
            return array();
        }

        $stocks_model = new shopProductStocksModel();
        $stocks = $stocks_model->getByField('sku_id', $sku_id, true);
        $sku['stock'] = array();
        foreach ($stocks as $stock) {
            $sku['stock'][$stock['stock_id']] = $stock['count'];
        }

        return $sku;
    }

    /**
     * @param int|array $product_id
     * @param bool $fill_empty_sku_by_null
     * @return array
     */
    public function getDataByProductId($product_id, $fill_empty_sku_by_null = false)
    {
        $stock_model = new shopStockModel();
        $stocks = $stock_model->getAll('id');
        $product_stocks_model = new shopProductStocksModel();
        $product_stocks = $product_stocks_model->getByField('product_id', $product_id, true);

        $skus = $this->getByField('product_id', $product_id, $this->id);
        foreach ($skus as &$sku) {
            $sku['count'] = ($sku['count'] === null ? null : (int)$sku['count']);
            $sku['price'] = (float)$sku['price'];
            $sku['purchase_price'] = (float)$sku['purchase_price'];
            $sku['compare_price'] = (float)$sku['compare_price'];
            $sku['primary_price'] = (float)$sku['primary_price'];
            if ((wa()->getEnv() == 'frontend') && $sku['compare_price'] && ($sku['price'] >= $sku['compare_price'])) {
                $sku['compare_price'] = 0.0;
            }
            if ($fill_empty_sku_by_null) {
                $sku['stock'] = array_fill_keys(array_keys($stocks), null);
            } else {
                $sku['stock'] = array();
            }
        }
        unset($sku);

        foreach ($product_stocks as $stock) {
            $id = $stock['sku_id'];
            if (isset($skus[$id])) {
                $skus[$id]['stock'][$stock['stock_id']] = $stock['count'];
            }
        }

        return $skus;
    }

    public function getData(shopProduct $product)
    {
        return $this->getDataByProductId($product->id);
    }

    private function convertPrice($price, $from)
    {
        if (!$this->currency_model) {
            $this->currency_model = new shopCurrencyModel();
        }
        if (!$this->primary_currency) {
            $this->primary_currency = wa('shop')->getConfig()->getCurrency();
        }

        return $this->currency_model->convert($price, $from, $this->primary_currency);
    }

    protected static function castStock($count)
    {
        if ($count === '' || !preg_match('@^\-?\d*(\.(\d+)?)?$@', $count)) {
            $count = null;
        } else {
            $count = floatval($count);
        }

        return $count;
    }

    /**
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function update($id, $data)
    {
        $sku = $this->getById($id);
        if (!$sku) {
            return false;
        }
        if (empty($data['product_id'])) {
            $data['product_id'] = $sku['product_id'];
        }

        $product_model = new shopProductModel();
        $product = $product_model->getById($data['product_id']);
        $primary_currency = wa('shop')->getConfig()->getCurrency();

        if ($product['currency'] == $primary_currency) {
            $data['primary_price'] = $data['price'];
        } else {
            $data['primary_price'] = $this->convertPrice($data['price'], $product['currency']);
        }

        $this->updateSku($id, $data);

        return true;
    }

    /**
     * @param array $data
     * @return bool
     */
    public function add($data)
    {
        if (empty($data['product_id'])) {
            return false;
        }

        $product_model = new shopProductModel();
        $product = $product_model->getById($data['product_id']);
        $primary_currency = wa('shop')->getConfig()->getCurrency();

        if ($product['currency'] == $primary_currency) {
            $data['primary_price'] = $data['price'];
        } else {
            $data['primary_price'] = $this->convertPrice($data['price'], $product['currency']);
        }

        if (empty($sku['available'])) {
            $sku['available'] = 0;
        }

        return $this->updateSku(0, $data);
    }

    /**
     * @param int $id
     * @param array $data
     * @param bool $correct
     * @param shopProduct $product
     * @return array
     */
    protected function updateSku($id = 0, $data, $correct = true, shopProduct $product = null)
    {
        /**
         * @var shopProductStocksModel $stocks_model
         */
        static $stocks_model;
        /**
         * @var bool $multi_stock
         */
        static $multi_stock = null;
        /**
         * @var shopFeatureModel $feature_model
         */
        static $feature_model;
        /**
         * @var shopProductFeaturesModel $product_features_model
         */
        static $product_features_model;

        if (isset($data['price'])) {
            $data['price'] = $this->castValue('double', $data['price']);
        }
        if (isset($data['purchase_price'])) {
            $data['purchase_price'] = $this->castValue('double', $data['purchase_price']);
        }
        if (isset($data['compare_price'])) {
            $data['compare_price'] = $this->castValue('double', $data['compare_price']);
        }

        if ($id > 0) {
            if ($product && (!isset($data['virtual']) || !empty($data['virtual']))) {
                #check changes for virtual SKU
                $virtual_sku_defaults = array(
                    'price'          => $product->base_price_selectable,
                    'purchase_price' => $product->purchase_price_selectable,
                    'compare_price'  => $product->compare_price_selectable,
                    'count'          => 0,
                );
                $virtual = null;
                foreach ($virtual_sku_defaults as $field => $default) {
                    if (isset($data[$field])) {
                        $value = $data[$field];
                        if (is_array($value)) {
                            $value = max($value);
                        }
                        if ($value != $default) {
                            if ($virtual === null) {
                                $virtual = isset($product->skus[$id]) && !empty($product->skus[$id]['virtual']);
                            }
                            if ($virtual) {
                                $data['virtual'] = 0;
                                $virtual = false;
                            }
                            if (!$virtual) {
                                break;
                            }
                        }
                    }
                }
            }


            if (empty($data['eproduct']) && isset($data['eproduct_manage']) && !empty($data['file_name'])) {
                $file_path = shopProduct::getPath(
                    $data['product_id'],
                    "sku_file/{$id}.".pathinfo($data['file_name'], PATHINFO_EXTENSION)
                );
                waFiles::delete($file_path);
                $data['file_name'] = '';
                $data['file_description'] = '';
            } elseif (isset($data['file_name'])) {
                unset($data['file_name']);
            }
            $this->updateById($id, $data);
        } else {
            if (!isset($data['sku'])) {
                $data['sku'] = '';
            }
            $id = $this->insert($data);
        }

        $data['id'] = $id;

        $sku_count = false;

        // if stocking for this sku
        if (isset($data['stock']) && count($data['stock'])) {
            if ($multi_stock === null) {
                $stock_model = new shopStockModel();
                $stocks = $stock_model->getAll($stock_model->getTableId());
                $multi_stock = $stocks ? array_keys($stocks) : false;
            }

            // not multistocking
            if (!$multi_stock || isset($data['stock'][0])) {
                $sku_count = self::castStock(ifset($data['stock'][0], ''));
                unset($data['stock']);

                $this->logCount($data['product_id'], $id, $sku_count);

                // multistocking
            } else {
                $sku_count = 0;
                $missed = array_combine($multi_stock, $multi_stock);
                if (!$stocks_model) {
                    $stocks_model = new shopProductStocksModel();
                }

                // need for track transition from aggregating mode to multistocking mode
                $has_any_stocks = $stocks_model->hasAnyStocks($id);
                if (!$has_any_stocks) {
                    $this->writeOffCount($data['product_id'], $id);
                }

                foreach ($data['stock'] as $stock_id => $count) {
                    if (($stock_id > 0) && isset($missed[$stock_id])) {
                        unset($missed[$stock_id]);
                        $field = array(
                            'sku_id'     => $id,
                            'stock_id'   => $stock_id,
                            'product_id' => $data['product_id'],
                        );
                        $count = self::castStock($count);
                        $stock = array('count' => $count);
                        if ($count === null) {
                            $sku_count = null;
                        } else {
                            // Once turned into NULL value is not changed
                            if ($sku_count !== null) {
                                $sku_count += $count;
                            }
                        }
                        // there is taking into account stocks log inside this method
                        $stocks_model->set(array_merge($field, $stock));
                        $data['stock'][$stock_id] = $count;
                    }
                }
                //get stock_count for missed stocks
                if (($sku_count !== null) && !empty($missed)) {
                    $search = array('stock_id' => $missed, 'sku_id' => $id, 'product_id' => $data['product_id']);
                    $missed_stocks = $stocks_model->getByField($search, 'stock_id');
                    foreach ($missed_stocks as $stock_id => $row) {
                        unset($missed[$stock_id]);
                        $count = $row['count'];
                        $data['stock'][$stock_id] = $count;
                        if ($count === null) {
                            $sku_count = null;
                        } else {
                            // Once turned into NULL value is not changed
                            if ($sku_count !== null) {
                                $sku_count += $count;
                            }
                        }
                    }

                    //fill null counters:
                    if ($missed) {
                        foreach ($missed as $stock_id) {
                            $data['stock'][$stock_id] = null;
                        }
                        $sku_count = null;
                    }
                }
            }
        }

        if ($sku_count !== false) {
            $data['count'] = $sku_count;
            $this->updateById($id, array('count' => $sku_count));
        }

        if (isset($data['features'])) {

            if (!$feature_model) {
                $feature_model = new shopFeatureModel();
            }
            if (!$product_features_model) {
                $product_features_model = new shopProductFeaturesModel();
            }

            $features = $data['features'];
            $data['features'] = array();

            $skip_values = array('', false, null);

            foreach ($features as $code => $value) {
                if ($feature = $feature_model->getByField('code', $code)) {
                    $model = shopFeatureModel::getValuesModel($feature['type']);
                    $field = array(
                        'product_id' => $data['product_id'],
                        'sku_id'     => $id,
                        'feature_id' => $feature['id'],
                    );
                    $product_features_model->deleteByField($field);
                    if (is_array($value)) {
                        if (!empty($value['id'])) {
                            $field['feature_value_id'] = $value['id'];
                        } elseif (isset($value['value']) && !in_array($value['value'], $skip_values, true)) {
                            $field['feature_value_id'] = $model->getId($feature['id'], ($code == 'weight') ? $value : $value['value'], $feature['type']);
                        }
                    } elseif (!in_array($value, $skip_values, true)) {
                        $field['feature_value_id'] = $model->getId($feature['id'], $value, $feature['type']);
                        $value = array(
                            'value' => $value,
                            'id'    => $field['feature_value_id'],
                        );
                    }
                    if (!empty($field['feature_value_id'])) {
                        $product_features_model->insert($field);
                        $data['features'][$code] = $value;
                    }
                } elseif (is_numeric($code) && is_numeric($value)) {
                    if ($feature = $feature_model->getById($code)) {
                        $field = array(
                            'product_id' => $data['product_id'],
                            'sku_id'     => $id,
                            'feature_id' => $code,
                        );
                        $product_features_model->deleteByField($field);
                        if (empty($value)) {
                            continue;
                        }
                        $field['feature_value_id'] = $value;
                        $product_features_model->insert($field);

                        $data['features'][$feature['code']] = $feature_model->getValuesModel($feature['type'])->getFeatureValue($value);
                    }
                }
            }
        }

        if ($correct) {
            $product_model = new shopProductModel();
            $product_model->correct($data['product_id']);
        }

        return $data;
    }

    /**
     *
     * @staticvar shopProductStocksLogModel $log_model
     * @param int $product_id
     * @param int $sku_id
     * @param int $count
     */
    public function logCount($product_id, $sku_id, $count)
    {
        /**
         * @var shopProductStocksLogModel
         */
        static $log_model = null;

        $old_count = $this->select('count')->where('id=i:sku_id', array('sku_id' => $sku_id))->fetchField();
        $log_data = array(
            'product_id'   => $product_id,
            'sku_id'       => $sku_id,
            'before_count' => $old_count,
            'after_count'  => $count
        );
        if (!$log_model) {
            $log_model = new shopProductStocksLogModel();
        }
        $log_model->add($log_data);
    }

    private function writeOffCount($product_id, $sku_id)
    {
        /**
         * @var shopProductStocksLogModel
         */
        static $log_model = null;

        $count = $this->select('count')->where('id=i:sku_id', array('sku_id' => $sku_id))->fetchField();
        if ($count === null) {
            return;
        }
        if (!$log_model) {
            $log_model = new shopProductStocksLogModel();
        }
        $log_model->add(
            array(
                'product_id'   => $product_id,
                'sku_id'       => $sku_id,
                'before_count' => $count,
                'after_count'  => 0
            )
        );
    }

    public function setData(shopProduct $product, $data)
    {
        $primary_currency = wa('shop')->getConfig()->getCurrency();

        $sort = 0;
        $default_sku_id = null;
        $result = array();

        foreach ($data as $sku_id => $sku) {
            $sku['sort'] = ++$sort;

            if (empty($sku['available'])) {
                $sku['available'] = 0;
            }

            if (isset($sku['price'])) {
                if ($product->currency == $primary_currency) {
                    $sku['primary_price'] = $sku['price'];
                } else {
                    $sku['primary_price'] = $this->convertPrice($sku['price'], $product->currency);
                }
            }
            $sku['product_id'] = $product->id;

            $sku = $this->updateSku($sku_id > 0 ? $sku_id : 0, $sku, false, $product);
            $result[$sku['id']] = $sku;

            if (!empty($sku['features'])) {
                foreach ($sku['features'] as $code => $value) {
                    if (!isset($features)) {
                        $features = $product->features;
                    }
                    if (!isset($features[$code])) {
                        $features[$code] = array();
                    }

                    if (is_array($features[$code])) {
                        if (is_array($value)) {
                            if (isset($value['id'])) {
                                if (!isset($features[$code][$value['id']])) {
                                    $features[$code][$value['id']] = $value['value'];
                                }
                            }
                        } else {
                            $features[$code][] = $value;
                        }
                    }
                }
            }

            if ($product->sku_id == $sku_id) {
                $default_sku_id = $sku['id'];
            }
        }

        $model = new shopProductModel();
        if (($default_sku_id === null) && ($result)) {
            $default_sku_id = current(array_keys($result));
        }
        $model->updateById($product->id, array('sku_id' => $default_sku_id));
        $model->correct($product->id);

        $product_data = $model->getById($product->id);
        $product->min_price = $product_data['min_price'];
        $product->max_price = $product_data['max_price'];
        $product->price = $product_data['price'];
        $product->compare_price = $product_data['compare_price'];
        $product->count = $product_data['count'];
        $product->setData('sku_count', count($data));
        $product->sku_id = $default_sku_id;
        if (isset($features)) {
            $product->features = $features;
        }

        return $result;
    }

    public function transfer($sku_id, $count, $src_stock, $dst_stock)
    {
        $src_stock = (int)$src_stock;
        $dst_stock = (int)$dst_stock;
        $sku_id = (int)$sku_id;
        $count = (int)$count;

        $sku = $this->getById($sku_id);
        if (empty($sku)) {
            return false;
        }

        $product_stocks_model = new shopProductStocksModel();

        return $product_stocks_model->transfer($src_stock, $dst_stock, $sku_id, $count);
    }

    public static function getPath($sku)
    {
        return empty($sku['file_name']) ? null : shopProduct::getPath($sku['product_id'], "sku_file/{$sku['id']}.".pathinfo($sku['file_name'], PATHINFO_EXTENSION));
    }
}
