<?php

abstract class shopFeatureValuesModel extends shopSortableModel
{
    protected $changed_fields = array();

    //TODO deleteByField = update related tables

    /**
     * @param int $value_id
     * @return mixed
     */
    public function getFeatureValue($value_id)
    {
        if ($row = $this->getById($value_id)) {
            return $this->getValue($row);
        } else {
            return null;
        }
    }

    /**
     * @param string $field
     * @param int|int[] $value
     * @param int $limit
     * @return array array of values for multiple features or values of single feature
     */
    public function getValues($field, $value = null, $limit = null)
    {
        if ($field === true) {
            $data = $this->getAll($this->id);
        } else {
            if ($limit) {
                $where = $this->getWhereByField($field, $value);
                $this->query('SET @a:=0');
                $this->query('SET @fid:=0');
                $sql = <<<SQL
SELECT `t`.*
FROM (
  SELECT * FROM `{$this->table}`
  WHERE {$where}
  ORDER BY `feature_id` ASC, `sort`
) AS `t`
WHERE (IF (@fid=`t`.`feature_id`, @a:=@a+1, @a:=(@fid:=t.feature_id) - `t`.`feature_id` + 1) <= i:0)
SQL;
                $data = $this->query($sql, max(1, $limit))->fetchAll($this->id);
                uasort($data, array($this, 'sort'));

            } else {
                if (is_array($field)) {
                    $data = $this->getByField($field, $this->id);
                } else {
                    $data = $this->getByField($field, $value, $this->id);
                }
            }
        }
        $values = array();
        foreach ($data as $id => $v) {
            $f_id = $v['feature_id'];
            if (!isset($values[$f_id])) {
                $values[$f_id] = array();
            }
            $values[$f_id][$id] = $this->getValue($v);
        }
        if (($field === true) || is_array($value) || ($field != 'feature_id')) {
            return $values;
        } else {
            return isset($values[$value]) ? $values[$value] : array();
        }
    }

    public function getProductValues($product_id, $feature_id, $field = 'value')
    {
        if (!$product_id) {
            return array();
        }
        if (is_array($field)) {
            $fields = 'fv.'.implode(', fv.', $field);
        } else {
            $fields = 'fv.'.$field;
        }
        $sql = "SELECT pf.product_id, pf.sku_id, ".$fields." FROM shop_product_features pf
                JOIN ".$this->table." fv ON pf.feature_value_id = fv.id
                WHERE pf.product_id IN (i:0) AND pf.feature_id = i:1";
        $query = $this->query($sql, $product_id, $feature_id);
        $result = array();
        foreach ($query as $row) {
            if ($row['sku_id']) {
                $result['skus'][$row['sku_id']] = is_array($field) ? $row : $row[$field];
            } else {
                $result[$row['product_id']] = is_array($field) ? $row : $row[$field];
            }
        }
        return $result;
    }

    protected function getValue($row)
    {
        return $row['value'];
    }

    /**
     * @param $row
     * @param $data
     * @return bool|array
     */
    protected function isChanged($row, $data)
    {
        return false;
    }

