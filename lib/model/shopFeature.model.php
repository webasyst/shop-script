<?php
class shopFeatureModel extends waModel
{
    const TYPE_VARCHAR = 'varchar';
    const TYPE_DOUBLE = 'double';
    const TYPE_TEXT = 'text';
    const TYPE_DIMENSION = 'dimension';
    const TYPE_RANGE = 'range';
    const TYPE_COMPLEX = 'complex';
    const TYPE_BOOLEAN = 'boolean';
    const TYPE_DIVIDER = 'divider';

    private static $instances = array();

    protected $table = 'shop_feature';

    public function deleteByField($field, $value = null)
    {
        if (is_array($field)) {
            $items = $this->getByField($field, $this->id);
        } else {
            $items = $this->getByField($field, $value, $this->id);
        }
        $res = false;
        if ($ids = array_keys($items)) {
            $res = parent::deleteByField($this->id, $ids);
            if ($res) {
                $type_upselling_model = new shopTypeUpsellingModel();
                $type_upselling_model->deleteByField('feature_id', $ids);

                $params_model = new shopProductFeaturesModel();
                $params_model->deleteByField('feature_id', $ids);

                $types = $this->groupByValueType($items);
                foreach ($types as $type => $type_ids) {
                    $model = self::getValuesModel($type);
                    $model->deleteByField('feature_id', $type_ids);
                }

            }
        }
        return $res;
    }

    public function save(&$feature, $id = null)
    {
        $default = array(
            'name' => '',
            'types' => array(),
            'values' => array(),
            'type' => self::TYPE_VARCHAR,
            'selectable' => 0,
            'multiple' => 0,
        );
        $feature = array_merge($default, $feature);
        $feature['selectable'] = intval($feature['selectable']);
        $feature['multiple'] = intval($feature['multiple']);

        if (empty($feature['code'])) {
            $feature['code'] = strtolower(waLocale::transliterate($feature['name']));
        }
        $feature['code'] = $this->getUniqueCode($feature['code'], $id);
        if ($id > 0) {
            if (isset($feature['type'])) {
                unset($feature['type']);
            }
            $this->updateById($id, $feature);
            $feature['type'] = $this->select('type')->where($this->getWhereByField('id', $id))->fetchField('type');
        } else {
            if (empty($feature['type'])) {
                $feature['type'] = $default['type'];
            } elseif ($feature['type'] == self::TYPE_DIMENSION . '.' . self::TYPE_DOUBLE) {
                $feature['type'] = self::TYPE_DOUBLE;
            }
            $id = $this->insert($feature);
        }
        return $id;
    }

    public function getUniqueCode($code, $id = null)
    {
        if ($code = preg_replace('/[^a-zA-Z0-9_]+/', '_', trim(waLocale::transliterate($code)))) {
            $sql = <<<SQL
            SELECT `id`, LOWER(`code`) AS `code`
            FROM `{$this->table}`
            WHERE (`id` != i:id) AND (`code` LIKE s:c)
SQL;
            $params = array(
                'c' => $this->escape($code, 'like') . '%',
                'id' => $id,
            );
            $codes = $this->query($sql, $params)->fetchAll('id', true);
            if ($codes) {
                $count = 0;
                $unique_code = $code;
                while (in_array(strtolower($unique_code), $codes)) {
                    $unique_code = $code . (++$count);
                }
            } else {
                $unique_code = $code;
            }
        } else {
            $unique_code = time();
        }
        return $unique_code;
    }

    public function getByCode($code)
    {
        if (!$code) {
            return array();
        }
        if (is_array($code)) {
            return $this->getByField('code', $code, 'code');
        }
        return $this->getByField('code', $code);
    }

    public function getByType($type_id, $key = null, $fill_values = false)
    {
        if ($type_id) {
            $sql = "
        SELECT f.*
        FROM `{$this->table}` `f`
        JOIN `shop_type_features` `t` ON (`t`.`feature_id`=`f`.`id`)
        WHERE `t`.`type_id` = i:type_id
        ORDER BY `t`.`sort`";
        } else {
            $sql = "
        SELECT f.*
        FROM `{$this->table}` `f`
        LEFT JOIN `shop_type_features` `t` ON (`t`.`feature_id`=`f`.`id`)
        WHERE `t`.`type_id` IS NULL";
        }

        $features = $this->query($sql, array('type_id' => $type_id))->fetchAll($key);
        return $fill_values ? $this->getValues($features, is_int($fill_values) ? $fill_values : null) : $features;
    }

