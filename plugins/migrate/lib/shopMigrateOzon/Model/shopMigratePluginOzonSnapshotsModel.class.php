<?php

class shopMigratePluginOzonSnapshotsModel extends shopMigratePluginOzonModel
{
    protected $table = 'shop_migrate_ozon_snapshots';
    const JSON_ENCODE_OPTIONS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

    public function create($status = 'draft', array $meta = array())
    {
        $now = date('Y-m-d H:i:s');
        $data = array(
            'status'     => (string) $status,
            'meta'       => $this->encodeMeta($meta),
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
            $data['meta'] = $this->encodeMeta($meta);
        }
        $this->updateById((int) $snapshot_id, $data);
    }

    private function encodeMeta(array $meta = array())
    {
        if (!$meta) {
            return null;
        }
        $encoded = json_encode($meta, self::JSON_ENCODE_OPTIONS);
        return $encoded === false ? null : $encoded;
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

    public function getLastBuilding()
    {
        return $this->select('*')
            ->where('status = ?', 'building')
            ->order('id DESC')
            ->limit(1)
            ->fetchAssoc();
    }

    public function decodeMeta(array $snapshot)
    {
        if (empty($snapshot['meta']) || !is_string($snapshot['meta'])) {
            return array();
        }
        $meta = json_decode($snapshot['meta'], true);
        return is_array($meta) ? $meta : array();
    }
}

