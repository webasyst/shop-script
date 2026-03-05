<?php

class shopMigratePluginOzonCategoryMapModel extends shopMigratePluginOzonModel
{
    protected $table = 'shop_migrate_ozon_category_map';

    public function saveAuto($snapshot_id, $description_category_id, array $data)
    {
        $now = date('Y-m-d H:i:s');
        $row = array(
            'snapshot_id'             => (int) $snapshot_id,
            'description_category_id' => (int) $description_category_id,
            'mode'                    => (string) ifset($data['mode'], 'auto'),
            'shop_category_id'        => isset($data['shop_category_id']) ? (int) $data['shop_category_id'] : null,
            'action'                  => (string) ifset($data['action'], 'auto'),
            'created_at'              => $now,
            'updated_at'              => $now,
        );
        $this->multipleInsert(array($row), array('mode', 'shop_category_id', 'action', 'updated_at'));
    }

    public function getMap($snapshot_id)
    {
        return $this->select('*')
            ->where('snapshot_id = ?', (int) $snapshot_id)
            ->fetchAll('description_category_id');
    }
}
