<?php

class shopProductFeaturesSelectableModel extends waModel implements shopProductStorageInterface
{
    protected $table = 'shop_product_features_selectable';

    public function deleteByProducts(array $product_ids)
    {
        $this->deleteByField('product_id', $product_ids);
    }

    /**
     * Insert(update) data for this product ID
     */
    private function save($product, $added, $deleted, $obsolete)
    {
        if ($obsolete) {
            $product_skus_model = new shopProductSkusModel();
            $params = array(
                'virtual' => 1,
            );
            if ($obsolete !== true) {
                $params['id'] = $obsolete;
            }
            $product_skus_model->deleteJoin('shop_product_features', $product->id, $params);
            $product_skus_model->deleteByField(array('product_id' => $product->id,) + $params);
        }

        $insert = array();
        foreach ($added as $f_id => $values) {
            foreach ($values as $v_id) {
                $insert[] = array(
                    'product_id' => (int)$product->id,
                    'feature_id' => $f_id,
                    'value_id'   => $v_id
                );
            }
        }

        if ($insert) {
            $this->multipleInsert($insert);
        }
        if ($deleted === true) {
            $this->deleteByField(
                array(
                    'product_id' => (int)$product->id,
                )
            );
        } else {
            foreach ($deleted as $f_id => $values) {
                if ($values === true) {
                    $this->deleteByField(
                        array(
                            'product_id' => (int)$product->id,
                            'feature_id' => $f_id,
                        )
                    );
                } else {
                    $this->deleteByField(
                        array(
                            'product_id' => (int)$product->id,
                            'feature_id' => $f_id,
                            'value_id'   => $values,
                        )
                    );
                }
            }
        }
    }

    /**
     * @param int|int[] $product_id
     * @return int[]
     */
    public function getFeatures($product_id)
    {
        $rows = $this->select(is_array($product_id) ? 'DISTINCT `feature_id`' : '`feature_id`')->where($this->getWhereByField('product_id', $product_id))->fetchAll('feature_id');

        return array_keys($rows);
    }

    /**
     * @param int $id product id
     * @return array value_id[feature_id][value_id]
     */
    public function getByProduct($id, &$sort = null)
    {
        $selected = array();
        if ($id) {
            $rows = $this->where('product_id=?', [$id])->order('sort, feature_id')->fetchAll();
            foreach ($rows as $row) {
                $row = array_map('intval', $row);
                $selected[$row['feature_id']][$row['value_id']] = $row['value_id'];
                if (!isset($sort[$row['feature_id']])) {
                    $sort[$row['feature_id']] = $row['sort'];
                }
            }
        }

        return $selected;
    }

    /**
     * @param shopProduct $product
     * @param $data
     * @return array
     * @example <pre>
     * array(
     *  'feature_code_0'=> array(%value_id_1%=>%value_id_1%, %value_id_2%=>%value_id_2%,),
     *  'feature_code_1'=>array(
     *      'values'=>array(%value_id_1%=>%value_id_1%, %value_id_2%=>%value_id_2%,)
     *      ['stock'=>(+/-)int|int[],]
     *  ),
     *  //next format used for import purpose
     *  'feature_code_2'=>array(
     *      'values'=>array(
     *          array(
     *              'value_id'=>%value_id%,
     *              ['price'=>(+/-)double,]
     *          ),
     *      ),
     *      ['stock'=>(+/-)int|int[],]
     *  ),
     *  'feature_code_3'=>array(
     *      'values'=>array(
     *          array(
     *              'value'=>%value%,
     *              ['price'=>(+/-)double,]
     *              ['stock'=>(+/-)int|int[],]
     *          ),
     *      ),
     *      ['stock'=>(+/-)int|int[],]
     *  ),
     * )
     * </pre>
     */
    public function setData(shopProduct $product, $data)
    {
        if ($product->sku_type == shopProductModel::SKU_TYPE_SELECTABLE) {
            $selected = $this->getSelectedData($data);
            $exists = $this->getByProduct($product->id);

            if (!$product->id || !$this->arrayEq($exists, $selected)) {
                //regenerate SKUs


                $sku_map = $this->generateSku($product, $selected, $data);

                //XXX check product features need to be updated
                $added = array();
                $deleted = array();

                foreach ($selected as $feature_id => $values) {
                    if (!isset($exists[$feature_id])) {
                        $added[$feature_id] = $values;
                    } else {
                        $added[$feature_id] = array_diff_assoc($values, $exists[$feature_id]);
                    }
                }
                $added = array_filter($added, 'count');

                foreach ($exists as $feature_id => $values) {
                    if (!isset($selected[$feature_id])) {
                        $deleted[$feature_id] = true;
                    } else {
                        $deleted[$feature_id] = array_diff_assoc($values, $selected[$feature_id]);
                        if (empty($deleted[$feature_id])) {
                            unset($deleted[$feature_id]);
                        }
                    }
                }

                $this->save($product, $added, $deleted, $sku_map);
            } else {
                //Or just update prices & stock
                $skus = $product->skus;
                $prices = array(
                    'price'          => $product->base_price_selectable,
                    'compare_price'  => $product->compare_price_selectable,
                    'purchase_price' => $product->purchase_price_selectable,
                );

                foreach ($skus as &$sku) {
                    if (!empty($sku['virtual'])) {
                        $sku = array_merge($sku, $prices);
                    }
                    unset($sku);
                }
                $product->skus = $skus;
            }
        } else {
            // selectable features are now used in both sku_type modes of a product
        }

        return $data;
    }