    public function getMultipleSelectableFeaturesByType($type_id, $key = null, $fill_values = false)
    {
        $features = array();
        foreach ($this->getByType($type_id) as $f) {
            if ($f['multiple'] && $f['selectable']) {
                $features[$f['code']] = $f;
            }
        }
        return $features;
    }

    /**
     *
     * Get features with their values
     * @param $field
     * @param mixed|void $value
     * @param string $key
     * @param bool $fill_values
     * @return array
     */
    public function getFeatures($field, $value = null, $key = 'id', $fill_values = false)
    {
        if ($field === true) {
            $features = $this->getAll($key);
        } else {
            $features = $this->getByField($field, $value, $key);
        }
        if ($fill_values) {
            $features = $this->getValues($features, ($field === true));
        }
        return $features;

    }

    public function getValues($features, $all = null)
    {
        foreach ($features as & $feature) {
            $feature['values'] = array();
        }
        unset($feature);

        $types = $this->groupByValueType($features);
        foreach ($types as $type => $ids) {
            $model = self::getValuesModel($type);
            $field = ($all === true) ? true : 'feature_id';
            $value = ($all === true) ? null : array_keys($ids);
            $features_values = $model->getValues($field, $value, ($all === true) ? null : $all);
            foreach ($features_values as $feature_id => $values) {
                if (isset($ids[$feature_id])) {
                    // avoid values with wrong types
                    $id = $ids[$feature_id];

                    if (isset($features[$id])) {
                        //avoid values without related features
                        $features[$id]['values'] = $values;
                    } else {
                        //TODO
                        trigger_error("Outdated records at {$this->table}", E_USER_WARNING);
                    }
                } else {
                    //TODO
                    trigger_error("Outdated records at {$model->getTableName()} with 'feature_id'={$feature_id}", E_USER_WARNING);
                }
            }
        }
        return $features;
    }

    private function groupByValueType($items)
    {
        $types = array();
        foreach ($items as $id => $item) {
            $type = $type = preg_replace('/\..*$/', '', $item['type']);
            if (!isset($types[$type])) {
                $types[$type] = array();
            }
            $feature_id = empty($item['id']) ? $id : $item['id'];
            $types[$type][$feature_id] = $id;
        }
        return $types;
    }

    /**
     *
     *
     * @param array $feature
     * @return array
     */
    public static function getFeatureValues($feature)
    {
        //TODO cast values
        return self::getValuesModel($feature['type'])->getValues('feature_id', $feature['id']);
    }

    public function setValues($feature, $values, $delete_obsolete = true, $force = false)
    {
        $model = self::getValuesModel($feature['type']);
        if ($delete_obsolete) {
            $current_values = $model->getByField('feature_id', $feature['id'], 'id');
            $obsolete_values = array_diff(array_keys($current_values), array_keys($values));
            if ($obsolete_values) {
                $product_features_model = new shopProductFeaturesModel();
                $product_features_model->deleteByFeature($feature['id'], $obsolete_values);
                $field = array(
                    'feature_id' => $feature['id'],
                    'id' => $obsolete_values,
                );
                $model->deleteByField($field);

            }
        }
        $data = array();
        $sort = 0;
        foreach ($values as $id => $value) {
            $data[] = $model->addValue($feature['id'], $value, $force ? null : $id, $feature['type'], ++$sort);
        }
        $this->recount($feature);
        return $data;

    }

    public function recount($feature = null)
    {
        if ($feature) {
            $model = self::getValuesModel($feature['type']);
            $this->updateById($feature['id'], array('count' => $model->countByField('feature_id', $feature['id'])));
        } else {
            $types = array(
                self::TYPE_VARCHAR,
                self::TYPE_DOUBLE,
                self::TYPE_TEXT,
                self::TYPE_DIMENSION,
            );
            foreach ($types as $type) {
                $model = self::getValuesModel($type);
                $sql = <<<SQL
            UPDATE `{$this->table}` `f`
SET `count` = (
SELECT COUNT(*) FROM `{$model->getTableName()}` `v`
WHERE (`v`.`feature_id` = `f`.`id`)
)
WHERE `f`.`type`=s:0
SQL;
                $this->query($sql, $type);
            }
        }
    }

