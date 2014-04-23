<?php
class shopFeatureValuesDimensionModel extends shopFeatureValuesModel
{
    protected $table = 'shop_feature_values_dimension';

    protected function getValue($row)
    {
        return new shopDimensionValue($row);
    }

    public function getProductValues($product_id, $feature_id, $field = 'value')
    {
        return parent::getProductValues($product_id, $feature_id, 'value_base_unit');
    }

    protected function parseValue($value, $type)
    {
        $dimensions = shopDimension::getInstance();
        $data = array();
        if (strpos($type, '.')) {
            $type = preg_replace('/^.+\./', '', $type);
            if (!is_array($value) || (count($value) == 1)) {
                $matches = null;
                $value = trim(is_array($value) ? reset($value) : $value);
                if (preg_match('/^(\-?\d+([\.,]\d+)?)\s+(.+)$/', $value, $matches)) {
                    $value = array(
                        'value' => $this->castValue('double', $matches[1]),
                        'unit'  => shopDimension::castUnit($type, trim($matches[3])),
                    );
                } else {
                    $value = array(
                        'value' => $this->castValue('double', $value),
                        'unit'  => '',
                    );
                }
            }
            $value['type'] = $type;
        }

        if (!empty($value['code'])) {
            if (strpos($value['code'], '.')) {
                list($data['type'], $data['unit']) = explode('.', $value['code'], 2);
                //$dimension = $dimensions->getDimension($data['type']);
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

        $data['value'] = isset($value['value']) ? $this->castValue('double', $value['value']) : 0;
        $data['value_base_unit'] = $dimensions->convert($data['value'], $data['type'], null, $data['unit']);
        return $data;
    }

    protected function getSearchCondition()
    {
        return '(`value`= :value) AND (`unit` = s:unit)';
    }

    public function getValueIdsByRange($feature_id, $min, $max)
    {
        $sql = 'SELECT id FROM '.$this->table.'
                WHERE feature_id = i:0';
        if ($min !== null && $min !== '') {
            $sql .= ' AND value_base_unit >= f:1';
        }
        if ($max !== null && $max !== '') {
            $sql .= ' AND value_base_unit <= f:2';
        }
        return $this->query($sql, $feature_id, $min, $max)->fetchAll(null, true);
    }
}
