<?php

class shopMigratePluginWbSizesModel extends shopMigratePluginWbModel
{
    protected $table = 'shop_migrate_wb_sizes';

    public function addBatch($snapshot_id, array $sizes)
    {
        $rows = array();
        foreach ($sizes as $size) {
            $chrt_id = (int) ifset($size['chrt_id'], 0);
            if ($chrt_id <= 0) {
                continue;
            }
            $rows[] = array(
                'snapshot_id' => (int) $snapshot_id,
                'nm_id'       => (int) ifset($size['nm_id'], 0),
                'chrt_id'     => $chrt_id,
                'tech_size'   => (string) ifset($size['tech_size'], ''),
                'wb_size'     => (string) ifset($size['wb_size'], ''),
                'barcodes'    => $this->encodeJson(ifset($size['barcodes'], array())),
                'details'     => $this->encodeJson(ifset($size['details'], array())),
            );
        }
        if ($rows) {
            $this->multipleInsert($rows, array('nm_id', 'tech_size', 'wb_size', 'barcodes', 'details'));
        }
    }

    public function getChrtIdsAfter($snapshot_id, $after_chrt_id, $limit)
    {
        return $this->select('chrt_id')
            ->where('snapshot_id = ? AND chrt_id > ?', (int) $snapshot_id, (int) $after_chrt_id)
            ->order('chrt_id')
            ->limit(max(1, (int) $limit))
            ->fetchAll(null, true);
    }

    public function getForNmIds($snapshot_id, array $nm_ids)
    {
        $nm_ids = array_values(array_unique(array_filter(array_map('intval', $nm_ids))));
        if (!$nm_ids) {
            return array();
        }
        $sql = "SELECT * FROM {$this->table} WHERE snapshot_id = ? AND nm_id IN ("
            .implode(',', array_fill(0, count($nm_ids), '?')).') ORDER BY chrt_id';
        return $this->query($sql, array_merge(array((int) $snapshot_id), $nm_ids))->fetchAll();
    }

    public function getByNmIds($snapshot_id, array $nm_ids)
    {
        return $this->getForNmIds($snapshot_id, $nm_ids);
    }

    public function getNmMapForChrtIds($snapshot_id, array $chrt_ids)
    {
        $chrt_ids = array_values(array_unique(array_filter(array_map('intval', $chrt_ids))));
        if (!$chrt_ids) {
            return array();
        }
        $sql = "SELECT chrt_id, nm_id FROM {$this->table} WHERE snapshot_id = ? AND chrt_id IN ("
            .implode(',', array_fill(0, count($chrt_ids), '?')).')';
        return $this->query($sql, array_merge(array((int) $snapshot_id), $chrt_ids))->fetchAll('chrt_id', true);
    }

    public function countBySnapshot($snapshot_id)
    {
        return (int) $this->select('COUNT(*)')->where('snapshot_id = ?', (int) $snapshot_id)->fetchField();
    }

    public function countUpToCursor($snapshot_id, $chrt_id)
    {
        $chrt_id = (int) $chrt_id;
        if ($chrt_id <= 0) {
            return 0;
        }
        return (int) $this->select('COUNT(*)')
            ->where('snapshot_id = ? AND chrt_id <= ?', (int) $snapshot_id, $chrt_id)
            ->fetchField();
    }
}
