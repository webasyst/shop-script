<?php
/**
 * Class shopSettingsShippingAction
 * @method shopConfig getConfig()
 */

class shopSettingsShippingAction extends waViewAction
{
    public function execute()
    {
        if (!$this->getUser()->getRights('shop', 'settings')) {
            throw new waRightsException(_w('Access denied'));
        }

        $plugins = shopShipping::getList();

        $model = new shopPluginModel();

        $instances = $model->listPlugins(shopPluginModel::TYPE_SHIPPING, array('all' => true));
        foreach ($instances as &$instance) {
            $instance['installed'] = isset($plugins[$instance['plugin']]);
            unset($instance);
        }

        $shipping_params = $this->getShippingParams();

        $cron_params = $this->getCronParams($plugins);

        $this->view->assign(array(
            'instances'       => $instances,
            'plugins'         => $plugins,
            'shipping_params' => $shipping_params,
            'cron_params'     => $cron_params,
            'installer'       => $this->getUser()->getRights('installer', 'backend'),
        ));
    }

    protected function getCronParams($plugins)
    {
        $interval = null;
        $count = 0;
        foreach ($plugins as $info) {
            if (!empty($info['sync'])) {
                ++$count;
                if (is_int($info['sync'])) {
                    if (empty($interval) || ($interval === true)) {
                        $interval = $info['sync'];
                    } else {
                        $interval = min($interval, $info['sync']);
                    }

                } elseif (empty($interval)) {
                    $interval = true;
                }
            }
        }
        $model = new waAppSettingsModel();
        $time = $model->get('shop', 'shipping_plugins_sync');
        $command = 'php '.$this->getConfig()->getRootPath().'/cli.php shop shipping';

        if ($interval === true) {
            $interval = 24;
        }
        if (empty($time)) {
            $status = 'error';
        } elseif (($time + 2 * $interval * 3600) < time()) {
            $status = 'warning';
        } else {
            $status = 'ok';
        }

        return compact('interval', 'command', 'count', 'time', 'status');
    }

