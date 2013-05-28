<?php
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
     * @param array $product_ids
     */
    public function deleteByProducts(array $product_ids)
    {
        $this->deleteByField('product_id', $product_ids);
        foreach ((array) $product_ids as $product_id) {
            $file_path = shopProduct::getPath($product_id, "sku_file");
            waFiles::delete($file_path);
        }
    }

    /**
     * Delete sku of product by id with taking into account foreign relations and some other nuances
     *
     * @param int $sku_id
     * @return boolean
     */
    public function delete($sku_id)
    {
        $sku = $this->getById($sku_id);

        if (!$sku) {
            return false;
        }

        $product_model = new shopProductModel();
        $product = $product_model->getById($sku['product_id']);
        if (!empty($sku['file_name'])) {
            $file_path = shopProduct::getPath($sku['product_id'], "sku_file/{$sku['id']}.".pathinfo($sku['file_name'], PATHINFO_EXTENSION));
            waFiles::delete($file_path);
        }

        if (!$product) {
            $this->deleteById($sku_id); // product doesn't exist, but sku exists, so kill hanging sku
            return false;
        }

        // get aggregated info of skus for this product
        $data = $this->query("
            SELECT COUNT(id) AS cnt, MAX(price) AS max_price, MIN(price) AS min_price
            FROM `{$this->table}`
            WHERE product_id = {$product['id']} AND id != {$sku_id}
        ")->fetchAssoc();

        if (!$data) {
            return false; // something's wrong
            }

        if (!$data['cnt']) {
            return true; // can't remove single sku
            }

        $primary = wa('shop')->getConfig()->getCurrency();
        $currency = $primary;
        if ($product['currency'] && $product['currency'] != $primary) {
            $currency = $product['currency'];
        }
        // info for updatig product when sku'll be deleted
        $update = array(
            'max_price' => $currency != $primary ? $this->convertPrice($data['max_price'], $currency) : $data['max_price'],
            'min_price' => $currency != $primary ? $this->convertPrice($data['min_price'], $currency) : $data['min_price']
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
        if ($sku['count'] !== null && $product['count'] !== null) {
            $update['count'] = $product['count'] - $sku['count']; // increase count if it's possible
            }
        $diff = array_diff_assoc($update, $product);
        if ($diff) {
            $product_model->updateById($product['id'], $diff); // we'll have difference after sku's deleting, so up product info
            }
        if (!$this->deleteById($sku_id)) { // delete sku
            return false;
        }

        $this->deleteFromStocks($product['id'], $sku_id); // take info account stocks
        $this->deleteServices($product['id'], $sku_id);

        return true;
    }

    /**
     * @param int $product_id
     * @param int $sku_id
     */
    public function deleteFromStocks($product_id, $sku_id)
    {
        $product_stocks_model = new shopProductStocksModel();
        return $product_stocks_model->deleteByField(array('product_id' => $product_id, 'sku_id' => $sku_id));
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

    public function getSku($sku_id) {
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

    public function getData(shopProduct $product)
    {
        $stocks_model = new shopProductStocksModel();
        $stocks = $stocks_model->getByField('product_id', $product->id, true);
        $skus = $this->getByField('product_id', $product->id, $this->id);
        foreach ($skus as $id => & $sku) {
            $sku['count'] = ($sku['count'] === null ? null : (int) $sku['count']);
            $sku['price'] = (float) $sku['price'];
            $sku['purchase_price'] = (float) $sku['purchase_price'];
            $sku['compare_price'] = (float) $sku['compare_price'];
            $sku['stock'] = array();
        }
        unset($sku);
        foreach ($stocks as $stock) {
            $id = $stock['sku_id'];
            if (isset($skus[$id])) {
                $skus[$id]['stock'][$stock['stock_id']] = $stock['count'];
            }
        }
        return $skus;
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

    private static function castStock($count)
    {
        if ($count === '' || !preg_match('@^\-?\d*(\.(\d+)?)?$@', $count)) {
            $count = null;
        } else {
            $count = floatval($count);
        }
        return $count;
    }

    public function setData(shopProduct $product, $data)
    {
        $result = array();
        $price = array();
        $product_id = $product->getId();

        $default_sku_id = null;

        $update_product_data = array();

        $primary_currency = wa()->getConfig()->getCurrency();

        $stocks_model = new shopProductStocksModel();
        $feature_model = new shopFeatureModel();
        $product_features_model = new shopProductFeaturesModel();

        // aggragate count by stocks for product
        // Invariant: if at least one sku.count IS NULL this aggragate count IS NULL
        $product_count = 0;

        $available_sku_count = 0;
        $sort = 0;
        foreach ($data as $sku_id => $sku) {
            $sku['sort'] = ++$sort;
            if (empty($sku['available'])) {
                $sku['available'] = 0;
            }

            if ($sku['available']) {
                $available_sku_count++;
            }

            $price[] = $this->castValue('double', $sku['price']);

            if (isset($sku['purchase_price'])) {
                $sku['purchase_price'] = $this->castValue('double', $sku['purchase_price']);
            }

            if (isset($sku['compare_price'])) {
                $sku['compare_price'] = $this->castValue('double', $sku['compare_price']);
            }

            if ($product->currency == $primary_currency) {
                $sku['primary_price'] = $sku['price'];
            } else {
                $sku['primary_price'] = $this->convertPrice($sku['price'], $product->currency);
            }

            if ($sku_id > 0) {
                if (empty($sku['eproduct']) && !empty($sku['file_name'])) {
                    $file_path = shopProduct::getPath($product_id, "sku_file/{$sku_id}.".pathinfo($sku['file_name'], PATHINFO_EXTENSION));
                    waFiles::delete($file_path);
                    $sku['file_name'] = '';
                    $sku['file_description'] = '';
                } elseif (isset($sku['file_name'])) {
                    unset($sku['file_name']);
                }
                $this->updateById($sku_id, $sku);

                if ($product->sku_id == $sku_id) {
                    $default_sku_id = $sku_id;
                }

            } else {
                $is_default = false;
                if ($product->sku_id == $sku_id) {
                    $is_default = true;
                }

                $sku['product_id'] = $product_id;
                $sku_id = $this->insert($sku);
                unset($sku['product_id']);

                if ($is_default) {
                    $default_sku_id = $sku_id;
                    //$update_product_data['sku_id'] = $sku_id;
                }
            }

            // aggregate count by stocks.
            // In multistocking: if at least one stock-sku count doesn't exists this aggregate counter turn into NULL
            $sku_count = null;

            // if stocking for this sku
            if (isset($sku['stock']) && count($sku['stock'])) {
                $sku_count = 0;

                // not multistocking
                if (isset($sku['stock'][0])) {
                    $sku_count = self::castStock($sku['stock'][0]);
                    unset($sku['stock'][0]);

                // multistocking
                } else {
                    foreach ($sku['stock'] as $stock_id => $count) {
                        $field = array(
                            'stock_id'   => $stock_id,
                            'sku_id'     => $sku_id,
                            'product_id' => $product->id,
                        );
                        $count = self::castStock($count);
                        if ($count === null) {
                            // turn into NULL and is not longer changing
                            $sku_count = null;
                            $stocks_model->deleteByField($field);
                        } else {
                            // Once turned into NULL value is not changed
                            if ($sku_count !== null) {
                                $sku_count += $count;
                            }

                            $stock = array('count' => $count);
                            try {
                                $stocks_model->insert(array_merge($field, $stock));
                            } catch (waDbException $ex) {
                                if ($ex->getCode() == '1062') {
                                    $stocks_model->updateByField($field, $stock);
                                } else {
                                    throw $ex;
                                }
                            }
                        }
                        $sku['stock'][$stock_id] = $count;
                    }
                }
            }

            // maintain product_count invariant. See above
            if ($sku['available']) {
                if ($sku_count === null) {
                    $product_count = null;
                } elseif ($product_count !== null) {
                    $product_count += $sku_count;
                }
            }

            $sku['count'] = $sku_count;

            $this->updateById($sku_id, array('count' => $sku_count));

            if (isset($sku['features'])) {

                foreach ($sku['features'] as $code => $value) {

                    // ingoring empty values
                    if (empty($value)) {
                        continue;
                    }

                    if ($feature = $feature_model->getByField('code', $code)) {
                        $model = shopFeatureModel::getValuesModel($feature['type']);
                        $field = array(
                            'product_id' => $product_id,
                            'sku_id'     => $sku_id,
                            'feature_id' => $feature['id'],
                        );
                        $product_features_model->deleteByField($field);
                        $field['feature_value_id'] = $model->getId($feature['id'], $value, $feature['type']);
                        $product_features_model->insert($field);
                    } elseif (is_numeric($code) && is_numeric($value)) {
                        $field = array(
                            'product_id' => $product_id,
                            'sku_id'     => $sku_id,
                            'feature_id' => $code,
                        );
                        $product_features_model->deleteByField($field);
                        $field['feature_value_id'] = $value;
                        $product_features_model->insert($field);
                    }
                }
            }
            $result[$sku_id] = $sku;
        }

        if ($available_sku_count == 0) {
            $product_count = 0;
        }

        //TODO save it
        if (!$price) {
            $price[] = 0;
        }

        //XXX temporal hack
        $model = new shopProductModel();

        // if default sku id not found choose first sku_id
        if ($default_sku_id === null) {
            $product->sku_id = $update_product_data['sku_id'] = current(array_keys($result));
        } else {
            $product->sku_id = $update_product_data['sku_id'] = $default_sku_id;
        }

//         if (empty($update_product_data['sku_id']) && $product->sku_id <= 0) {
//             $update_product_data['sku_id'] = current(array_keys($result));
//         }
//         if (!empty($update_product_data['sku_id'])) {
//             $product->sku_id = $update_product_data['sku_id'];
//         }

        if ($product->currency && $product->currency != $primary_currency) {
            $product->min_price = $this->convertPrice(min($price), $product->currency);
            $product->max_price = $this->convertPrice(max($price), $product->currency);

            $product->price = $update_product_data['price'] =
                $this->convertPrice(
                    $result[$product->sku_id]['price'],
                    $product->currency
                );

            if (isset($result[$product->sku_id]['compare_price'])) {
                $product->compare_price = $update_product_data['compare_price'] =
                    $this->convertPrice(
                        $result[$product->sku_id]['compare_price'],
                        $product->currency
                    );
            }

        } else {
            $product->min_price = min($price);
            $product->max_price = max($price);
            $product->price = $update_product_data['price'] =
                $this->castValue(
                    'double',
                    $result[$product->sku_id]['price']
                );

            if (isset($result[$product->sku_id]['compare_price'])) {
                $product->compare_price = $update_product_data['compare_price'] =
                    $this->castValue(
                        'double',
                        $result[$product->sku_id]['compare_price']
                    );
            }
        }

        $product->count = $product_count;

        if (!$product->currency) {
            $product->currency = $primary_currency;
        }

        $update_product_data['min_price'] = $product->min_price;
        $update_product_data['max_price'] = $product->max_price;
        $update_product_data['count'] = $product_count;
        $update_product_data['currency'] = $product->currency;

        $model->updateById($product->getId(), $update_product_data);

        return $result;
    }

    public function transfer($sku_id, $count, $src_stock, $dst_stock)
    {
        $src_stock = (int) $src_stock;
        $dst_stock = (int) $dst_stock;
        $sku_id = (int) $sku_id;
        $count = (int) $count;

        $sku = $this->getById($sku_id);
        if (empty($sku)) {
            return false;
        }

        $product_stocks_model = new shopProductStocksModel();
        return $product_stocks_model->transfer($src_stock, $dst_stock, $sku_id, $count);
    }
}
