<?php
class shopProductFeaturesModel extends waModel implements shopProductStorageInterface
{
    protected $table = 'shop_product_features';

    //protected $id = array('product_id', 'sku_id', 'feature_id', 'feature_value_id', );

    /**
     * @param array $product_ids
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

    public function getSkuFeatures($product_id)
    {
        if (!$product_id) {
            return array();
        }
        $sql = "SELECT * FROM ".$this->table." WHERE product_id = i:id AND sku_id IS NOT NULL";
        $result = array();
        $rows = $this->query($sql, array('id' => $product_id))->fetchAll();
        foreach ($rows as $row) {
            $result[$row['sku_id']][$row['feature_id']] = $row['feature_value_id'];
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

    public function deleteByFeature($feature_id, $feature_value_id = null)
    {
        $sql = "DELETE FROM {$this->table} WHERE ".$this->getWhereByField('feature_id', $feature_id);
        if ($feature_id !== null) {
            $sql .= ' AND '.$this->getWhereByField('feature_value_id', $feature_value_id);
        }
        return $this->exec($sql);
    }

    public function getData(shopProduct $product)
    {
        return $this->getValues($product->getId(), null, $product->type_id);
    }

    public function getValues($product_id, $sku_id = null, $type_id = null)
    {
        $sql = "SELECT ".($type_id ? 'tf.sort, ' : '')."f.code, f.type, f.multiple, pf.*
                FROM ".$this->table." pf";
        $sql .= " JOIN shop_feature f ON (pf.feature_id = f.id)";
        if ($type_id) {
            $sql .= " LEFT JOIN shop_type_features tf ON ((tf.feature_id = IFNULL(f.parent_id,f.id)) AND (tf.type_id=i:type_id))";
        }
        $sql .= " WHERE pf.product_id = i:id AND ";
        if ($sku_id) {
            $sql .= '(pf.sku_id = i:sku_id OR pf.sku_id IS NULL) ORDER BY pf.sku_id';
        } else {
            $sql .= 'pf.sku_id IS NULL';
        }

        if ($type_id) {
            $sql .= " ORDER BY tf.sort";
        }
        $features = $storages = array();
        $params = array(
            'id'      => $product_id,
            'sku_id'  => $sku_id,
            'type_id' => $type_id,
        );
        $data = $this->query($sql, $params);
        $result = array();
        foreach ($data as $row) {
            if ($sku_id && $row['code'] == 'weight' && !$row['sku_id']) {
                continue;
            }
            $features[$row['feature_id']] = array(
                'code'     => $row['code'],
                'multiple' => $row['multiple'],
            );
            if (preg_match('/^(.+)\.[0-2]$/', $row['code'], $matches)) {
                $result[$matches[1]] = null;
            } else {
                $result[$row['code']] = null;
            }
            $type = preg_replace('/\..*$/', '', $row['type']);
            if ($type == shopFeatureModel::TYPE_BOOLEAN) {
                /**
                 * @var shopFeatureValuesBooleanModel $model
                 */
                $model = shopFeatureModel::getValuesModel($type);
                $values = $model->getValues('id', $row['feature_value_id']);
                $result[$row['code']] = reset($values);
            } elseif ($type == shopFeatureModel::TYPE_DIVIDER) {
                /**
                 * @var shopFeatureValuesDividerModel $model
                 */
                $model = shopFeatureModel::getValuesModel($type);
                $values = $model->getValues('id', $row['feature_value_id']);
                $result[$row['code']] = reset($values);
            } else {
                if ($sku_id) {
                    $storages[$type][$row['feature_id']] = $row['feature_value_id'];
                } else {
                    $storages[$type][] = $row['feature_value_id'];
                }
            }

        }

        foreach ($storages as $type => $value_ids) {
            $model = shopFeatureModel::getValuesModel($type);
            $feature_values = $model->getValues('id', $value_ids);
            foreach ($feature_values as $feature_id => $values) {
                if (isset($features[$feature_id])) {
                    $f = $features[$feature_id];
                    $result[$f['code']] = ($sku_id || empty($f['multiple'])) ? reset($values) : $values;
                }
            }
        }


        /**
         * composite fields workaround
         */
        $composite = array_filter(array_keys($result), create_function('$a', 'return preg_match("/\.0$/",$a);'));
        foreach ($composite as $code) {
            $code = preg_replace('/\.0$/', '', $code);
            $result[$code] = new shopCompositeValue($code, $result);
        }

        return $result;
    }

    /**
     * @see shopProductStorageInterface::setData()
     * @param shopProduct $product current product object
     * @param array [string] mixed $data new product feature values
     */
    public function setData(shopProduct $product, $data)
    {
        $product_id = $product->getId();

        $feature_model = new shopFeatureModel();
        $codes = array_keys($data);
        $features = $feature_model->getByCode($codes);


        /**
         * composite fields workaround
         */
        $composite_codes = array();
        foreach ($data as $code => $value) {
            if (!preg_match('/\.[0-3]$/', $code) && isset($features[$code]) && preg_match('/^([23])d\\./', $features[$code]['type'], $matches)) {
                $n = $matches[1];
                $pattern = '/^'.implode('\\s*[×xX\\*]?\\s*', array_fill(0, $n, '([^\\s]+)')).'(\\s+.+)?$/u';
                if (preg_match($pattern, trim($value), $matches)) {
                    for ($i = 0; $i < $n; $i++) {
                        $c_code = $code.'.'.$i;
                        $data[$c_code] = $matches[$i + 1].$matches[$n + 1];
                        $composite_codes[] = $c_code;
                    }
                    unset($features[$code]);
                } else {
                    /**
                     * invalid complex feature format
                     */
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
                ;
            }
        }

        $add = $delete = array();
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
                    //delete it
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
            } elseif (!empty($value) && is_array($value)) {
                //it's a new feature
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
                $delete[$f['id']] = $current[$code];
            }
        }
        foreach ($delete as $feature_id => $value_id) {
            $this->deleteByField(array('product_id' => $product_id, 'sku_id' => null, 'feature_id' => $feature_id, 'feature_value_id' => $value_id));
        }

        foreach ($add as $feature_id => $value_id) {
            $this->multipleInsert(array('product_id' => $product_id, 'feature_id' => $feature_id, 'feature_value_id' => $value_id));
        }
    }
}
