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

    const STATUS_PUBLIC = 'public';
    const STATUS_HIDDEN = 'hidden';
    const STATUS_PRIVATE = 'private';

    const SEARCH_STEP = 20;

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
            'status'     => 'public',
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
                        'code'   => $feature['code'].$n,
                        'name'   => $feature['name'].$n,
                        'status' => $feature['status'],
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

            if (!self::isCodeAllowed($code)) {
                $code = ($code === '_' ? 'f' : 'f_').$code;
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

    /**
     * Is string allowed for use as code for feature
     * @param $code
     * @return bool
     */
    public static function isCodeAllowed($code)
    {
        // check for prefixes
        $forbidden_prefixes = array(
            'utm_'
        );
        foreach ($forbidden_prefixes as $prefix) {
            if (strpos($code, $prefix) === 0) {
                return false;
            }
        }

        // check for whole words
        $forbidden = array('_', 'tag', 'sort', 'order', 'page');
        $db_path = wa()->getAppPath('lib/config/db.php', 'shop');
        if (file_exists($db_path)) {
            $db = include($db_path);
            foreach (array('shop_product', 'shop_category') as $tbl) {
                $forbidden += array_keys($db[$tbl]);
            }
        }
        $forbidden = array_fill_keys(array_unique($forbidden), true);
        if (isset($forbidden[$code])) {
            return false;
        }

        return true;

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
     * Get features count by options
     * @param $options
     * @return int|bool
     * @throws waException
     */
    public function getFeaturesCount($options)
    {
        $options['select'] = 'COUNT(DISTINCT sf.id) as count';
        $feature_count = $this->getFilterFeatures($options, 100500, false);

        return ifset($feature_count, 'count', false);
    }

    /**
     * @param array $options
     *      array|int $code get features by code's
     *      array|int $id get features by id's
     *      array|int $ignore_code ignore this code's
     *      array|int $ignore_id ignore this id's
     *      array|int $type_id get features by product type id's
     *      bool|int $count is greater than or equal to the number of values. Default > 0
     *      bool|string $status get by feature status. Default - public
     *      bool $interval get interval features
     *      bool $frontend get the features that you can use for the frontend
     *      bool $ignore ignore divider and %d.dimensions. Default - true
     *      array|string $select select expression. Default - *
     *      int $offset mysql offset
     *
     * @param int $limit
     * @param bool|string $all if string, fetch array by $all
     * @return array
     * @throws waException
     *
     * @see test tests/wa-apps/shop/model/feature/shopFeatureModelGetFilterFeaturesTest.php
     */
    public function getFilterFeatures($options = [], $limit = 500, $all = true)
    {
        $select = ['Distinct sf.*'];
        $join = [];
        $where = [];
        $fetch = 'code';

        $options += [
            'status' => self::STATUS_PUBLIC,
            'count'  => true,
            'ignore' => true,
        ];

        foreach ($options as $field => $value) {
            switch ($field) {
                case 'code':
                case 'id':
                    if ($value !== false) {
                        $where[$field] = $this->getWhereByField($field, $value, 'sf');
                    }
                    break;
                case 'type_id':
                    if ($value !== false) {
                        $type_where = [];
                        if (is_array($value)) {
                            foreach ($value as $type_id) {
                                if ($type_id = intval($type_id)) {
                                    $type_where[] = $type_id;
                                }
                            }
                        } elseif ($value = intval($value)) {
                            $type_where[] = $value;
                        }
                        $type_where[] = 0;

                        $join[] = " JOIN shop_type_features AS stf ON stf.feature_id = sf.id ";
                        $where[] = 'stf.type_id IN ('.implode(', ', $type_where).')';
                    }
                    break;
                case 'count':
                    if ($value !== false) {
                        if ($value === true) {
                            $where[$field] = 'sf.count > 0';
                        } else {
                            $where[$field] = sprintf('sf.count >= %d', max(0, $value));
                        }
                    }
                    break;
                case 'status':
                    if (!empty($value)) {
                        $where[$field] = $this->getWhereByField($field, $value, 'sf');
                    }
                    break;
                case 'interval':
                    if ($value !== false) {
                        $where['union'][] = sprintf("sf.type='%s'", self::TYPE_DOUBLE);
                        $where['union'][] = sprintf("sf.type LIKE '%s.%%'", self::TYPE_DIMENSION);
                        $where['union'][] = sprintf("sf.type LIKE '%s.%%'", self::TYPE_RANGE);
                    }
                    break;
                case 'frontend':
                    if ($value !== false) {
                        $where['union'][] = 'sf.selectable=1';
                        $where['union'][] = sprintf("sf.type='%s'", self::TYPE_BOOLEAN);
                        $where['union'][] = sprintf("sf.type='%s'", self::TYPE_DOUBLE);
                        $where['union'][] = sprintf("sf.type='%s'", self::TYPE_TEXT);
                        $where['union'][] = sprintf("sf.type='%s'", self::TYPE_VARCHAR);
                        $where['union'][] = sprintf("sf.type LIKE '%s.%%'", self::TYPE_DIMENSION);
                        $where['union'][] = sprintf("sf.type LIKE '%s.%%'", self::TYPE_RANGE);
                    }
                    break;
                case 'ignore_id':
                    if (!empty($value)) {
                        $ignore_id = $this->getWhereByField('id', $value, 'sf');
                        $ignore_id = preg_replace('/IN/', 'NOT IN', $ignore_id, 1);
                        $ignore_id = preg_replace('/^sf.`id` = /', 'sf.`id` != ', $ignore_id, 1);
                        $where[$field] = $ignore_id;
                    }
                    break;
                case 'ignore_code':
                    if (!empty($value)) {
                        $ignore_code = $this->getWhereByField('code', $value, 'sf');
                        $ignore_code = preg_replace('/IN/', 'NOT IN', $ignore_code, 1);
                        $ignore_code = preg_replace('/^sf.`code` = /', 'sf.`code` != ', $ignore_code, 1);
                        $where[$field] = $ignore_code;
                    }
                    break;
                case 'ignore':
                    if ($value === true) {
                        $where[] = 'sf.parent_id IS NULL';
                        $where[] = 'sf.type != \'divider\'';
                        $where[] = sprintf("sf.type NOT LIKE '%%.%s.%%'", self::TYPE_DIMENSION); //ignore %D.dimension
                    }
                    break;
                case 'select':
                    $select = [];
                    if (is_array($value)) {
                        foreach ($value as $data) {
                            $select[] = $data;
                        }
                    } elseif (is_string($value)) {
                        $select[] = $value;
                    }

                    //Reset fetch if not found key code
                    if (!array_search('*', $select) && !array_search('code', $select) && !is_string($all)) {
                        $fetch = null;
                    }

                    break;
                case 'offset':
                    if ($value !== false) {
                        $limit = $value.', '.$limit;
                    }
                    break;
                case 'term':
                    $value = (array)$value;
                    $unions = [];
                    foreach ($value as $term) {
                        $term = $this->escape($term, 'like');
                        $unions[] = "sf.name LIKE '$term%'";
                        $unions[] = "sf.code LIKE '$term%'";
                    }
                    $where[$field] = $this->getUnion($unions);
                    break;
                default:
                    $where[$field] = $this->getWhereByField($field, $value, 'sf');
                    break;
            }
        }

        if (isset($where['union']) && count($where['union'])) {
            $where['union'] = $this->getUnion($where['union']);
        } else {
            unset($where['union']);
        }

        $where = '('.implode(') AND (', $where).')';
        $join = join(' ', $join);
        $select = join(', ', $select);

        $query = "SELECT {$select} FROM {$this->table} AS sf {$join} WHERE {$where} ORDER BY sf.count, sf.id DESC LIMIT {$limit}";
        $result = $this->query($query);

        if ($all === false) {
            return $result->fetchAssoc();
        } else {
            if (is_string($all)) {
                $fetch = $all;
            }
            return $result->fetchAll($fetch);
        }
    }

    public function getUnion($union)
    {
        return '('.implode(') OR (', $union).')';
    }

    /**
     *
     * Get features with their values
     * @param $field
     * @param mixed|void $value
     * @param string $key
     * @param bool $fill_values
     * @return array
     * @throws waException
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
            if (is_array($field)) {
                $features = $this->getByField($field, $key);
            } else {
                $features = $this->getByField($field, $value, $key);
            }
        }
        if ($fill_values) {
            $features = $this->getValues($features, (is_int($fill_values) ? $fill_values : (($field === true) || $fill_values === true)));
        }
        return $features;

    }

    /**
     * @param array[] $features An array of features for which the values will be filled
     *
     * @param null|int|true|int[][] $all
     * - null for all
     * - int for values with limit=$all
     * - true for every one
     * - array of features value_ids to get only specified values
     * @return array[]
     * @throws waException
     */
    public function getValues($features, $all = null)
    {
        foreach ($features as &$feature) {
            $feature['values'] = array();
        }
        unset($feature);

        $types = $this->groupByValueType($features);

        foreach ($types as $type => $keys) {
            if ($model = self::getValuesModel($type)) {

                $value_ids = null;
                if (is_array($all)) {
                    $field = 'id';
                    $value = array();
                    $limit = null;

                    $value_ids = array();
                    foreach ($keys as $feature_id => $feature_key) {
                        if (isset($all[$feature_key])) {
                            $value_ids[$feature_key] = (array)$all[$feature_key];
                            $value = array_merge($value, (array)$all[$feature_key]);
                        }
                    }
                } elseif ($all === true) {
                    $field = true;
                    //Actually will be used method getAll at model;
                    $value = null;
                    $limit = null;
                } else {
                    # $all is null or integer
                    $field = 'feature_id';
                    $value = array_keys($keys);
                    $limit = $all;
                }

                $features_values = $model->getValues($field, $value, $limit);

                foreach ($features_values as $feature_id => $values) {

                    if ($feature_id === 0) {
                        foreach ($keys as $feature_key) {
                            if ($value_ids === null) {
                                $features[$feature_key]['values'] = $values;
                            } else {
                                if (isset($value_ids[$feature_key])) {
                                    foreach ($value_ids[$feature_key] as $value_id) {
                                        if (isset($values[$value_id])) {
                                            $features[$feature_key]['values'][$value_id] = $values[$value_id];
                                        }
                                    }
                                }
                            }
                        }

                    } elseif (isset($keys[$feature_id])) {
                        // avoid values with wrong types
                        $feature_key = $keys[$feature_id];

                        if (isset($features[$feature_key])) {
                            //avoid values without related features
                            $features[$feature_key]['values'] = $values;
                        } else {
                            trigger_error("Outdated records at {$this->table}", E_USER_WARNING);
                        }
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
     * @return shopFeatureValuesModel
     * @throws waException
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
        $model = self::getValuesModel($feature['type']);
        if ($model) {
            $id = $model->getValueId($feature['id'], $value, $feature['type'], $update);
            if ($update) {
                $this->recount($feature);
            }
        } else {
            $id = null;
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
