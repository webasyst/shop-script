<?php
abstract class shopFeatureValuesModel extends shopSortableModel
{
    //TODO deleteByField = update related tables

    public function getValues($field, $value = null){
        if ($field === true) {
            $data = $this->getAll($this->id);
        } else {
            $data = $this->getByField($field, $value, $this->id);
        }
        $values = array();
        foreach ($data as $id => $value) {
            if (!isset($values[$value['feature_id']])) {
                $values[$value['feature_id']] = array();
            }
            $values[$value['feature_id']][$id] = $this->getValue($value);
        }
        if (($field === true) || is_array($value)) {
            return $values;
        } else {
            return isset($values[$field]) ? $values[$field] : array();
        }
    }

    public function getProductValues($product_id, $feature_id, $field = 'value')
    {
        $sql = "SELECT pf.product_id, fv.".$field." FROM shop_product_features pf
                JOIN ".$this->table." fv ON pf.feature_value_id = fv.id
                WHERE pf.product_id IN (i:0) AND pf.feature_id = i:1";
        return $this->query($sql, $product_id, $feature_id)->fetchAll('product_id', true);
    }

    protected function getValue($row)
    {
        return $row['value'];
    }

    /**

     * Return value id by feature_id and value
     * If value not found this function creates it and return new id
     *
     * @param int $feature_id
     * @param mixed $value
     * @param string $type extented feature type (e.g. dimension)
     * @return int|array
     */
    public function getId($feature_id, $value, $type = null, $update = true)
    {
        $result = array();
        $exists = array();
        $multi = false;
        $sort = null;
        $op = $this->getSearchCondition();
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
        foreach ($values as $v) {
            $data = $this->parseValue($v, $type);
            $data['feature_id'] = $feature_id;
            $data['sort'] = $sort;

            $sql = "SELECT `id`,`sort` FROM ".$this->table." WHERE (`feature_id` = i:feature_id) AND (`value` ".$op.')';
            $row = $this->query($sql, $data)->fetchAssoc();
            if ($row) {
                $exists[$row['sort']] = intval($row['id']);
            } elseif ($update) {
                ++$sort;
                array_unshift($result, intval($this->insert($data)));
            }
        }
        ksort($exists);
        $result = array_unique(array_merge($exists, $result));
        return $multi ? $result : reset($result);
    }

    /**
     *
     * @param int $feature_id
     * @param mixed $value
     * @param string $type extented feature type (e.g. dimension)
     * @return int|array
     */
    public function getValueId($feature_id, $value, $type = null)
    {
        return $this->getId($feature_id, $value, $type, false);
    }

    /**
     *
     * Safe insert new feature value
     * @param int $feature_id
     * @param float|string $value
     * @param int $id
     * @return int inserted or exist value
     */
    public function addValue($feature_id, $value, $id = null, $type = null, $sort = null)
    {
        $row = array('id' => $id, 'value' => $value);

        try { //store
            $data = $this->parseValue($value, $type);
            $row['value'] = (string) $this->getValue($data);
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
        } catch (waDbException $ex) {
            $row['error'] = $ex->getMessage();
            switch ($ex->getCode()) {
                case '1062':
                    $id = $this->getId($feature_id, $value, $type);
                    $row['error'] = array(
                        'message'     => _w('Not unique value'),
                        'original_id' => $id,
                    );

                    $row['original_id'] = $id;
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
