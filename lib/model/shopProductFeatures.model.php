<?php
class shopProductFeaturesModel extends waModel implements shopProductStorageInterface
{
    protected $table = 'shop_product_features';
    //protected $id = array('product_id', 'sku_id', 'feature_id', 'feature_value_id', );

    /**
     * TODO: realize
     * @param array $product_ids
     */
    public function deleteByProducts(array $product_ids)
    {

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
        return $this->getValues($product->getId());
    }

    public function getValues($product_id, $sku_id = null)
    {
        $sql = "SELECT f.code, f.type, f.multiple, pf.* FROM ".$this->table." pf JOIN
                shop_feature f ON pf.feature_id = f.id
                WHERE pf.product_id = i:id AND ";
        if ($sku_id) {
            $sql .= '(pf.sku_id = i:sku_id OR pf.sku_id IS NULL) ORDER BY pf.sku_id';
        } else {
            $sql .= 'pf.sku_id IS NULL';
        }
        $features = $storages = array();
        $params = array(
            'id'     => $product_id,
            'sku_id' => $sku_id,
        );
        $data = $this->query($sql, $params);
        foreach ($data as $row) {
            $features[$row['feature_id']] = array(
                'code'     => $row['code'],
                'multiple' => $row['multiple'],
            );
            $type = preg_replace('/\..*$/', '', $row['type']);
            if ($sku_id) {
                $storages[$type][$row['feature_id']] = $row['feature_value_id'];
            } else {
                $storages[$type][] = $row['feature_value_id'];
            }

        }
        $result = array();

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
        return $result;
    }

    /**
     * (non-PHPdoc)
     * @see shopProductStorageInterface::setData()
     * @param shopProduct $product current product object
     * @param array[string] mixed $data new product feature values
     */
    public function setData(shopProduct $product, $data)
    {
        $product_id = $product->getId();

        $feature_model = new shopFeatureModel();
        $features = $feature_model->getAll('code');
        $features_map = array();
        foreach ($features as $code => $f) {
            $features_map[$f['id']] =& $features[$code];
        }

        $current = array();
        $rows = $this->getByField('product_id', $product_id, true);
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
                $model = shopFeatureModel::getValuesModel($f['type']);
                if ($value === '') {
                    //delete it
                    if (!empty($current[$code])) {
                        $delete[$f['id']] = $current[$code];
                    }
                } else {
                    $id = $model->getId($f['id'], $value, $f['type']);

                    if (isset($current[$code])) {
                        if (empty($f['multiple'])) {
                            if ($current[$code] != $id) {
                                $delete[$f['id']] = $current[$code];
                                $add[$f['id']] = $id;
                            }
                        } else {
                            $delete[$f['id']] = array_diff($current[$code], (array) $id);
                            if (empty($delete[$f['id']])) {
                                unset($delete[$f['id']]);
                            }
                            $add[$f['id']] = array_diff((array) $id, $current[$code]);
                            if (empty($add[$f['id']])) {
                                unset($add[$f['id']]);
                            }
                        }
                    } else {
                        $add[$f['id']] = $id;
                    }
                }
            } else {
                //it's a new feature
                if (!empty($value) && !empty($value['value'])) {
                    $f = array(
                        'name'  => $value['name'],
                        'type'  => $value['type'],
                        'types' => $value['types'],
                    );
                    $f['id'] = $feature_model->save($f);

                    $type_features_model = new shopTypeFeaturesModel();
                    $type_features_model->updateByFeature($f['id'], $f['types']);

                    $model = shopFeatureModel::getValuesModel($f['type']);
                    $add[$f['id']] = $model->getId($f['id'], $value['value'], $f['type']);
                }
            }
        }

        foreach ($features as $code => $f) {
            if (empty($data[$code]) && !empty($current[$code])) {
                $delete[$f['id']] = $current[$code];
            }
        }
        foreach ($delete as $feature_id => $value_id) {
            $this->deleteByField(array('product_id' => $product_id, 'feature_id' => $feature_id, 'feature_value_id' => $value_id));
        }

        foreach ($add as $feature_id => $value_id) {
            $this->multipleInsert(array('product_id' => $product_id, 'feature_id' => $feature_id, 'feature_value_id' => $value_id));
        }
    }
}
