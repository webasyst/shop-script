<?php

class shopMigrateOzonTypeMapModel extends shopMigrateOzonModel
{
    protected $table = 'shop_migrate_ozon_type_map';

    public function saveAuto($snapshot_id, $description_category_id, $type_id, array $data)
    {
        $now = date('Y-m-d H:i:s');
        $row = array(
            'snapshot_id'             => (int) $snapshot_id,
            'description_category_id' => (int) $description_category_id,
            'type_id'                 => (int) $type_id,
            'mode'                    => (string) ifset($data['mode'], 'auto'),
            'shop_type_id'            => isset($data['shop_type_id']) ? (int) $data['shop_type_id'] : null,
            'shop_type_name'          => isset($data['shop_type_name']) ? (string) $data['shop_type_name'] : null,
            'created_at'              => $now,
            'updated_at'              => $now,
        );

        $this->multipleInsert(array($row), array('mode', 'shop_type_id', 'shop_type_name', 'updated_at'));
    }

    public function getMap($snapshot_id)
    {
        $rows = $this->select('*')
            ->where('snapshot_id = ?', (int) $snapshot_id)
            ->fetchAll();
        $map = array();
        foreach ($rows as $row) {
            $key = sprintf('%d:%d', $row['description_category_id'], $row['type_id']);
            $map[$key] = $row;
        }
        return $map;
    }
}
