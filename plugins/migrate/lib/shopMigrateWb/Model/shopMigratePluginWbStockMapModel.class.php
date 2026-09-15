<?php

class shopMigratePluginWbStockMapModel extends shopMigratePluginWbModel
{
    protected $table = 'shop_migrate_wb_stock_map';

    public function saveMapping($snapshot_id, $warehouse_key, array $data)
    {
        $now = date('Y-m-d H:i:s');
        $row = array(
            'snapshot_id'  => (int) $snapshot_id,
            'warehouse_key'=> (string) $warehouse_key,
            'mode'         => (string) ifset($data['mode'], 'auto'),
            'action'       => (string) ifset($data['action'], 'auto'),
            'shop_stock_id'=> isset($data['shop_stock_id']) && $data['shop_stock_id'] !== '' ? (int) $data['shop_stock_id'] : null,
            'created_at'   => $now,
            'updated_at'   => $now,
        );
        $this->multipleInsert(array($row), array('mode', 'action', 'shop_stock_id', 'updated_at'));
    }

    public function getMap($snapshot_id)
    {
        return $this->select('*')->where('snapshot_id = ?', (int) $snapshot_id)->fetchAll('warehouse_key');
    }

    public function saveAuto($snapshot_id, $warehouse_key, array $data)
    {
        $this->saveMapping($snapshot_id, $warehouse_key, $data);
    }
}