    /**
     * Verify input data and get selected base features
     * @param array &$data
     * @return array value_id[value_id][feature_id]
     */
    private function getSelectedData(&$data)
    {
        $selected = array();
        $features = array();
        if ($feature_codes = array_keys($data)) {
            $feature_model = new shopFeatureModel();
            $features = $feature_model->getByCode($feature_codes);
        }

        foreach ($data as $code => &$feature) {
            if (!isset($features[$code])) {
                unset($data[$code]);
            } else {

                $feature_id = intval($features[$code]['id']);
                $selected[$feature_id] = array();
                if (!isset($feature['values'])) {
                    $feature = array(
                        'values' => $feature,
                    );
                }
                $feature['feature_id'] = $feature_id;
                $values = $feature['values'];
                $feature['values'] = array();
                foreach ($values as $value) {
                    if (is_array($value)) {
                        if (isset($value['value']) && empty($value['id'])) {
                            $value['id'] = $feature_model->getValueId($features[$code], $value['value'], true);
                        }
                    } else {
                        $value = array(
                            'id' => $value,
                        );
                    }
                    $id = $value['id'];

                    if (!isset($value['value'])) {
                        $value['value'] = (string)$feature_model->getValuesModel($features[$code]['type'])->getFeatureValue($id);
                    }

                    $feature['values'][$id] = $value;
                    $selected[$feature_id][$id] = $id;
                    unset($value);
                }
            }
            unset($values);
        }
        ksort($selected, SORT_NUMERIC);

        return $selected;
    }

