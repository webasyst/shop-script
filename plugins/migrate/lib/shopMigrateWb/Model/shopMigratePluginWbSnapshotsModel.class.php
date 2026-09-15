<?php

class shopMigratePluginWbSnapshotsModel extends shopMigratePluginWbModel
{
    protected $table = 'shop_migrate_wb_snapshots';

    public function createBuilding(array $meta, $phase = 'cards')
    {
        $now = date('Y-m-d H:i:s');
        return (int) $this->insert(array(
            'status'     => 'building',
            'phase'      => (string) $phase,
            'meta'       => $this->encodeJson($meta),
            'created_at' => $now,
            'updated_at' => $now,
        ));
    }

    public function updateState($snapshot_id, $status, $phase, array $meta)
    {
        $this->updateById((int) $snapshot_id, array(
            'status'     => (string) $status,
            'phase'      => (string) $phase,
            'meta'       => $this->encodeJson($meta),
            'updated_at' => date('Y-m-d H:i:s'),
        ));
    }

    public function getByIdSafe($snapshot_id)
    {
        return $this->getById((int) $snapshot_id);
    }

    public function getLastByStatus($status)
    {
        return $this->select('*')
            ->where('status = ?', (string) $status)
            ->order('id DESC')
            ->limit(1)
            ->fetchAssoc();
    }

    public function getLatestReady()
    {
        return $this->getLastByStatus('ready');
    }

    public function decodeMeta(array $snapshot)
    {
        if (empty($snapshot['meta'])) {
            return array();
        }
        $meta = json_decode((string) $snapshot['meta'], true);
        return is_array($meta) ? $meta : array();
    }
}
