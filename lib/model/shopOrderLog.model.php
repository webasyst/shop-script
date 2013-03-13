<?php

class shopOrderLogModel extends waModel
{
    protected $table = 'shop_order_log';

    public function add($data)
    {
        $data['datetime'] = date('Y-m-d H:i:s');
        if (!isset($data['contact_id'])) {
            $data['contact_id'] = wa()->getUser()->getId();
        }
        $log_id = $this->insert($data);
        if (!empty($data['params'])) {
            $params_model = new shopOrderLogParamsModel();
            $order_id = $data['order_id'];
            $params = array();
            foreach ($data['params'] as $name => $value) {
                $params[] = array(
                    'order_id' => $order_id,
                    'log_id' => $log_id,
                    'name' => $name,
                    'value' => $value
                );
            }
            $params_model->multipleInsert($params);
        }
        return $log_id;
    }

    public function getLog($order_id)
    {
        $sql = "SELECT l.*, c.name AS contact_name, c.photo AS contact_photo FROM ".$this->table." l
                LEFT JOIN wa_contact c ON l.contact_id = c.id
                WHERE l.order_id = i:order_id
                ORDER BY id DESC";
        return $this->query($sql, array('order_id' => $order_id))->fetchAll();
    }

    public function getPreviousState($order_id)
    {
        $sql = "SELECT before_state_id FROM ".$this->table." WHERE order_id = i:id ORDER BY id DESC LIMIT 1";
        return $this->query($sql, array('id' => $order_id))->fetchField();
    }
}