    /**
     * @todo use delta/absolute price
     * @param shopProduct $product
     * @param array $selected
     * @param array $data
     * @return array
     */
    private function generateSku(shopProduct $product, $selected, &$data)
    {
        $skus = $product->skus;
        if (empty($skus)) {
            $skus = array();
        }

        #build features map for exists SKUs
        $sku_map = array();
        $product_features_model = new shopProductFeaturesModel();
        foreach ($product_features_model->getSkuFeatures($product->id) as $sku_id => $f) {
            $key = "";
            foreach ($f as $feature_id => $value_id) {
                if (!self::ignoredFeature($feature_id)) {
                    $key .= $feature_id.":".$value_id.";";
                }
            }
            $sku_map[$key] = $sku_id;
        }

        $map = array();
        foreach ($data as $code => $d) {
            $map[$d['feature_id']] = $code;
        }

        $default_sku = array(
            'sku'       => '',
            'virtual'   => 1,
            'available' => 1,
            'status'    => 1,
            'count'     => null
        );


        $i = 0;

        $cartesian = $this->arrayCartesian($selected);
        $sku_count = count($cartesian);
        while ($features = array_shift($cartesian)) {
            $sku = array(
                'name'           => array(),
                'features'       => array(),
                'price'          => $product->base_price_selectable,
                'compare_price'  => $product->compare_price_selectable,
                'purchase_price' => $product->purchase_price_selectable,
            );

            $sku_key = "";
            $last_value_id = end($features);

            foreach ($features as $feature_id => $value_id) {
                if (!self::ignoredFeature($feature_id)) {
                    $sku_key .= $feature_id.":".$value_id.";";
                }
                $sku['key'] = $sku_key;
                $code = $map[$feature_id];

                $value = $data[$code]['values'][$value_id];

                $sku['features'][$code] = $value;
                $sku['name'][] = ifset($value['value'], $value['id']);

                #correct price
                if (isset($data[$code]['values'][$value_id]['price'])) {
                    self::parseSkuPrice($sku, $data[$code]['values'][$value_id]['price']);
                }

                #set counts per stock
                if (isset($data[$code]['stock'])) {
                    $sku_id = isset($sku_map[$sku_key]) ? $sku_map[$sku_key] : null;
                    if ($sku_id !== null) {
                        self::parseSkuStock($skus[$sku_id], $data[$code]['stock'], empty($cartesian) ? $sku_count : null);
                    } else {
                        self::parseSkuStock($sku, $data[$code]['stock'], empty($cartesian) ? $sku_count : null);
                    }
                }

            }

            #concat name from feature values
            $sku['name'] = implode(', ', $sku['name']);

            if (isset($sku_map[$sku_key])) {
                // already exists
                $sku_id = $sku_map[$sku_key];

                if (!empty($skus[$sku_id]['virtual'])) {
                    //update SKU if still virtual
                    $skus[$sku_id] = array_merge($skus[$sku_id], $sku);
                }

                $sku_map[$sku_key] = false;
            } else {
                # get free sku_id
                do {
                    --$i;
                } while (isset($skus[$i]));

                $skus[$i] = array_merge($default_sku, $sku);
            }
        }


        if ($product->id) {
            // remove old virtual skus
            $sku_map = array_filter($sku_map);
            foreach ($sku_map as $key => $sku_id) {
                if (isset($skus[$sku_id])) {
                    if (empty($skus[$sku_id]['virtual'])) {
                        unset($sku_map[$key]);
                    } else {
                        unset($skus[$sku_id]);
                    }
                }
            }

            if ($sku_map && false) {
                $product_skus_model = new shopProductSkusModel();
                $product_skus_model->deleteJoin('shop_product_features', $product->id, array('virtual' => 1, 'id' => $sku_map));
                $product_skus_model->deleteByField(array('product_id' => $product->id, 'virtual' => 1, 'id' => $sku_map));
            }
        }
        $product->skus = $skus;

        return $sku_map;
    }

    /**
     * @param shopProduct $product
     * @return array|bool
     * @example <pre>
     * array(
     *  'feature_1'=>array(
     *      'selected'=>int,
     *      'values'=>array(
     *         %value_id%=> array(
     *              'value_id'=>int,
     *              'value'=>string,
     *              'selected'=>bool,
     *          ),
     *      ),
     *  ),
     * )
     * </pre>
     */
    public function getData(shopProduct $product)
    {
        return $this->getDataByProduct($product);
    }

