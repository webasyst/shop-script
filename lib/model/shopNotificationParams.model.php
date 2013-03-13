<?php

class shopNotificationParamsModel extends waModel
{
    protected $table = 'shop_notification_params';

    public function save($id, $params)
    {
        $data = array();
        foreach ($params as $name => $value) {
            $data[] = array(
                'notification_id' => $id,
                'name' => $name,
                'value' => $value
            );
        }
        // remove old params
        $this->deleteByField('notification_id', $id);
        // insert new params
        return $this->multipleInsert($data);
    }

    public function getParams($id)
    {
        $sql = "SELECT name, value FROM ".$this->table." WHERE notification_id = i:0";
        return $this->query($sql, $id)->fetchAll('name', true);
    }
}
