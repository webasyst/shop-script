<?php
/**
 * List of measurement units on the settings page
 */
class shopSettingsCompatibilityAction extends waViewAction
{
    const REQUIRED_APPS = [
        'shop',
        'crm',
        'ofdferma',
        'schetmash',
        'ozon',
        'kkm',
    ];

    const REQUIRED_PLUGINS_SLUG_MASKS = [
        '~^(ofdferma|schetmash|ozon|kkm)/plugins/.*$~',
    ];

    const COMPATIBILITY = [
        'no'      => '0',  // не поддерживает
        'yes'     => '1',  // поддерживает полностью
        'partial' => '2',  // частично поддерживает
        'unknown' => '3'   // нет информации
    ];

    const TAG_SHOP_PREMIUM_NO = 'shop_premium_no';
    const TAG_SHOP_PREMIUM_YES = 'shop_premium_yes';

    public function execute()
    {
        $installer_enable = !!wa()->appExists('installer');
        $groups = ($installer_enable ? $this->getGroups() : []);

        $this->view->assign([
            'installer_enable' => $installer_enable,
            'groups'           => $groups,
            'is_premium'       => shopLicensing::isPremium(),
        ]);
    }

    protected function getGroups()
    {
        $delivery  = [];
        $payment   = [];
        $apps      = [];
        $sys_items = [];

        wa('installer');
        $options = [
            'installed'    => true,
            'requirements' => true,
            'action'       => true,
            'system'       => true,
            'status'       => true,
            'filter'       => [],
        ];
        $items = installerHelper::getInstaller()->getApps($options);

        /** приложения */
        foreach ($items as $name => $app) {
            if (strpos($name, 'wa-plugins') === false) {
                $app['name'] = _wd($name, $app['name']);
                $apps[$name] = $app;
            } else {
                $sys_items[] = $name;
            }
        }
        unset($name, $app);

        /** плагины приложений */
        foreach ($this->extrasProduct(array_keys($apps)) as $name => $plugins) {
            if (!empty($plugins['plugins'])) {
                $apps[$name]['plugins'] = $plugins['plugins'];
            }
        }
        unset($name, $plugins);

        /** фильтр приложений и плагинов */
        foreach ($apps as $name => $app) {
            if (empty($app['plugins'])) {
                if (!in_array($name, self::REQUIRED_APPS)) {
                    unset($apps[$name]);
                }
                continue;
            }

            $plugins_data = $this->getStoreProductsData(array_column($app['plugins'], 'slug'));
            if ($name === 'shop') {
                foreach ($app['plugins'] as $app_plugin) {
                    $_app_plg = &$apps[$name]['plugins'][$app_plugin['id']];

                    $_app_plg['version_latest'] = ifset($plugins_data, $app_plugin['slug'], 'version', null);
                    $_app_plg['icon'] = ifset($plugins_data, $app_plugin['slug'], 'icon', '');

                    $tags = ifset($plugins_data, $app_plugin['slug'], 'tags', []);
                    $support_frac = shopHelper::getShopSupportJson($app_plugin['path']);

                    if (in_array(self::TAG_SHOP_PREMIUM_YES, $tags)) {
                        $_app_plg += ['compatibility'=> self::COMPATIBILITY['yes']];
                    } elseif (in_array(self::TAG_SHOP_PREMIUM_NO, $tags)) {
                        $_app_plg += ['compatibility'=> self::COMPATIBILITY['no']];
                    } elseif (!empty($support_frac)) {
                        $_app_plg += $support_frac;
                    } else {
                        $_app_plg += ['compatibility'=> self::COMPATIBILITY['unknown']];
                    }
                }

                $apps[$name]['plugins'] = $this->pluginsFormat($apps[$name]['plugins']);
            } else {
                $support_plugins = [];
                foreach ($app['plugins'] as $app_plugin) {
                    $_app_plg = &$apps[$name]['plugins'][$app_plugin['id']];
                    $_app_plg['icon'] = ifset($plugins_data, $app_plugin['slug'], 'icon', '');
                    $support_frac = shopHelper::getShopSupportJson($app_plugin['path']);

                    if (empty($support_frac)) {
                        // Check if plugin is required to be shown in list no matter what
                        foreach(self::REQUIRED_PLUGINS_SLUG_MASKS as $regex) {
                            if (preg_match($regex, $app_plugin['slug'])) {
                                $support_frac = [
                                    'support_premium' => null,
                                    'support_premium_description' => null,
                                ];
                            }
                        }
                    }
                    if (!empty($support_frac)) {
                        $_app_plg['version_latest'] = ifset($plugins_data, $app_plugin['slug'], 'version', '');
                        $_app_plg += $support_frac;
                        $support_plugins[] = $_app_plg;
                    }
                }
                $support_frac = shopHelper::getShopSupportJson($app['path']);
                if (
                    empty($support_plugins)
                    && empty($support_frac)
                    && !in_array($name, self::REQUIRED_APPS)
                ) {
                    unset($apps[$name]);
                } else {
                    $apps[$name] += $support_frac;
                    $apps[$name]['plugins'] = $this->pluginsFormat($support_plugins);
                }
            }
        }
        unset($name, $app, $app_plugin, $_app_plg);

        $apps_data = $this->getStoreProductsData(array_keys($apps));
        foreach ($apps as $name => $app) {
            $link = wa()->getAppUrl('installer')."store/app/$name/";
            $apps[$name] += [
                'id'                => $name,
                'name'              => $app['name'],
                'image'             => $app['icon'],
                'version_installed' => $app['version'],
                'version_latest'    => ifset($apps_data, $name, 'version', $app['version']),
                'link_view'         => $link,
                'link_update'       => $link,
                'plugins'           => [],
            ];

            // Информация о совместимости приложений
            $tags = ifset($apps_data, $name, 'tags', []);
            $support_frac = shopHelper::getShopSupportJson($app['path']);
            if (in_array(self::TAG_SHOP_PREMIUM_YES, $tags)) {
                $apps[$name] += ['compatibility' => self::COMPATIBILITY['yes']];
            } elseif (in_array(self::TAG_SHOP_PREMIUM_NO, $tags)) {
                $apps[$name] += ['compatibility' => self::COMPATIBILITY['no']];
            } elseif (!empty($support_frac)) {
                $apps[$name] += $support_frac;
            } else {
                $apps[$name] += ['compatibility' => self::COMPATIBILITY['unknown']];
            }
        }
        unset($name, $app);

        /** системные плагины оплаты и доставки */
        if (!empty($sys_items)) {
            $extras     = $this->extrasProduct($sys_items);
            $payments   = ifset($extras, 'wa-plugins/payment', 'plugins', []);
            $deliveries = ifset($extras, 'wa-plugins/shipping', 'plugins', []);

            if (!empty($payments)) {
                $payment = $this->systemPluginsPrepare($payments);
            }
            if (!empty($deliveries)) {
                $delivery = $this->systemPluginsPrepare($deliveries);
            }
        }

        return [
            "delivery" => $delivery,
            "payment"  => $payment,
            "apps"     => $apps,
            "themes"   => $this->getThemes()
        ];
    }

