<?php

class shopMigratePluginOzonStockMapModel extends shopMigratePluginOzonModel
{
    protected $table = 'shop_migrate_ozon_stock_map';

    public function saveAuto($snapshot_id, $warehouse_id, array $data)
    {
        $now = date('Y-m-d H:i:s');
        $row = array(
            'snapshot_id'  => (int) $snapshot_id,
            'warehouse_id' => (int) $warehouse_id,
            'mode'         => (string) ifset($data['mode'], 'auto'),
            'shop_stock_id'=> isset($data['shop_stock_id']) ? (int) $data['shop_stock_id'] : null,
            'action'       => (string) ifset($data['action'], 'auto'),
            'created_at'   => $now,
            'updated_at'   => $now,
        );
        $this->multipleInsert(array($row), array('mode', 'shop_stock_id', 'action', 'updated_at'));
    }

    public function getMap($snapshot_id)
    {
        return $this->select('*')
            ->where('snapshot_id = ?', (int) $snapshot_id)
            ->fetchAll('warehouse_id');
    }
}
