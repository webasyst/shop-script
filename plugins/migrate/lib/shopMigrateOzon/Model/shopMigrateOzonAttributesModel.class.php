<?php

class shopMigrateOzonAttributesModel extends shopMigrateOzonModel
{
    protected $table = 'shop_migrate_ozon_attributes';

    public function addBatch($snapshot_id, array $attributes)
    {
        if (!$attributes) {
            return;
        }
        $rows = array();
        foreach ($attributes as $attribute) {
            $rows[] = array(
                'snapshot_id'             => (int) $snapshot_id,
                'description_category_id' => (int) ifset($attribute['description_category_id']),
                'type_id'                 => (int) ifset($attribute['type_id']),
                'attribute_id'            => (int) ifset($attribute['attribute_id']),
                'name'                    => (string) ifset($attribute['name'], ''),
                'type'                    => (string) ifset($attribute['type'], ''),
                'unit'                    => isset($attribute['unit']) ? (string) $attribute['unit'] : null,
                'is_required'             => empty($attribute['is_required']) ? 0 : 1,
                'is_collection'           => empty($attribute['is_collection']) ? 0 : 1,
                'meta'                    => isset($attribute['meta']) ? json_encode($attribute['meta']) : null,
            );
        }
        $this->multipleInsert($rows, array('name', 'type', 'unit', 'is_required', 'is_collection', 'meta'));
    }

    public function getBySnapshot($snapshot_id)
    {
        return $this->select('*')
            ->where('snapshot_id = ?', (int) $snapshot_id)
            ->order('description_category_id, attribute_id')
            ->fetchAll('attribute_id');
    }

    public function getForCategoryPairs($snapshot_id, array $pairs)
    {
        if (!$pairs) {
            return array();
        }
        $where = array();
        $params = array((int) $snapshot_id);
        foreach ($pairs as $pair) {
            $where[] = '(description_category_id = ? AND type_id = ?)';
            $params[] = (int) $pair['description_category_id'];
            $params[] = (int) $pair['type_id'];
        }
        $sql = sprintf(
            'SELECT * FROM %s WHERE snapshot_id = ? AND (%s)',
            $this->table,
            implode(' OR ', $where)
        );
        return $this->query($sql, $params)->fetchAll('attribute_id');
    }
}
