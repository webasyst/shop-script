<?php

/**
 * Class shopYandexmarketPlugin
 * @see https://help.yandex.ru/partnermarket/yml/about-yml.xml
 */
class shopYandexmarketPlugin extends shopPlugin
{
    private $types;

    private function initTypes()
    {
        $config_path = $this->path.'/lib/config/map.php';
        if (file_exists($config_path)) {
            $data = include($config_path);
            /**
             * @var $data array
             */

            $missed = array();
            $sort = array_flip(array_keys($data['fields']));
            foreach ($data['types'] as &$type) {
                foreach ($type['fields'] as $field => $required) {
                    $field_type = (strpos($field, 'param.') === 0) ? 'param' : $field;
                    if (empty($data['fields'][$field_type])) {
                        $missed[] = $field;
                    }
                    $type['fields'][$field] = array(
                        'required' => $required,
                        'sort'     => ifset($sort[$field_type], 0),
                    );
                    $type['fields'][$field] += ifempty($data['fields'][$field_type], array());
                }
                unset($type);
            }
            $this->types = $data['types'];
        } else {
            $this->types = array();
        }
    }

    public static function getConfigParam($param = null)
    {
        static $config = null;
        if (is_null($config)) {
            $app_config = wa('shop');
            $files = array(
                $app_config->getAppPath('plugins/yandexmarket', 'shop').'/lib/config/config.php', // defaults
                $app_config->getConfigPath('shop/plugins/yandexmarket').'/config.php' // custom
            );
            $config = array();
            foreach ($files as $file_path) {
                if (file_exists($file_path)) {
                    $config = include($file_path);
                    if ($config && is_array($config)) {
                        foreach ($config as $name => $value) {
                            $config[$name] = $value;
                        }
                    }
                }
            }
        }
        return ($param === null) ? $config : (isset($config[$param]) ? $config[$param] : null);
    }


    /**
     * @param array $post
     * @param null $types
     * @param bool $sort
     * @return array[]
     */
    public function map($post = array(), $types = null, $sort = false)
    {
        /**
         * array $map[%type%]['fields'][%field%]
         */
        $this->initTypes();
        $map = $this->types;

        if (is_array($types)) {
            $types['simple'] = true;

            foreach ((array)$map as $type => $info) {
                if (empty($types[$type])) {
                    unset($map[$type]);
                }
            }
        }
        $source_pattern = '@^(.+):%s$@';
        if (!empty($post)) {
            foreach ($post as $type => $info) {
                if (isset($map[$type])) {
                    foreach ($info as $field => $post_data) {
                        $field_type = (strpos($field, 'param.') === 0) ? 'param.*' : $field;
                        if (isset($map[$type]['fields'][$field_type]) && !empty($post_data['source'])) {
                            if (preg_match($source_pattern, $post_data['source'], $matches)) {
                                switch ($source = $matches[1]) {
                                    case 'feature':
                                    case 'text':
                                        if (isset($post_data[$source]) && ($post_data[$source] !== '')) {
                                            $post_data['source'] = $source.':'.$post_data[$source];
                                        } else {
                                            $post_data['source'] = null;
                                        }
                                        break;
                                }
                            }
                            $map[$type]['fields'][$field]['source'] = $post_data['source'];
                        }
                    }
                }
            }
        }

        if ($sort) {
            foreach ($map as &$info) {
                uasort($info['fields'], array(__CLASS__, 'sort'));
                unset($info);
            }
        }

        return $map;
    }

    public function verifyMap()
    {

    }

    /**
     * @param $a
     * @param $b
     * @return mixed
     */
    private static function sort($a, $b)
    {
        $a = ifset($a['sort'], 0);
        $b = ifset($b['sort'], 0);
        return max(-1, min(1, $a - $b));
    }

    /**
     * UI event handler
     * @return array
     */
    public function backendProductsEvent()
    {
        $icon = shopHelper::getIcon($this->getPluginStaticUrl().'img/yandexmarket.png');
        $html = <<<HTML
 <li data-action="export" data-plugin="yandexmarket" title="Экспорт выбранных товаров в Яндекс.Маркет">
    <a href="#">{$icon}Яндекс.Маркет</a>
 </li>
HTML;

        return array(
            'toolbar_export_li' => $html,
        );
    }

    /**
     * @param array $settings
     * @return string
     */
    public function backendCategoryDialog($settings)
    {
        if (!isset($settings['params'])) {
            if (!empty($settings['id'])) {
                $category_params_model = new shopCategoryParamsModel();
                $params = $category_params_model->get($settings['id']);
            } else {
                $params = array();
            }
        } else {
            $params = $settings['params'];
        }
        $control_params = array(
            'namespace'           => 'yandexmarket',
            'value'               => !empty($params['yandexmarket_group_skus']),
            'title'               => 'Яндекс.Маркет',
            'description'         => 'При экспорте в Яндекс.Маркет группировать все артикулы товаров <span class="hint">Настройка учитывается только для случая экспорта каждого артикула товара как отдельной товарной позиции на Яндекс.Маркете (настраивается в профиле экспорта)</span>',
            'title_wrapper'       => '%s',
            'description_wrapper' => '%s',
            'control_wrapper'     => '<div class="name">%s</div><div class="value no-shift"><label>%s %s</label></div>',
        );
        $control = waHtmlControl::getControl(waHtmlControl::CHECKBOX, 'group_skus', $control_params);
        return <<<HTML
<div class="field-group">
    <div class="field">
        {$control}
    </div>
</div>
HTML;


    }