    /**
     * @param $plugins
     * @return array
     * @throws waException
     */
    protected function pluginsFormat($plugins = [])
    {
        foreach ($plugins as &$plugin) {
            $link = wa()->getAppUrl('installer').'store/plugin/';
            $type = '';
            if (strpos($plugin['slug'], 'wa-plugins/payment') !== false) {
                $link .= 'payment/'.$plugin['id'].'/';
                $type = shopPluginModel::TYPE_PAYMENT;
            } elseif (strpos($plugin['slug'], 'wa-plugins/shipping') !== false) {
                $link .= 'shipping/'.$plugin['id'].'/';
                $type = shopPluginModel::TYPE_SHIPPING;
            } elseif (!empty($plugin['app'])) {
                $link .= $plugin['app'].'/'.$plugin['id'].'/';
            }
            $html = sprintf(
                _w('For additional information, please contact the developer. Their contact details are available on the %splugin%s page.'),
                '<a href="'.$link.'" target="_blank">',
                '</a>'
            );
            $plugin = [
                'id'                        => $plugin['id'],
                'enabled'                   => (empty($plugin['enabled']) ? '0' : '1'),
                'name'                      => ifempty($plugin['name'], $plugin['id']),
                'image'                     => ifempty($plugin, 'icon', ''),
                'version_installed'         => $plugin['version'],
                'version_latest'            => ifset($plugin, 'version_latest', $plugin['version']),
                'link_view'                 => $link,
                'link_update'               => $link,
                'frac_mode'                 => shopFrac::getPluginFractionalMode($plugin['id'], shopFrac::PLUGIN_MODE_FRAC, $type),
                'units_mode'                => shopFrac::getPluginFractionalMode($plugin['id'], shopFrac::PLUGIN_MODE_UNITS, $type),
                'compatibility'             => ifset($plugin, 'compatibility', '0'),
                'compatibility_description' => ifset($plugin, 'compatibility_description', $html)
            ];
        }

        return $plugins;
    }

