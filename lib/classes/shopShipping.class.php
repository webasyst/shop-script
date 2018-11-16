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
                throw new waException(sprintf(_w('Shipping method with id=%s not found.'), $plugin_id), 404);
            }

            if ($info['type'] != shopPluginModel::TYPE_SHIPPING) {
                throw new waException("Shipping plugin {$plugin_id} has invalid type", 404);
            }
            $plugin = $info['plugin'];
        }
        if ($plugin == self::DUMMY) {
            return shopShippingDummy::getDummy();
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
                throw new waException(sprintf(_w('Shipping method with id=%s not found.'), $plugin_id), 404);
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
        if (waConfig::get('is_template')) {
            throw new waException('access from template is not allowed');
        }
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

        // Clean shipping methods cache
        self::flushCache();

        return $plugin;
    }

    public static function flushCache()
    {
        try {
            waFiles::delete(wa()->getCachePath('shipping_methods', 'shop'), true);
        } catch (waException $e) {
        }
    }

    public function getDataPath($order_id, $path = null)
    {
        $str = str_pad($order_id, 4, '0', STR_PAD_LEFT);
        $path = 'orders/'.substr($str, -2).'/'.substr($str, -4, 2).'/'.$order_id.'/shipping/'.$path;

        return wa('shop')->getDataPath($path, false, 'shop', false);
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

    /**
     *
     * formalize order data
     * @param array $order order ID or order data
     * @param waShipping $shipping_plugin
     * @return waOrder
     * @throws waException
     */
    public static function getOrderData(array $order, waShipping $shipping_plugin = null)
    {

        if (!isset($order['id_str'])) {
            $order['id_str'] = shopHelper::encodeOrderId($order['id']);
        }

        if (!isset($order['params'])) {
            $order_params_model = new shopOrderParamsModel();
            $order['params'] = $order_params_model->get($order['id']);
        }
        $options = array();
        if ($shipping_plugin) {
            $options['currency'] = $shipping_plugin->allowedCurrency();
            $options['dimensions'] = $shipping_plugin->allowedWeightUnit();
        }

        return shopHelper::getWaOrder($order, $options);
    }

    public static function getParams($shipping_id)
    {
        if ($shipping_params = waRequest::post('shipping_'.$shipping_id)) {
            foreach ($shipping_params as $key => $value) {
                if (strpos('_', $key) === 0) {
                    unset($shipping_params[$key]);
                }
            }
        }

        return is_array($shipping_params) ? $shipping_params : null;
    }

    public static function extendItems(&$items, $units = array())
    {
        $map = array();

        $dimension_fields = array('height', 'width', 'length');
        $dimension_types = array();

        $feature_model = new shopFeatureModel();

        if (!isset($units['weight']) || !empty($units['weight'])) {
            if ($weight = $feature_model->getByCode('weight')) {
                $map['weight'] = $weight;
                $dimension_types['weight']='weight';
            }
        }

        /** @var waSystem $wa */
        $wa = wa('shop');

        if (!isset($units['dimensions']) || !empty($units['dimensions'])) {

            if ($dimensions = self::getShopSettings('shipping_dimensions')) {
                $dimensions = preg_split('@\D+@', $dimensions);
                $features = $feature_model->getByField((count($dimensions) == 1) ? 'parent_id' : 'id', $dimensions, true);

                if (count($features) == 3) {
                    $map += array_combine($dimension_fields, array_values($features));
                    if (isset($units['dimensions'])) {
                        foreach ($dimension_fields as $field) {
                            $units[$field] = $units['dimensions'];
                            $dimension_types[$field]='length';
                        }
                    }
                }
            }
        }

        foreach ($map as $field => $feature) {
            $product_ids = array();

            foreach ($items as $i) {
                if (!isset($i[$field])) {
                    if (isset($i['item'])) {
                        $product_ids[] = $i['item']['product_id'];
                    } else {
                        $product_ids[] = $i['product_id'];
                    }
                }
            }

            $product_ids = array_unique($product_ids);

            $multiplier = null;
            if (!$feature || !$product_ids) {
                $values = array();
            } else {
                $values_model = $feature_model->getValuesModel($feature['type']);
                $values = $values_model->getProductValues($product_ids, $feature['id']);
                if ($values && $units && !empty($unit_types[$field]) && !empty($dimension_types[$field])) {
                    $unit = $units[$field];
                    $dimension_type = $dimension_types[$field];
                    $dimension = shopDimension::getInstance()->getDimension($dimension_type);
                    if ($units[$field] != $dimension['base_unit']) {
                        if (isset($dimension['units'][$unit])) {
                            $multiplier = (double)$dimension['units'][$unit]['multiplier'];
                        } else {
                            throw new waException(sprintf('Unknown %s unit [%s] for %s', $dimension_type, $unit, $field));
                        }
                    }
                }
            }

            foreach ($items as &$item) {
                if (isset($item[$field])) {
                    continue;
                }
                if (empty($item['type']) || ($item['type'] == 'product')) {

                    if (isset($item['item'])) {
                        $i = $item['item'];
                    } else {
                        $i = $item;
                    }

                    if (isset($values['skus'][$i['sku_id']])) {
                        $value = $values['skus'][$i['sku_id']];
                    } elseif (isset($i['product_id']) && isset($values[$i['product_id']])) {
                        $value = $values[$i['product_id']];
                    } else {
                        if (isset($item['product_id'])) {
                            $value = isset($values[$item['product_id']]) ? $values[$item['product_id']] : 0;
                        } else {
                            $value = isset($values[$item['id']]) ? $values[$item['id']] : 0;
                        }
                    }
                    if ($multiplier !== null) {
                        $value = $value / $multiplier;
                    } else {
                        $value = (double)$value;
                    }
                } else {
                    $value = 0;
                }
                $item[$field] = $value;
                unset($item);
            }
        }

        $total = array();



        $preferred_id = self::getShopSettings('shipping_package_provider');


        if ($preferred_id) {
            /**
             * @event shipping_package Experimental hook
             *
             *
             * @param array[] mixed $items
             * @param float [string][] $items[]['weight'] Item `weight` property
             * @param float [string][] $items[]['height'] Item `height` property
             * @param float [string][] $items[]['width'] Item `width` property
             * @param float [string][] $items[]['length'] Item `length` property
             *
             * @return array[string]array $return[%plugin_id%] array package data
             * @return array[string][string]callable $return[%plugin_id%]['callback'] Callback method to calculate package totals
             * @return array[string][string]float $return[%plugin_id%]['weight'] Calculated total package weight
             * @return array[string][string]float $return[%plugin_id%]['height'] Calculated total package height
             * @return array[string][string]float $return[%plugin_id%]['width'] Calculated total package width
             * @return array[string][string]float $return[%plugin_id%]['length'] Calculated total package length
             */
            $result = $wa->event('shipping_package', $items);
            $preferred_id = sprintf('%s-plugin', $preferred_id);
            if (isset($result[$preferred_id])) {
                $plugin_result = ifset($result, $preferred_id, array());
                if (isset($plugin_result['callback']) && is_callable($plugin_result['callback'])) {
                    try {
                        $plugin_result += (array)call_user_func($plugin_result['callback'], $items);
                    } catch (waException $ex) {
                        waLog::log($ex->getMessage(), 'shop/shipping_package.log');
                    }
                }
                $total_fields = array_merge(array('weight'), $dimension_fields);
                foreach ($total_fields as $field) {
                    if (!isset($total[$field]) && isset($plugin_result[$field])) {
                        $total[$field] = $plugin_result[$field];
                    }
                }

            }
        }

        return $total;
    }

    public function getAvailableLinearUnits()
    {
        $dimension = shopDimension::getInstance()->getDimension('length');
        $units = array();
        foreach ($dimension['units'] as $unit => $info) {
            $units[$unit] = $info['name'];
        }

        return $units;
    }

    public static function getShippingTypes()
    {
        return array(
            waShipping::TYPE_PICKUP => sprintf(_w('shipping type “%s”'), _w('pickup')),
            waShipping::TYPE_TODOOR => sprintf(_w('shipping type “%s”'), _w('courier')),
            waShipping::TYPE_POST   => sprintf(_w('shipping type “%s”'), _w('post')),
        );
    }

    private static function getShopSettings($field)
    {
        /** @var shopConfig $config */
        static $config;
        if (empty($config)) {
            $config = wa('shop')->getConfig();
        }

        return $config->getGeneralSettings($field);
    }

    public function getAppProperties($name = null)
    {
        if ($name === 'dimensions') {
            $dimensions = self::getShopSettings('shipping_dimensions');
            $return = !empty($dimensions);
            if ($return) {
                $shipping_package_provider = self::getShopSettings('shipping_package_provider');
                if ($shipping_package_provider) {
                    $return = $shipping_package_provider;
                }
            }
        } elseif ($name === null) {
            $return = parent::getAppProperties($name);
            $return['dimensions'] = $this->getAppProperties('dimensions');
        } else {
            $return = parent::getAppProperties($name);
        }

        return $return;
    }
}