    /**
     * Return value id by feature_id and value
     * If value not found this function creates it and return new id
     *
     * @param int $feature_id
     * @param mixed $value
     * @param string $type extended feature type (e.g. dimension)
     * @param bool $update
     * @return int|int[]
     */
    public function getId($feature_id, $value, $type = null, $update = true)
    {
        $result = array();
        $exists = array();
        $multi = false;
        $sort = null;

        if (is_array($value) && isset($value['id'])) {
            $result[] = $value['id'];
            $result = array_unique(array_map('intval', array_filter($result)));
        } elseif (is_array($value) && ($v = reset($value)) && is_array($v) && isset($v['id'])) {
            foreach ($value as $v) {
                if (isset($v['id'])) {
                    $result[] = $v['id'];
                }
            }
            $result = array_unique(array_map('intval', array_filter($result)));
            $multi = (count($result) > 1);
        } else {
            if (is_array($value)) {
                if (isset($value['value'])) {
                    $values = array($value);
                } else {
                    $multi = true;
                    $values = $value;
                }
            } else {
                $values = array($value);
            }
            if ($update) {
                $sql = "SELECT (MAX(`sort`)+1) `max_sort` FROM ".$this->table." WHERE (`feature_id` = i:0)";
                $sort = $this->query($sql, $feature_id)->fetchField('max_sort');
            }
            $op = $this->getSearchCondition();
            foreach ($values as $v) {
                $data = $this->parseValue($v, $type);
                $data['feature_id'] = $feature_id;
                $data['sort'] = $sort;
                $fields = array_unique(array_merge(array($this->id, $this->sort), $this->changed_fields));
                $fields = '`'.implode('`, `', $fields).'`';
                $sql = "SELECT {$fields} FROM ".$this->table." WHERE (`feature_id` = i:feature_id) AND (".$op.')';
                $row = $this->query($sql, $data)->fetchAssoc();
                if ($row) {
                    if ($changed = $this->isChanged($row, $data)) {
                        $this->updateById($row['id'], $changed);
                    }
                    if (isset($exists[$row['sort']]) && ($exists[$row['sort']] != $row['id'])) {
                        $row['sort'] = ($sort === false) ? ($sort = 0) : ++$sort;
                        $this->updateById($row['id'], array('sort' => $row['sort']));
                    }
                    $exists[$row['sort']] = intval($row['id']);

                } elseif ($update) {
                    $data['sort'] = ($sort === false) ? ($sort = 0) : ++$sort;;
                    $data['id'] = intval($this->insert($data));;
                    $result[$data['sort']] = $data['id'];
                }
            }
            $result = array_map('intval', $result);
            $exists = array_map('intval', $exists);
            $result = array_unique(array_merge($exists, $result));

        }
        ksort($result);
        return $multi ? $result : reset($result);
    }

    /**
     *
     * @param int $feature_id
     * @param mixed $value
     * @param string $type extended feature type (e.g. dimension)
     * @param bool $update
     * @return int|array
     */
    public function getValueId($feature_id, $value, $type = null, $update = false)
    {
        return $this->getId($feature_id, $value, $type, $update);
    }

    public function getValueIdsByRange($feature_id, $min, $max)
    {
        return array();
    }

    /**
     *
     * Safe insert new feature value
     * @param int $feature_id
     * @param float|string $value
     * @param int $id
     * @param string $type
     * @param int $sort
     * @return int inserted or exist value
     */
    public function addValue($feature_id, $value, $id = null, $type = null, $sort = null)
    {
        $row = array('id' => $id, 'value' => $value);

        $data = $this->parseValue($value, $type);

        try { //store
            if (isset($data['code'])) {
                $row['code'] = $data['code'];
            }
            if ($sort !== null) {
                $row['sort'] = $sort;
                $data['sort'] = $sort;
            }
            if ($id > 0) {
                $this->updateById($id, $data);
            } else {
                $data['feature_id'] = $feature_id;
                $row['id'] = $this->insert($data);
                $row['insert_id'] = $id;
            }
            $row['value'] = (string)$this->getValue($data);
        } catch (waDbException $ex) {
            $row['error'] = $ex->getMessage();
            switch ($ex->getCode()) {
                case '1062':
                    $id = $this->getId($feature_id, $value, $type);
                    $row['error'] = array(
                        'message'        => _w('Not unique value'),
                        'original_id'    => $id,
                        'original_value' => (string)$this->getValue($data),
                    );

                    $row['original_id'] = $id;
                    break;
                case '1406':
                    $row['error'] = array(
                        'message'     => wa()->getConfig()->isDebug() ? $ex->getMessage() : _w("Data too long"),
                        'original_id' => 0,
                    );
                    break;
                default:
                    $row['error'] = array(
                        'message'     => wa()->getConfig()->isDebug() ? $ex->getMessage() : _w('Error while insert'),
                        'original_id' => 0,
                    );
                    break;
            }
        }

        return $row;
    }

    abstract protected function parseValue($value, $type);

    abstract protected function getSearchCondition();
}
