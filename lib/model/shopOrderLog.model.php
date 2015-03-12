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
        $sql = "SELECT l.*,
                c.firstname AS contact_firstname,
                c.middlename AS contact_middlename,
                c.lastname AS contact_lastname,
                c.photo AS contact_photo
            FROM ".$this->table." l
                LEFT JOIN wa_contact c ON l.contact_id = c.id
                WHERE l.order_id = i:order_id
                ORDER BY id DESC";
        $data = $this->query($sql, array('order_id' => $order_id))->fetchAll();
        foreach ($data as &$row) {
            $contact = array(
                'firstname' => $row['contact_firstname'],
                'middlename' => $row['contact_middlename'],
                'lastname' => $row['contact_lastname']
            );
            $row['contact_name'] = waContactNameField::formatName($contact);
        }
        unset($row);
        return $data;
    }

    public function getPreviousState($order_id, &$params=null)
    {
        $sql = "SELECT id, before_state_id FROM ".$this->table." WHERE order_id = i:id AND
            before_state_id != after_state_id
            ORDER BY id DESC LIMIT 1";
        $row = $this->query($sql, array('id' => $order_id))->fetchAssoc();

        // Load additional data from shop_order_log_params
        if (func_num_args() > 1) {
            $sql = "SELECT name, value FROM shop_order_log_params WHERE log_id=?";
            $params = $this->query($sql, $row['id'])->fetchAll('name', true);
        }

        return $row['before_state_id'];
    }
}
