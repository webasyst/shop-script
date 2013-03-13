<?php

class shopTypeUpsellingModel extends waModel
{
    protected $table = 'shop_type_upselling';

    public function getByType($type_id)
    {
        $sql = "SELECT ts.*, f.name AS feature_name, f.type AS feature_type FROM ".$this->table.' ts
                LEFT JOIN shop_feature f ON ts.feature_id = f.id
                WHERE ts.type_id = i:id';
        return $this->query($sql, array('id' => $type_id))->fetchAll();
    }
}