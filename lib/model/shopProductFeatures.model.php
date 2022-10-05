<?php

class shopProductFeaturesModel extends waModel implements shopProductStorageInterface
{
    protected $table = 'shop_product_features';

    //protected $id = array('product_id', 'sku_id', 'feature_id', 'feature_value_id', );

    /**
     * @param int[] $product_ids
     * @return bool
     */
    public function deleteByProducts(array $product_ids)
    {
        return $this->deleteByField('product_id', $product_ids);
    }

    public function getValuesByCategory($category_id)
    {
        $sql = "SELECT DISTINCT f.feature_id, f.feature_value_id FROM ".$this->table." f
        JOIN shop_category_products c ON f.product_id = c.product_id
        WHERE c.category_id ".(is_array($category_id) ? 'IN (i:id)' : '= i:id');
        $rows = $this->query($sql, array('id' => $category_id));
        $result = array();
        foreach ($rows as $row) {
            $result[$row['feature_id']][] = $row['feature_value_id'];
        }
        return $result;
    }

    /**
     * @param int $product_id
     * @param string $key
     * @return int[][] value_id[sku_id][$key]
     */
    public function getSkuFeatures($product_id, $key = 'feature_id')
    {
        if (!$product_id) {
            return array();
        }
        $sql = "SELECT * FROM ".$this->table." WHERE product_id = i:id AND sku_id IS NOT NULL";
        $result = array();
        $rows = $this->query($sql, array('id' => $product_id))->fetchAll();
        foreach ($rows as $row) {
            $row = array_map('intval', $row);
            $result[$row['sku_id']][$row[$key]] = $row['feature_value_id'];
        }
        foreach ($result as &$sku) {
            ksort($sku, SORT_NUMERIC);
            unset($sku);
        }
        return $result;
    }

    public function getSkuByFeatures($product_id, $features)
    {
        $sql = "SELECT t0.sku_id FROM ".$this->table." t0 ";
        for ($i = 1; $i < count($features); $i++) {
            $sql .= " JOIN ".$this->table." t".$i." ON t0.product_id = t".$i.".product_id AND t0.sku_id = t".$i.".sku_id";
        }
        $sql .= " WHERE t0.product_id = ".(int)$product_id." AND t0.sku_id IS NOT NULL";
        $i = 0;
        foreach ($features as $f => $v) {
            $sql .= " AND t".$i.".feature_id = ".(int)$f." AND t".$i.".feature_value_id = ".(int)$v;
            $i++;
        }
        $sql .= " LIMIT 1";
        return $this->query($sql)->fetchField();
    }

    public function getSkusByFeatures($product_ids, $features, $in_stock_only = false)
    {
        if (!$product_ids || !$features) {
            return array();
        }
        $sql = "SELECT s.id, s.image_id, s.product_id, s.price, s.compare_price, s.sort FROM shop_product_skus s";
        for ($i = 0; $i < count($features); $i++) {
            $sql .= " JOIN ".$this->table." t".$i."
                ON t".$i.".product_id = s.product_id AND (t".$i.".sku_id IS NULL OR t".$i.".sku_id = s.id)";
        }
        $sql .= " WHERE s.product_id IN (i:product_ids) AND s.available > 0 AND s.status > 0";
        if ($in_stock_only) {
            $sql .= ' AND (s.count IS NULL OR s.count > 0)';
        }
        $i = 0;
        foreach ($features as $f => $v) {
            $sql .= " AND t".$i.".feature_id = ".(int)$f." AND t".$i.".sku_id IS NOT NULL AND t".$i.".feature_value_id ";
            if (is_array($v)) {
                $sql .= 'IN ('.implode(',', array_map('intval', $v)).')';
            } else {
                $sql .= '= '.(int)$v;
            }
            $i++;
        }
        return $this->query($sql, array('product_ids' => $product_ids))->fetchAll();
    }

    public function deleteByFeature($feature_id, $feature_value_id = null)
    {
        $sql = "DELETE FROM {$this->table} WHERE ".$this->getWhereByField('feature_id', $feature_id);
        if ($feature_id !== null) {
            $sql .= ' AND '.$this->getWhereByField('feature_value_id', $feature_value_id);
        }
        return $this->exec($sql);
    }

