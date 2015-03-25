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

    public function getActionTransportsBySource($source)
    {
        $sql = "SELECT n.* FROM ".$this->table." n
                JOIN shop_notification_params np ON n.id = np.notification_id
                WHERE n.status = 1 AND (n.source IS NULL OR n.source = s:0) AND np.name = 'to' AND np.value = 'customer'";
        $rows = $this->query($sql, $source);

        $result = array();
        foreach ($rows as $row) {
            if (substr($row['event'], 0, 6) == 'order.') {
                $action = substr($row['event'], 6);
                $result[$action][$row['transport']] = 1;
            }
        }
        return $result;
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