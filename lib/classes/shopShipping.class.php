<?php

class shopShipping extends waAppShipping
{
    const DUMMY = 'dummy';
    private static $instance;

    public static function getInstance()
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    protected function init()
    {
        $this->app_id = 'shop';
        parent::init();
    }

    /**
     *
     * @return shopPluginSettingsModel
     */
    private function model()
    {
        static $model;
        if (!$model) {
            $model = new shopPluginSettingsModel();
        }
        return $model;
    }

    /**
     *
     * @param string $plugin
     * @param int $plugin_id
     * @return waShipping
     * @throws waException
     */
    public static function getPlugin($plugin, $plugin_id = null)
    {
        if (!$plugin && $plugin_id) {
            $model = new shopPluginModel();
            $info = $model->getById($plugin_id);

            if (!$info) {
                throw new waException("Shipping plugin {$plugin_id} not found", 404);
            }

            if ($info['type'] != shopPluginModel::TYPE_SHIPPING) {
                throw new waException("Shipping plugin {$plugin_id} has invalid type", 404);
            }
            $plugin = $info['plugin'];
        }
        if ($plugin == self::DUMMY) {
            return shopShippingDummy::getDummy($plugin_id);
        } else {
            return waShipping::factory($plugin, $plugin_id, self::getInstance());
        }
    }

    public static function getPluginInfo($id)
    {
        if ($plugin_id = max(0, intval($id))) {

            $model = new shopPluginModel();
            $info = $model->getById($plugin_id);

            if (!$info) {
                throw new waException("Shipping plugin {$plugin_id} not found", 404);
            }
        } else {
            $info = array(
                'plugin' => $id,
                'status' => 1,
            );
        }

        if ($info['plugin'] == self::DUMMY) {
            $default_info = shopShippingDummy::dummyInfo();
        } else {
            $default_info = waShipping::info($info['plugin']);
        }
        return is_array($default_info) ? array_merge($default_info, $info) : $default_info;
    }

    public static function savePlugin($plugin)
    {
        $default = array(
            'status' => 0,
        );
        $plugin = array_merge($default, $plugin);
        if (!intval(ifempty($plugin['id'])) && isset($plugin['settings']) && ($plugin['plugin'] != self::DUMMY)) {
            $instance = waShipping::factory($plugin['plugin'], null, self::getInstance());
            $instance->saveSettings($plugin['settings']);
        }

        $model = new shopPluginModel();
        if (!empty($plugin['id']) && ($id = max(0, intval($plugin['id']))) && ($row = $model->getByField(array('id' => $id, 'type' => shopPluginModel::TYPE_SHIPPING)))) {
            $plugin['plugin'] = $row['plugin'];
            $model->updateById($plugin['id'], $plugin);
        } elseif (!empty($plugin['plugin'])) {
            $plugin['type'] = shopPluginModel::TYPE_SHIPPING;
            $plugin['id'] = $model->insert($plugin);
        }
        if (!empty($plugin['id']) && isset($plugin['settings']) && ($plugin['plugin'] != self::DUMMY)) {
            $instance = waShipping::factory($plugin['plugin'], $plugin['id'], self::getInstance());
            $instance->saveSettings($plugin['settings']);
        }
        return $plugin;
    }

    public function getDataPath($order_id, $path = null)
    {
        $str = str_pad($order_id, 4, '0', STR_PAD_LEFT);
        $path = 'orders/'.substr($str, -2).'/'.substr($str, -4, 2).'/'.$order_id.'/shipping/'.$path;
        return wa('shop')->getDataPath($path, false, 'shop');
    }

    public static function getList()
    {
        if (!class_exists('waShipping')) {
            throw new waException(_w('Shipping plugins not installed yet'));
        }
        $list = waShipping::enumerate();
        $list['dummy'] = shopShippingDummy::dummyInfo();
        return $list;
    }

    public function getSettings($plugin_id, $key)
    {
        return $this->model()->get($key);
    }

    public function setSettings($plugin_id, $key, $name, $value)
    {
        if (!empty($key)) {
            $this->model()->set($key, $name, $value);
        }
    }
}
