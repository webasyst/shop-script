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
                    'value'    => is_array($value) ? waUtils::jsonEncode($value) : $value,
                );
            }
            $params_model->multipleInsert($params);
        }
        return $log_id;
    }

    /**
     * @param int $order_id
     * @return array
     * @throws waException
     */
    public function getLog($order_id)
    {
        $data = $this->getLogItems("l.order_id = i:order_id", array(
            'order_id' => $order_id,
        ));
        return array_values($data);
    }

    /**
     * @param int|int[] $log_id
     * @return array
     * @throws waException
     */
    public function getLogById($log_id)
    {
        if (is_scalar($log_id)) {
            $log_id = (int)$log_id;
            $log_ids = (array)$log_id;
        } elseif (is_array($log_id)) {
            $log_ids = $log_id;
        } else {
            $log_ids = array();
        }

        $log_ids = array_map('intval', $log_ids);
        $log_ids = array_filter($log_ids, function ($log_id) {
            return $log_id > 0;
        });
        $log_ids = array_unique($log_ids);
        if (!$log_ids) {
            return array();
        }

        return $this->getLogItems('l.id IN(:ids)', array(
            'ids' => $log_ids,
        ));
    }

    /**
     * @param       $where
     * @param array $bind_params
     * @return array
     * @throws waException
     */
    protected function getLogItems($where, $bind_params = array())
    {
        $sql = "SELECT l.*,
                c.firstname AS contact_firstname,
                c.middlename AS contact_middlename,
                c.lastname AS contact_lastname,
                c.photo AS contact_photo
            FROM `{$this->table}` l
                LEFT JOIN wa_contact c ON l.contact_id = c.id
                WHERE {$where}
                ORDER BY id DESC";

        $data = $this->query($sql, $bind_params)->fetchAll('id');
        if (!$data) {
            return array();
        }

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
        $params = $log_params_model->getByField('log_id', array_keys($data), true);
        foreach ($params as $p) {
            if (!isset($data[$p['log_id']])) {
                continue;
            }
            $data[$p['log_id']]['params'][$p['name']] = $p['value'];
        }

        return $data;
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
        self::explainLog($log);
        foreach ($log as $l) {
            if ($order->state_id == $l['after_state_id']) {
                $params = $order->params;
                $params['last_action_datetime'] = $l['datetime'];
                $order->params = $params;
            }
        }
        return $log;
    }

    /**
     * @param array &$log
     * @throws waException
     */
    public static function explainLog(&$log)
    {
        $plugins = null;
        $workflow = new shopWorkflow();
        $root_url = wa()->getRootUrl();

        $log = is_array($log) ? $log : array();

        $stock_names = array();

        foreach ($log as &$l) {
            if (!$l['action_id']) {
                continue;
            }

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

            if (!empty($l['params']['refund_items'])) {
                try {
                    $l['params']['refund_items'] = waUtils::jsonDecode($l['params']['refund_items'], true);
                } catch (waException $ex) {
                    $l['params']['refund_items'] = [];
                }
            }

            if (!empty($l['params']['return_stock'])) {
                $stock_id = intval($l['params']['return_stock']);
                if (!isset($stock_names[$stock_id])) {
                    $stock_names[$stock_id] = $stock_id;
                }
                $l['params']['return_stock_name'] =& $stock_names[$stock_id];
            }
        }

        if ($stock_names) {
            $model = new shopStockModel();
            $stocks = $model->getById(array_keys($stock_names));
            foreach ($stocks as $stock_id => $stock) {
                $stock_names[$stock_id] = $stock['name'];
            }
        }

        unset($l);
    }
}
