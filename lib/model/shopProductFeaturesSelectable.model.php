<?php

class shopProductFeaturesSelectableModel extends waModel
{
    protected $table = 'shop_product_features_selectable';

    public function deleteByProducts(array $product_ids) {
        $this->deleteByField('product_id', $product_ids);
    }

    /**
     * Insert(update) data for this product ID
     *
     * @param array $data If empty - delete all records for this product ID
     * @param int $product_id product ID
     */
    public function save($data, $product_id)
    {
        $old_data = array();
        foreach ($this->getByField('product_id', $product_id, true) as $item) {
            $old_data[$item['feature_id']][$item['value_id']] = true;
        }

        $insert = array();
        foreach ($data as $f_id => $values) {
            foreach ($values as $v_id) {
                if (isset($old_data[$f_id][$v_id])) {
                    unset($old_data[$f_id][$v_id]);
                } else {
                    $insert[] = array(
                        'product_id' => $product_id,
                        'feature_id' => $f_id,
                        'value_id' => $v_id
                    );
                }
            }
            if (isset($old_data[$f_id]) && empty($old_data[$f_id])) {
                unset($old_data[$f_id]);
            }
        }

        if ($insert) {
            $this->multiInsert($insert);
        }
        if ($old_data) {
            foreach ($old_data as $f_id => $values) {
                foreach ($values as $v_id => $selected) {
                    $this->deleteByField(array(
                        'product_id' => $product_id,
                        'feature_id' => $f_id,
                        'value_id' => $v_id
                    ));
                }
            }
        }
    }

    public function getByProduct($id)
    {
        $rows = $this->getByField('product_id', $id, true);
        $result = array();
        foreach ($rows as $row) {
            $result[$row['feature_id']][] = $row['value_id'];
        }
        return $result;
    }
}
