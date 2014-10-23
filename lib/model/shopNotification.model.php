<?php

class shopNotificationModel extends waModel
{
    protected $table = 'shop_notification';

    public function getByEvent($event, $enabled_only = false)
    {
        if (!$enabled_only) {
            $data = $this->getByField('event', $event, 'id');
        } else {
            $data = $this->getByField(array(
                'event' => $event,
                'status' => 1
            ), 'id');
        }
        if ($data) {
            $params_model = new shopNotificationParamsModel();
            $rows = $params_model->getByField('notification_id', array_keys($data), true);
            foreach ($rows as $row) {
                $data[$row['notification_id']][$row['name']]= $row['value'];
            }
        }
        return $data;
    }
    
    public function getOne($id)
    {
        $data = $this->getById($id);
        if ($data) {
            $params_model = new shopNotificationParamsModel();
            $rows = $params_model->getByField('notification_id', $id, true);
            foreach ($rows as $row) {
                $data[$row['name']]= $row['value'];
            }
        }
        return $data;
    }

    public function delete($id)
    {
        $params_model = new shopNotificationParamsModel();
        if ($params_model->deleteByField('notification_id', $id)) {
            return $this->deleteById($id);
        }
        return false;
    }
}