    /**
     * @param shopProduct $product
     * @param bool $public_only
     * @return array
     * @throws waDbException
     * @throws waException
     */
    public function getData(shopProduct $product, $public_only = false)
    {
        return $this->getValues($product->getId(), null, $product->type_id, $product->sku_type, $public_only);
    }

    /**
     * @param int  $product_id
     * @param int  $sku_id positive value will return SKU either Product feature values, negative value only SKU feature values
     * @param int  $type_id
     * @param int  $sku_type
     * @param bool $public_only
     * @return array
     * @throws waDbException
     * @throws waException
     */
    public function getValues($product_id, $sku_id = null, $type_id = null, $sku_type = 0, $public_only = false)
    {
        $result = array();
        $features = array();

        // Contains feature codes of features that we're not yet sure contain actual values.
        // E.g. shop_product_features may contain value_id that was deleted from shop_feature_values_*
        // or value_id corresponds to an empty string that does not count as value to show.
        // Dividers must never get into this array.
        $codes_to_remove = array();

        //
        // Get all features of product type. This allows to set up dividers properly,
        // even if divider is not saved for particular product in shop_product_features.
        //
        if ($type_id) {
            $result = $this->getListFeatures($type_id, $public_only);
            foreach ($result as $code => $row) {
                $result[$code] = null;
                if ($row['type'] != shopFeatureModel::TYPE_DIVIDER) {
                    $codes_to_remove[$code] = true;
                }
            }
        }

        //
        // Get all features of this product
        //
        $tf_join = '';
        $tf_select = '';
        $order_by = array();
        if ($type_id) {
            $tf_select = ", tf.sort";
            $tf_join = " LEFT JOIN shop_type_features tf ON ((tf.feature_id = IFNULL(f.parent_id,f.id)) AND (tf.type_id=i:type_id))";
            $order_by[] = "tf.sort";
        }

        if ($sku_id) {
            array_unshift($order_by, 'pf.sku_id');
            if ($sku_id > 0) {
                $sku_where = '(pf.sku_id = i:sku_id OR pf.sku_id IS NULL)';
            } else {
                $sku_where = '(pf.sku_id = i:sku_id)';
                $sku_id = -$sku_id;
            }
        } else {
            $sku_where = 'pf.sku_id IS NULL';
            if ($sku_type) {
                $selectable_model = new shopProductFeaturesSelectableModel();
                $selectable_feature_ids = $selectable_model->getProductFeatureIds($product_id);
                if ($selectable_feature_ids) {
                    $sku_where = '(pf.sku_id IS NULL OR pf.feature_id IN ('.implode(',', $selectable_feature_ids).'))';
                }
            }
        }

        $status_sql = '';
        if ($public_only) {
            $status_sql = "AND f.status='public'";
        }

        if ($order_by) {
            $order_by = 'ORDER BY '.join(', ', $order_by);
        } else {
            $order_by = '';
        }

        $sql = "SELECT pf.*, f.code, f.type, f.multiple {$tf_select}
                FROM {$this->table} pf
                    JOIN shop_feature f
                        ON pf.feature_id = f.id
                    {$tf_join}
                WHERE pf.product_id = i:id
                    AND {$sku_where}
                    {$status_sql}
                {$order_by}";
        $data = $this->query($sql, array(
            'id'      => $product_id,
            'sku_id'  => $sku_id,
            'type_id' => $type_id,
        ));

        // Prepare list of value_ids to fetch later and places to fetch them from
        $storages = array();
        foreach ($data as $row) {
            $features[$row['feature_id']] = array(
                'type'     => $row['type'],
                'code'     => $row['code'],
                'multiple' => $row['multiple'],
            );
            if (preg_match('/^(.+)\.[0-2]$/', $row['code'], $matches)) {
                $code = $matches[1];
            } else {
                $code = $row['code'];
            }
            $result[$code] = null;

            $type = preg_replace('/\..*$/', '', $row['type']);
            switch ($type) {
                case shopFeatureModel::TYPE_BOOLEAN:
                case shopFeatureModel::TYPE_DIVIDER:
                    /** @var shopFeatureValuesBooleanModel|shopFeatureValuesDividerModel $model */
                    $model = shopFeatureModel::getValuesModel($type);
                    $values = $model->getValues('id', $row['feature_value_id']);
                    $feature_values = reset($values);
                    $result[$row['code']] = $feature_values[$row['feature_value_id']];
                    unset($codes_to_remove[$row['code']]);
                    break;
                default:
                    $codes_to_remove[$code] = true;
                    if ($sku_id) {
                        // Make sure feature assigned to SKU overrides feature assigned to product
                        if (!empty($row['sku_id']) || !isset($storages[$type][$row['feature_id']])) {
                            $storages[$type][$row['feature_id']] = $row['feature_value_id'];
                        }
                    } else {
                        $storages[$type][] = $row['feature_value_id'];
                    }
            }
        }

        // Fetch actual values from shop_feature_values_* tables
        foreach ($storages as $type => $value_ids) {
            if ($type === shopFeatureModel::TYPE_2D || $type === shopFeatureModel::TYPE_3D) {
                continue;
            }
            if ($model = shopFeatureModel::getValuesModel($type)) {
                $feature_values = $model->getValues('id', $value_ids);
                foreach ($feature_values as $feature_id => $values) {
                    if (isset($features[$feature_id])) {
                        $f = $features[$feature_id];
                        $result[$f['code']] = ($sku_id || empty($f['multiple'])) ? reset($values) : $values;
                        if ($result[$f['code']] !== null) {
                            unset($codes_to_remove[$f['code']]);
                        }
                    } else {
                        //obsolete feature value
                    }
                }
            } else {
                waLog::log(sprintf('Feature model for type %s not found', $type), 'shop/features.error.log');
            }
        }

        /** composite fields workaround */
        $composite = array_filter(array_keys($result), wa_lambda('$a', 'return preg_match("/\.[0-2]$/",$a);'));
        $composite = array_unique(preg_filter('#\.[0-3]#', '', $composite));
        foreach ($composite as $code) {
            $code = preg_replace('/\.0$/', '', $code);
            $result[$code] = new shopCompositeValue($code, $result);
            unset($codes_to_remove[$code]);
        }

        // Remove all features without values (except dividers)
        foreach (array_keys($codes_to_remove) as $code) {
            unset($result[$code]);
        }

        return $result;
    }

