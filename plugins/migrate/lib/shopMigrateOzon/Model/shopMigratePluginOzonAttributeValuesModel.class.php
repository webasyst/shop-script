<?php

class shopMigratePluginOzonAttributeValuesModel extends shopMigratePluginOzonModel
{
    protected $table = 'shop_migrate_ozon_attribute_values';

    public function addBatch($snapshot_id, array $values)
    {
        if (!$values) {
            return;
        }
        $rows = array();
        foreach ($values as $value) {
            $rows[] = array(
                'snapshot_id'        => (int) $snapshot_id,
                'product_id'         => (int) ifset($value['product_id']),
                'attribute_id'       => (int) ifset($value['attribute_id']),
                'dictionary_value_id'=> isset($value['dictionary_value_id']) ? (int) $value['dictionary_value_id'] : null,
                'value'              => isset($value['value']) ? (string) $value['value'] : null,
                'position'           => (int) ifset($value['position'], 0),
            );
        }

        $this->multipleInsert($rows, array('dictionary_value_id', 'value', 'position'));
    }

    public function getForProducts($snapshot_id, array $product_ids)
    {
        if (!$product_ids) {
            return array();
        }
        $placeholders = implode(',', array_fill(0, count($product_ids), '?'));
        $sql = sprintf(
            'SELECT * FROM %s WHERE snapshot_id = ? AND product_id IN (%s) ORDER BY attribute_id, position',
            $this->table,
            $placeholders
        );
        $params = array_merge(array((int) $snapshot_id), array_map('intval', $product_ids));
        return $this->query($sql, $params)->fetchAll();
    }
}