    protected function getShippingParams()
    {
        $weight_code = 'weight';

        $model = new shopFeatureModel();

        $weight = $model->getByCode($weight_code);
        $shipping_params = array(
            'weight'                    => array(
                'name'        => _w('Weight'),
                'description' => '',
                'readonly'    => sprintf(
                    _w('Product feature “%s” with code <tt>%s</tt> is used.'),
                    $weight ? $weight['name'] : _w('Weight'),
                    $weight_code
                ),
                'states'      => array(
                    'valid'   => sprintf(
                        '<i class="icon16 yes"></i>%s',
                        sprintf(
                            _w('Shipping plugins retrieve weight from the vaules of a product feature with code <tt>%s</tt>.'),
                            $weight_code
                        )
                    ),
                    'invalid' =>
                        '<i class="icon16 exclamation"></i>'.
                        sprintf(
                            _w('Most shipping rate calculation plugins require <strong>product weight</strong> information which is taken from a product feature with code <tt>%s</tt>.'),
                            $weight_code
                        ).
                        ' '.
                        _w('You have no such feature in your online store settings.').
                        ' '.
                        sprintf(
                            _w('Add it in “<a href="%s">Product types &amp; Features</a>” screen.'),
                            '?action=settings#/typefeat/'
                        ),
                ),
                'state'       => $weight ? 'valid' : 'invalid',

            ),
            'dimensions'                => array(
                'name'        => _w('Product dimensions (value × value × value)'),
                'description' => _w('Select a common product feature containing 3 dimension values to avoid selecting separate features for individual dimensions below.'),
                'features'    => array(
                    '3d.dimension.length',
                ),
                'states'      => array(
                    'valid'   => sprintf(
                        '<i class="icon16 yes"></i>%s',
                        _w('Product dimensions are passed on to shipping plugins.')
                    ),
                    'invalid' => sprintf(
                        '<i class="icon16 exclamation"></i>%s',
                        _w('Passing of product dimensions on to shipping plugins is not set up.')
                    ),
                ),
                'state'       => 'invalid',

            ),
            'height'                    => array(
                'name'     => _w('Product height'),
                'features' => array(
                    'dimension.length',
                ),

            ),
            'width'                     => array(
                'name'     => _w('Product width'),
                'features' => array(
                    'dimension.length',
                ),

            ),
            'length'                    => array(
                'name'        => _w('Product length'),
                'description' => _w('Select separate product features for individual product dimensions if you have not selected a common dimensions feature above.'),
                'features'    => array(
                    'dimension.length',
                ),

            ),
            'shipping_package_provider' => array(
                'value'   => $this->getConfig()->getGeneralSettings('shipping_package_provider'),
                'name'    => _w('Order dimensions calculation plugin'),
                'options' => array(),

                'states' => array(
                    'valid'   => '<i class="icon16 yes"></i>'.
                        _w('Order dimensions calculation is set up.'),
                    'invalid' => '<i class="icon16 exclamation"></i>'.
                        _w('Order dimensions calculation is not set up.'),
                ),
                'state'  => 'invalid',
            ),
        );

        //TODO add custom shipping plugins params


        $available_shop_plugins = $this->getConfig()->getPlugins();

        $shipping_package_provider = &$shipping_params['shipping_package_provider'];

        foreach ($available_shop_plugins as $plugin_id => $plugin_info) {
            if (!empty($plugin_info['handlers']['shipping_package'])) {
                $shipping_package_provider['options'][$plugin_id] = array(
                    'name'  => $plugin_info['name'],
                    'title' => $plugin_info['description'],
                    'value' => $plugin_id,
                );
            }
        }

        if (empty($shipping_package_provider['options'])) {
            $shipping_package_provider['readonly'] = _w('Install an order dimensions calculation plugin.');
        } else {
            array_unshift(
                $shipping_package_provider['options'],
                array(
                    'value' => '',
                    'name'  => _w('Do not calculate order dimensions'),
                )
            );
            $shipping_package_provider['description'] = _w('If no plugin is selected, then order dimensions are calculated by shipping plugins.');

            if (!empty($shipping_package_provider['value']) && isset($shipping_package_provider['options'][$shipping_package_provider['value']])) {
                $shipping_package_provider['state'] = 'valid';
            }
        }


        unset($shipping_package_provider);

        if ($shipping_dimensions = $this->getConfig()->getGeneralSettings('shipping_dimensions')) {
            $shipping_dimensions = preg_split('@\D+@', $shipping_dimensions);
            if (count($shipping_dimensions) == 1) {
                $shipping_params['dimensions']['value'] = reset($shipping_dimensions);
                $shipping_params['dimensions']['state'] = 'valid';
            } elseif (count($shipping_dimensions) == 3) {
                $shipping_params['height']['value'] = $shipping_dimensions[0];
                $shipping_params['width']['value'] = $shipping_dimensions[1];
                $shipping_params['length']['value'] = $shipping_dimensions[2];
                $shipping_params['dimensions']['state'] = 'valid';
            }
        }


        $search_cache = array();

        foreach ($shipping_params as &$params) {
            if (!empty($params['features'])) {
                $params['options'] = array();
                foreach ((array)$params['features'] as $type) {
                    $search = array(
                        'type'      => $type,
                        'parent_id' => null,
                    );
                    if (!isset($search_cache[$type])) {
                        $search_cache[$type] = array();
                        if ($features = $model->getFeatures($search, null, 'id')) {
                            $search_cache[$type][''] = array(
                                'value' => '',
                                'name'  => _w('Select a feature'),
                            );
                            foreach ($features as $id => $feature) {
                                $search_cache[$type][$id] = array(
                                    'value' => $id,
                                    'name'  => ifempty($feature, 'name', $feature['code']),
                                    'title' => $feature['code'],
                                );
                            }
                        }
                    }

                    $params['options'] += $search_cache[$type];
                }
            }
            unset($params);
        }

        return $shipping_params;
    }
}