    /**
     * Get feature values of multiple SKUs of the same product.
     * Faster than calling getValues() many times in a loop.
     * Only given $features will be returned.
     *
     * @param array $features feature settings
     * @param array[int] $sku_ids
     */
    public function getValuesMultiple($features, $product_id, $sku_ids)
    {
        if (empty($features) || empty($sku_ids)) {
            return [];
        }

        // index features by id in case they are indexed by code
        $features = array_column($features, null, 'id');

        // Fetch data from DB
        $sql = "SELECT pf.*, f.code, f.type, f.multiple, f.parent_id
                FROM {$this->table} pf
                    JOIN shop_feature f
                        ON pf.feature_id = f.id
                WHERE pf.product_id = i:product_id
                    AND pf.sku_id IN (i:sku_ids)
                    AND (f.id IN (i:feature_ids)
                        OR f.parent_id IN (i:feature_ids))";
        $data = $this->query($sql, array(
            'sku_ids'  => $sku_ids,
            'product_id' => $product_id,
            'feature_ids' => array_keys($features),
        ));

        // Prepare list of value_ids to fetch later (group by places to fetch them from)
        $sku_feature_value_ids = []; // sku_id => feature_id => value ids
        $storages = array(); // type => value ids
        foreach ($data as $row) {
            if (!isset($features[$row['feature_id']])) {
                if (empty($row['parent_id']) || empty($features[$row['parent_id']])) {
                    continue; // ignore features we've not been asked for
                }
                $features[$row['feature_id']] = array(
                    'id'        => $row['feature_id'],
                    'type'      => $row['type'],
                    'code'      => $row['code'],
                    'multiple'  => $row['multiple'],
                    'parent_id' => $row['parent_id'],
                );
            }
            $feature = $features[$row['feature_id']];
            if (!empty($feature['parent_id'])) {
                if (empty($features[$feature['parent_id']])) {
                    // ignore features if parent id we've not been asked for
                    unset($features[$feature['id']]);
                    continue;
                }
                $features[$feature['id']]['is_internal'] = true;
                $features[$feature['parent_id']]['is_composite'] = true;
            }

            $type = preg_replace('/\..*$/', '', $row['type']);
            $features[$feature['id']]['storage_type'] = $type;
            $storages[$type][$row['feature_value_id']] = $row['feature_value_id'];
            $sku_feature_value_ids[$row['sku_id']][$row['feature_id']][$row['feature_value_id']] = $row['feature_value_id'];
        }

        // Fetch actual values from shop_feature_values_* tables
        $feature_values = []; // feature_id => value_id => value
        $storage_type_values = []; // type => value_id => value
        foreach ($storages as $type => $value_ids) {
            $model = shopFeatureModel::getValuesModel($type);
            if ($model) {
                $values = $model->getValues('id', $value_ids);

                // Most storage types return $values as feature_id => value_id => value
                // Some (boolean, divider) do not know real feature_id and return 0 instead.
                // We keep them separately.
                $storage_type_values[$type] = ifset($values, 0, []);
                unset($values[0]);
                $feature_values += $values;
            }
        }

        unset($storages);

        // So far we have:
        // - $sku_feature_value_ids - value ids by sku, and
        // - $feature_values / $storage_type_values - value by value id.
        // Now combine them.
        $result = []; // sku_id => feature code => value(s)
        foreach($sku_ids as $sku_id) {

            $result[$sku_id] = [];

            $sku_values = [];
            foreach(ifset($sku_feature_value_ids, $sku_id, []) as $feature_id => $value_ids) {
                $feature = $features[$feature_id];
                $type = $feature['storage_type'];
                // Some values are attached to specific feature ids...
                $values = ifset($feature_values, $feature_id, []);
                // ...while some other values (boolean, divider) are common for all features of given type
                $values += ifset($storage_type_values, $type, []);
                $sku_values[$feature['code']] = array_intersect_key($values, $value_ids);
                // SKU can not have multiple values (only product can). Return a single value per SKU.
                $sku_values[$feature['code']] = reset($sku_values[$feature['code']]);
            }

            foreach($features as $feature) {
                if (!empty($feature['is_internal'])) {
                    continue; // internal features are part of composite (2d, 3d) - see below
                }
                if (!empty($feature['is_composite'])) {
                    // Call to CompositeValue constructor removes its parts from $sku_values
                    // because second argument to constructor is passed by referense X_x
                    $count_before = count($sku_values);
                    $value = new shopCompositeValue($feature['code'], $sku_values);
                    if ($count_before != count($sku_values)) {
                        $result[$sku_id][$feature['code']] = $value;
                    }
                } else if (isset($sku_values[$feature['code']])) {
                    $result[$sku_id][$feature['code']] = $sku_values[$feature['code']];
                }
            }

        }

        return $result;
    }

