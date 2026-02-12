<?php

class shopMigrateOzonFeatureMapModel extends shopMigrateOzonModel
{
    protected $table = 'shop_migrate_ozon_feature_map';

    public function saveAuto($snapshot_id, $attribute_id, array $data)
    {
        $now = date('Y-m-d H:i:s');
        $row = array(
            'snapshot_id'      => (int) $snapshot_id,
            'attribute_id'     => (int) $attribute_id,
            'mode'             => (string) ifset($data['mode'], 'auto'),
            'shop_feature_id'  => isset($data['shop_feature_id']) ? (int) $data['shop_feature_id'] : null,
            'shop_feature_code'=> isset($data['shop_feature_code']) ? (string) $data['shop_feature_code'] : null,
            'action'           => (string) ifset($data['action'], 'auto'),
            'created_at'       => $now,
            'updated_at'       => $now,
        );
        $this->multipleInsert(array($row), array('mode', 'shop_feature_id', 'shop_feature_code', 'action', 'updated_at'));
    }

    public function getMap($snapshot_id)
    {
        return $this->select('*')
            ->where('snapshot_id = ?', (int) $snapshot_id)
            ->fetchAll('attribute_id');
    }
}
