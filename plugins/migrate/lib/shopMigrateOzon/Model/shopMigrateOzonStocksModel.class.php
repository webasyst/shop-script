<?php

class shopMigrateOzonStocksModel extends shopMigrateOzonModel
{
    protected $table = 'shop_migrate_ozon_stocks';

    public function addBatch($snapshot_id, array $stocks)
    {
        if (!$stocks) {
            return;
        }
        $now = date('Y-m-d H:i:s');
        $rows = array();
        foreach ($stocks as $stock) {
            $rows[] = array(
                'snapshot_id'  => (int) $snapshot_id,
                'product_id'   => (int) ifset($stock['product_id']),
                'offer_id'     => (string) ifset($stock['offer_id']),
                'warehouse_id' => (int) ifset($stock['warehouse_id']),
                'quantity'     => (float) ifset($stock['quantity'], 0),
                'updated_at'   => $now,
            );
        }
        $this->multipleInsert($rows, array('offer_id', 'quantity', 'updated_at'));
    }

    public function getAllBySnapshot($snapshot_id)
    {
        return $this->select('*')
            ->where('snapshot_id = ?', (int) $snapshot_id)
            ->fetchAll();
    }

    public function getForProducts($snapshot_id, array $product_ids)
    {
        if (!$product_ids) {
            return array();
        }
        $placeholders = implode(',', array_fill(0, count($product_ids), '?'));
        $sql = sprintf(
            'SELECT * FROM %s WHERE snapshot_id = ? AND product_id IN (%s)',
            $this->table,
            $placeholders
        );
        $params = array_merge(array((int) $snapshot_id), array_map('intval', $product_ids));
        return $this->query($sql, $params)->fetchAll();
    }
}