    /**
     * @param $type_id
     * @param bool $public_only
     * @return array
     */
    public function getListFeatures($type_id, $public_only = false)
    {
        if (!$type_id) {
            return [];
                }
        $result     = [];
        $status_sql = '';
        if ($public_only) {
            $status_sql = "AND f.status='public'";
        }
        $sql = "SELECT f.id AS feature_id, f.code, f.type, f.multiple, tf.sort
                    FROM shop_feature AS f
                        JOIN shop_type_features AS tf
                            ON tf.feature_id = IFNULL(f.parent_id,f.id)
                    WHERE tf.type_id=i:type_id
                        {$status_sql}
                    ORDER BY tf.sort";

        $data = $this->query($sql, ['type_id' => $type_id]);
        foreach ($data as $row) {
            $features[$row['feature_id']] = [
                'type'     => $row['type'],
                'code'     => $row['code'],
                'multiple' => $row['multiple'],
            ];
            if (preg_match('/^(.+)\.[0-2]$/', $row['code'], $matches)) {
                $code = $matches[1];
            } else {
                $code = $row['code'];
            }
            $result[$code] = $row;
        }

        return $result;
    }

    /**
     * @see shopProductStorageInterface::setData()
     * @param shopProduct $product current product object
     * @param mixed $data $data new product feature values
     * @throws waDbException
     * @throws waException
     */
    public function setData(shopProduct $product, $data)
    {
        $product_id = $product->getId();
        $codes = array_keys($data);

        /** unset features_selectable and don't save them */
        foreach ($codes as $code) {
            if (isset($product->features_selectable[$code])) {
                // Data in $feature_selectable_data may come in two different formats
                // depending on history of shopProduct interactions X_x
                // * In old editor (i.e. when $product['features_selectable'] got assigned)
                //   there won't be 'selected' key and the array will only contain features that are enabled.
                // * In new editor (i.e. $product['features_selectable'] were not touched)
                //   all andidates for selectable features are returned.
                //   'selected' means feature is enabled for this product as selectable.
                $feature_selectable_data = $product->features_selectable[$code];
                $is_enabled = ifset($feature_selectable_data, 'selected', 1);
                if ($is_enabled) {
                    $data[$code] = array();
                }
            }
        }
        unset($code);

        $codes = array_keys($data);
        $feature_model = new shopFeatureModel();
        $features = $feature_model->getByCode($codes);

        /**
         * composite fields workaround
         * @todo use shopCompositeValue::parse
         */

        $value_pattern = '(\d+|[\.,]\d+|\d+[\.,]\d+)';
        $multi_pattern = '[XxХх×✕✖\*\s]+';
        $unit_pattern  = '(\s+.+)?';

        $composite_codes = array();
        foreach ($data as $code => $value) {
            if (
                !preg_match('/\.[0-3]$/', $code)
                && isset($features[$code])
                && preg_match('/^([23])d\\./', $features[$code]['type'], $matches)
            ) {
                $n = $matches[1];
                $pattern = '/^'.implode($multi_pattern, array_fill(0, $n, $value_pattern)).$unit_pattern.'$/ui';
                if (preg_match($pattern, trim($value), $matches)) {
                    $unit = ifset($matches[$n + 1]);
                    for ($i = 0; $i < $n; $i++) {
                        $c_code = $code.'.'.$i;
                        $data[$c_code] = str_replace(',', '.', $matches[$i + 1]).$unit;
                        $composite_codes[] = $c_code;
                    }
                    unset($features[$code]);
                } elseif (is_string($value) && '' === trim($value)) {
                    for ($i = 0; $i < $n; $i++) {
                        $c_code = $code.'.'.$i;
                        $data[$c_code] = '';
                        $composite_codes[] = $c_code;
                    }
                } else {
                    waLog::log(sprintf('Error during parse %dD feature value [%s]', $n, $value), 'shop/features.error.log');
                }
                unset($data[$code]);
            }
        }

        if ($composite_codes) {
            $features += $feature_model->getByCode($composite_codes);
        }

        $features_map = array();
        foreach ($features as $code => $f) {
            $features_map[$f['id']] =& $features[$code];
        }

        $current = array();
        $rows = $this->getByField(array('product_id' => $product_id, 'sku_id' => null), true);
        foreach ($rows as $row) {
            $id = $row['feature_id'];
            if (isset($features_map[$id])) {
                $f = $features_map[$id];
                $code = $f['code'];
                if (empty($f['multiple'])) {
                    $current[$code] = intval($row['feature_value_id']);
                } else {
                    if (!isset($current[$code])) {
                        $current[$code] = array();
                    }
                    $current[$code][] = intval($row['feature_value_id']);
                }
            } else {
                //obsolete data
            }
        }

        $add = [];
        $delete = [];
        foreach ($data as $code => $value) {
            if (isset($features[$code])) {
                $f =& $features[$code];
                if (is_array($value)) {
                    $empty = isset($value['value']) && ($value['value'] === '');
                    if (!$empty && isset($value['value']) && is_array($value['value'])) {
                        foreach ($value['value'] as $key => $v) {
                            if ($v === '') {
                                unset($value['value'][$key]);
                            }
                        }
                        $empty = (count($value['value']) == 0);
                    }
                    if (!$empty && !isset($value['value'])) {
                        foreach ($value as $key => $v) {
                            if ($v === '') {
                                unset($value[$key]);
                            }
                        }
                        $empty = (count($value) == 0);
                    }
                } else {
                    $empty = ($value === '');
                }
                if ($empty) {
                    /** delete it */
                    if (isset($current[$code])) {
                        $delete[$f['id']] = $current[$code];
                    }
                } else {
                    if (is_array($value) && preg_match('/^(.+\.)[12]$/', $code, $matches) && isset($data[$matches[1].'0'])) {
                        $value = array_merge($data[$matches[1].'0'], $value);
                    }
                    $id = $feature_model->getValueId($f, $value, true);

                    if (isset($current[$code])) {
                        if (empty($f['multiple'])) {
                            if ($current[$code] != $id) {
                                $delete[$f['id']] = $current[$code];
                                $add[$f['id']] = $id;
                            }
                        } else {
                            $delete[$f['id']] = array_diff($current[$code], (array)$id);
                            if (empty($delete[$f['id']])) {
                                unset($delete[$f['id']]);
                            }
                            $add[$f['id']] = array_diff((array)$id, $current[$code]);
                            if (empty($add[$f['id']])) {
                                unset($add[$f['id']]);
                            }
                        }
                    } else {
                        $add[$f['id']] = $id;
                    }
                }
                unset($f);
            } elseif (!empty($value) && is_array($value)) {
                /** it's a new feature */
                if (!empty($value) && ((ifset($value['type']) == shopFeatureModel::TYPE_BOOLEAN) || !empty($value['value']))) {
                    $f = array(
                        'name'  => $value['name'],
                        'type'  => $value['type'],
                        'types' => $value['types'],
                    );
                    $f['id'] = $feature_model->save($f);

                    $type_features_model = new shopTypeFeaturesModel();
                    $type_features_model->updateByFeature($f['id'], $f['types']);
                    if ($value['value'] !== '') {
                        $add[$f['id']] = $feature_model->getValueId($f, $value['value'], true);
                    }
                }
            }
        }

        foreach ($features as $code => $f) {
            if (empty($data[$code]) && !empty($current[$code])) {
                if (!(isset($data[$code]) && is_string($data[$code]) && '0' === $data[$code])) {
                    $delete[$f['id']] = $current[$code];
                }
            }
        }
        foreach ($delete as $feature_id => $value_id) {
            $this->deleteByField([
                'product_id'       => $product_id,
                'sku_id'           => null,
                'feature_id'       => $feature_id,
                'feature_value_id' => $value_id
            ]);
        }

        foreach ($add as $feature_id => $value_id) {
            $this->multipleInsert([
                'product_id'       => $product_id,
                'feature_id'       => $feature_id,
                'feature_value_id' => $value_id
            ]);
        }
    }

