<?php

class shopOrderLogModel extends waModel implements shopOrderStorageInterface
{
    protected $table = 'shop_order_log';

    public function add($data)
    {
        $data['datetime'] = date('Y-m-d H:i:s');
        if (!isset($data['contact_id'])) {
            $data['contact_id'] = wa()->getUser()->getId();
        }
        if (isset($data['id'])) {
            unset($data['id']);
        }
        $log_id = $this->insert($data);
        if (!empty($data['params'])) {
            $params_model = new shopOrderLogParamsModel();
            $order_id = $data['order_id'];
            $params = array();
            foreach ($data['params'] as $name => $value) {
                $params[] = array(
                    'order_id' => $order_id,
                    'log_id'   => $log_id,
                    'name'     => $name,
                    'value'    => $value,
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
        $data = $this->query($sql, array('order_id' => $order_id))->fetchAll('id');
        foreach ($data as &$row) {
            $contact = array(
                'firstname'  => $row['contact_firstname'],
                'middlename' => $row['contact_middlename'],
                'lastname'   => $row['contact_lastname'],
            );
            $row['contact_name'] = waContactNameField::formatName($contact);
            $row['params'] = array();
        }
        unset($row);

        $log_params_model = new shopOrderLogParamsModel();
        $params = $log_params_model->getByField('order_id', $order_id, true);
        foreach ($params as $p) {
            if (!isset($data[$p['log_id']])) {
                continue;
            }
            $data[$p['log_id']]['params'][$p['name']] = $p['value'];
        }

        return array_values($data);
    }

    public function getPreviousState($order_id, &$params = null)
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

    public function getData(shopOrder $order)
    {
        $log = $this->getLog($order->id);
        $plugins = null;
        $root_url = wa()->getRootUrl();
        $workflow = new shopWorkflow();
        foreach ($log as &$l) {
            if ($l['action_id']) {
                $l['action'] = $workflow->getActionById($l['action_id']);

                if (!empty($l['text']) && ($l['action_id'] == 'callback') && strpos($l['text'], ' ')) {
                    $type = 'payment';
                    $chunks = explode(' ', $l['text'], 2);
                    $l['plugin'] = ifset($chunks[0]);
                    $l['text'] = $chunks[1];
                    if ($l['plugin']) {
                        if (preg_match('@^(shop|payment|shipping):(\w+)$@', $l['plugin'], $matches)) {
                            $type = $matches[1];
                            $l['plugin'] = $matches[2];
                        }
                        $info = array();
                        switch ($type) {
                            case 'payment':
                                $info = shopPayment::getPluginInfo($l['plugin']);
                                break;
                            case 'shipping':
                                $info = shopShipping::getPluginInfo($l['plugin']);
                                break;
                            case 'shop':
                                if ($plugins === null) {
                                    /** @var shopConfig $config */
                                    $config = wa('shop')->getConfig();
                                    $plugins = $config->getPlugins();
                                }
                                $info = ifset($plugins[$l['plugin']]);
                                break;
                        }

                        $l['plugin'] = ifset($info['name'], $l['plugin']);
                        $l['plugin_icon_url'] = ifset($info['icon'][16], ifset($info['img']));
                        if (($root_url !== '/')
                            && !empty($l['plugin_icon_url'])
                            && (strpos($l['plugin_icon_url'], $root_url) !== 0)
                        ) {
                            $l['plugin_icon_url'] = $root_url.$l['plugin_icon_url'];
                        }

                    }
                }

            }
            if ($order->state_id == $l['after_state_id']) {
                $params = $order->params;
                $params['last_action_datetime'] = $l['datetime'];
                $order->params = $params;
            }
            unset($l);
        }
        return $log;
    }
}
