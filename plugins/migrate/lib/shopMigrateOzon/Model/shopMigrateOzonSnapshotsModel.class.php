<?php

class shopMigrateOzonSnapshotsModel extends shopMigrateOzonModel
{
    protected $table = 'shop_migrate_ozon_snapshots';

    public function create($status = 'draft', array $meta = array())
    {
        $now = date('Y-m-d H:i:s');
        $data = array(
            'status'     => (string) $status,
            'meta'       => $meta ? json_encode($meta) : null,
            'created_at' => $now,
            'updated_at' => $now,
        );

        return (int) $this->insert($data);
    }

    public function updateStatus($snapshot_id, $status, array $meta = null)
    {
        $data = array(
            'status'     => (string) $status,
            'updated_at' => date('Y-m-d H:i:s'),
        );
        if ($meta !== null) {
            $data['meta'] = $meta ? json_encode($meta) : null;
        }
        $this->updateById((int) $snapshot_id, $data);
    }

    public function getByIdSafe($snapshot_id)
    {
        return $this->getById((int) $snapshot_id);
    }

    public function getLastReady()
    {
        return $this->select('*')
            ->where('status = ?', 'ready')
            ->order('id DESC')
            ->limit(1)
            ->fetchAssoc();
    }
}

