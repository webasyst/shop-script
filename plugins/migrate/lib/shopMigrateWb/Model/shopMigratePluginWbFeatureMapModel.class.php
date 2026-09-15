<?php

class shopMigratePluginWbFeatureMapModel extends shopMigratePluginWbModel
{
    protected $table = 'shop_migrate_wb_feature_map';

    public function saveMapping($snapshot_id, $subject_id, $characteristic_id, array $data)
    {
        $now = date('Y-m-d H:i:s');
        $row = array(
            'snapshot_id'       => (int) $snapshot_id,
            'subject_id'        => (int) $subject_id,
            'characteristic_id' => (int) $characteristic_id,
            'mode'              => (string) ifset($data['mode'], 'auto'),
            'action'            => (string) ifset($data['action'], 'auto'),
            'shop_feature_id'   => isset($data['shop_feature_id']) && $data['shop_feature_id'] !== '' ? (int) $data['shop_feature_id'] : null,
            'shop_feature_code' => isset($data['shop_feature_code']) ? (string) $data['shop_feature_code'] : null,
            'created_at'        => $now,
            'updated_at'        => $now,
        );
        $this->multipleInsert(array($row), array(
            'mode', 'action', 'shop_feature_id', 'shop_feature_code', 'updated_at',
        ));
    }

    public function getMap($snapshot_id)
    {
        $rows = $this->select('*')->where('snapshot_id = ?', (int) $snapshot_id)->fetchAll();
        $map = array();
        foreach ($rows as $row) {
            $map[$row['subject_id'].':'.$row['characteristic_id']] = $row;
        }
        return $map;
    }

    public function saveAuto($snapshot_id, $subject_id, $characteristic_id, array $data)
    {
        $this->saveMapping($snapshot_id, $subject_id, $characteristic_id, $data);
    }
}
