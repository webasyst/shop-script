<?php

class shopMigratePluginWbAttributeValuesModel extends shopMigratePluginWbModel
{
    protected $table = 'shop_migrate_wb_attribute_values';

    public function addBatch($snapshot_id, array $values)
    {
        $rows = array();
        foreach ($values as $value) {
            $nm_id = (int) ifset($value['nm_id'], 0);
            $characteristic_id = (int) ifset($value['characteristic_id'], 0);
            if ($nm_id <= 0 || $characteristic_id <= 0) {
                continue;
            }
            $actual_value = ifset($value['value']);
            if (is_array($actual_value) || is_object($actual_value)) {
                $actual_value = $this->encodeJson($actual_value);
            } elseif ($actual_value !== null) {
                $actual_value = (string) $actual_value;
            }
            $rows[] = array(
                'snapshot_id'      => (int) $snapshot_id,
                'nm_id'            => $nm_id,
                'subject_id'       => (int) ifset($value['subject_id'], 0),
                'characteristic_id'=> $characteristic_id,
                'name'             => (string) ifset($value['name'], ''),
                'value'            => $actual_value,
            );
        }
        if ($rows) {
            $this->multipleInsert($rows, array('subject_id', 'name', 'value'));
        }
    }

    public function getByNmIds($snapshot_id, array $nm_ids)
    {
        $nm_ids = array_values(array_unique(array_filter(array_map('intval', $nm_ids))));
        if (!$nm_ids) {
            return array();
        }
        $sql = "SELECT * FROM {$this->table} WHERE snapshot_id = ? AND nm_id IN ("
            .implode(',', array_fill(0, count($nm_ids), '?')).') ORDER BY nm_id, characteristic_id';
        return $this->query($sql, array_merge(array((int) $snapshot_id), $nm_ids))->fetchAll();
    }

    public function getGroupedByNmIds($snapshot_id, array $nm_ids)
    {
        $grouped = array();
        foreach ($this->getByNmIds($snapshot_id, $nm_ids) as $row) {
            $grouped[$row['nm_id']][$row['characteristic_id']] = $row;
        }
        return $grouped;
    }

    public function countBySnapshot($snapshot_id)
    {
        return (int) $this->select('COUNT(*)')->where('snapshot_id = ?', (int) $snapshot_id)->fetchField();
    }
}
