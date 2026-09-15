<?php

class shopMigratePluginWbSubjectsModel extends shopMigratePluginWbModel
{
    protected $table = 'shop_migrate_wb_subjects';

    public function addBatch($snapshot_id, array $subjects)
    {
        $rows = array();
        foreach ($subjects as $subject) {
            $subject_id = (int) ifset($subject['subject_id'], ifset($subject['id'], 0));
            if ($subject_id <= 0) {
                continue;
            }
            $rows[] = array(
                'snapshot_id'   => (int) $snapshot_id,
                'subject_id'    => $subject_id,
                'parent_id'     => (int) ifset($subject['parent_id'], 0),
                'name'          => (string) ifset($subject['name'], ''),
                'parent_name'   => (string) ifset($subject['parent_name'], ''),
                'products_count'=> (int) ifset($subject['products_count'], 0),
            );
        }
        if ($rows) {
            $this->multipleInsert($rows, array('parent_id', 'name', 'parent_name', 'products_count'));
        }
    }

    public function getIdsAfter($snapshot_id, $after_id, $limit)
    {
        return $this->select('subject_id')
            ->where(
                'snapshot_id = ? AND products_count > 0 AND subject_id > ?',
                (int) $snapshot_id,
                (int) $after_id
            )
            ->order('subject_id')
            ->limit(max(1, (int) $limit))
            ->fetchAll(null, true);
    }

    public function refreshProductCounts($snapshot_id)
    {
        $this->exec(
            "UPDATE {$this->table} s
             LEFT JOIN (
                 SELECT subject_id, COUNT(*) AS cnt
                 FROM shop_migrate_wb_cards
                 WHERE snapshot_id = i:snapshot_id
                 GROUP BY subject_id
             ) c ON c.subject_id = s.subject_id
             SET s.products_count = COALESCE(c.cnt, 0)
             WHERE s.snapshot_id = i:snapshot_id",
            array('snapshot_id' => (int) $snapshot_id)
        );
    }

    public function getAllBySnapshot($snapshot_id)
    {
        return $this->select('*')->where('snapshot_id = ?', (int) $snapshot_id)->order('parent_name, name')->fetchAll('subject_id');
    }

    public function countBySnapshot($snapshot_id)
    {
        return (int) $this->select('COUNT(*)')->where('snapshot_id = ?', (int) $snapshot_id)->fetchField();
    }
}
