<?php

class shopMigratePluginWbRunsModel extends shopMigratePluginWbModel
{
    protected $table = 'shop_migrate_wb_runs';

    public function createRun($snapshot_id, $kind, array $options = array())
    {
        $now = date('Y-m-d H:i:s');
        return (int) $this->insert(array(
            'snapshot_id'     => (int) $snapshot_id,
            'kind'            => (string) $kind,
            'status'          => 'running',
            'cursor'          => 0,
            'current_group_id'=> 0,
            'image_offset'    => 0,
            'cancel_requested'=> 0,
            'options'         => $this->encodeJson($options),
            'counters'        => $this->encodeJson(array()),
            'error'           => null,
            'created_at'      => $now,
            'updated_at'      => $now,
            'finished_at'     => null,
        ));
    }

    public function saveState($run_id, array $state)
    {
        $allowed = array('status', 'cursor', 'current_group_id', 'image_offset', 'cancel_requested', 'error');
        $data = array('updated_at' => date('Y-m-d H:i:s'));
        foreach ($allowed as $field) {
            if (array_key_exists($field, $state)) {
                $data[$field] = $state[$field];
            }
        }
        foreach (array('options', 'counters') as $field) {
            if (array_key_exists($field, $state)) {
                $data[$field] = $this->encodeJson($state[$field]);
            }
        }
        if (isset($state['status']) && in_array($state['status'], array('completed', 'failed', 'cancelled'), true)) {
            $data['finished_at'] = date('Y-m-d H:i:s');
        }
        $this->updateById((int) $run_id, $data);
    }

    public function updateRun($run_id, array $state)
    {
        $this->saveState($run_id, $state);
    }

    public function getByIdSafe($run_id)
    {
        return $this->getById((int) $run_id);
    }

    public function requestCancel($run_id)
    {
        $this->updateById((int) $run_id, array(
            'cancel_requested' => 1,
            'updated_at'       => date('Y-m-d H:i:s'),
        ));
    }

    public function getActive($snapshot_id, $kind)
    {
        return $this->select('*')
            ->where(
                "snapshot_id = ? AND kind = ? AND status IN ('running', 'cancel_requested')",
                (int) $snapshot_id,
                (string) $kind
            )
            ->order('id DESC')->limit(1)->fetchAssoc();
    }

    public function decodeJsonField(array $run, $field)
    {
        $value = isset($run[$field]) ? json_decode((string) $run[$field], true) : array();
        return is_array($value) ? $value : array();
    }
}