    /**
     * @param shopProduct $product
     * @param array $options
     * @return array|bool|null
     */
    public function getDataByProduct(shopProduct $product, $options = array())
    {
        $env = !empty($options['env']) ? $options['env'] : wa()->getEnv();

        $feature_model = new shopFeatureModel();
        if ($product->sku_type == shopProductModel::SKU_TYPE_SELECTABLE) {
            $sort = [];
            $selected = $this->getByProduct($product->id, $sort);

            if ($env == 'backend') {
                $features = $feature_model->getMultipleSelectableFeaturesByType($product->type_id);
            } else {
                $features = $feature_model->getById(array_keys($selected));

                // Sort ordering for normal sort (when saved via new product editor)
                foreach ($sort as $feature_id => $s) {
                    if (isset($features[$feature_id])) {
                        $features[$feature_id]['sort1'] = $s;
                    }
                }

                // In case we need to sort features by type (see below), fetch the data
                $need_sort_by_type = count(array_flip(array_values($sort))) <= 1;
                if ($need_sort_by_type && $product->type_id) {
                    $types = array($product->type_id => true);
                    $type_features_model = new shopTypeFeaturesModel();
                    $type_features_model->fillTypes($features, $types);
                    foreach ($features as &$feature) {
                        unset($feature['types']);
                        $feature['sort2'] = ifset($feature, 'sort', $product->type_id, null);
                    }
                    unset($feature);
                }

                uasort($features, function($a, $b) {

                    //
                    // Normal case: both features have their ordering specified
                    // in shop_product_features_selectable. This is the case when
                    // product has been saved via new editor.
                    //
                    if ($a['sort1'] < $b['sort1']) {
                        return -1;
                    } else if ($a['sort1'] > $b['sort1']) {
                        return 1;
                    }

                    //
                    // Legacy case: features have equal `sort` in shop_product_features_selectable.
                    // This means that product has never been saved in new editor.
                    // We should use old ordering as features are sorted in product type.
                    //
                    if (isset($a['sort2']) && isset($b['sort2'])) {
                        if ($a['sort2'] < $b['sort2']) {
                            return -1;
                        } else if ($a['sort2'] > $b['sort2']) {
                            return 1;
                        } else {
                            return 0;
                        }
                    }

                    //
                    // Abnormal case: one or both features are not part of product type.
                    // This may happen if product type has been changed.
                    // Append unknown features at the end.
                    //
                    if (isset($a['sort2'])) {
                        return 1;
                    } else if (isset($b['sort2'])) {
                        return -1;
                    }

                    return 0;
                });
            }

            // attach values
            $features = $feature_model->getValues($features);

            foreach ($features as &$f) {
                $count = 0;
                foreach ($f['values'] as $id => &$v) {
                    $is_selected = isset($selected[$f['id']][$id]);
                    if ($env == 'backend') {
                        if (is_object($v)) {
                            $v['selected'] = $is_selected;
                        } else {
                            $v = array(
                                'value'    => htmlentities((string)$v, ENT_NOQUOTES, 'utf-8'),
                                'selected' => $is_selected
                            );
                        }
                        unset($v);
                        if ($is_selected) {
                            $count += 1;
                        }
                    } elseif (!$is_selected) {
                        unset($f['values'][$id]);
                    }
                }
                $f['selected'] = $count;
                unset($f);
            }
        } else {
            if ($env == 'backend') {
                $features = $feature_model->isTypeMultipleSelectable($product->type_id);
            } else {
                $features = array();
            }
        }

        return $features;
    }

    /**
     * @param $arrays
     * @return array
     *
     * @example <pre>
     * array(
     *  'feature_1'=>array(
     *      'values'=>array(%value_id_1%=>%value_id_1%, %value_id_2%=>%value_id_2%,)
     *  ),
     *  'feature_2'=>array(
     *      'values'=>array(
     *          array(
     *              'value_id'=>%value_id%,
     *              ['price'=>(+/-)double,]
     *          ),
     *      ),
     *  ),
     *  'feature_3'=>array(
     *      'values'=>array(
     *          array(
     *              'value'=>%value%,
     *              ['price'=>(+/-)double,]
     *          ),
     *      ),
     *  ),
     * )
     * </pre>
     */
    protected function arrayCartesian($arrays)
    {
        $result = array();
        $keys = array_keys($arrays);
        $reverse_keys = array_reverse($keys);
        $size = intval(count($arrays) > 0);
        foreach ($arrays as $array) {
            $size *= count($array);
        }
        for ($i = 0; $i < $size; $i++) {
            $result[$i] = array();
            foreach ($keys as $j) {
                $result[$i][$j] = current($arrays[$j]);
            }
            foreach ($reverse_keys as $j) {
                if (next($arrays[$j])) {
                    break;
                } elseif (isset($arrays[$j])) {
                    reset($arrays[$j]);
                }
            }
            ksort($result[$i], SORT_NUMERIC);
        }

        return $result;
    }

