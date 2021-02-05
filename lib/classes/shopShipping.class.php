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
     * @param int    $plugin_id
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
                'type'   => waShipping::PLUGIN_TYPE,
            );
        }

        self::fillDefaultData($info);
        if ($info && !empty($info['info']) && is_array($info['info'])) {
            $info += $info['info'];
        }

        return $info;
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
        $list[self::DUMMY] = shopShippingDummy::info(self::DUMMY);
        uasort($list, wa_lambda('$a, $b', 'return strcasecmp($a["name"], $b["name"]);'));

        return $list;
    }

    public function getSettings($plugin_id, $key)
    {
        return $this->model()->get($key);
    }

    public function setSettings($plugin_id, $key, $name, $value)
    {
        if (!empty($key)) {
            $m = $this->model();
            if ($m->isValueOverflow($value)) {
                throw new waException(_w('Settings cannot be saved because of too large data size.'));
            }
            $m->set($key, $name, $value);
        }
    }

    /**
     *
     * formalize order data
     * @param array      $order order ID or order data
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
            $options['weight'] = $shipping_plugin->allowedWeightUnit();
            $options['dimensions'] = $shipping_plugin->allowedLinearUnit();
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

    /**
     * @param array $params
     * @param waShipping $plugin
     * @param array $plugin_info
     * @return array
     * @throws waException
     */
    public static function workupShippingParams($params, $plugin, $plugin_info)
    {
        $params = shopShipping::convertTotalDimensions($params, $plugin);

        if (!isset($params['departure_datetime'])) {
            $departure_datetime = shopDepartureDateTimeFacade::getDeparture();
            $assembly_time = max(0, ifset($plugin_info, 'options', 'assembly_time', 0)) * 3600;
            $departure_datetime->setExtraProcessingTime($assembly_time);
            $params['departure_datetime'] = (string)$departure_datetime;
        }

        if (isset($params['total_price'])) {
            $currency = $plugin->allowedCurrency();

            /** @var shopConfig $shop_config */
            $shop_config = wa('shop')->getConfig();

            /** @var string $current_currency */
            $current_currency = $shop_config->getCurrency(false);

            if ($currency != $current_currency) {
                $params['total_price'] = shop_currency($params['total_price'], $current_currency, $currency, false);
            }
        }

        return $params;
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
                $dimension_types['weight'] = 'weight';
            }
        }

        if (!isset($units['dimensions']) || !empty($units['dimensions'])) {

            if ($dimensions = self::getShopSettings('shipping_dimensions')) {
                $dimensions = preg_split('@\D+@', $dimensions);
                $features = $feature_model->getByField((count($dimensions) == 1) ? 'parent_id' : 'id', $dimensions, true);

                if (count($features) == 3) {
                    if (count($dimensions) == 1) {
                        $map += array_combine($dimension_fields, array_values($features));
                    } else {
                        // height|width|length => feature_id
                        $feature_ids = array_combine($dimensions, $dimension_fields);
                        foreach($features as $f) {
                            // height|width|length => feature
                            $map[$feature_ids[$f['id']]] = $f;
                        }
                    }

                    if (isset($units['dimensions'])) {
                        foreach ($dimension_fields as $field) {
                            $units[$field] = $units['dimensions'];
                            $dimension_types[$field] = 'length';
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
                        if (!empty($i['item']['product_id'])) {
                            $product_ids[] = $i['item']['product_id'];
                        }
                    } else {
                        if (!empty($i['product_id'])) {
                            $product_ids[] = $i['product_id'];
                        }
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
                        } elseif ($unit !== true) {
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

                    if (isset($i['sku_id']) && isset($values['skus'][$i['sku_id']])) {
                        $value = $values['skus'][$i['sku_id']];
                    } elseif (isset($i['product_id']) && isset($values[$i['product_id']])) {
                        $value = $values[$i['product_id']];
                    } else {
                        if (isset($item['product_id'])) {
                            $value = isset($values[$item['product_id']]) ? $values[$item['product_id']] : 0;
                        } elseif (isset($item['id'])) {
                            $value = isset($values[$item['id']]) ? $values[$item['id']] : 0;
                        } else {
                            $value = 0;
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
    }

    public static function extractItemsTotal($order_params, $format = false)
    {
        $total = array();
        $dimension_fields = array_fill_keys(array('height', 'width', 'length'), 'length');
        $dimension_fields['weight'] = 'weight';
        foreach ($dimension_fields as $field => $type) {
            $total_field = sprintf('package_total_%s', $field);
            if (isset($order_params[$total_field])) {
                $value = $order_params[$total_field];
                if ($format) {
                    $unit = shopDimension::getBaseUnit($type);
                    $row = array(
                        'value_base_unit' => $value,
                        'value'           => $value,
                        'type'            => $type,
                        'unit'            => $unit['value'],
                    );
                    $value = new shopDimensionValue($row);
                    $total[$total_field] = $value->html;
                } else {
                    $total[$total_field] = $order_params[$total_field];
                }
            }
        }
        return $total;
    }

    public static function getItemsTotal($items)
    {
        $total = array();

        $preferred_id = self::getShopSettings('shipping_package_provider');
        $dimension_fields = array_fill_keys(array('height', 'width', 'length'), 'length');
        $dimension_fields['weight'] = 'weight';

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
            $result = wa('shop')->event('shipping_package', $items);
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
                foreach ($dimension_fields as $field => $type) {
                    $total_field = 'total_'.$field;
                    if (!isset($total[$total_field])) {
                        if (isset($plugin_result[$total_field])) {
                            $total[$total_field] = $plugin_result[$total_field];
                        } elseif (isset($plugin_result[$field])) {
                            $total[$total_field] = $plugin_result[$field];
                        }
                    }
                }
            }
        }

        return $total;
    }

    /**
     * @param                  $total
     * @param waShipping|array $plugin
     * @return array
     * @throws waException
     */
    public static function convertTotalDimensions($total, $plugin)
    {
        $dimension_fields = array();
        $units = self::getDimensionUnits($plugin);
        if (!empty($units['dimensions'])) {
            $dimension_fields += array_fill_keys(array('height', 'width', 'length'), $units['dimensions']);
        }

        if (!empty($units['weight'])) {
            $dimension_fields['weight'] = $units['weight'];
        }

        if (!empty($units['currency'])) {
            $dimension_fields['price'] = $units['currency'];
        }

        foreach ($dimension_fields as $dimension => $unit) {
            $field = 'total_'.$dimension;

            if (isset($total[$field])) {
                $total[$field] = shopHelper::workupValue($total[$field], $dimension, $unit);
            }
        }
        return $total;
    }


    /**
     * @param                  $items
     * @param waShipping|array $plugin
     */
    public static function convertItemsDimensions(&$items, $plugin)
    {
        $dimension_fields = array();

        $units = self::getDimensionUnits($plugin);

        $dimensions = shopDimension::getInstance();

        if (!empty($units['weight'])) {
            $dimension_fields['weight'] = array(
                'type' => 'weight',
                'unit' => $units['weight'],
            );
        }

        if (!empty($units['dimensions'])) {
            $dimension_fields['height'] = array(
                'type' => 'length',
                'unit' => $units['dimensions'],
            );
            $dimension_fields['length'] = array(
                'type' => 'length',
                'unit' => $units['dimensions'],
            );
            $dimension_fields['width'] = array(
                'type' => 'length',
                'unit' => $units['dimensions'],
            );
        }

        foreach ($items as &$item) {
            foreach ($dimension_fields as $field => $unit) {
                if (!empty($item[$field])) {
                    $original_field = 'original_'.$field;
                    if (empty($item[$original_field])) {
                        $item[$original_field] = $item[$field];
                    }
                    $item[$field] = $dimensions->convert($item[$original_field], $unit['type'], $unit['unit']);
                }
            }
        }
        unset($item);
    }

    private static function getDimensionUnits($params)
    {
        $units = array();
        if (is_array($params)) {
            if (isset($params['dimensions'])) {
                $units['dimensions'] = $params['dimensions'];
            }
            if (isset($params['weight'])) {
                $units['weight'] = $params['weight'];
            }
            if (isset($params['currency'])) {
                $units['currency'] = $params['currency'];
            }
        } elseif ($params instanceof waShipping) {
            if ($unit = $params->allowedLinearUnit()) {
                $units['dimensions'] = $unit;
            }
            if ($unit = $params->allowedWeightUnit()) {
                $units['weight'] = $unit;
            }

        }

        return $units;
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

    public static function getShippingPaymentTypes()
    {
        return array(
            waShipping::PAYMENT_TYPE_CARD    => _w('bank card on order receipt'),
            waShipping::PAYMENT_TYPE_CASH    => _w('cash on order receipt'),
            waShipping::PAYMENT_TYPE_PREPAID => _w('prepayment'),
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
        } elseif ($name === 'sync') {
            $return = wa('shop')->getSetting('shipping_plugins_sync', true);
        } elseif ($name === 'callback') {
            $methods = get_class_methods(get_class($this));
            $return = array();
            foreach ($methods as $method) {
                if (preg_match('@callback(.+)Handler@', $method, $matches)) {
                    $return[] = $matches[1];
                }
            }
        } elseif ($name === null) {
            $return = parent::getAppProperties($name);
            $return['dimensions'] = $this->getAppProperties('dimensions');
            $return['sync'] = $this->getAppProperties('sync');
            $return['callback'] = $this->getAppProperties('callback');
        } else {
            $return = parent::getAppProperties($name);
        }

        return $return;
    }

    public static function fillDefaultData(&$data)
    {
        if (!isset($data['info'])) {
            if ($data['plugin'] == self::DUMMY) {
                $data['info'] = shopShippingDummy::info($data['plugin']);
            } else {
                $data['info'] = waShipping::info($data['plugin']);
            }
        }
        if (!isset($data['options']['customer_type'])) {
            $data['options']['customer_type'] = '';
        }
    }

    protected function callbackNotifyHandler($status_data)
    {
        return $this->callbackAction($status_data);
    }


    protected function callbackAction($status_data)
    {
        $result = array();
        $order_model = new shopOrderModel();

        $workflow = new shopWorkflow();
        /** @var shopWorkflowCallbackAction $callback */
        $callback = $workflow->getActionById('callback');

        if (!empty($status_data['order_id'])) {
            /** @var waShipping $plugin */
            $plugin = ifset($status_data['shipping_plugin_instance']);

            $order = $order_model->getById($status_data['order_id']);
            if (!$order) {
                $result['error'] = 'Order not found';
            } else {
                $appropriate = $this->isSuitable($order['id'], $plugin);
                if ($appropriate !== true) {
                    $result['error'] = $appropriate;
                } else {
                    if (empty($status_data['customer_id']) && !empty($order['contact_id'])) {
                        $result['customer_id'] = $order['contact_id'];
                        $status_data['customer_id'] = $order['contact_id'];
                    }
                }
            }


        } else {
            $result['error'] = 'Order not found';
        }


        if (empty($result['error'])) {
            $status_data['plugin'] = 'shipping:'.$status_data['shipping_id'];
            $callback->run($status_data);
        }

        return $result;
    }

    /**
     * Verify if shipping type is valid for this order
     * @param int        $order_id
     * @param waShipping $plugin
     * @return bool|string
     */
    private function isSuitable($order_id, $plugin)
    {
        if (!$plugin) {
            return 'Invalid plugin id';
        } else {
            $order_params_model = new shopOrderParamsModel();

            if ($plugin->getPluginKey() != $order_params_model->getOne($order_id, 'shipping_id')) {
                return 'Order shipping type did not match the callback request';
            }
        }
        return true;
    }
}