    /**
     *
     * @param string $type
     * @throws waException
     * @return shopFeatureValuesModel
     */
    public static function getValuesModel($type)
    {
        $type = preg_replace('/\..*$/', '', $type);
        if (!$type) {
            throw new waException("unknown feature value type");
        }
        if (!isset(self::$instances[$type])) {
            $model = 'shopFeatureValues' . ucfirst($type) . 'Model';
            if (class_exists($model)) {
                self::$instances[$type] = new $model();
            } else {
                throw new waException("Not found model for feature value type {$type}");
            }
        }
        return self::$instances[$type];
    }

    /**
     * @param array $feature
     * @param $value
     * @param bool $update
     * @return int|int[]
     */
    public function getValueId($feature, $value, $update = false)
    {
        $id = self::getValuesModel($feature['type'])->getValueId($feature['id'], $value, $feature['type'], $update);
        if ($update) {
            $this->recount($feature);
        }
        return $id;
    }

    public static function appendTypeNames(&$features)
    {
        foreach ($features as & $feature) {
            $feature['type_name'] = self::getTypeName($feature);
        }
        unset($feature);
        return $features;
    }

    public static function getTypeName($feature)
    {
        static $names = array();
        if (empty($names)) {
            $types = self::getTypes();
            foreach ($types as $type) {
                if (empty($type['subtype'])) {
                    $key = sprintf('m:%d;s:%d;t:%s', !empty($type['multiple']), !empty($type['selectable']), $type['type']);
                    $names[$key] = $type['name'];
                } else {
                    foreach ($type['subtype'] as $subtype) {
                        $subtype += $type;
                        $key = sprintf('m:%d;s:%d;t:%s', !empty($subtype['multiple']), !empty($subtype['selectable']), $subtype['type']);
                        $names[$key] = $type['name'] . ':' . $subtype['name'];
                        if (isset($subtype['alias']) && isset($subtype['alias'][$subtype['type']])) {
                            $subtype['type'] = $subtype['alias'][$subtype['type']];
                            $key = sprintf('m:%d;s:%d;t:%s', !empty($subtype['multiple']), !empty($subtype['selectable']), $subtype['type']);
                            $names[$key] = $subtype['name'];
                        }
                    }
                }
            }
        }

        $key = sprintf('m:%d;s:%d;t:%s', !empty($feature['multiple']), !empty($feature['selectable']), $feature['type']);
        if (empty($names[$key])) {
            $names[$key] = $key;
        }
        return $names[$key];
    }