    protected function arrayEq($a, $b)
    {
        if (count($a) != count($b)) {
            return false;
        }
        foreach ($a as $k => $v) {
            if (!isset($b[$k]) || count($v) != count($b[$k])) {
                return false;
            }
            foreach ($v as $sv) {
                if (!in_array($sv, $b[$k])) {
                    return false;
                }
            }
        }

        return true;
    }

    protected static function parseSkuStock(&$sku, $stock, $sku_count)
    {
        if (!is_array($stock)) {
            $stock = array(0 => $stock);
        }

        foreach ($stock as $id => $count) {
            if (preg_match('@^(\+|-|)(\d+|\d+\.\d+|\.\d+)$@', $count, $matches)) {
                $count = intval($matches[2]);
                if ($sku_count) {
                    $count_ = doubleval($matches[2]) - $count;
                    if ($count_ > 0) {
                        $count += ceil((doubleval($matches[2]) - $count) * $sku_count);
                    }
                }
                if (!isset($sku['stock'])) {
                    $sku['stock'] = array();
                }
                if (!isset($sku['stock'][$id])) {
                    $sku['stock'][$id] = 0;
                }
                switch ($matches[1]) {
                    case '-':
                        $sku['stock'][$id] -= $count;
                        break;
                    case '+':
                        $sku['stock'][$id] += $count;
                        break;
                    default:
                        $sku['stock'][$id] = $count;
                        break;
                }
            }
        }
    }

    protected static function parseSkuPrice(&$sku, $price)
    {
        if (preg_match('@^(\+|-|)(\d+|\d+\.\d+|\.\d+)$@', $price, $matches)) {
            $price = doubleval($matches[2]);
            switch ($matches[1]) {
                case '-':
                    $sku['price'] -= $price;
                    break;
                case '+':
                default:
                    $sku['price'] += $price;
                    break;
            }
        }
    }

    /**
     * @param int $feature_id
     * @return bool
     */
    protected static function ignoredFeature($feature_id)
    {
        static $ignored = null;
        if ($ignored === null) {
            $model = new shopFeatureModel();
            $data = array(
                'code' => array(
                    'weight',
                    'gtin'
                ),
            );
            $features = $model->select('id')->where('code in (s:code)', $data)->fetchAll('id');
            $ignored = array_map('intval', array_keys($features));
        }
        return in_array((int)$feature_id, $ignored, true);
    }

    public function getProductFeatureIds($product_id)
    {
        $sql = 'SELECT DISTINCT feature_id, sort FROM '.$this->table.' WHERE product_id = i:0 ORDER BY sort';
        return array_keys($this->query($sql, $product_id)->fetchAll('feature_id', true));
    }

    public function setFeatureIds(shopProduct $product, array $features_selectable_ids)
    {
        // Select value ids of given features, saved for SKUs of current product
        $values = [];
        if ($features_selectable_ids) {
            $sort = 0;
            $feature_ids_sort = [];
            $feature_ids_with_no_values = [];
            foreach ($features_selectable_ids as $id) {
                $feature_ids_with_no_values[$id] = true;
                $feature_ids_sort[$id] = $sort;
                $sort++;
            }

            $product_features_model = new shopProductFeaturesModel();
            $rows = $product_features_model->getByField([
                'sku_id' => array_keys($product['skus']),
                'product_id' => $product['id'],
                'feature_id' => $features_selectable_ids,
            ], true);
            foreach ($rows as $row) {
                unset($feature_ids_with_no_values[$row['feature_id']]);
                $values[$row['feature_id'].'.'.$row['feature_value_id']] = [
                    'product_id' => $product['id'],
                    'feature_id' => $row['feature_id'],
                    'value_id' => $row['feature_value_id'],
                    'sort' => ifset($feature_ids_sort, $row['feature_id'], 0),
                ];
            }
            unset($rows);

            foreach ($feature_ids_with_no_values as $feature_id => $_) {
                $values[$feature_id.'.0'] = [
                    'product_id' => $product['id'],
                    'feature_id' => $feature_id,
                    'value_id' => 0,
                    'sort' => ifset($feature_ids_sort, $feature_id, 0),
                ];
            }
        }

        // Set them as selectable values
        $this->deleteByField('product_id', $product['id']);
        if ($values) {
            $this->multipleInsert(array_values($values));
        }
    }
}
