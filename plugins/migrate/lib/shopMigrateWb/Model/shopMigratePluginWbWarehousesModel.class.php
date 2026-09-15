<?php

class shopMigratePluginWbWarehousesModel extends shopMigratePluginWbModel
{
    protected $table = 'shop_migrate_wb_warehouses';

    public function deleteBySource($snapshot_id, $source)
    {
        if ((int) $snapshot_id > 0 && in_array($source, array('seller', 'wb'), true)) {
            $this->deleteByField(array('snapshot_id' => (int) $snapshot_id, 'source' => $source));
        }
    }

    public function copyMissingWbWarehouses($source_snapshot_id, $target_snapshot_id)
    {
        if ((int) $source_snapshot_id <= 0 || (int) $target_snapshot_id <= 0
            || (int) $source_snapshot_id === (int) $target_snapshot_id
        ) {
            return;
        }
        // Keep known warehouses addressable through unavailable FBW snapshots
        // and for zeroing balances on the next complete import. No stock rows
        // are copied: availability is controlled by the target build status.
        // Current names/details win for warehouses present in the new response.
        $this->exec(
            "INSERT IGNORE INTO {$this->table} (snapshot_id, warehouse_key, source, warehouse_id, name, details)
             SELECT i:target_id, warehouse_key, source, warehouse_id, name, details
             FROM {$this->table} WHERE snapshot_id = i:source_id AND source = 'wb'",
            array('source_id' => (int) $source_snapshot_id, 'target_id' => (int) $target_snapshot_id)
        );
    }

    public function addBatch($snapshot_id, array $warehouses)
    {
        $rows = array();
        foreach ($warehouses as $warehouse) {
            $warehouse_key = trim((string) ifset($warehouse['warehouse_key'], ''));
            if ($warehouse_key === '') {
                continue;
            }
            $rows[] = array(
                'snapshot_id'  => (int) $snapshot_id,
                'warehouse_key'=> $warehouse_key,
                'source'       => (string) ifset($warehouse['source'], 'seller'),
                'warehouse_id' => (int) ifset($warehouse['warehouse_id'], 0),
                'name'         => (string) ifset($warehouse['name'], ''),
                'details'      => $this->encodeJson(ifset($warehouse['details'], array())),
            );
        }
        if ($rows) {
            $this->multipleInsert($rows, array('source', 'warehouse_id', 'name', 'details'));
        }
    }

    public function getBySource($snapshot_id, $source)
    {
        return $this->select('*')
            ->where('snapshot_id = ? AND source = ?', (int) $snapshot_id, (string) $source)
            ->order('id')
            ->fetchAll();
    }

    public function getAllBySnapshot($snapshot_id)
    {
        return $this->select('*')
            ->where('snapshot_id = ?', (int) $snapshot_id)
            ->order('source, name')
            ->fetchAll('warehouse_key');
    }

    public function getSourceAtOffset($snapshot_id, $source, $offset)
    {
        $offset = max(0, (int) $offset);
        return $this->select('*')
            ->where('snapshot_id = ? AND source = ?', (int) $snapshot_id, (string) $source)
            ->order('id')
            ->limit($offset.', 1')
            ->fetchAssoc();
    }

    public function countBySnapshot($snapshot_id)
    {
        return (int) $this->select('COUNT(*)')->where('snapshot_id = ?', (int) $snapshot_id)->fetchField();
    }

    public function countBySource($snapshot_id, $source)
    {
        return (int) $this->select('COUNT(*)')
            ->where('snapshot_id = ? AND source = ?', (int) $snapshot_id, (string) $source)
            ->fetchField();
    }
}
