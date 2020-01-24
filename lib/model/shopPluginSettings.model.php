<?php
class shopPluginSettingsModel extends waModel implements waiPluginSettings
{
    protected $table = 'shop_plugin_settings';
    protected $id = array('id', 'name');
    protected $value_size;

    protected static $settings = array();

    /**
     * Is value that would be saved into db overflow size of field?
     * @param $value
     * @return bool
     * @throws waDbException
     * @throws waException
     */
    public function isValueOverflow($value)
    {
        if ($value === null) {
            return false;
        }
        if (is_array($value)) {
            $value = json_encode($value);
        }
        if (!is_scalar($value)) {
            return true;
        }
        $value = (string)$value;
        $value_size = $this->getValueSize();
        return strlen($value) > $value_size;
    }

    /**
     * Get size of field 'value'
     * @return int
     * @throws waDbException
     * @throws waException
     */
    public function getValueSize()
    {
        if ($this->value_size === null) {
            $meta = $this->getMetadata();
            $type = strtolower($meta['value']['type']);
            switch ($type) {
                case 'mediumtext':
                    $this->value_size = 16777215;
                    break;
                default:
                    $this->value_size = 65535;
            }
        }
        return $this->value_size;
    }

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
                $json = shopHelper::jsonDecode($value, true);
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
