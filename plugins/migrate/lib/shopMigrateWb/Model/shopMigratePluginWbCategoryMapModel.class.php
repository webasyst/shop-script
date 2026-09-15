<?php

class shopMigratePluginWbCategoryMapModel extends shopMigratePluginWbModel
{
    protected $table = 'shop_migrate_wb_category_map';

    public function saveMapping($snapshot_id, $entity_type, $wb_id, $parent_id, array $data)
    {
        $now = date('Y-m-d H:i:s');
        $row = array(
            'snapshot_id'      => (int) $snapshot_id,
            'entity_type'      => (string) $entity_type,
            'wb_id'            => (int) $wb_id,
            'parent_id'        => (int) $parent_id,
            'mode'             => (string) ifset($data['mode'], 'auto'),
            'action'           => (string) ifset($data['action'], 'auto'),
            'shop_category_id' => isset($data['shop_category_id']) && $data['shop_category_id'] !== '' ? (int) $data['shop_category_id'] : null,
            'created_at'       => $now,
            'updated_at'       => $now,
        );
        $this->multipleInsert(array($row), array(
            'parent_id', 'mode', 'action', 'shop_category_id', 'updated_at',
        ));
    }

    public function getMap($snapshot_id)
    {
        $rows = $this->select('*')->where('snapshot_id = ?', (int) $snapshot_id)->fetchAll();
        $map = array();
        foreach ($rows as $row) {
            $map[$row['entity_type'].':'.$row['wb_id']] = $row;
        }
        return $map;
    }

    public function saveAuto($snapshot_id, $entity_type, $wb_id, $parent_id, array $data)
    {
        $this->saveMapping($snapshot_id, $entity_type, $wb_id, $parent_id, $data);
    }
}
