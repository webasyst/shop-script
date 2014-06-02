<?php
class shopPluginSettingsModel extends waModel implements waiPluginSettings
{
    protected $table = 'shop_plugin_settings';
    protected $id = array('id', 'name');

    protected static $settings = array();

    public function set($key, $name, $value)
    {
        self::$settings[$name] = $value;
        $data['id'] = $key;
        $data['name'] = $name;
        $data['value'] = is_array($value) ? json_encode($value) : $value;
        $this->insert($data, true);
    }

    public function get($key, $name = null, $default = null)
    {
        if (!isset(self::$settings[$key])) {
            $where = $this->getWhereByField('id', $key);
            self::$settings[$key] = $this->select('name, value')->where($where)->fetchAll('name', true);
            foreach (self::$settings[$key] as & $value) {
                $json = json_decode($value, true);
                if (is_array($json)) {
                    $value = $json;
                }
            }
            unset($value);
        }
        return ($name === null) ? self::$settings[$key] : (isset(self::$settings[$key][$name]) ? self::$settings[$key][$name] : $default);
    }

    public function del($key, $name)
    {
        $params = array('id' => $key);
        if ($name === null) {
            if (isset(self::$settings[$key])) {
                unset(self::$settings[$key]);
            }
        } else {
            if (isset(self::$settings[$key][$name])) {
                unset(self::$settings[$key][$name]);
            }
            $params['name'] = $name;
        }
        return $this->deleteByField($params);
    }
}
