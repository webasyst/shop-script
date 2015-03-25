<?php

class shopFeatureModel extends waModel
{
    const TYPE_VARCHAR = 'varchar';
    const TYPE_DOUBLE = 'double';
    const TYPE_TEXT = 'text';
    const TYPE_DIMENSION = 'dimension';
    const TYPE_RANGE = 'range';
    const TYPE_2D = '2d';
    const TYPE_3D = '3d';
    const TYPE_BOOLEAN = 'boolean';
    const TYPE_DIVIDER = 'divider';
    const TYPE_COLOR = 'color';

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
                    if ($model = self::getValuesModel($type)) {
                        $model->deleteByField('feature_id', $type_ids);
                    }
                }
                $this->deleteByField('parent_id', $ids);
            }
        }
        return $res;
    }

    public function save(&$feature, $id = null)
    {
        $default = array(
            'name'       => '',
            'types'      => array(),
            'values'     => array(),
            'type'       => self::TYPE_VARCHAR,
            'selectable' => 0,
            'multiple'   => 0,
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
            if ($id && preg_match('/^(\d)d\.(.+)$/', $feature['type'], $matches)) {
                $child_features = $this->getByField('parent_id', $id, true);
                foreach ($child_features as $child_feature) {
                    $n = preg_replace('/^.*(\.\d)$/', '$1', $child_feature['code']);
                    $data = array(
                        'code' => $feature['code'].$n,
                        'name' => $feature['name'].$n,
                    );
                    $this->updateById($child_feature['id'], $data);
                }
            }
        } else {
            if (empty($feature['type'])) {
                $feature['type'] = $default['type'];
            } elseif ($feature['type'] == self::TYPE_DIMENSION.'.'.self::TYPE_DOUBLE) {
                $feature['type'] = self::TYPE_DOUBLE;
            }
            $id = $this->insert($feature);
            if ($id && preg_match('/^(\d)d\.(.+)$/', $feature['type'], $matches)) {
                for ($i = 0; $i < $matches[1]; $i++) {
                    $child_feature = $feature;
                    $child_feature['type'] = $matches[2];
                    $child_feature['parent_id'] = $id;
                    $child_feature['code'] .= '.'.$i;
                    $child_feature['name'] .= '.'.$i;
                    $this->insert($child_feature);
                }
            }
        }
        return $id;
    }

    public function getUniqueCode($code, $id = null)
    {
        if ($code = preg_replace('/[^a-zA-Z0-9_]+/', '_', trim(waLocale::transliterate($code)))) {
            if ($code == '_') {
                $code = 'f_';
            }
            $sql = <<<SQL
            SELECT `id`, LOWER(`code`) AS `code`
            FROM `{$this->table}`
            WHERE (`id` != i:id) AND (`code` LIKE s:c)
SQL;
            $params = array(
                'c'  => $this->escape($code, 'like').'%',
                'id' => $id,
            );
            $codes = $this->query($sql, $params)->fetchAll('id', true);
            if ($codes) {
                $count = 0;
                $unique_code = $code;
                while (in_array(strtolower($unique_code), $codes)) {
                    $unique_code = $code.(++$count);
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
        if (!in_array($type_id, array(false, null), true)) {
            $sql = "
        SELECT f.*
        FROM `{$this->table}` `f`
        JOIN `shop_type_features` `t` ON (`t`.`feature_id`=`f`.`id`)
        WHERE `t`.`type_id` = i:type_id AND `f`.`parent_id` IS NULL
        ORDER BY `t`.`sort`";
        } else {
            $sql = "
        SELECT f.*
        FROM `{$this->table}` `f`
        LEFT JOIN `shop_type_features` `t` ON (`t`.`feature_id`=`f`.`id`)
        WHERE `t`.`type_id` IS NULL  AND `f`.`parent_id` IS NULL";
        }

        $features = $this->query($sql, array('type_id' => $type_id))->fetchAll($key);
        return $fill_values ? $this->getValues($features, is_int($fill_values) ? $fill_values : null) : $features;
    }

    /**
     * @param int[]|int $product_id
     * @return array
     */
    public function getByProduct($product_id)
    {
        $sql = "SELECT DISTINCT `f`.* FROM `{$this->table}` `f`
        JOIN `shop_product_features` `pf` ON `pf`.`feature_id` = `f`.`id`
        WHERE `pf`.`product_id` ".(is_array($product_id) ? 'IN (i:id)' : '= i:id');
        $features = $this->query($sql, array('id' => $product_id))->fetchAll('id');
        $sql = "SELECT DISTINCT `f`.* FROM `{$this->table}` `f`
        JOIN `shop_product_features_selectable` `pf` ON `pf`.`feature_id` = `f`.`id`
        WHERE `pf`.`product_id` ".(is_array($product_id) ? 'IN (i:id)' : '= i:id');
        $features += $this->query($sql, array('id' => $product_id))->fetchAll('id');
        return $features;
    }

    public function getMultipleSelectableFeaturesByType($type_id, $key = null, $fill_values = false)
    {
        $features = array();
        foreach ($this->getByType($type_id, $key, $fill_values) as $f) {
            if ($f['multiple'] && $f['selectable']) {
                $features[$f['code']] = $f;
            }
        }
        return $features;
    }

    public function isTypeMultipleSelectable($type_id)
    {
        $sql = "
        SELECT 1
        FROM `{$this->table}` `f`
        JOIN `shop_type_features` `t` ON (`t`.`feature_id`=`f`.`id`)
        WHERE `t`.`type_id` = i:type_id AND `f`.`parent_id` IS NULL
        LIMIT 1";
        $res = $this->query($sql, array('type_id' => $type_id))->fetchRow();
        return !!$res;
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
            $where = '`parent_id` IS NULL';
            if ($value) {
                $where .= ' AND `count`>0';
            }
            $features = $this->select('*')->where($where)->fetchAll($key);
        } elseif ($field == 'name') {
            $where = '`parent_id` IS NULL';
            if ($value) {
                $value = (array)$value;
                foreach ($value as &$name) {
                    $name = $this->escape($name, 'l');
                }
                unset($name);
                $where .= " AND ((`name` LIKE '".implode("') OR (`name` LIKE '", $value)."'))";

            }
            $features = $this->select('*')->where($where)->fetchAll($key);
        } elseif ($field == 'lname') {
            $where = '`parent_id` IS NULL';
            $params = array();
            if ($value) {
                $value = (array)$value;
                foreach ($value as &$name) {
                    $name = mb_strtolower($name, 'utf-8');
                }
                unset($name);
                $params['name'] = $value;
                $where .= " AND (LOWER(`name`) IN (s:name))";

            }
            $features = $this->select('*')->where($where, $params)->fetchAll($key);
        } else {

            $features = $this->getByField($field, $value, $key);
        }
        if ($fill_values) {
            $features = $this->getValues($features, (is_int($fill_values) ? $fill_values : (($field === true) || $fill_values === true)));
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
            if ($model = self::getValuesModel($type)) {
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
                        //trigger_error("Outdated records at {$model->getTableName()} with 'feature_id'={$feature_id}", E_USER_WARNING);
                    }
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
                    'id'         => $obsolete_values,
                );
                $model->deleteByField($field);

            }
        }
        $data = array();
        $sort = 0;
        foreach ($values as $id => $value) {
            $value = $model->addValue($feature['id'], $value, $force ? null : $id, $feature['type'], ++$sort);
            if ($force) {
                if (!empty($value['error'])) {
                    if (!empty($value['error']['original_id'])) {
                        $value['id'] = $value['error']['original_id'];
                        $value['value'] = $value['error']['original_value'];
                        unset($value['error']);
                        $data[] = $value;
                    }
                }
            } else {
                $data[] = $value;
            }
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
                self::TYPE_COLOR,
            );
            foreach ($types as $type) {
                try {
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
                } catch (waDbException $ex) {
                    throw $ex;
                } catch (waException $ex) {
                    //ignore waException
                }
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
            $model = 'shopFeatureValues'.ucfirst($type).'Model';
            if (class_exists($model)) {
                self::$instances[$type] = new $model();
            } elseif (in_array($type, array(self::TYPE_2D, self::TYPE_3D))) {
                self::$instances[$type] = false;
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
                        $names[$key] = $type['name'].':'.$subtype['name'];
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
                'name'      => _w('Texts'),
                'type'      => self::TYPE_VARCHAR,
                'available' => 1,
            );
            $single_types[] = array(
                'name'      => _w('Custom number'),
                'type'      => self::TYPE_DOUBLE,
                'available' => 1,
            );
            $single_types[] = array(
                'name'      => _w('Color'),
                'type'      => self::TYPE_COLOR,
                'available' => 1,
            );


            $numerical_types = array();

            $numerical_types[] = array(
                'name'       => _w('Custom number'),
                'group'      => _w('Numerical'),
                'type'       => '%s.'.self::TYPE_DOUBLE,
                'alias'      => array(
                    self::TYPE_DIMENSION.'.'.self::TYPE_DOUBLE => self::TYPE_DOUBLE,
                ),
                'multiple'   => false,
                'selectable' => false,
                'available'  => 2,
            );
            foreach ($units_index as $unit) {
                $type = array(
                    'type'      => '%s'.'.'.self::TYPE_DIMENSION.'.'.$unit,
                    'alias'     => array(
                        self::TYPE_RANGE.'.'.self::TYPE_DIMENSION.'.'.$unit     => self::TYPE_RANGE.'.'.$unit,
                        self::TYPE_DIMENSION.'.'.self::TYPE_DIMENSION.'.'.$unit => self::TYPE_DIMENSION.'.'.$unit,
                    ),
                    'available' => 2,
                );
                if ($dimension = $dimensions->getDimension($unit)) {
                    if (empty($type['name'])) {
                        $type['name'] = $dimension['name'];
                    }
                    $count = count($dimension['units']);
                    $units = array_slice(array_values($dimension['units']), 0, min(4, $count));

                    if ($count > 5) {
                        $units[] = array('name' => '...');
                    }
                    foreach ($units as &$u) {
                        $u = $u['name'];
                    }
                    unset($u);
                    $type['short_name'] = $type['name'];

                    $type['name'] .= " (".implode(', ', $units).")";
                } else {
                    $type['available'] = 0;
                }
                $single_types[] = $type;

                $numerical_types[$unit] = $type;
            }

            $types = array();
            $types[] = array(
                'name'       => _w('Custom text (input)'),
                'type'       => self::TYPE_VARCHAR,
                'multiple'   => false,
                'selectable' => false,
                'available'  => 2, /*0 under development; 1 only at features settings; 2 full access*/
            );
            $types[] = array(
                'name'       => _w('HTML (textarea)'),
                'type'       => self::TYPE_TEXT,
                'multiple'   => false,
                'selectable' => false,
                'available'  => 2,
            );
            $types[] = array(
                'name'       => _w('Yes/No toggle (boolean)'),
                'type'       => self::TYPE_BOOLEAN,
                'multiple'   => false,
                'selectable' => false,
                'available'  => 2,
            );
            $types[] = array(
                'name'       => _w('Select from a list of values (select)'),
                'type'       => '*',
                'multiple'   => false,
                'selectable' => true,
                'available'  => 1,
                'subtype'    => self::extendSubtypes($single_types, self::TYPE_DIMENSION),
            );

            /* Shortcuts */

            $types[] = array(
                'name'       => _w('Color'),
                'type'       => self::TYPE_COLOR,
                'multiple'   => true,
                'selectable' => true,
                'available'  => 2,
            );

            foreach (array('weight', 'length') as $unit) {
                if (isset($numerical_types[$unit])) {
                    $type = $numerical_types[$unit];
                    $type += array(
                        'multiple'   => false,
                        'selectable' => false,
                        'available'  => 2,
                    );
                    if (strpos($type['type'], '%s') !== false) {
                        $type['type'] = sprintf($type['type'], self::TYPE_DIMENSION);
                        if (!empty($type['alias']) && isset($type['alias'][$type['type']])) {
                            $type['type'] = $type['alias'][$type['type']];
                        }
                    }
                    if (!empty($type['short_name'])) {
                        $type['name'] = $type['short_name'];
                    }
                    $types[] = $type;
                }
            }

            /* Numerical */

            $types[] = array(
                'name'       => _w('Value'),
                'group'      => _w('Numerical'),
                'type'       => self::TYPE_DIMENSION.'.*',
                'multiple'   => false,
                'selectable' => false,
                'available'  => 2,
                'subtype'    => self::extendSubtypes($numerical_types, self::TYPE_DIMENSION),
            );
            /**
             * $types[] = array(
             * 'name'       => _w('Date'),
             * 'group'      => _w('Numerical'),
             * 'type'       => 'date',
             * 'multiple'   => false,
             * 'selectable' => false,
             * 'available'  => 0,
             * );
             */

            $types[] = array(
                'name'       => _w('Range'),
                'group'      => _w('Numerical'),
                'type'       => self::TYPE_RANGE.'.*',
                'multiple'   => false,
                'selectable' => false,
                'available'  => 2,
                'subtype'    => self::extendSubtypes($numerical_types, self::TYPE_RANGE),
            );

            $types[] = array(
                'name'       => _w('Value').' × '._w('Value'),
                'group'      => _w('Numerical'),
                'type'       => self::TYPE_2D.'.*',
                'multiple'   => false,
                'selectable' => false,
                'available'  => 1,
                'subtype'    => self::extendSubtypes($numerical_types, self::TYPE_2D),
            );

            $types[] = array(
                'name'       => _w('Value').' × '._w('Value').' × '._w('Value'),
                'group'      => _w('Numerical'),
                'type'       => self::TYPE_3D.'.*',
                'multiple'   => false,
                'selectable' => false,
                'available'  => 1,
                'subtype'    => self::extendSubtypes($numerical_types, self::TYPE_3D),
            );


            /* Selectable */
            /** TODO
             * $types[] = array(
             * 'name'       => 'Radiobuttons',
             * 'group'      => _w('Selectable'),
             * 'type'       => '*',
             * 'multiple'   => false,
             * 'selectable' => true,
             * 'available'  => 0,
             * 'subtype'    => & $single_types,
             * );
             */
            $types[] = array(
                'name'       => _w('Multiple values (checkboxes)'),
                'group'      => _w('Selectable features'),
                'type'       => '*',
                'multiple'   => true,
                'selectable' => true,
                'available'  => 1,
                'subtype'    => self::extendSubtypes($single_types, self::TYPE_DIMENSION),
            );
            
            /* divider */
            $types[] = array(
                'name'       => _w('Divider'),
                'group'      => _w('Other'),
                'type'       => self::TYPE_DIVIDER,
                'multiple'   => false,
                'selectable' => false,
                'available'  => 2,
            );
        }
        return $types;
    }

    private static function extendSubtypes($subtypes, $prefix)
    {

        foreach ($subtypes as &$subtype) {
            if (strpos($subtype['type'], '%s') !== false) {
                $subtype['type'] = sprintf($subtype['type'], $prefix);
                if (!empty($subtype['alias']) && isset($subtype['alias'][$subtype['type']])) {
                    $subtype['type'] = $subtype['alias'][$subtype['type']];
                }
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
