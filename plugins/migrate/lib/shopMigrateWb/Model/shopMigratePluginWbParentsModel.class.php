<?php

class shopMigratePluginWbParentsModel extends shopMigratePluginWbModel
{
    protected $table = 'shop_migrate_wb_parents';

    public function addBatch($snapshot_id, array $parents)
    {
        $rows = array();
        foreach ($parents as $parent) {
            $parent_id = (int) ifset($parent['parent_id'], ifset($parent['id'], 0));
            if ($parent_id <= 0) {
                continue;
            }
            $rows[] = array(
                'snapshot_id' => (int) $snapshot_id,
                'parent_id'   => $parent_id,
                'name'        => (string) ifset($parent['name'], ''),
                'is_visible'  => !isset($parent['is_visible']) || $parent['is_visible'] ? 1 : 0,
            );
        }
        if ($rows) {
            $this->multipleInsert($rows, array('name', 'is_visible'));
        }
    }

    public function getAllBySnapshot($snapshot_id)
    {
        return $this->select('*')->where('snapshot_id = ?', (int) $snapshot_id)->order('name')->fetchAll('parent_id');
    }

    public function countBySnapshot($snapshot_id)
    {
        return (int) $this->select('COUNT(*)')->where('snapshot_id = ?', (int) $snapshot_id)->fetchField();
    }
}
