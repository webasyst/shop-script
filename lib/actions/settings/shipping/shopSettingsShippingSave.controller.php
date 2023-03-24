<?php

class shopSettingsShippingSaveController extends waJsonController
{
    public function execute()
    {
        if (!$this->getUser()->getRights('shop', 'settings')) {
            throw new waRightsException(_w('Access denied'));
        }
        if ($plugin = waRequest::post('shipping')) {
            try {
                if (!isset($plugin['settings'])) {
                    $plugin['settings'] = array();
                }
                shopShipping::savePlugin($plugin);

                $is_edit = $plugin['id'] > 0;

                $log_params = array(
                    'id' => $plugin['id'],
                    'status' => !empty($plugin['status']),
                    'plugin' => $plugin['plugin']
                );

                if ($is_edit) {
                    $this->logAction('shipping_plugin_edit', $log_params);
                } else {
                    $this->logAction('shipping_plugin_add', $log_params);
                }

                $this->disableShipping();

                $this->response['message'] = _w('Saved');
            } catch (waException $ex) {
                $this->setError($ex->getMessage());
            }
        } elseif ($params = waRequest::post('params')) {

            $errors = array();
            $this->response['message'] = _w('Saved');
            if (!empty($params['dimensions'])) {
                $shipping_dimensions = $params['dimensions'];
            } else {
                $dimensions = array(
                    'height' => _w('Product height'),
                    'width'  => _w('Product width'),
                    'length' => _w('Product length'),
                );
                $shipping_dimensions = array();

                foreach ($dimensions as $dimension => $name) {
                    if (!empty($params[$dimension]) && !in_array($params[$dimension], $shipping_dimensions)) {
                        $shipping_dimensions[] = $params[$dimension];
                    } else {
                        $errors[$dimension] = $name;
                    }
                }
                if ($errors) {
                    $shipping_dimensions = false;
                } else {
                    $shipping_dimensions = implode('.', $shipping_dimensions);
                }
            }

            $app_settings = new waAppSettingsModel();
            if ($shipping_dimensions !== false) {

                $app_settings->set('shop', 'shipping_dimensions', $shipping_dimensions);
            } else {
                $shipping_dimensions = $app_settings->get('shop', 'shipping_dimensions');
            }

            if ($shipping_dimensions) {
                $shipping_dimensions = preg_split('@\D+@', $shipping_dimensions);
            } else {
                $shipping_dimensions = array();
            }

            $status = array();

            if ((count($shipping_dimensions) == 1) || (count($shipping_dimensions) == 3)) {
                $status['dimensions'] = 'valid';
            } else {
                $status['dimensions'] = 'invalid';
            }
            $id = ifset($params, 'shipping_package_provider', false);
            $app_settings->set('shop', 'shipping_package_provider', $id);
            $status['shipping_package_provider'] = $id ? 'valid' : 'invalid';

            if ($errors) {
                $this->setError(sprintf(
                    _w('Parameters are not specified: %s'),
                    implode(', ', $errors)
                ));
            } else {
                $this->response['params'] = $status;
            }
            shopShipping::flushCache();
        }
    }

    public static function disableShipping()
    {
        $plugin_model = new shopPluginModel();
        $all_plugins_disabled = empty($plugin_model->listPlugins($plugin_model::TYPE_SHIPPING));
        if ($all_plugins_disabled) {
            self::saveCheckout();
            self::saveCheckout2();
        }
    }

    protected static function getCheckout($version = '')
    {
        $path = wa()->getConfig()->getConfigPath("checkout$version.php", true, 'shop');
        $file = [];
        if (file_exists($path)) {
            $file = include($path);
        } else {
            if (empty($version)) {
                $file = [
                    'contactinfo' => true,
                    'shipping' => true,
                    'payment' => true,
                    'confirmation' => true
                ];
            }
        }
        return $file;
    }

    protected static function saveCheckout()
    {
        $checkout = self::getCheckout();
        if (isset($checkout['shipping'])) {
            unset($checkout['shipping']);
        }
        $app_settings_model = new waAppSettingsModel();
        $app_settings_model->set('shop', 'checkout_flow_changed', time());
        self::saveFile($checkout);
    }

    protected static function saveCheckout2()
    {
        $checkout2 = self::getCheckout(2);
        foreach ($checkout2 as &$params) {
            if (isset($params['shipping'])) {
                $params['shipping']['used'] = false;
            }
        }
        unset($params);
        self::saveFile($checkout2, 2);

        $storefronts = wa()->getRouting()->getByApp('shop');
        foreach ($storefronts as $routes) {
            foreach ($routes as $route) {
                if ($route['checkout_version'] == 2 && !isset($checkout2[$route['checkout_storefront_id']])) {
                    $checkout_config = new shopCheckoutConfig($route['checkout_storefront_id']);
                    $checkout_config->setData([
                        'shipping' => [
                            'used' => false
                        ]
                    ]);
                    $checkout_config->commit();
                }
            }
        }
    }

    protected static function saveFile($data, $version = '')
    {
        waUtils::varExportToFile($data, wa()->getConfig()->getConfigPath("checkout$version.php", true, 'shop'));
    }
}