    public static function getTypes()
    {
        static $types;
        if (!$types) {
            $dimensions = shopDimension::getInstance();
            $units_list = $dimensions->getList();
            $units_index = array_keys($units_list);

            #single types
            $single_types = array();
            $single_types[] = array(
                'name' => _w('Texts'),
                'type' => self::TYPE_VARCHAR,
                'available' => 1,
            );
            $single_types[] = array(
                'name' => _w('Custom number'),
                'type' => self::TYPE_DOUBLE,
                'available' => 1,
            );
            $single_types[] = array(
                'name' => _w('Color'),
                'type' => 'color',
                'available' => 0,
            );


            $numerical_types = array();

            $numerical_types[] = array(
                'name' => _w('Custom number'),
                'group' => _w('Numerical'),
                'type' => '%s.' . self::TYPE_DOUBLE,
                'alias' => array(
                    self::TYPE_DIMENSION . '.' . self::TYPE_DOUBLE => self::TYPE_DOUBLE,
                ),
                'multiple' => false,
                'selectable' => false,
                'available' => 2,
            );
            foreach ($units_index as $unit) {
                $type = array(
                    'type' => '%s' . '.' . $unit,
                    'available' => 2,
                );
                if ($dimension = $dimensions->getDimension($unit)) {
                    if (empty($type['name'])) {
                        $type['name'] = $dimension['name'];
                    }
                    $units = array_unique(array_merge(array($dimension['base_unit']), array_keys($dimension['units'])));
                    if (count($units) > 5) {
                        $units[4] = '...';
                        $units = array_slice($units, 0, 4);
                    }
                    $units = array_map('_w', $units);
                    $type['name'] .= " (" . implode(', ', $units) . ")";
                } else {
                    $type['available'] = 0;
                }
                $single_types[] = $type;

                $numerical_types[$unit] = $type;
            }

            $types = array();
            $types[] = array(
                'name' => _w('Custom text (input)'),
                'type' => self::TYPE_VARCHAR,
                'multiple' => false,
                'selectable' => false,
                'available' => 2, /*0 under development; 1 only at features settings; 2 full access*/
            );
            $types[] = array(
                'name' => _w('HTML (textarea)'),
                'type' => self::TYPE_TEXT,
                'multiple' => false,
                'selectable' => false,
                'available' => 2,
            );
            $types[] = array(
                'name' => _w('Yes/No toggle (boolean)'),
                'type' => self::TYPE_BOOLEAN,
                'multiple' => false,
                'selectable' => false,
                'available' => 2,
            );
            /* TODO
             $types[] = array(
             'name'       => 'Color',
             'type'       => 'color',
             'multiple'   => false,
             'selectable' => false,
             'available'  => 0,
             );
             */
            /* Numerical */

            /** TODO
            $types[] = array(
            'name'       => 'Date',
            'group'      => 'Numerical',
            'type'       => 'date',
            'multiple'   => false,
            'selectable' => false,
            'available'  => 0,
            );
             */

            $types[] = array(
                'name' => _w('Value'),
                'group' => _w('Numerical'),
                'type' => self::TYPE_DIMENSION . '.*',
                'multiple' => false,
                'selectable' => false,
                'available' => 2,
                'subtype' => self::extendSubtypes($numerical_types, self::TYPE_DIMENSION),
            );
            $types[] = array(
                'name' => _w('Range'),
                'group' => _w('Numerical'),
                'type' => self::TYPE_RANGE . '.*',
                'multiple' => false,
                'selectable' => false,
                'available' => 2,
                'subtype' => self::extendSubtypes($numerical_types, self::TYPE_RANGE),
            );


            /* Selectable */
            $types[] = array(
                'name' => _w('Select'),
                'group' => _w('Selectable'),
                'type' => '*',
                'multiple' => false,
                'selectable' => true,
                'available' => 1,
                'subtype' => self::extendSubtypes($single_types, self::TYPE_DIMENSION),
            );
            /** TODO
            $types[] = array(
            'name'       => 'Radiobuttons',
            'group'      => _w('Selectable'),
            'type'       => '*',
            'multiple'   => false,
            'selectable' => true,
            'available'  => 0,
            'subtype'    => & $single_types,
            );
             */
            $types[] = array(
                'name' => _w('Checkboxes (multiple)'),
                'group' => _w('Selectable'),
                'type' => '*',
                'multiple' => true,
                'selectable' => true,
                'available' => 1,
                'subtype' => self::extendSubtypes($single_types, self::TYPE_DIMENSION),
            );
            /* divider */
            $types[] = array(
                'name' => _w('Divider'),
                'group' => _w('Other'),
                'type' => self::TYPE_DIVIDER,
                'multiple' => true,
                'selectable' => true,
                'available' => 0,
            );
        }
        return $types;
    }

    private static function extendSubtypes($subtypes, $prefix)
    {

        foreach ($subtypes as &$subtype) {
            if (strpos($subtype['type'], '%s') !== false) {
                $subtype['type'] = sprintf($subtype['type'], $prefix);
            }
            unset($subtype);
        }
        return $subtypes;

    }

    public function delete($id)
    {
        // related model
        foreach (array(
                     new shopProductFeaturesSelectableModel(),
                     new shopTypeFeaturesModel(),
                     new shopFeatureValuesDimensionModel(),
                     new shopFeatureValuesDoubleModel(),
                     new shopFeatureValuesTextModel(),
                     new shopFeatureValuesVarcharModel(),
                     new shopProductFeaturesModel(),
                     new shopTypeUpsellingModel()
                 ) as $m) {
            /**
             * @var waModel $m
             */
            $m->deleteByField('feature_id', $id);
        }
        return $this->deleteById($id);
    }
}
