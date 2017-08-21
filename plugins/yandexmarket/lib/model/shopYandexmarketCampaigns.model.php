<?php

class shopYandexmarketCampaignsModel extends waModel
{
    protected $table = 'shop_yandexmarket_campaigns';
    protected $id = array('id', 'name');

    protected static $settings = array();

    public function set($campaign_id, $name, $value = null)
    {
        if (is_array($name)) {
            foreach ($name as $_name => $value) {
                $this->set($campaign_id, $_name, $value);
            }
        } else {
            if (isset(self::$settings[$campaign_id])) {
                self::$settings[$campaign_id][$name] = $value;
            }
            $data['id'] = $campaign_id;
            $data['name'] = $name;
            $data['value'] = is_array($value) ? json_encode($value) : $value;
            $this->insert($data, true);
        }
    }

    public function get($campaign_id, $name = null, $default = null)
    {
        if (!isset(self::$settings[$campaign_id])) {
            $where = $this->getWhereByField('id', $campaign_id);
            self::$settings[$campaign_id] = $this->select('name, value')->where($where)->fetchAll('name', true);
            foreach (self::$settings[$campaign_id] as & $value) {
                if (in_array(substr($value, 0, 1), array('[', '{'))) {
                    if (method_exists('shopHelper', 'jsonDecode')) {
                        $json = shopHelper::jsonDecode($value, true);
                    } else {
                        $json = json_decode($value, true);
                    }

                    if (is_array($json)) {
                        $value = $json;
                    }
                }
            }
            unset($value);
            if (empty(self::$settings[$campaign_id])) {
                self::$settings[$campaign_id] = self::getDefaults();
            }
        }
        return ($name === null) ? self::$settings[$campaign_id] : (isset(self::$settings[$campaign_id][$name]) ? self::$settings[$campaign_id][$name] : $default);
    }

    public function del($campaign_id, $name)
    {
        $params = array('id' => $campaign_id);
        if ($name === null) {
            if (isset(self::$settings[$campaign_id])) {
                unset(self::$settings[$campaign_id]);
            }
        } else {
            if (isset(self::$settings[$campaign_id][$name])) {
                unset(self::$settings[$campaign_id][$name]);
            }
            $params['name'] = $name;
        }
        return $this->deleteByField($params);
    }

    public static function getDefaults()
    {
        return array(
            'order_before_mode'   => 'generic',
            'order_before'        => 20,
            'payment'             => array(
                'CASH_ON_DELIVERY' => true,
            ),
            'delivery'            => true,
            'local_delivery_only' => true,
        );
    }
}
