<?php

abstract class shopMigratePluginWbModel extends waModel
{
    const JSON_OPTIONS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

    public function deleteBySnapshot($snapshot_id)
    {
        $snapshot_id = (int) $snapshot_id;
        if ($snapshot_id > 0 && $this->hasField('snapshot_id')) {
            $this->deleteByField('snapshot_id', $snapshot_id);
        }
    }

    public function hasField($field)
    {
        static $fields = array();
        if (!isset($fields[$this->table])) {
            $fields[$this->table] = array_map('strtolower', array_keys($this->describe()));
        }
        return in_array(strtolower((string) $field), $fields[$this->table], true);
    }

    protected function encodeJson($value)
    {
        if ($value === null || $value === '') {
            return null;
        }
        $json = json_encode($value, self::JSON_OPTIONS);
        return $json === false ? null : $json;
    }

    protected function normalizeDateTime($value)
    {
        if ($value === null || $value === '') {
            return null;
        }
        $timestamp = is_numeric($value) ? (int) $value : strtotime((string) $value);
        return $timestamp ? date('Y-m-d H:i:s', $timestamp) : null;
    }
}
