<?php

class shopMigratePluginOzonWarehousesModel extends shopMigratePluginOzonModel
{
    protected $table = 'shop_migrate_ozon_warehouses';

    public function addBatch($snapshot_id, array $warehouses)
    {
        if (!$warehouses) {
            return;
        }
        $now = date('Y-m-d H:i:s');
        $rows = array();
        foreach ($warehouses as $warehouse) {
            $rows[] = array(
                'snapshot_id'  => (int) $snapshot_id,
                'warehouse_id' => (int) ifset($warehouse['warehouse_id']),
                'name'         => (string) ifset($warehouse['name'], ''),
                'type'         => (string) ifset($warehouse['type'], ''),
                'created_at'   => $now,
            );
        }

        $this->multipleInsert($rows, array('name', 'type'));
    }

    public function getAllBySnapshot($snapshot_id)
    {
        return $this->select('*')
            ->where('snapshot_id = ?', (int) $snapshot_id)
            ->order('name ASC')
            ->fetchAll('warehouse_id');
    }
}
