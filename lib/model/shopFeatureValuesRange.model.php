<?php

class shopFeatureValuesRangeModel extends shopFeatureValuesModel
{
    protected $table = 'shop_feature_values_range';

    protected function getValue($row)
    {
        return new shopRangeValue($row);
    }

    public function getProductValues($product_id, $feature_id, $field = 'value')
    {
        $values = parent::getProductValues($product_id, $feature_id, array('begin_base_unit', 'end_base_unit'));
        return $values;
    }

    protected function parseValue($value, $type)
    {
        $dimensions = shopDimension::getInstance();
        $data = array();
        /**
         * @todo string based pattern
         * d.d(\s+|\.\.\.?|\s*[\-—]\s*)\d\.\d
         */
        if (strpos($type, '.')) {
            if (!is_array($value) || ((count($value) == 1) && !isset($value['value']))) {
                $matches = null;
                $value = trim(is_array($value) ? reset($value) : $value);
                if (preg_match('/^(.*)\s+([\D]+)$/', $value, $matches)) {
                    $value = array(
                        'value' => trim($matches[1]),
                        'unit'  => trim($matches[2]),
                    );
                } else {
                    $value = array(
                        'value' => trim($value),
                        'unit'  => null,
                    );
                }
                $values = array_map('trim', preg_split('/\s*([\s—]+|\.\.\.?|-\s+)\s*/', $value['value'], 2));

                $cast_type = $type == 'range.date' ? 'date' : 'double';
                if (count($values) > 1) {
                    $value['value'] = array(
                        'begin' => ($values[0] === '') ? null : $this->castValue($cast_type, $values[0]),
                        'end'   => ($values[1] === '') ? null : $this->castValue($cast_type, $values[1]),
                    );
                } else {
                    $value['value'] = array(
                        'begin' => ($values[0] === '') ? null : $this->castValue($cast_type, $values[0]),
                        'end'   => null,
                    );
                }
            }

            $type = preg_replace('/^.+\./', '', $type);
            $value['type'] = $type;
        }

        if (!empty($value['code'])) {
            if (strpos($value['code'], '.')) {
                list($data['type'], $data['unit']) = explode('.', $value['code'], 2);
            } elseif ($dimension = $dimensions->getDimension(empty($value['type']) ? $value['code'] : $value['type'])) {
                $data['type'] = !empty($value['type']) ? $value['type'] : $value['code'];
                $data['unit'] = !empty($value['unit']) ? $value['unit'] : $dimension['base_unit'];
            } else {
                $data['unit'] = !empty($value['unit']) ? $value['unit'] : '';
                $data['type'] = null;
            }
        } else {
            $data['type'] = !empty($value['type']) ? $value['type'] : '';
            if ($dimension = $dimensions->getDimension($data['type'])) {
                $data['unit'] = !empty($value['unit']) ? $value['unit'] : $dimension['base_unit'];
            } else {
                $data['unit'] = !empty($value['unit']) ? $value['unit'] : '';
            }
        }

        $data['unit'] = $dimensions->fixUnit($data['type'], $data['unit']);
        $cast_type = $data['type'] == 'date' ? 'timestamp' : 'double';
        $data['begin'] = (isset($value['value']['begin']) && ($value['value']['begin'] !== '')) ? $this->castValue($cast_type, $value['value']['begin']) : null;
        $data['begin_base_unit'] = $dimensions->convert($data['begin'], $data['type'], null, $data['unit']);

        $data['end'] = (isset($value['value']['end']) && ($value['value']['end'] !== '')) ? $this->castValue($cast_type, $value['value']['end']) : null;
        $data['end_base_unit'] = $dimensions->convert($data['end'], $data['type'], null, $data['unit']);

        if (($data['end'] !== null) && ($data['begin'] !== null) && ($data['begin_base_unit'] > $data['end_base_unit'])) {
            //swap interval values in case wrong order
            $end = $data['begin'];
            $data['begin'] = $data['end'];
            $data['end'] = $end;

            $end = $data['begin_base_unit'];
            $data['begin_base_unit'] = $data['end_base_unit'];
            $data['end_base_unit'] = $end;
        }
        return $data;
    }

    protected function getSearchCondition()
    {
        return '(`begin`= :begin) AND (`end`=:end) AND (`unit` = s:unit)';
    }

    public function getValueIdsByRange($feature_id, $min, $max)
    {
        $sql = 'SELECT id FROM '.$this->table.'
                WHERE feature_id = i:0';
        if ($min !== null && $min !== '') {
            $sql .= ' AND end_base_unit >= f:1';
        }
        if ($max !== null && $max !== '') {
            $sql .= ' AND begin_base_unit <= f:2';
        }
        return $this->query($sql, $feature_id, $min, $max)->fetchAll(null, true);
    }

    protected function castValue($type, $value, $is_null = false)
    {
        if ($type == 'date') {
            return $value;
        }

        if ($value instanceof waModelExpr || $type !== 'timestamp') {
            return parent::castValue($type, $value, $is_null);
        }

        // Dates in this table are stored as timestamps
        $date = @strtotime($value);
        if ($date) {
            return $this->escape($date);
        }
        return null;
    }
}
