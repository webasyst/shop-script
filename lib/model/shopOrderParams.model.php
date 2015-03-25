<?php

class shopOrderParamsModel extends waModel
{
    protected $table = 'shop_order_params';

    /**
     * Get custom params of order
     * @param array|int $ids order ID
     * @return array params in key=>value format
     */
    public function get($ids, $full = false)
    {
        if (!$ids) {
            return array();
        }
        $params = array();
        $payment = $shipping = array();
        $plugin_model = new shopPluginModel();
        foreach ($this->getByField('order_id', $ids, true) as $p) {
            $params[$p['order_id']][$p['name']] = $p['value'];
            if ($full) {
                if ($p['name'] == 'shipping_id' && $p['value']) {
                    if (!isset($shipping[$p['value']])) {
                        $shipping[$p['value']] = $plugin_model->getById($p['value']);
                    }
                    if (!empty($shipping[$p['value']])) {
                        $params[$p['order_id']]['shipping_description'] = $shipping[$p['value']]['description'];
                    } else {
                        $params[$p['order_id']]['shipping_description'] = '';
                    }
                }
                if ($p['name'] == 'payment_id' && $p['value']) {
                    if (!isset($payment[$p['value']])) {
                        $payment[$p['value']] = $plugin_model->getById($p['value']);
                    }
                    if (!empty($payment[$p['value']])) {
                        $params[$p['order_id']]['payment_description'] = $payment[$p['value']]['description'];
                    } else {
                        $params[$p['order_id']]['payment_description'] = '';
                    }
                }
            }
        }
        if (is_numeric($ids)) {
            $params = isset($params[$ids]) ? $params[$ids] : array();
        } else {
            foreach ($ids as $id) {
                if (!isset($params[$id])) {
                    $params[$id] = array();
                }
            }
        }
        return $params;
    }

    /**
     * Get value of one custom param on order
     * @param int $order_id
     * @param string $name
     */
    public function getOne($order_id, $name)
    {
        $item = $this->getByField(array(
            'order_id' => $order_id,
            'name' => $name
        ));
        return $item ? $item['value'] : null;
    }

    /**
     *
     * @param int $order_id
     * @param string $name
     * @param string $value
     */
    public function setOne($order_id, $name, $value)
    {
        return $this->set($order_id, array($name => $value), false);
    }

    /**
     * Set custom params to order
     *
     * @param int|array $id order ID
     * @param array|null $params key=>value format of array or null (to delete all params assigned to order)
     * @param bool $delete_old
     * @return bool
     */
    public function set($id, $params = array(), $delete_old = true)
    {
        if ($id) {
            $id = (array)$id;

            // remove if params is null
            if (is_null($params)) {
                return $this->deleteByField(array(
                    'order_id' => $id
                ));
            }

            if (empty($params)) {
                return true;
            }

            // old params (candidate to delete)
            $old_params = $this->get($id);

            // accumulate params to add (new params) and update old params
            $add_params = array();
            foreach ($params as $name => $value) {
                foreach ($id as $order_id) {
                    if (isset($old_params[$order_id][$name])) {
                        if ($value === null) {
                            // delete this param
                            $this->deleteByField(array(
                                'order_id' => $id,
                                'name' => $name
                            ));
                        } else {
                            // update old param
                            $this->updateByField(array('order_id' => $id, 'name' => $name), array('value' => $value));
                        }
                        // remove from candidate to delete
                        unset($old_params[$order_id][$name]);
                    } else {
                        // param to add
                        $add_params[] = array(
                            'order_id' => $order_id,
                            'name' => $name,
                            'value' => $value
                        );
                    }
                }
            }

            if ($delete_old) {
                // delete
                foreach ($old_params as $prms) {
                    foreach ($prms as $name => $value) {
                        $this->deleteByField(array(
                            'order_id' => $id,
                            'name' => $name
                        ));
                    }
                }
            }

            // add new params
            if ($add_params) {
                $this->multipleInsert($add_params);
            }

            return true;
        }
        return false;
    }

    public function getAllUtmCampaign()
    {
        return $this->query(
                "SELECT DISTINCT value FROM `{$this->table}` WHERE name = 'utm_campaign'")
                ->fetchAll(null, true);
    }

}