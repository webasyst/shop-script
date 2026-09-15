<?php

class shopMigratePluginWbStocksModel extends shopMigratePluginWbModel
{
    protected $table = 'shop_migrate_wb_stocks';

    public function deleteBySource($snapshot_id, $source)
    {
        if ((int) $snapshot_id > 0 && in_array($source, array('seller', 'wb'), true)) {
            $this->exec("DELETE FROM {$this->table} WHERE snapshot_id = i:id AND warehouse_key LIKE s:prefix", array(
                'id' => (int) $snapshot_id,
                'prefix' => $source.':%',
            ));
        }
    }

    public function addBatch($snapshot_id, array $stocks)
    {
        $rows = array();
        foreach ($stocks as $stock) {
            $warehouse_key = trim((string) ifset($stock['warehouse_key'], ''));
            $chrt_id = (int) ifset($stock['chrt_id'], 0);
            if ($warehouse_key === '' || $chrt_id <= 0) {
                continue;
            }
            $rows[] = array(
                'snapshot_id'       => (int) $snapshot_id,
                'warehouse_key'     => $warehouse_key,
                'nm_id'             => (int) ifset($stock['nm_id'], 0),
                'chrt_id'           => $chrt_id,
                'quantity'          => (string) ifset($stock['quantity'], 0),
                'in_way_to_client'   => (string) ifset($stock['in_way_to_client'], 0),
                'in_way_from_client' => (string) ifset($stock['in_way_from_client'], 0),
                'details'           => $this->encodeJson(ifset($stock['details'], array())),
            );
        }
        if ($rows) {
            $this->multipleInsert($rows, array(
                'nm_id', 'quantity', 'in_way_to_client', 'in_way_from_client', 'details',
            ));
        }
    }

    public function countBySnapshot($snapshot_id)
    {
        return (int) $this->select('COUNT(*)')->where('snapshot_id = ?', (int) $snapshot_id)->fetchField();
    }

    public function getByChrtIds($snapshot_id, array $chrt_ids)
    {
        $chrt_ids = array_values(array_unique(array_filter(array_map('intval', $chrt_ids))));
        if (!$chrt_ids) {
            return array();
        }
        $sql = "SELECT * FROM {$this->table} WHERE snapshot_id = ? AND chrt_id IN ("
            .implode(',', array_fill(0, count($chrt_ids), '?')).') ORDER BY chrt_id, warehouse_key';
        return $this->query($sql, array_merge(array((int) $snapshot_id), $chrt_ids))->fetchAll();
    }
}
