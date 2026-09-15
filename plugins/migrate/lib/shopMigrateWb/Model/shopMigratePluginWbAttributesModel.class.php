<?php

class shopMigratePluginWbAttributesModel extends shopMigratePluginWbModel
{
    protected $table = 'shop_migrate_wb_attributes';

    public function addBatch($snapshot_id, array $attributes)
    {
        $rows = array();
        foreach ($attributes as $attribute) {
            $subject_id = (int) ifset($attribute['subject_id'], 0);
            $characteristic_id = (int) ifset($attribute['characteristic_id'], 0);
            if ($subject_id <= 0 || $characteristic_id <= 0) {
                continue;
            }
            $rows[] = array(
                'snapshot_id'      => (int) $snapshot_id,
                'subject_id'       => $subject_id,
                'characteristic_id'=> $characteristic_id,
                'name'             => (string) ifset($attribute['name'], ''),
                'type'             => (string) ifset($attribute['type'], ''),
                'unit'             => isset($attribute['unit']) && $attribute['unit'] !== '' ? (string) $attribute['unit'] : null,
                'is_required'      => empty($attribute['is_required']) ? 0 : 1,
                'max_count'        => max(0, (int) ifset($attribute['max_count'], 1)),
                'meta'             => $this->encodeJson(ifset($attribute['meta'], array())),
            );
        }
        if ($rows) {
            $this->multipleInsert($rows, array('name', 'type', 'unit', 'is_required', 'max_count', 'meta'));
        }
    }

    public function getBySubject($snapshot_id, $subject_id)
    {
        return $this->select('*')
            ->where('snapshot_id = ? AND subject_id = ?', (int) $snapshot_id, (int) $subject_id)
            ->order('characteristic_id')
            ->fetchAll('characteristic_id');
    }

    public function getMapBySubject($snapshot_id, array $subject_ids = array())
    {
        $query = $this->select('*')->where('snapshot_id = ?', (int) $snapshot_id);
        if ($subject_ids) {
            $query->where('subject_id IN (i:subject_ids)', array(
                'subject_ids' => array_values(array_unique(array_map('intval', $subject_ids))),
            ));
        }
        $rows = $query->order('subject_id, characteristic_id')->fetchAll();
        $map = array();
        foreach ($rows as $row) {
            $map[$row['subject_id']][$row['characteristic_id']] = $row;
        }
        return $map;
    }

    public function countBySnapshot($snapshot_id)
    {
        return (int) $this->select('COUNT(*)')->where('snapshot_id = ?', (int) $snapshot_id)->fetchField();
    }
}
