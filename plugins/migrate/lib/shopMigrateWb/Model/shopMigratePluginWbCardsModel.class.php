<?php

class shopMigratePluginWbCardsModel extends shopMigratePluginWbModel
{
    protected $table = 'shop_migrate_wb_cards';

    public function addBatch($snapshot_id, array $cards)
    {
        $now = date('Y-m-d H:i:s');
        $rows = array();
        foreach ($cards as $card) {
            $nm_id = (int) ifset($card['nm_id'], 0);
            if ($nm_id <= 0) {
                continue;
            }
            $rows[] = array(
                'snapshot_id'  => (int) $snapshot_id,
                'nm_id'        => $nm_id,
                'imt_id'       => (int) ifset($card['imt_id'], 0),
                'nm_uuid'      => isset($card['nm_uuid']) && $card['nm_uuid'] !== '' ? (string) $card['nm_uuid'] : null,
                'subject_id'   => (int) ifset($card['subject_id'], 0),
                'parent_id'    => (int) ifset($card['parent_id'], 0),
                'vendor_code'  => (string) ifset($card['vendor_code'], ''),
                'brand'        => (string) ifset($card['brand'], ''),
                'name'         => (string) ifset($card['name'], ''),
                'description'  => isset($card['description']) ? (string) $card['description'] : null,
                'details'      => $this->encodeJson(ifset($card['details'], array())),
                'wb_created_at'=> $this->normalizeDateTime(ifset($card['wb_created_at'])),
                'wb_updated_at'=> $this->normalizeDateTime(ifset($card['wb_updated_at'])),
                'created_at'   => $now,
                'updated_at'   => $now,
            );
        }
        if ($rows) {
            $this->multipleInsert($rows, array(
                'imt_id', 'nm_uuid', 'subject_id', 'parent_id', 'vendor_code', 'brand', 'name',
                'description', 'details', 'wb_created_at', 'wb_updated_at', 'updated_at',
            ));
        }
    }

    public function getNmIdsAfter($snapshot_id, $after_nm_id, $limit)
    {
        return $this->select('nm_id')
            ->where('snapshot_id = ? AND nm_id > ?', (int) $snapshot_id, (int) $after_nm_id)
            ->order('nm_id')
            ->limit(max(1, (int) $limit))
            ->fetchAll(null, true);
    }

    public function syncParentIds($snapshot_id)
    {
        $this->exec(
            "UPDATE {$this->table} c
             JOIN shop_migrate_wb_subjects s
               ON s.snapshot_id = c.snapshot_id AND s.subject_id = c.subject_id
             SET c.parent_id = s.parent_id
             WHERE c.snapshot_id = i:snapshot_id",
            array('snapshot_id' => (int) $snapshot_id)
        );
    }

    public function countBySnapshot($snapshot_id)
    {
        return (int) $this->select('COUNT(*)')->where('snapshot_id = ?', (int) $snapshot_id)->fetchField();
    }

    public function countGroupsBySnapshot($snapshot_id)
    {
        return (int) $this->query(
            "SELECT COUNT(DISTINCT IF(imt_id > 0, CONCAT('i:', imt_id), CONCAT('n:', nm_id)))
             FROM {$this->table} WHERE snapshot_id = i:snapshot_id",
            array('snapshot_id' => (int) $snapshot_id)
        )->fetchField();
    }

    public function countGroups($snapshot_id)
    {
        return $this->countGroupsBySnapshot($snapshot_id);
    }

    public function getByGroup($snapshot_id, $group_id)
    {
        $group_id = (int) $group_id;
        if ($group_id <= 0) {
            return array();
        }
        if ($group_id % 2 === 0) {
            $query = $this->select('*')->where(
                'snapshot_id = ? AND imt_id = ?',
                (int) $snapshot_id,
                (int) ($group_id / 2)
            );
        } else {
            $query = $this->select('*')->where(
                'snapshot_id = ? AND imt_id = 0 AND nm_id = ?',
                (int) $snapshot_id,
                (int) (($group_id - 1) / 2)
            );
        }
        return $query->order('nm_id')->fetchAll('nm_id');
    }

    public function nextGroup($snapshot_id, $after_group_id = 0)
    {
        return $this->query(
            "SELECT IF(imt_id > 0, imt_id * 2, nm_id * 2 + 1) AS group_id,
                    MAX(imt_id) AS imt_id,
                    MIN(nm_id) AS nm_id,
                    COUNT(*) AS cards_count
             FROM {$this->table}
             WHERE snapshot_id = i:snapshot_id
             GROUP BY group_id
             HAVING group_id > i:after_group_id
             ORDER BY group_id
             LIMIT 1",
            array(
                'snapshot_id'   => (int) $snapshot_id,
                'after_group_id'=> (int) $after_group_id,
            )
        )->fetchAssoc();
    }

    public function getGroupId(array $card)
    {
        $imt_id = (int) ifset($card['imt_id'], 0);
        return $imt_id > 0
            ? $imt_id * 2
            : (int) ifset($card['nm_id'], 0) * 2 + 1;
    }
}
