<?php

class shopMigratePluginWbTypeMapModel extends shopMigratePluginWbModel
{
    protected $table = 'shop_migrate_wb_type_map';

    public function saveMapping($snapshot_id, $parent_id, $subject_id, array $data)
    {
        $now = date('Y-m-d H:i:s');
        $row = array(
            'snapshot_id'   => (int) $snapshot_id,
            'parent_id'     => (int) $parent_id,
            'subject_id'    => (int) $subject_id,
            'mode'          => (string) ifset($data['mode'], 'auto'),
            'action'        => (string) ifset($data['action'], 'auto'),
            'shop_type_id'  => isset($data['shop_type_id']) && $data['shop_type_id'] !== '' ? (int) $data['shop_type_id'] : null,
            'shop_type_name'=> isset($data['shop_type_name']) ? (string) $data['shop_type_name'] : null,
            'created_at'    => $now,
            'updated_at'    => $now,
        );
        $this->multipleInsert(array($row), array(
            'parent_id', 'mode', 'action', 'shop_type_id', 'shop_type_name', 'updated_at',
        ));
    }

    public function getMap($snapshot_id)
    {
        return $this->select('*')->where('snapshot_id = ?', (int) $snapshot_id)->fetchAll('subject_id');
    }

    public function saveAuto($snapshot_id, $parent_id, $subject_id, array $data)
    {
        $this->saveMapping($snapshot_id, $parent_id, $subject_id, $data);
    }
}
