<?php

class shopTypeServicesModel extends waModel
{
    protected $table = 'shop_type_services';

    public function getTypes($service_id, $just_own = false)
    {
        $service_id = (int)$service_id;
        $sql = "SELECT t.* ".(!$just_own ? ', ts.type_id' : '')."
                FROM `shop_type` t ".(!$just_own ? 'LEFT' : '')."
                JOIN `{$this->table}` ts ON t.id = ts.type_id AND service_id = $service_id
                ORDER BY t.sort
        ";
        return $this->query($sql)->fetchAll();
    }

    public function getByType($type_id)
    {
        $sql = "SELECT s.* FROM ".$this->table." ts JOIN shop_service s ON ts.service_id = s.id
        WHERE ts.type_id = i:type_id";
        $services = $this->query($sql, array('type_id' => $type_id))->fetchAll('id');
        if (!$services) {
            return array();
        }
        return $services;
    }

    public function getServiceIds($type_id)
    {
        return $this->select('service_id')->where('type_id = ?', $type_id)->fetchAll(null, true);
    }

}