<?php

/**
 * Class shopYandexmarketPlugin
 * @see https://help.yandex.ru/partnermarket/yml/about-yml.xml
 * @see https://tech.yandex.ru/market/partner/doc/dg/reference/all-methods-docpage/
 */
class shopYandexmarketPlugin extends shopPlugin
{
    private $types;
    private $api_limits;

    private $api_url = 'https://api.partner.market.yandex.ru/v2/';
    private static $level = array(
        'SUBJECT_FEDERATION',
        'REPUBLIC',
    );

    public static function getTTL()
    {
        return 14400;
    }

    public function customMap()
    {
        $app_config = wa('shop');
        $path = $app_config->getConfigPath('shop/plugins/yandexmarket').'/map.php';
        return file_exists($path);
    }

    private function initTypes()
    {
        $app_config = wa('shop');
        $files = array(
            $app_config->getConfigPath('shop/plugins/yandexmarket').'/map.php',
            $app_config->getAppPath('plugins/yandexmarket', 'shop').'/lib/config/map.php',
        );

        $this->types = array();

        foreach ($files as $file_path) {
            if (file_exists($file_path)) {
                $data = include($file_path);
                if (is_array($data)) {
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
                    break;
                }
            }
        }
    }

    public static function getConfigParam($param = null)
    {
        static $config = null;
        if (is_null($config)) {
            $app_config = wa('shop');
            $files = array(
                $app_config->getAppPath('plugins/yandexmarket', 'shop').'/lib/config/config.php', // defaults
                $app_config->getConfigPath('shop/plugins/yandexmarket').'/config.php', // custom
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

                        if (isset($map[$type]['fields'][$field_type]) && !empty($post_data)) {
                            if (is_array($post_data)) {
                                if (!empty($post_data['source'])) {
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
                                if (!empty($post_data['options']) && !empty($map[$type]['fields'][$field]['available_options'])) {
                                    $available_options = $map[$type]['fields'][$field]['available_options'];
                                    $post_options = array_intersect(array_keys($available_options), array_keys(array_filter($post_data['options'])));
                                    $map[$type]['fields'][$field]['options'] = array();
                                    $formatted_post_options = array();
                                    foreach ($post_options as $post_option) {
                                        if (is_array($available_options[$post_option])) {
                                            $value = $post_data['options'][$post_option];
                                            if (isset($available_options[$post_option]['options'][$value])) {
                                                $map[$type]['fields'][$field]['options'][$post_option] = $value;
                                                $formatted_post_options[] = sprintf('%s:%s', $post_option, $value);
                                            }

                                        } else {
                                            $map[$type]['fields'][$field]['options'][$post_option] = true;
                                            $formatted_post_options[] = $post_option;
                                        }

                                    }
                                    $map[$type]['fields'][$field]['source'] .= '@'.implode('@', $formatted_post_options);
                                } elseif (isset($map[$type]['fields'][$field]['options'])) {
                                    unset($map[$type]['fields'][$field]['options']);
                                }
                            } else {
                                $map[$type]['fields'][$field]['source'] = $post_data;
                            }
                        } else {
                            $map[$type]['fields'][$field]['source'] = null;
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

    public static function parseMapOptions(&$source)
    {
        $options = array();
        if ($source && strpos($source, '@')) {
            list($source, $option) = explode('@', $source, 2);
            if (strlen($option)) {
                $option = explode('@', $option);
                foreach ($option as $option_data) {
                    $value = true;
                    if (strpos($option_data, ':')) {
                        list($option_data, $value) = explode(':', $option_data, 2);
                    }
                    $options[$option_data] = $value;
                }
            }
        }
        return $options;
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
 <li data-action="export" data-plugin="yandexmarket" title="Экспорт выбранных товаров в «Яндекс.Маркет»">
    <a href="#">{$icon}Яндекс.Маркет</a>
 </li>
HTML;
        return array(
            'toolbar_export_li' => $html,
        );
    }


    /**
     * UI event handler
     * @param array $order
     * @return array
     */
    public function backendOrderEvent($order)
    {
        if (!empty($order['params']['yandexmarket.id'])) {
            $error = null;
            if (!empty($order['params']['yandexmarket.status'])) {
                try {
                    $data = json_decode($order['params']['yandexmarket.status'], true);
                    $params = array(
                        'yandexmarket.campaign_id' => array('value' => $order['params']['yandexmarket.campaign_id']),
                        'yandexmarket.id'          => array('value' => $order['params']['yandexmarket.id']),
                    );
                    $this->changeOrderState($order['id'], $params, $data);
                    $model = new shopOrderParamsModel();
                    $model->deleteByField(array('id' => $order['id'], 'name' => 'yandexmarket.status'));
                } catch (waException $ex) {
                    $code = $ex->getCode();
                    $message = sprintf("Возникла ошибка с кодом %s при выполнении API-запроса для заказа %s:\n%s", $code, $order['id'], $ex->getMessage());
                    waLog::log($message, 'shop/plugins/yandexmarket/api.order.status.error.log');
                    $message = htmlentities($ex->getMessage(), ENT_NOQUOTES, 'utf-8');
                    $error = <<<HTML
<br/>
<i class="icon16 exclamation"></i>
При обновлении статуса заказа №{$order['params']['yandexmarket.id']} на «Яндекс.Маркет» произошла ошибка <b>{$code}</b>: {$message}
HTML;
                }
            }

            $shipping = array();
            if (!empty($order['params']['shipping_est_delivery'])) {
                $template = <<<HTML
Дата доставки, указанная для программы «Заказ на Маркете»: <b>%s</b>
HTML;
                $shipping[] = sprintf($template, htmlentities($order['params']['shipping_est_delivery'], ENT_NOQUOTES, 'utf-8'));
            }

            if (!empty($order['params']['yandexmarket.outlet_id'])) {

                //$order['params']['yandexmarket.campaign_id'];
                $template = <<<HTML
Выбранная точка продаж для программы «Заказ на Маркете»: <b>%s</b>
HTML;
                $shipping[] = sprintf($template, htmlentities($order['params']['yandexmarket.outlet_id'], ENT_NOQUOTES, 'utf-8'));
            }

            if (!empty($error) || !empty($shipping)) {
                $shipping = implode('<br/>', $shipping);
                $info_section = <<<HTML
<div class="block not-padded">
{$error}
{$shipping}
</div>
HTML;
                return compact('info_section');
            }


        }
        return null;
    }

    /**
     * UI event handler
     * @param array $params
     */
    public function backendReportsChannelsEvent(&$params)
    {
        if (isset($params['plugin_yandexmarket:'])) {
            $params['plugin_yandexmarket:'] = 'Яндекс.Быстрый заказ';
        }
    }

    /**
     * UI event handler
     * @return array
     */
    public function backendReportsEvent()
    {
        //check access via API
        if (true || !$this->checkApi() || !class_exists('shopYandexmarketPluginReportsAction')) {
            return null;
        }
        $menu_item = <<<HTML
<li>
<a href="#/yandexmarket/">Яндекс.Маркет</a>
<script type="text/javascript">
    $(function(){
        $.reports.yandexmarketAction = function(){
            var content=$("#reportscontent");
            content.html('<div class="block double-padded ">Загрузка... <i class="icon16 loading"></i></div>');
            content.load("?plugin=yandexmarket&module=reports"+this.getTimeframeParams());
        };
    });
</script>
</li>

HTML;

        return array('menu_li' => $menu_item);
    }

    /**
     * currency_delete event handler
     * @param $params
     */
    public function currencyDeleteHandler($params)
    {
        $primary_currency = $this->getSettings('primary_currency');
        if (ifset($params['code']) == $primary_currency) {

            $currency = ifempty($params['convert_to']);

            $available_currencies = shopYandexmarketPlugin::settingsPrimaryCurrencies();

            unset($available_currencies['auto']);
            unset($available_currencies['front']);
            if (!empty($available_currencies)) {
                $available_currencies = array_keys($available_currencies);
                if (!in_array($currency, $available_currencies)) {
                    $currency = reset($available_currencies);
                }
                $this->setSettings('primary_currency', $currency);
            }
        }
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
            'description'         => <<<HTML
При экспорте в «Яндекс.Маркет» группировать все артикулы товаров
<span class="hint">
Настройка учитывается только для случая экспорта каждого артикула товара в виде отдельной товарной позиции на «Яндекс.Маркете» (настраивается в профиле экспорта).
</span>
HTML
            ,
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
            if (!file_exists($path)
                ||
                ((filesize($path) != filesize($original)) && waFiles::delete($path))
            ) {
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

    public function getInfoByFeed($feed_id)
    {
        static $updated = false;
        $path = null;
        $campaign_id = null;
        $profile_id = null;
        $feeds = $this->getSettings('feed_map');


        if (is_array($feeds) && !empty($feeds[$feed_id])) {
            list($profile_id, $campaign_id) = explode(':', $feeds[$feed_id]);
            $path = self::path($profile_id.'.xml');
        } else {
            if (!$updated) {
                $updated = true;
                $this->getCampaigns();
                return $this->getInfoByFeed($feed_id);
            }
        }
        return array($path, $profile_id, $campaign_id);
    }

    public function getCampaignByProfile($profile)
    {
        if (is_array($profile)) {
            $profile = ifset($profile['profile_id']);
        }
        if ($profile) {
            if ($feed_map = $this->getSettings('feed_map')) {
                foreach ($feed_map as $feed_id => $item) {
                    list($profile_id, $campaign_id) = explode(':', $item, 2);
                    if ($profile_id == $profile) {
                        return $campaign_id;
                        break;
                    }
                }
            }
        }
        return null;
    }

    public function getAddress(&$profile)
    {
        $address = null;
        if (!empty($profile['home_region_id'])) {
            $region = $this->getRegion($profile['home_region_id']);
        } elseif ($campaign_id = $this->getCampaignByProfile($profile)) {
            if ($region = $this->getCampaignRegion($campaign_id)) {
                $profile['home_region_id'] = $region['id'];
                $profile['campaign_id'] = $campaign_id;
            }
        }
        if (!empty($region)) {
            $address = shopYandexmarketPluginOrder::parseAddress($region, null, true);
        }

        return $address;
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

                $this->setSettings('uuid', $uuid);
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
        $available_currencies = $model->getCurrencies($primary);
        if ($available_currencies) {
            $config = wa('shop')->getConfig();
            /**
             * @var shopConfig $config
             */
            $default = $config->getCurrency();
            if (isset($available_currencies[$default])) {
                $currencies['auto'] = array(
                    'value' => 'auto',
                    'title' => $default.' — основная валюта магазина',
                    'rate'  => 0,

                );
            }

            $currencies['front'] = array(
                'value' => 'front',
                'title' => 'Использовать валюту витрины',
                'rate'  => 0,

            );

            foreach ($available_currencies as $currency) {
                $currencies[$currency['code']] = array(
                    'value' => $currency['code'],
                    'title' => $currency['code'].' — '.$currency['title'],
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

    public function apiRequest($method, $params = array(), $data = array())
    {
        if (!class_exists('waNet')) {
            throw new waException('Отсутствует PHP-класс waNet. Обновите фреймворк Webasyst.');
        }

        //TODO check api limits before run request
        $query = array(
            'oauth_token'     => $this->getSettings('api_oauth_token'),
            'oauth_client_id' => $this->getSettings('api_client_id'),
        );

        $type = 'GET';

        if (count(array_filter($query, 'strlen')) < 2) {
            throw new waException('Не указаны настройки API.');
        }
        //TODO detect by $method request type

        $query = array_merge($query, $params);
        $options = array('format' => waNet::FORMAT_JSON);
        $network = new waNet($options);
        $response = array();
        try {
            if ($data) {
                $data = json_encode($data);
                $type = waNet::METHOD_PUT;
            }
            $url = $this->api_url.$method.'.json?'.http_build_query($query);
            $response = $network->query($url, $data, $type);
            $this->updateApiLimits($method, $network->getResponseHeader());
        } catch (waException $ex) {
            waLog::log($ex->getMessage(), 'shop/plugins/yandexmarket/api.error.log');
            switch ($ex->getCode()) {
                case 420:
                    /**
                     * 420 Enhance Your Calm» с поясняющим сообщением:
                     *         * Hit rate limit of 2 parallel requests
                     */
                    //no-break
                case 400: # ошибки запроса/формата
                case 401: # ошибки запроса/формата
                case 403: # ошибки token
                case 405: # неверный $method
                    $response = $network->getResponse();
                    if (isset($response['error'])) {
                        throw new waException(ifset($response['error']['message']), ifset($response['error']['code']));
                    } else {
                        $response = array();
                    }
                    break;
                case 503:
                    //
                    break;
                default:
                    throw $ex;
                    break;
            }
            if (empty($response)) {
                throw $ex;
            }
        }

        return $response;
    }

    public function checkApi($fast = true)
    {

        $oauth_token = $this->getSettings('api_oauth_token');
        $oauth_client_id = $this->getSettings('api_client_id');
        if (empty($oauth_token) || empty($oauth_client_id)) {
            return false;
        }

        if ($fast) {
            return class_exists('waNet');
        }

        //TODO check real access
        return true;
    }

    public function checkCpa()
    {
        /**
         * @var shopConfig $config ;
         */
        $config = wa('shop')->getConfig();
        $app_info = $config->getInfo();
        return version_compare($app_info['version'], '7.1.1.60', '>=');
    }

    private function updateApiLimits($method, $headers)
    {
        /**
         * X-RateLimit-Resource-Limit: 10000
         * X-RateLimit-Resource-Until: Tue, 17 Apr 2012 00:00:00 GMT
         * X-RateLimit-Resource-Remaining: 9998
         */
        $limits = array(
            'limit'     => (int)$headers['X-RateLimit-Resource-Limit'],
            'until'     => strtotime($headers['X-RateLimit-Resource-Until']),
            'remaining' => (int)$headers['X-RateLimit-Resource-Remaining'],
        );

        $day = 24 * 3600;

        $limits['since'] = $limits['until'] - $day;

        $limits['speed'] = (($limits['limit'] - $limits['remaining']) / $limits['limit']) / ((time() - $limits['since']) / $day);

        if (is_null($this->api_limits)) {
            $this->api_limits = array();
        }
        $this->api_limits[self::normalizeMethod($method)] = $limits;
    }

    private static function normalizeMethod($method)
    {
        return implode('/', array_slice(explode('/', $method), 0, 3));
    }

    public function apiLimits($method = null)
    {
        if ($method) {
            $method = self::normalizeMethod($method);
            return isset($this->api_limits[$method]) ? $this->api_limits[$method] : null;
        } else {
            return $this->api_limits;
        }
    }

    /**
     * @param array $options
     * @param bool [string] $options['offers']
     * @param bool [string] $options['outlets']
     * @param bool [string] $options['balance']
     * @param bool [string] $options['orders']
     * @return null
     * @throws waException
     */
    public function getCampaigns($options = array())
    {
        $hash_pattern = '@/([0-9A-Fa-f]{8}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{12})\.xml$@';

        if (!empty($options['campaign_id'])) {
            $data = $this->apiRequest(sprintf('campaigns/%d', $options['campaign_id']));
            if (!empty($data['campaign'])) {
                $data['campaigns'] = array($data['campaign']);
            }
        } else {
            $data = $this->apiRequest('campaigns');
        }
        $campaigns = array();

        $feed_map = $this->getSettings('feed_map');
        $feed_map_changed = false;
        if (!is_array($feed_map)) {
            $feed_map = array();
        }

        foreach (ifset($data['campaigns'], array()) as $campaign) {
            self::workupCampaign($campaign);

            if (!empty($campaign['settlements']) && (!isset($options['feeds']) || !empty($options['feeds']))) {
                #add feed info
                $data = $this->apiRequest(sprintf('campaigns/%d/feeds', $campaign['id']));
                $campaign['feeds'] = array();
                if (!empty($data['feeds'])) {
                    foreach ($data['feeds'] as $feed) {
                        if (!empty($feed['url']) && preg_match($hash_pattern, $feed['url'], $matches)) {
                            list($feed['path'], $feed['profile_id']) = $this->getInfoByHash(strtolower($matches[1]));
                            if (!empty($feed['profile_id'])) {
                                if (!isset($profiles_list)) {
                                    $profile_helper = new shopImportexportHelper('yandexmarket');
                                    $profiles_list = $profile_helper->getList();
                                }

                                $feed_map_value = sprintf('%d:%d', $feed['profile_id'], $campaign['id']);

                                if (empty($feed_map[$feed['id']]) || ($feed_map[$feed['id']] != $feed_map_value)) {
                                    $feed_map[$feed['id']] = $feed_map_value;
                                    $feed_map_changed = true;
                                }

                                $feed['profile_info'] = ifset($profiles_list[$feed['profile_id']], array());
                            }
                            if (!empty($feed['path']) && file_exists($feed['path'])) {
                                $feed['path_mtime'] = filemtime($feed['path']);
                            }
                        }
                        $campaign['feeds'][$feed['id']] = $feed;
                    }

                }

            }
            #add exported offers info
            if (!empty($options['offers'])) {
                $data = $this->apiRequest(sprintf('campaigns/%d/offers', $campaign['id']));
                $campaign['offers_count'] = ifset($data['pager']['total'], '-');
            }

            #add orders info
            if (!empty($options['orders'])) {
                $params = array(
                    'fromDate' => date('d-m-Y', strtotime('-30days')),
                );
                $data = $this->apiRequest(sprintf('campaigns/%d/orders', $campaign['id']), $params);
                $campaign['orders_count'] = ifset($data['pager']['total'], '-');
            }

            if (!empty($options['bids'])) {
                $data = $this->apiRequest(sprintf('campaigns/%d/bids', $campaign['id']));
                foreach (ifset($data['offers']) as $offer) {
                    if (isset($campaign['feeds'][$offer['id']])) {
                        //TODO
                    }
                }
            }

            #Balance info
            if (!empty($options['balance'])) {
                $data = $this->apiRequest(sprintf('campaigns/%d/balance', $campaign['id']));
                $campaign['balance'] = ifset($data['balance'], array());
                if (isset($campaign['balance']['balance'])) {
                    $campaign['balance']['balance_str'] = sprintf('%0.2f у.е.', $campaign['balance']['balance']);
                }
            }

            #Outlets info
            if (!empty($options['outlets'])) {
                $campaign['outlets'] = $this->getOutlets($campaign['id']);
            }

            #Settings info
            if (!empty($options['settings'])) {
                $campaign += $this->apiRequest(sprintf('campaigns/%d/settings', $campaign['id']));
                if (!empty($campaign['settings']['localRegion']['delivery']['schedule'])) {
                    $model = new shopYandexmarketCampaignsModel();
                    $model->set($campaign['id'], 'schedule', $campaign['settings']['localRegion']['delivery']['schedule']);
                }
            }


            $campaigns[$campaign['id']] = $campaign;
            unset($campaign);
        }

        if ($feed_map_changed) {
            $this->setSettings('feed_map', $feed_map);
        }

        return $campaigns;
    }


    private static function workupCampaign(&$campaign)
    {

        $map = array(
            'state'     => array(
                'name'   => array(
                    1 => 'включена',
                    2 => 'выключена',
                    3 => 'включается',
                    4 => 'выключается',
                ),
                'icon'   => array(
                    1 => 'yes',
                    2 => 'no',
                    3 => 'yes-bw',
                    4 => 'no-bw',
                ),
                'reason' => array(
                    5  => 'кампания проверяется',
                    6  => 'требуется проверка кампании',
                    7  => 'кампания выключена или выключается менеджером',
                    9  => 'кампания выключена или выключается из-за финансовых проблем',
                    11 => 'кампания выключена или выключается из-за ошибок в прайс-листе кампании',
                    12 => 'кампания выключена или выключается пользователем',
                    13 => 'кампания выключена или выключается за неприемлемое качество',
                    15 => 'кампания выключена или выключается из-за обнаружения дублирующих витрин',
                    16 => 'кампания выключена или выключается из-за прочих проблем качества',
                    20 => 'кампания выключена или выключается по расписанию',
                    21 => 'кампания выключена или выключается, так как сайт кампании временно недоступен',
                    24 => 'кампания выключена или выключается за недостаток информации о магазине',
                    25 => 'кампания выключена или выключается из-за неактуальности информации',
                ),
            ),
            'state_cpa' => array(
                'name'   => array(
                    'ON'           => 'включена',
                    'OFF'          => 'отключена',
                    'SWITCHING_ON' => 'в процессе подключения',
                ),
                'icon'   => array(
                    'ON'           => 'yes',
                    'OFF'          => 'no',
                    'SWITCHING_ON' => 'yes-bw',
                ),
                'reason' => array(
                    'CPA_QUALITY_API'   => 'программа отключена из-за проблем обработки запросов через API',
                    'CPA_QUALITY_AUTO'  => 'программа отключена автоматически за ошибки качества',
                    'CPA_API_NEED_INFO' => 'предоставлено недостаточно информации для участия в программе',
                    'CPA_QUALITY_OTHER' => 'программа отключена за прочие проблемы качества',
                    'CPA_CONTRACT'      => 'отсутствует договор об участии в программе, либо истек срок его действия',
                    'CPA_CPC'           => 'программа отключена в связи с тем, что соответствующая кампания выключена (размещение магазина на «Яндекс.Маркете» приостановлено)',
                    'CPA_FEED'          => 'в прайс-листе отсутствуют товарные предложения, участвующие в программе',
                    'CPA_PARTNER'       => 'программа отключена по инициативе пользователя',
                ),
            ),
        );


        #add verbal descriptions for state
        $campaign['stateDescription'] = ifset($map['state']['name'][$campaign['state']], $campaign['state']);
        $campaign['stateIcon'] = ifset($map['state']['icon'][$campaign['state']]);
        if (!empty($campaign['stateReasons'])) {
            foreach ($campaign['stateReasons'] as &$reason) {
                $reason = ifset($map['state']['reason'][$reason], $reason);
                unset($reason);
            }
        }

        #add verbal descriptions for CPA state
        $campaign['stateDescriptionCpa'] = ifset($map['state_cpa']['name'][$campaign['stateCpa']], $campaign['stateCpa']);
        $campaign['stateIconCpa'] = ifset($map['state_cpa']['icon'][$campaign['stateCpa']]);
        $campaign['stateReasonsCpa'] = ifempty($campaign['stateReasonsCpa'], array());
        foreach ($campaign['stateReasonsCpa'] as &$reason) {
            $reason = ifset($map['state_cpa']['reason'][$reason], $reason);
            unset($reason);
        }

        #add settlement info
        $campaign['settlements'] = self::getMatchedSettlements($campaign['domain']);
        $campaigns_model = new shopYandexmarketCampaignsModel();
        if ($domain = $campaigns_model->get($campaign['id'], 'domain')) {
            $campaign['settlements'] += self::getMatchedSettlements($domain);
        }
        $campaign['settlements'] = array_unique($campaign['settlements']);
    }

    public static function getSettlements()
    {
        static $settlements;
        if ($settlements === null) {
            $settlements = array();

            $domain_routes = wa()->getRouting()->getByApp('shop');

            foreach ($domain_routes as $domain => $routes) {
                $original_domain = preg_replace('@^www\.@', '', $domain);
                $domain = waIdna::dec($original_domain);
                if ($original_domain == $domain) {
                    unset($original_domain);
                }
                foreach ($routes as $route) {
                    $settlement = $domain.'/'.$route['url'];
                    if (!empty($original_domain)) {
                        $original_settlement = $original_domain.'/'.$route['url'];
                    }

                    $settlements[] = compact('domain', 'original_domain', 'settlement', 'original_settlement');
                }

            }

            $routes = wa()->getConfig()->getConfigFile('routing');
            foreach ($routes as $domain => $alias) {
                if (!is_array($alias) && isset($domain_routes[$alias])) {
                    $original_domain = preg_replace('@^www\.@', '', $domain);
                    $domain = waIdna::dec($original_domain);
                    if ($original_domain == $domain) {
                        unset($original_domain);
                    }
                    foreach ($domain_routes[$alias] as $route) {
                        $settlement = $domain.'/'.$route['url'];
                        if (!empty($original_domain)) {
                            $original_settlement = $original_domain.'/'.$route['url'];
                        }
                        $settlements[] = compact('alias', 'domain', 'original_domain', 'settlement', 'original_settlement');
                    }
                }
            }
        }

        return $settlements;
    }

    private static function cleanupSettlementDomain($domain)
    {
        return preg_replace('@^www\.@', '', preg_replace('@/.*$@', '', $domain));
    }

    private static function isMatchedDomain($domain, $campaign_domain)
    {
        $settlement_domain =self::cleanupSettlementDomain($domain);
        $l = strlen($campaign_domain) + 1;
        $settlement_parent_domain = substr($settlement_domain, -$l);
        $matched = false;
        if ($settlement_domain == $campaign_domain) {
            $matched = true;
        } elseif ('.'.$campaign_domain == $settlement_parent_domain) {
            $matched = true;
        }
        return $matched;
    }

    private static function getMatchedSettlements($expected_domain)
    {
        $campaign_domains = array(
            preg_replace('@^(www|ввв)\.@ui', '', $expected_domain),
            preg_replace('@^(www|ввв)\.@ui', '', waIdna::dec($expected_domain)),

        );
        $campaign_domains = array_unique($campaign_domains);
        $settlements = self::getSettlements();
        $matched = array();

        $fields = array(
            'domain',
            'original_domain',
            'alias',
            'original_alias',
        );

        foreach ($campaign_domains as $campaign_domain) {
            foreach ($settlements as $settlement) {
                foreach ($fields as $field) {
                    if (!empty($settlement[$field]) && self::isMatchedDomain($settlement[$field], $campaign_domain)) {
                        $matched[] = $settlement['settlement'];
                        break;
                    }
                }
            }
        }

        return $matched;
    }

    /**
     * @param int $campaign_id
     * @param string $start_date
     * @param string $end_date
     * @param string $group_period
     * @return array
     * @throws waException
     * @see https://tech.yandex.ru/market/partner/doc/dg/reference/get-campaigns-id-stats-main-docpage/
     */
    public function getStats($campaign_id, $start_date, $end_date = null, $group_period = 'main')
    {
        $stats = array();
        if (!empty($campaign_id)) {

            if ($start_date) {
                $start_date = date('d-m-Y', strtotime($start_date));
            }
            if ($end_date) {
                $end_date = date('d-m-Y', strtotime($end_date));
            }

            $params = array(
                'fromDate' => $start_date,//Формат даты: ДД-ММ-ГГГГ.
                'toDate'   => $end_date,
                //Максимальный размер отчетного периода: 180 дней.
            );

            $params = array_filter($params);

            $periods = array(
                'main',
                'main-daily',
                'main-weekly',
                'main-monthly',
            );

            if (!in_array($group_period, $periods, true)) {
                $group_period = reset($periods);
            }

            $data = $this->apiRequest(sprintf('campaigns/%d/stats/%s', $campaign_id, $group_period), $params);

            $place_map = array(
                0 => 'common',
                3 => 'search',
                4 => 'card',
                5 => 'market',
                6 => 'partner',
            );

            foreach (ifset($data['mainStats'], array()) as $item) {
                //Формат даты: ГГГГ-ММ-ДД.
                $date = date('Ymd', strtotime($item['date']));

                if (!isset($stats[$date])) {
                    $stats[$date] = array();
                }

                $stats[$date][ifset($place_map[$item], $item)] = array(
                    'clicks'   => ifset($item['clicks'], 0),
                    'spending' => ifset($item['spending'], 0),//у.е.
                );
            }
        }

        return $stats;
    }

    private function getRawOutlets($campaign_id, $region_id = null)
    {
        $params = array(
            'page'     => 0,
            'pageSize' => 50,
        );
        if ($region_id) {
            $params['regionId'] = $region_id;
        }
        $outlets = array();
        do {
            if ($params['page'] > 50) {
                break;
            }
            $data = $this->apiRequest(sprintf('campaigns/%d/outlets', $campaign_id), $params);
            $pager = ifset($data['pager'], array());
            if (!empty($data['outlets'])) {
                foreach ($data['outlets'] as $outlet) {
                    if (!empty($outlet['region'])) {
                        $outlet['region_id'] = self::getOutletRegion($outlet['region']);
                    }

                    $outlets[$outlet['id']] = $outlet;
                }
                ++$params['page'];
            } else {
                break;
            }
        } while (!empty($data['outlets']) && ifset($pager['pageSize']) == $params['pageSize']);

        if (count($outlets) == 2500) {
            $region = $this->getRegions($region_id);
            foreach ($region['children'] as $region_id => $name) {
                $outlets += $this->getRawOutlets($campaign_id, $region_id);
            }
        }

        return $outlets;
    }

    public static function getOutletRegion($region)
    {
        $region_id = $region['id'];
        while (!empty($region['parent'])) {
            $region = $region['parent'];
            $region_id = $region['id'];
            if (in_array($region['type'], self::$level, true)) {
                break;
            }
        }
        return $region_id;
    }

    private static function workupOutlets(&$outlets)
    {
        $map = array(
            'status'     => array(
                'name' => array(
                    'AT_MODERATION' => 'проверяется',
                    'FAILED'        => 'не прошла проверку и отклонена модератором',
                    'MODERATED'     => 'проверена и одобрена',
                    'NONMODERATED'  => 'новая точка, нуждается в проверке',
                ),
                'icon' => array(
                    'AT_MODERATION' => 'yes-bw',
                    'FAILED'        => 'no',
                    'MODERATED'     => 'yes',
                    'NONMODERATED'  => 'no-bw',
                ),
            ),
            'visibility' => array(
                'name' => array(
                    'HIDDEN'  => 'выключена',
                    'UNKNOWN' => 'состояние неизвестно',
                    'VISIBLE' => 'включена',
                ),
                'icon' => array(
                    'HIDDEN'  => 'status-red',
                    'UNKNOWN' => 'status-gray',
                    'VISIBLE' => 'status-green',
                ),
            ),
            'type'       => array(
                'name' => array(
                    'DEPOT'       => 'пункт выдачи заказов',
                    'MIXED'       => 'смешанный тип точки продаж (торговый зал и пункт выдачи заказов)',
                    'NOT_DEFINED' => 'тип точки продаж не определен',
                    'RETAIL'      => 'розничная точка продаж (торговый зал)',
                ),
                'icon' => array(
                    'DEPOT'       => 'ss pt box',
                    'MIXED'       => 'ss pt building',
                    'NOT_DEFINED' => 'status-gray',
                    'RETAIL'      => 'ss shop',
                ),
            ),
        );

        if ($outlets) {
            foreach ($outlets as &$outlet) {
                foreach (array_keys($map) as $field) {
                    $outlet[$field.'Icon'] = ifset($map[$field]['icon'][$outlet[$field]]);
                    $outlet[$field.'Name'] = ifset($map[$field]['name'][$outlet[$field]]);
                }
                if ($outlet['status'] == 'FAILED') {
                    $outlet['statusName'] .= ' '.$outlet['reason'];
                }
                unset($outlet);
            }
        } else {
            $outlets = array();
        }
    }

    /**
     * @param int $campaign_id
     * @param int $region_id
     * @return array
     * @throws waException
     * @see https://tech.yandex.ru/market/partner/doc/dg/reference/get-campaigns-id-outlets-docpage/
     */
    public function getOutlets($campaign_id, $region_id = null)
    {
        $key = sprintf('outlets/%s/%s', $campaign_id, md5(var_export(self::$level, true)));

        $cache = new waVarExportCache($key, self::getTTL(), 'shop/plugins/yandexmarket');
        $outlets = null;
        $data = null;
        if ($region_id) {
            if ($cache->isCached()) {
                $data = $cache->get();

                if (($data['regions'] === true) || in_array($region_id, $data['regions'])) {
                    $outlets = $data['outlets'];
                }
            }
        }
        if (($region_id === null) || ($outlets === null)) {

            if (!$region_id) {
                $root_region = null;
                $campaign_region = $this->getCampaignRegion($campaign_id, true);
                while (!empty($campaign_region['parent'])) {
                    $campaign_region = $campaign_region['parent'];
                    $root_region = $campaign_region['id'];
                };
            } else {
                $root_region = $region_id;
            }
            $outlets = array();

            if ($root_region) {
                $outlets += $this->getRawOutlets($campaign_id, $root_region);
            }

            if ($cache) {
                if (!$data || !$region_id) {
                    $data = array(
                        'regions' => $region_id ? array($region_id) : true,
                        'outlets' => $outlets,
                    );
                } else {
                    $data['regions'][] = $region_id;
                    $data['outlets'] = $outlets;
                }
                $cache->set($data);
            }
        }

        if ($region_id) {
            foreach ($outlets as $id => $outlet) {
                if ($outlet['region_id'] != $region_id) {
                    unset($outlets[$id]);
                }
            }
        }
        self::workupOutlets($outlets);
        return $outlets;
    }

    /**
     * @param $campaign_id
     * @param bool $flush
     * @return array
     * @throws waException
     * @see https://tech.yandex.ru/market/partner/doc/dg/reference/get-campaigns-id-region-docpage/
     */
    public function getCampaignRegion($campaign_id, $flush = false)
    {
        $key = sprintf('region/%s', $campaign_id);
        $cache = new waVarExportCache($key, self::getTTL(), 'shop/plugins/yandexmarket');
        $region = $cache ? $cache->get($key, 'yandexmarket') : null;
        if ($flush || ($region == null)) {
            $data = $this->apiRequest(sprintf('campaigns/%d/region', $campaign_id));
            $region = ifset($data['region'], null);
            if ($cache && ($region !== null)) {
                $cache->set($region);
            }
        }
        return $region;
    }

    /**
     * @param $region_id
     * @param bool $flush
     * @return array
     * @throws waException
     * @see https://tech.yandex.ru/market/partner/doc/dg/reference/get-regions-id-docpage/
     */
    public function getRegion($region_id, $flush = false)
    {
        $key = sprintf('regions/%s', $region_id);
        $cache = new waVarExportCache($key, self::getTTL(), 'shop/plugins/yandexmarket');
        $region = $cache ? $cache->get() : null;
        if ($flush || ($region == null)) {
            $data = $this->apiRequest(sprintf('regions/%d', $region_id));
            $regions = ifset($data['regions'], null);
            $region = reset($regions);
            if ($cache && ($region !== null)) {
                $cache->set($region);
            }
        }
        return $region;
    }

    public function getRegions($region_id = null)
    {
        $region_id = max(0, intval($region_id));
        if ($region_id) {
            $key = sprintf('/regions/%d/children', $region_id);
            $cache = new waVarExportCache($key, self::getTTL(), 'shop/plugins/yandexmarket');
            $region = $cache->get();
            if ($region == null) {

                $params = array(
                    'page'     => 1,
                    'pageSize' => 100,
                );

                $response = $this->apiRequest($key, $params);
                $region = $response['regions'];
                $cache->set($region);
            }
            $region['formatted'] = shopYandexmarketPluginOrder::parseAddress($region, null, true);
            $children = array();
            if (!empty($region['children'])) {
                foreach ($region['children'] as $info) {
                    if (!preg_match('@^(Прочее|Обще|Другие|Универсальн)@ui', $info['name'])) {
                        $children[$info['id']] = $info['name'];
                    }
                }
            }
            $region['children'] = $children;
        } else {
            $region_map = include(dirname(__FILE__).'/config/regions.php');

            $region = array(
                'children' => array(),
            );
            foreach ($region_map as $id => $info) {
                $region['children'][$id] = $info['name'];
            }

        }
        return $region;
    }


    private function setSettings($name, $value)
    {
        if ($this->settings !== null) {
            $this->settings[$name] = $value;
        }
        self::getSettingsModel()->set($this->getSettingsKey(), $name, is_array($value) ? json_encode($value) : $value);
    }

    /**
     * @throws waException
     * @param mixed [string] $data
     * @param int [string] $data[order_id]        Номер заказа
     * @param string [string] $data[action_id]       ID действия
     * @param string [string] $data[before_state_id] ID статуса до выполнения действия
     * @param string [string] $data[after_state_id]  ID статуса после выполнения действия
     * @param int [string] $data[id]              ID записи в логе истории заказа
     * @param string $event_name
     * @link https://tech.yandex.ru/market/partner/doc/dg/reference/put-campaigns-id-orders-id-status-docpage/
     */
    public function orderActionHandler($data, $event_name = null)
    {
        $params = null;
        $action = null;
        if ($this->checkApi()) {
            $available_actions = array('ship', 'complete', 'delete');
            if (in_array($data['action_id'], $available_actions)) {
                $action = $data['action_id'];
            } else {
                foreach ($available_actions as $available_action) {
                    $settings = $this->getSettings('order_action_'.$available_action);
                    $matched_actions = array_keys(array_filter($settings));
                    $matched = in_array($data['action_id'], $matched_actions);
                    if ($matched) {
                        $action = $available_action;
                        break;
                    }
                }
            }
        }
        if ($action && !empty($data['order_id'])) {
            $params_model = new shopOrderParamsModel();
            $search = array(
                'order_id' => $data['order_id'],
                'name'     => array('yandexmarket.id', 'yandexmarket.campaign_id'),
            );
            $params = $params_model->getByField($search, 'name');


            if ($params && (count($params) == 2)) {
                $result = null;
                try {
                    switch ($action) {
                        case 'ship':
                            $order = array(
                                'status' => 'DELIVERY',
                            );
                            break;
                        case 'complete':
                            $order = array(
                                'status' => 'DELIVERED',
                            );
                            break;
                        case 'pickup':
                            $order = array(
                                'status' => 'PICKUP',
                            );
                            break;
                        case 'delete':
                            if (wa()->getEnv() == 'backend') {
                                $post = waRequest::post('plugins');
                                $substatus = 'USER_CHANGED_MIND';
                                if (!empty($post['yandexmarket']['substatus'])) {
                                    $substatus = $post['yandexmarket']['substatus'];
                                    $available = self::getCancelSubstatus();
                                    if (!isset($available[$substatus])) {
                                        $substatus = 'USER_CHANGED_MIND';
                                    }
                                }
                                $order = array(
                                    'status'    => 'CANCELLED',
                                    'substatus' => $substatus,
                                );

                            }
                            break;
                    }
                    if (!empty($order)) {
                        $this->changeOrderState($data['order_id'], $params, $order);
                    }
                } catch (waException $ex) {
                    if (!empty($order)) {
                        $params_model->set($data['order_id'], array('yandexmarket.status' => json_encode($order)), false);
                    }
                    $message = sprintf("Возникла ошибка с кодом %s при выполнении API-запроса для заказа %s:\n%s", $ex->getCode(), $data['order_id'], $ex->getMessage());
                    waLog::log($message, 'shop/plugins/yandexmarket/api.order.status.error.log');
                    throw $ex;
                }
            }
        }
    }

    private function changeOrderState($order_id, $params, $order)
    {
        $method = 'campaigns/%d/orders/%d/status';
        $method = sprintf($method, $params['yandexmarket.campaign_id']['value'], $params['yandexmarket.id']['value']);
        $result = $this->apiRequest($method, array(), compact('order'));
        if ($result) {
            if ($result['order']) {
                $o = $result['order'];
                $template = 'Статус заказа в «Яндекс.Маркете» был обновлен на %s %s';
                $comment = sprintf($template, self::describeStatus($o['status']), self::describeSubStatus(ifset($o['sub_status'])));

                $message = sprintf('Заказ %s(%s) обновлен в «Яндекс.Маркете»: %s', $order_id, $o['id'], $comment);
                waLog::log($message, 'shop/plugins/yandexmarket/api.order.status.log');
            }

        }
    }

    public static function getActions($default_id = null)
    {
        static $actions = null;
        if ($actions === null) {
            $actions = array();
            $workflow = new shopWorkflow();
            $available_actions = $workflow->getAvailableActions();
            foreach ($available_actions as $id => $available_action) {
                $actions[$id] = $available_action['name'];
            }
        }
        if (!empty($default_id) && isset($actions[$default_id])) {
            $result = $actions;
            $result[$default_id] = array(
                'title'    => $actions[$default_id],
                'value'    => $default_id,
                'disabled' => true,
                'checked'  => true,
            );
            return $result;

        }

        return $actions;
    }

    public static function getShipActions()
    {
        return self::getActions('ship');
    }

    public static function getCompleteActions()
    {
        return self::getActions('complete');
    }

    public static function getDeleteActions()
    {
        return self::getActions('delete');
    }

    private static function getCancelSubstatus()
    {
        return array(
            'USER_UNREACHABLE'      => 'не удалось связаться с покупателем',
            'USER_CHANGED_MIND'     => 'покупатель отменил заказ по собственным причинам',
            'USER_REFUSED_DELIVERY' => 'покупателя не устраивают условия доставки',
            'USER_REFUSED_PRODUCT'  => 'покупателю не подошел товар',
            'USER_REFUSED_QUALITY'  => 'покупателя не устраивает качество товара',
            'SHOP_FAILED'           => 'магазин не может выполнить заказ',
        );
    }

    public static function describeSubStatus($sub_status)
    {
        $description = array(
            'RESERVATION_EXPIRED'   => 'покупатель не завершил оформление зарезервированного заказа вовремя',
            'USER_NOT_PAID'         => 'покупатель не оплатил заказ (для типа оплаты PREPAID)',
            'USER_UNREACHABLE'      => 'не удалось связаться с покупателем',
            'USER_CHANGED_MIND'     => 'покупатель отменил заказ по собственным причинам',
            'USER_REFUSED_DELIVERY' => 'покупателя не устраивают условия доставки',
            'USER_REFUSED_PRODUCT'  => 'покупателю не подошел товар',
            'SHOP_FAILED'           => 'магазин не может выполнить заказ',
            'USER_REFUSED_QUALITY'  => 'покупателя не устраивает качество товара',
            'REPLACING_ORDER'       => 'покупатель изменяет состав заказа',
            'PROCESSING_EXPIRED'    => 'магазин не обработал заказ вовремя',
        );
        return ifset($description[$sub_status], $sub_status);
    }

    public static function describeStatus($status)
    {
        $description = array(
            'CANCELLED'  => 'заказ отменен',
            'DELIVERED'  => 'заказ получен покупателем',
            'DELIVERY'   => 'заказ передан в доставку',
            'PICKUP'     => 'заказ доставлен в пункт самовывоза',
            'PROCESSING' => 'заказ находится в обработке',
            'RESERVED'   => 'заказ в резерве (ожидается подтверждение от пользователя)',
            'UNPAID'     => 'заказ оформлен, но еще не оплачен (если выбрана плата при оформлении)',
        );
        return ifset($description[$status], $status);
    }

    /**
     * @param string|array $string
     * @return array
     */
    public static function getDays($string)
    {
        if (!is_array($string)) {
            $string = array_filter(preg_split('@\D+@', trim($string), 2), 'strlen');
        }
        return array_map('intval', $string);
    }

    public function orderDeleteFormHandler($data, $event_name = null)
    {
        $raw_id = null;
        if ($this->checkApi()) {
            if (empty($event_name)) {
                $matched = true;
            } else {
                $action = str_replace('order_action_form.', '', $event_name);
                $settings = $this->getSettings('order_action_delete');
                $matched = in_array($action, array_keys(array_filter($settings)));
            }
            if ($matched && !empty($data['order_id'])) {
                $order_params_model = new shopOrderParamsModel();
                $raw_id = $order_params_model->getOne($data['order_id'], 'yandexmarket.id');
            }
        }
        if (empty($raw_id)) {
            return null;
        } else {
            $sub_status = self::getCancelSubstatus();
            $html = <<<HTML
        Причина отмены заказа:
        <select name="plugins[yandexmarket][substatus]">
HTML;
            foreach ($sub_status as $id => $description) {
                $html .= <<<HTML
<option value="{$id}">{$description}</option>
HTML;

            }
            $html .= "</select>";
            return $html;
        }
    }
}
