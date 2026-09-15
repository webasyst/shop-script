<?php

class shopMigratePluginWbPricesModel extends shopMigratePluginWbModel
{
    protected $table = 'shop_migrate_wb_prices';

    public function addBatch($snapshot_id, array $prices)
    {
        $rows = array();
        foreach ($prices as $price) {
            $nm_id = (int) ifset($price['nm_id'], 0);
            if ($nm_id <= 0) {
                continue;
            }
            $rows[] = array(
                'snapshot_id'          => (int) $snapshot_id,
                'nm_id'                => $nm_id,
                'chrt_id'              => (int) ifset($price['chrt_id'], 0),
                'tech_size'            => (string) ifset($price['tech_size'], ''),
                'currency'             => (string) ifset($price['currency'], 'RUB'),
                'price'                => $this->decimalOrNull(ifset($price['price'])),
                'discounted_price'     => $this->decimalOrNull(ifset($price['discounted_price'])),
                'club_discounted_price'=> $this->decimalOrNull(ifset($price['club_discounted_price'])),
                'details'              => $this->encodeJson(ifset($price['details'], array())),
            );
        }
        if ($rows) {
            $this->multipleInsert($rows, array(
                'currency', 'price', 'discounted_price', 'club_discounted_price', 'details',
            ));
        }
    }

    public function getByNmIds($snapshot_id, array $nm_ids)
    {
        $nm_ids = array_values(array_unique(array_filter(array_map('intval', $nm_ids))));
        if (!$nm_ids) {
            return array();
        }
        $sql = "SELECT * FROM {$this->table} WHERE snapshot_id = ? AND nm_id IN ("
            .implode(',', array_fill(0, count($nm_ids), '?')).') ORDER BY nm_id, chrt_id';
        return $this->query($sql, array_merge(array((int) $snapshot_id), $nm_ids))->fetchAll();
    }

    public function getByNmAndChrt($snapshot_id, $nm_id, $chrt_id = 0)
    {
        return $this->select('*')
            ->where(
                'snapshot_id = ? AND nm_id = ? AND chrt_id = ?',
                (int) $snapshot_id,
                (int) $nm_id,
                (int) $chrt_id
            )
            ->fetchAssoc();
    }

    public function countBySnapshot($snapshot_id)
    {
        return (int) $this->select('COUNT(*)')->where('snapshot_id = ?', (int) $snapshot_id)->fetchField();
    }

    private function decimalOrNull($value)
    {
        return $value === null || $value === '' ? null : (string) $value;
    }
}
