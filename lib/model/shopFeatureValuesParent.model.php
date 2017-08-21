<?php
class shopFeatureValuesParentModel extends shopFeatureValuesModel
{
    protected $table = null;
    protected  $feature_id = 0;

    protected function getSearchCondition()
    {
        return '`value`= i:value';
    }

    protected function parseValue($value, $type)
    {
        return array('value' => '-');
    }
    public function getValueId($feature_id, $value, $type = null, $update = false)
    {
        if(is_numeric($feature_id)) {
            
        } else {
            $feature_model = new shopFeatureModel();
                $feature_id = $feature_model->getByCode($feature_id);
        }
        $parent_feature = shopFeatureModel::getParentFeature($feature_id);
        return shopFeatureModel::getValuesModel($parent_feature['type'])->getValueId($parent_feature['id'], $value, $parent_feature['type'], $update);
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

        $parent_feature = shopFeatureModel::getParentFeature($feature_id);
        $model = shopFeatureModel::getValuesModel($parent_feature['type']);
        $sql = "SELECT pf.product_id, pf.sku_id, ".$fields." FROM shop_product_features pf
                JOIN ".$model->table." fv ON pf.feature_value_id = fv.id
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

    public function getFeatureValue($value_id)
    {
        if ($row = $this->getById($value_id)) {
            return $this->getValue($row);
        } else {
            return null;
        }
    }
    //////////////////////////////////////////////////////////////////////////////////////////////
    //////////////////////////////////////////////////////////////////////////////////////////////
    //////////////////////////////////////////////////////////////////////////////////////////////

    public function setFeatureId($id) {
        $this->feature_id = $id;
    }
    /**
     * @param string $field
     * @param int|int[] $value
     * @param int $limit
     * @return array array of values for multiple features or values of single feature
     */
    public function getValues($field, $value = null, $limit = null)
    {
       $feature_model = new shopFeatureModel();
      $values = array();
        if($field=='id') {
            if($this->feature_id) {
                $feature =  $feature_model->getById($this->feature_id);
                $parent_feature = $feature_model->getById($feature['parent_id']);
                return  shopFeatureModel::getValuesModel($parent_feature['type'])
                    ->getValues($field, $value);
            }
              
        } else {
            if(is_array($value)) {
                foreach ($value as $id) {
                    $feature =  $feature_model->getById($id);
                    $parent_feature = $feature_model->getById($feature['parent_id']);
                    $feature_values = shopFeatureModel::getValuesModel($parent_feature['type'])->getValues($field, $feature['parent_id']);
                    $values[$id] =  $feature_values;
                }
            } else {
                $feature =  $feature_model->getById($value);
                $parent_feature = $feature_model->getById($feature['parent_id']);
                $values[$value] = shopFeatureModel::getValuesModel($parent_feature['type'])->getValues($field, $value);
            }
        }

        return $values;

        //return shopFeatureModel::getFeatureValues($parent_feature);
    }
    /*public function getValues($field, $value = null, $limit = null)
    {
       $feature_model = new shopFeatureModel();
       $feature =  $feature_model->getById($value);
        waLog::dump( $feature);
        if(preg_match('/parent\.(.*)$/',  $feature['type'], $matches)) {
            $parent_feature = $feature_model->getById($matches[1]);
            return shopFeatureModel::getFeatureValues($parent_feature);
        } else {
            return array();
        }
        waLog::dump($matches);
        $parent_feature = $feature_model->getById($matches[1]);
        return shopFeatureModel::getFeatureValues($parent_feature);

    }*/

    public function getId($feature_id, $value, $type = null, $update = true)
    {
        $result = array(
            1 => '-',
        );
        return (is_array($value) && !isset($value['value'])) ? $result : reset($result);
    }

    /**
     * @param $product_id
     * @param $feature_id
     * @param string $field
     * @return array array of values for multiple features or values of single feature
     */
   

    public function addValue($feature_id, $value, $id = null, $type = null, $sort = null)
    {
        $row = $this->parseValue($value, $type);
        $row['id'] = ($id === null) ? $this->getId($feature_id, $value) : $id;
        $row['sort'] = $id;
        return $row;
    }

    public function countByField($field, $value = null)
    {
        return 1;
    }

    public function deleteByField($field, $value = null)
    {
        return true;
    }

}