    /**
     * @param int|int[] $feature_id
     * @return int
     */
    public function countProductsByFeature($feature_id)
    {
        return (int)$this->select('COUNT(DISTINCT product_id)')->where('feature_id IN (i:feature_id)', compact('feature_id'))->fetchField();
    }

    /**
     * @param int|int[] $feature_id
     * @return int
     */
    public function countSkusByFeature($feature_id)
    {
        return (int)$this->select('COUNT(DISTINCT sku_id)')->where('feature_id IN (?) AND sku_id IS NOT NULL', [$feature_id])->fetchField();
    }

    public function deleteSkuValuesByFeature($feature_id)
    {
        if (!$feature_id) {
            return;
        }
        $sql = "DELETE FROM {$this->table}
                WHERE feature_id IN (?)
                    AND sku_id IS NOT NULL";
        $this->exec($sql, [$feature_id]);
    }

    /**
     * @param $value
     * @param bool $ignore_empty_string
     * @return bool
     */
    public function validateGtinFeatureValue($value, $ignore_empty_string = true)
    {
        if (!is_string($value)) {
            return false;
        }

        $len = strlen($value);

        if ($ignore_empty_string && $len == 0) {
            return true;
        }

        if ($len != 8 && $len != 12 && $len != 13 && $len != 14) {
            return false;
        }

        return $this->isAllDigits($value);
    }

    private function isAllDigits($str)
    {
        $len = strlen($str);
        for ($i = 0; $i < $len; $i++) {
            $val = ord($str[$i]) - ord('0');
            if ($val < 0 || $val > 9) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param $product_id
     * @param $product_type_id
     * @return bool
     * @throws waDbException
     */
    public function checkProductFeaturesValues($product_id, $product_type_id)
    {
        $product_features_model = new shopProductFeaturesModel();
        $sql = "SELECT pf.id
                FROM shop_product_features pf
                    JOIN shop_feature f ON pf.feature_id = f.id
                    JOIN shop_type_features tf ON pf.feature_id = tf.feature_id
                    JOIN shop_product_skus ps ON pf.sku_id = ps.id
                WHERE pf.product_id = i:product_id AND f.type != s:feature_type AND
                    (tf.type_id = 0 OR tf.type_id = i:product_type_id) AND pf.feature_value_id > 0
                LIMIT 1";

        return (bool)$product_features_model->query($sql, array(
            'product_id' => $product_id,
            'product_type_id' => $product_type_id,
            'feature_type' => shopFeatureModel::TYPE_DIVIDER
        ))->fetchField();
    }
}
