<?php

abstract class shopMigrateOzonModel extends waModel
{
    public function deleteBySnapshot($snapshot_id)
    {
        $snapshot_id = (int) $snapshot_id;
        if ($snapshot_id <= 0) {
            return;
        }
        if ($this->fieldExists('snapshot_id')) {
            $this->deleteByField('snapshot_id', $snapshot_id);
        }
    }

    public function fieldExists($field)
    {
        static $cache = array();
        if (!isset($cache[$this->table])) {
            $cache[$this->table] = array_map('strtolower', array_keys($this->describe()));
        }
        return in_array(strtolower($field), $cache[$this->table], true);
    }
}