    protected function getThemes()
    {
        $themes = [];
        $shop_themes = $this->extrasProduct(['shop'], 'themes');
        $shop_themes = ifset($shop_themes, 'shop', 'themes', []);
        if (empty($shop_themes)) {
            return [];
        }

        $themes_data = $this->getStoreProductsData(array_keys($shop_themes), true);
        foreach ($themes_data as $slug => $theme_data) {
            $parts = explode('/', $slug);
            $themes_data[end($parts)] = $theme_data;
            unset($themes_data[$slug]);
        }

        foreach ($shop_themes as $theme) {
            $tags = ifset($themes_data, $theme['id'], 'tags', []);
            $support_frac = shopHelper::getShopSupportJson($theme['path'], true);

            if (in_array(self::TAG_SHOP_PREMIUM_YES, $tags)) {
                $theme['compatibility'] = self::COMPATIBILITY['yes'];
            } elseif (in_array(self::TAG_SHOP_PREMIUM_NO, $tags)) {
                $theme['compatibility'] = self::COMPATIBILITY['no'];
            } elseif (!empty($support_frac)) {
                $theme += $support_frac;
            } else {
                $theme['compatibility'] = self::COMPATIBILITY['unknown'];
            }

            $link = wa()->getAppUrl('installer').'store/theme/'.$theme['id'];
            $html = sprintf(
                _w('For additional information, please contact the developer. Their contact details are available on the  %stheme%s page.'),
                '<a href="'.$link.'" target="_blank">',
                '</a>'
            );
            $themes[] = [
                'name'                      => $theme['name'],
                'version_installed'         => $theme['version'],
                'version_latest'            => ifset($themes_data, $theme['id'], 'version', $theme['version']),
                'link_view'                 => $link,
                'link_update'               => $link,
                'compatibility'             => $theme['compatibility'],
                'compatibility_description' => ifset($theme, 'compatibility_description', $html)
            ];
        }

        return $themes;
    }

    /**
     * @param $apps_name
     * @param $type
     * @return array
     * @throws waException
     */
    private function extrasProduct($apps_name = [], $type = 'plugins')
    {
        if (!wa()->appExists('installer') || empty($apps_name)) {
            return [];
        }
        wa('installer');
        $options = [
            'local'            => true,
            'status'           => false,
            'system'           => true,
            'installed'        => true,
            'translate_titles' => true,
        ];
        if ($type === 'themes') {
            unset($options['system']);
        }

        return installerHelper::getInstaller()->getExtras(
            $apps_name,
            $type,
            $options
        );
    }

    /**
     * @param $slugs
     * @param $themes
     * @return array
     * @throws waException
     */
    private function getStoreProductsData($slugs, $themes = false)
    {
        $result = [];
        if (!wa()->appExists('installer') || empty($slugs)) {
            return $result;
        }
        $fields = [
            'name',
            'icon',
            'price',
            'tags',
        ];

        $renew = !!wa('shop')->getConfig()->getOption('shop_cache_store_data');
        wa('installer');
        if ($themes) {
            $products_data = installerHelper::getStoreThemesData($slugs, $fields, $renew);
        } else {
            $products_data = installerHelper::getStoreProductsData($slugs, $fields, $renew);
        }
        foreach ($products_data as $data) {
            $result[$data['slug']] = $data;
        }

        return $result;
    }

    /**
     * @param $sys_plugins
     * @return array
     * @throws waException
     */
    private function systemPluginsPrepare($sys_plugins)
    {
        $plugins_data = $this->getStoreProductsData(array_column($sys_plugins, 'slug'));
        foreach ($sys_plugins as &$sys_pl) {
            $frac = null;
            $sys_pl['version_latest'] = ifset($plugins_data, $sys_pl['slug'], 'version', null);
            $sys_pl['icon'] = ifset($plugins_data, $sys_pl['slug'], 'icon', '');
            $tags = ifset($plugins_data, $sys_pl['slug'], 'tags', []);
            $support_frac = shopHelper::getShopSupportJson($sys_pl['path']);

            if (file_exists($sys_pl['config_path'])) {
                $config = include($sys_pl['config_path']);
                if (isset($config['fractional_quantity'], $config['stock_units'])) {
                    if ($config['fractional_quantity'] === true && $config['stock_units'] === true) {
                        $frac = self::COMPATIBILITY['yes'];
                    } elseif ($config['fractional_quantity'] === false || $config['stock_units'] === false) {
                        $frac = self::COMPATIBILITY['no'];
                    }
                }
            }

            if (in_array(self::TAG_SHOP_PREMIUM_YES, $tags)) {
                $sys_pl += ['compatibility'=> self::COMPATIBILITY['yes']];
            } elseif (in_array(self::TAG_SHOP_PREMIUM_NO, $tags)) {
                $sys_pl += ['compatibility'=> self::COMPATIBILITY['no']];
            } elseif (!empty($support_frac)) {
                $sys_pl += $support_frac;
            } elseif ($frac === self::COMPATIBILITY['no']) {
                $sys_pl += ['compatibility' => self::COMPATIBILITY['no']];
            } elseif ($frac === self::COMPATIBILITY['yes']) {
                $sys_pl += ['compatibility'=> self::COMPATIBILITY['yes']];
            } else {
                $sys_pl += ['compatibility'=> self::COMPATIBILITY['unknown']];
            }
        }

        return $this->pluginsFormat($sys_plugins);
    }
}
