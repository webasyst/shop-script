<?php
class shopFeatureModel extends waModel
{
    const TYPE_VARCHAR = 'varchar';
    const TYPE_DOUBLE = 'double';
    const TYPE_TEXT = 'text';
    const TYPE_DIMENSION = 'dimension';
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
        $move = false;
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
            }
            $id = $this->insert($feature);
        }
        return $id;
    }

    public function getUniqueCode($code, $id = null)
    {
        if ($code = preg_replace('/[^a-zA-Z0-9_]+/', '_', trim(waLocale::transliterate($code)))) {
            $sql = "SELECT `id`, LOWER(`code`) as `code` FROM `{$this->table}` WHERE (`id` != i:id) AND (`code` LIKE s:code)";
            $codes = $this->query($sql, array('code' => $this->escape($code, 'like').'%', 'id' => $id))->fetchAll('id', true);
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
        $sql = "
        SELECT f.*
        FROM `{$this->table}` `f`
        JOIN `shop_type_features` `t` ON (`t`.`feature_id`=`f`.`id`)
        WHERE `t`.`type_id` = i:type_id
        ORDER BY `t`.`sort`";

        $features = $this->query($sql, array('type_id' => $type_id))->fetchAll($key);
        return $fill_values ? $this->getValues($features) : $features;
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
     * @param string|array[string]mixed $field
     * @param mixed|void $value
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
            $features_values = $model->getValues($field, $value);
            foreach ($features_values as $feature_id => $values) {
                if (isset($ids[$feature_id])) {
                    // avoid values with wrong types
                    $id = $ids[$feature_id];

                    if (isset($features[$id])) {
                        //avoid values without related features
                        $features[$id]['values'] = $values;
                    } else {
                        //TODO
                        trigger_error("Outdate records at {$this->table}", E_USER_WARNING);
                    }
                } else {
                    //TODO
                    trigger_error("Outdate records at {$model->getTableName()} with 'feature_id'={$feature_id}", E_USER_WARNING);
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
     * @param int|array[]int $feature_ids
     */
    public function getFeatureValues($feature)
    {
        $model = self::getValuesModel($feature['type']);
        //TODO cast values
        return $model->getValues('feature_id', $feature['id']);
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
            $data[] = $model->addValue($feature['id'], $value, $force ? null : $id, $feature['type'], ++$sort);
        }

        return $data;

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
            } else {
                throw new waException("Not found model for feature value type {$type}");
            }
        }
        return self::$instances[$type];
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
        $key = (ifempty($feature['multiple']) ? 'm' : '_').'.'.(ifempty($feature['selectable']) ? 's' : '_').'.'.$feature['type'];
        $fields = array(
            'multiple'   => 2,
            'selectable' => 2,
            'type'       => 1,
        );
        if (!isset($names[$key])) {
            $types = self::getTypes();
            $max_match = 0;
            $names[$key] = '';
            foreach ($types as $type) {

                $match = 0;
                foreach ($fields as $field => $weight) {
                    if ($type[$field] == $feature[$field]) {
                        $match += $weight;
                    }
                }
                if (preg_match('/\*$/', $type['type'])) {
                    $match += $fields['type'];
                }
                $match += $type['available'];
                if ($match >= $max_match) {
                    $names[$key] = $type['name'];
                    if (preg_match('/\*$/', $type['type'])) {
                        foreach ($type['subtype'] as $subtype) {
                            if ($subtype['type'] == $feature['type']) {
                                if ($feature['multiple'] || $feature['selectable']) {
                                    $names[$key] .= ' : '.$subtype['name'];
                                } else {
                                    $names[$key] = $subtype['name'];
                                }
                                $match += $fields['type'];
                                break;
                            }
                        }
                    }
                    $max_match = $match;
                }
            }
            if (empty($names[$key])) {
                $names[$key] = $key;
            }
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

            $other_units = array();
            $single_types = array();
            $dimension_types = array();
            $single_types[] = array(
                'name'      => _w('Texts'),
                'type'      => 'varchar',
                'available' => 1,
            );
            $single_types[] = array(
                'name'      => _w('Numbers'),
                'type'      => 'double',
                'available' => 1,
            );
            $single_types[] = array(
                'name'      => _w('Color'),
                'type'      => 'color',
                'available' => 0,
            );
            foreach ($units_index as $unit) {
                $type = array(
                    'type'      => 'dimension.'.$unit,
                    'available' => 1,
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
                    $type['name'] .= " (".implode(', ', $units).")";
                } else {
                    $type['available'] = 0;
                }
                $dimension_types[$unit] = $type;

                $single_types[] = $type;

            }

            $types = array();
            $types[] = array(
                'name'       => _w('Custom text (input)'),
                'type'       => 'varchar',
                'multiple'   => false,
                'selectable' => false,
                'available'  => 2, /*0 under development; 1 only at features settings; 2 full access*/
            );
            $types[] = array(
                'name'       => _w('HTML (textarea)'),
                'type'       => 'text',
                'multiple'   => false,
                'selectable' => false,
                'available'  => 2,
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
            $types[] = array(
                'name'       => _w('Custom number'),
                'group'      => _w('Numerical'),
                'type'       => 'double',
                'multiple'   => false,
                'selectable' => false,
                'available'  => 2,
            );
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
            $z = 3;
            $count = count($units_index);
            if ($count > ($z + 1)) {
                $count = $z - 1;
            }
            for ($i = 0; $i < $count; $i++) {
                $types[] = array(
                    'group'      => _w('Numerical'),
                    'type'       => 'dimension.%'.$i,
                    'multiple'   => false,
                    'selectable' => false,
                    'available'  => 2,
                );
            }

            if (count($units_index) > $count) {
                $types[] = array(
                    'name'       => _w('Other'),
                    'group'      => _w('Numerical'),
                    'type'       => 'dimension.*',
                    'multiple'   => false,
                    'selectable' => false,
                    'available'  => 1,
                    'alias'      => 'dimension',
                    'units'      => & $other_units,
                    'subtype'    => & $dimension_types,
                );
            }

            /* Selectable */
            $types[] = array(
                'name'       => _w('Select'),
                'group'      => _w('Selectable'),
                'type'       => '*',
                'multiple'   => false,
                'selectable' => true,
                'available'  => 1,
                'subtype'    => & $single_types,
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
                'name'       => _w('Checkboxes (multiple)'),
                'group'      => _w('Selectable'),
                'type'       => '*',
                'multiple'   => true,
                'selectable' => true,
                'available'  => 1,
                'subtype'    => & $single_types,
            );
            /** TODO
             $types[] = array(
             'name'       => 'On/Off checkbox',
             'group'      => 'Selectable',
             'type'       => '???',
             'multiple'   => true,
             'selectable' => true,
             'available'  => 0,
             );
             */
            /* divider */
            $types[] = array(
                'name'       => _w('Divider'),
                'group'      => _w('Other'),
                'type'       => 'divider',
                'multiple'   => true,
                'selectable' => true,
                'available'  => 0,
            );

            $meeted = 0;
            $other = null;
            foreach ($types as $id => & $type) {
                $select_type = explode('.', $type['type']);
                if ($select_type[0] == 'dimension') {
                    $dimension = null;
                    $unit = $select_type[1];
                    if ($unit == '*') {
                        $other = $id;

                    } else {
                        if (preg_match('/^%(\d+)$/', $unit, $matches)) {
                            if (isset($units_index[$matches[1]])) {
                                $unit = $units_index[$matches[1]];
                                $type['type'] = 'dimension'.'.'.$unit;
                            } else {
                                $unit = false;
                            }
                        }
                        if ($unit && ($dimension = $dimensions->getDimension($unit))) {
                            ++$meeted;
                            unset($dimension_types[$unit]);
                            unset($units_list[$unit]);
                            if (empty($type['name'])) {
                                $type['name'] = $dimension['name'];
                            }
                        } else {
                            $type['available'] = 0;
                        }

                    }
                    if ($dimension) {
                        $units = array_unique(array_merge(array($dimension['base_unit']), array_keys($dimension['units'])));
                        if (count($units) > 5) {
                            $units[4] = '...';
                            $units = array_slice($units, 0, 4);
                        }
                        $units = array_map('_w', $units);
                        $type['name'] .= " (".implode(', ', $units).")";
                    }
                }
                unset($type);
            }
            if ($other !== null) {
                if ($units_list) {
                    foreach ($units_list as $code => $info) {
                        $other_units[] = $dimensions->getUnits($code);
                    }
                } else {
                    unset($types[$other]);
                }
            }

        }
        return $types;
    }
}
