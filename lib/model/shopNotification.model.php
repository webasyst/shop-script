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
                $data[$row['notification_id']][$row['name']] = $row['value'];
            }

            $sources_model = new shopNotificationSourcesModel();
            $sources = $sources_model->getByField('notification_id', array_keys($data), true);
            foreach ($sources as $source) {
                $data[$source['notification_id']]['sources'][] = isset($source['source']) ? $source['source'] : 'all_sources';
            }
        }
        return $data;
    }

    public function getActionTransportsBySource($source)
    {
        $sql = "SELECT DISTINCT n.* FROM ".$this->table." n
                JOIN shop_notification_params np ON n.id = np.notification_id
                LEFT JOIN shop_notification_sources ns ON n.id = ns.notification_id
                WHERE n.status = 1
                      AND (ns.source = 'all_sources' OR ns.source IS NULL OR ns.source = s:0)
                      AND np.name = 'to' AND np.value = 'customer'";
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
            $sources_model = new shopNotificationSourcesModel();
            $sources = $sources_model->getByField('notification_id', $id, true);
            foreach ($sources as $source) {
                $data['sources'][] = $source['source'];
            }
        }
        return $data;
    }

    public function delete($id)
    {
        $params_model = new shopNotificationParamsModel();
        if ($params_model->deleteByField('notification_id', $id)) {
            $sources_model = new shopNotificationSourcesModel();
            $sources_model->deleteByField('notification_id', $id);
            return $this->deleteById($id);
        }
        return false;
    }

    public function getAllTransportSources()
    {
        $sql = "SELECT DISTINCT ns.source, n.transport, np.value
                FROM shop_notification n
                    JOIN shop_notification_params np
                        ON n.id = np.notification_id
                    JOIN shop_notification_sources ns
                        ON n.id = ns.notification_id
                WHERE np.name = 'from'";
        return $this->query($sql)->fetchAll();
    }
}