    /**
     * @param array $category
     */
    public function categorySaveHandler($category)
    {
        if (!empty($category['id'])) {
            $category_id = $category['id'];
            $data = waRequest::post($this->id, array());
            $category_params_model = new shopCategoryParamsModel();


            $name = 'yandexmarket_group_skus';
            $value = ifempty($data['group_skus']) ? 1 : 0;
            if ($value) {
                $category_params_model->insert(compact('category_id', 'name', 'value'), 1);
            } else {
                $category_params_model->deleteByField(compact('category_id', 'name'));
            }
        }

    }

    public function categories()
    {
        $available = array(
            'simple'       => 'Упрощенное описание',
            'book'         => 'Книги (book)',
            'audiobook'    => 'Аудиокниги (audiobook)',
            'artist.title' => 'Музыкальная и видео продукция (artist.title)',
            'tours'        => 'Туры (tour)',
            'event-ticket' => 'Билеты на мероприятие (event-ticket)',
            'vendor.model' => 'Произвольный товар (vendor.model)',
        );

        $types = $this->getSettings('types');
        $categories = array();
        $categories['simple'] = $available['simple'];
        $categories['vendor.model'] = $available['vendor.model'];
        foreach ((array)$types as $type => $enabled) {
            if (!empty($enabled) && isset($available[$type])) {
                $categories[$type] = $available[$type];
            }
        }
        return $categories;
    }

    public static function path($file = 'market.xml')
    {
        $path = wa()->getDataPath('plugins/yandexmarket/'.$file, false, 'shop', true);
        if ($file == 'shops.dtd') {
            $original = dirname(__FILE__).'/config/'.$file;
            if (
                !file_exists($path)
                ||
                ((filesize($path) != filesize($original)) && waFiles::delete($path))) {
                waFiles::copy($original, $path);
            }
        }
        return $path;
    }

    public function getInfoByHash($hash)
    {
        $path = null;
        $uuid = $this->getSettings('uuid');
        $profile_id = null;
        if (!is_array($uuid)) {
            if ($uuid == $hash) {
                $path = self::path();
            }
        } else {
            if ((count($uuid) > 1) && isset($uuid[0])) {
                unset($uuid[0]);
            }
            $profile_id = array_search($hash, $uuid);
            if ($profile_id !== false) {
                $path = self::path($profile_id.'.xml');
            }
        }
        return array($path, $profile_id);
    }

    public function getHash($profile = 0)
    {
        $uuid = $this->getSettings('uuid');
        if (!is_array($uuid)) {
            if ($uuid) {
                $uuid = array(
                    0 => $uuid,
                );
            } else {
                $uuid = array();
            }
        }

        if ($profile) {
            $updated = false;
            if ((count($uuid) == 1) && isset($uuid[0])) {
                $uuid[$profile] = $uuid[0];
                $updated = true;
            } elseif (!isset($uuid[$profile])) {
                $uuid[$profile] = self::uuid();
                $updated = true;
            }
            if ($updated) {
                $this->saveSettings(array('uuid' => $uuid));
            }
        }
        return ifset($uuid[$profile]);
    }

    private static function uuid()
    {
        $uuid = sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x', // 32 bits for "time_low"
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff), // 16 bits for "time_mid"
            mt_rand(0, 0xffff), // 16 bits for "time_hi_and_version",
            // four most significant bits holds version number 4
            mt_rand(0, 0x0fff) | 0x4000, // 16 bits, 8 bits for "clk_seq_hi_res",
            // 8 bits for "clk_seq_low",
            // two most significant bits holds zero and one for variant DCE1.1
            mt_rand(0, 0x3fff) | 0x8000, // 48 bits for "node"
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
        return $uuid;
    }

    public static function settingsPrimaryCurrencies()
    {

        $primary = self::getConfigParam('primary_currency');
        $currencies = array();
        $model = new shopCurrencyModel();
        if ($available_currencies = $model->getCurrencies($primary)) {
            $config = wa('shop')->getConfig();
            /**
             * @var shopConfig $config
             */
            $default = $config->getCurrency();
            if (isset($currencies[$default])) {
                $currencies['auto'] = array(
                    'value' => 'auto',
                    'title' => $default.' - Основная валюта магазина.',
                    'rate'  => 0,

                );
            }
            foreach ($available_currencies as $currency) {
                $currencies[$currency['code']] = array(
                    'value' => $currency['code'],
                    'title' => $currency['code'].' - '.$currency['title'],
                    'rate'  => $currency['rate'],

                );
            }
        }
        return $currencies;
    }

    /**
     * @param string $category
     * @return bool
     */
    public function isGroupedCategory($category)
    {
        if (!is_array($category)) {
            $category = explode('/', trim($category, '/'));
        }
        return in_array(reset($category), self::getConfigParam('group_market_category')) && !in_array(end($category), self::getConfigParam('group_market_category_exclude'));
    }
}
