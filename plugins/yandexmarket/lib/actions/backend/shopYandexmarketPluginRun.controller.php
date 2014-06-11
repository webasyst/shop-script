<?php

class shopYandexmarketPluginRunController extends waLongActionController
{
    private $encoding = 'utf-8'; //windows-1251

    private $collection;
    /**
     *
     * @var DOMDocument
     */
    private $dom;

    protected function preExecute()
    {
        $this->getResponse()->addHeader('Content-type', 'application/json');
        $this->getResponse()->sendHeaders();
    }

    private function initRouting()
    {
        $routing = wa()->getRouting();
        $app_id = $this->getAppId();
        $domain_routes = $routing->getByApp($app_id);
        $success = false;
        foreach ($domain_routes as $domain => $routes) {
            foreach ($routes as $route) {
                if ($domain.'/'.$route['url'] == $this->data['domain']) {
                    $routing->setRoute($route, $domain);
                    $this->data['type_id'] = ifempty($route['type_id'], array());
                    if ($this->data['type_id']) {
                        $this->data['type_id'] = array_map('intval', $this->data['type_id']);
                    }
                    waRequest::setParam($route);
                    $this->data['base_url'] = parse_url('http://'.preg_replace('@https?://@', '', $domain), PHP_URL_HOST);
                    $success = true;
                    break;
                }
            }
        }
        if (!$success) {
            throw new waException('Error while select routing');
        }
        $app_settings_model = new waAppSettingsModel();
        $this->data['app_settings'] = array(
            'ignore_stock_count' => $app_settings_model->get($app_id, 'ignore_stock_count', 0)
        );
    }

    protected function init()
    {
        try {
            $backend = (wa()->getEnv() == 'backend');
            $profiles = new shopImportexportHelper('yandexmarket');
            switch ($this->encoding) {
                case 'windows-1251':
                    setlocale(LC_CTYPE, 'ru_RU.CP-1251', 'ru_RU.CP1251', 'ru_RU.win');
                    break;
            }

            $this->data['offset'] = array(
                'offers' => 0,
            );


            $this->data['timestamp'] = time();

            if ($backend) {
                $hash = shopImportexportHelper::getCollectionHash();
                $profile_config = array(
                    'hash'         => $hash['hash'],
                    'domain'       => waRequest::post('domain'),
                    'map'          => array(),
                    'types'        => array_filter((array)waRequest::post('types')),
                    'export'       => (array)waRequest::post('export', array()) + array(
                            'zero_stock' => 0,
                            'sku'        => 0,
                        ),
                    'company'      => waRequest::post('company'),
                    'shop'         => waRequest::post('shop'),
                    'lifetime'     => waRequest::post('lifetime', 0, waRequest::TYPE_INT),
                    'utm_source'   => waRequest::post('utm_source'),
                    'utm_medium'   => waRequest::post('utm_medium'),
                    'utm_campaign' => waRequest::post('utm_campaign'),
                );
                $this->data['map'] = $this->plugin()->map(waRequest::post('map', array()), $profile_config['types']);
                foreach ($this->data['map'] as $type => $offer_map) {
                    $profile_config['map'][$type] = array();
                    foreach ($offer_map['fields'] as $field => $info) {
                        if (!empty($info['source']) && preg_match('@^\\w+:(.+)$@', $info['source'], $matches) && ($matches[1] != '%s')) {
                            $profile_config['map'][$type][$field] = $info['source'];
                        }
                    }
                    if (empty($profile_config['map'][$type])) {
                        unset($profile_config['map'][$type]);
                    }
                }


                foreach ($this->data['map'] as $type => &$offer_map) {
                    if ($type != 'simple') {
                        $offer_map['fields']['type'] = array(
                            'source'    => 'value:'.$type,
                            'attribute' => true,
                        );
                    }
                    unset($offer_map);
                }
                $profile_id = $profiles->setConfig($profile_config);
                $this->plugin()->getHash($profile_id);
            } else {
                $profile_id = waRequest::param('profile_id');;
                if (!$profile_id || !($profile = $profiles->getConfig($profile_id))) {
                    throw new waException('Profile not found', 404);
                }
                $profile_config = $profile['config'];
                $this->data['map'] = $this->plugin()->map(array(), $profile_config['types']);
                foreach ($this->data['map'] as $type => &$offer_map) {
                    foreach ($offer_map['fields'] as $field => &$info) {
                        $info['source'] = ifempty($profile_config['map'][$type][$field], 'skip:');
                    }
                    unset($info);
                    if ($type != 'simple') {
                        $offer_map['fields']['type'] = array(
                            'source'    => 'value:'.$type,
                            'attribute' => true,
                        );
                    }
                }
                unset($offer_map);
            }

            $this->data['hash'] = $profile_config['hash'];


            $this->data['export'] = $profile_config['export'];
            $this->data['domain'] = $profile_config['domain'];
            $this->data['utm'] = array();
            foreach (array('utm_source', 'utm_medium', 'utm_campaign',) as $field) {
                if (!empty($profile_config[$field])) {
                    $this->data['utm'][$field] = $profile_config[$field];
                }
            }
            if ($this->data['utm']) {
                $this->data['utm'] = http_build_query(array_map('rawurlencode', $this->data['utm']));
            }

            $this->data['types'] = array();
            foreach ($profile_config['types'] as $type => $type_map) {
                $this->data['types'] += array_fill_keys(array_filter(array_map('intval', $type_map)), $type);
            }

            $this->initRouting();

            $model = new shopCategoryModel();
            $this->data['count'] = array(
                'category' => $model->select('COUNT(1) as `cnt`')->where('`type`='.shopCategoryModel::TYPE_STATIC)->fetchField('cnt'),
                'product'  => $this->getCollection()->count(),
            );
            $stages = array_keys($this->data['count']);

            $this->data['current'] = array_fill_keys($stages, 0);
            $this->data['processed_count'] = array_fill_keys($stages, 0);
            $this->data['stage'] = reset($stages);
            $this->data['stage_name'] = $this->getStageName($this->data['stage']);
            $this->data['memory'] = memory_get_peak_usage();
            $this->data['memory_avg'] = memory_get_usage();

            if (!class_exists('DOMDocument')) {
                throw new waException('PHP extension DOM required');
            }

            $this->dom = new DOMDocument("1.0", $this->encoding);
            /**
             * @var shopConfig $config
             */
            $config = wa('shop')->getConfig();
            $this->dom->encoding = $this->encoding;
            $this->dom->preserveWhiteSpace = false;
            $this->dom->formatOutput = true;
            $xml = <<<XML
<?xml version="1.0" encoding="{$this->encoding}"?>
<!DOCTYPE yml_catalog SYSTEM "shops.dtd">
<yml_catalog  date="%s">
</yml_catalog>
XML;
            waFiles::copy(shopYandexmarketPlugin::path('shops.dtd'), $this->getTempPath('shops.dtd'));

            $this->dom->loadXML(sprintf($xml, date("Y-m-d H:i")));

            $this->dom->encoding = $this->encoding;
            $this->dom->preserveWhiteSpace = false;
            $this->dom->formatOutput = true;

            $this->dom->lastChild->appendChild($shop = $this->dom->createElement("shop"));
            $name = $config->getGeneralSettings('name');
            $name = str_replace('&', '&amp;', $name);
            $name = str_replace("'", '&apos;', $name);
            $company = str_replace('&', '&amp;', $profile_config['company']);
            $company = str_replace("'", '&apos;', $company);
            $this->addDomValue($shop, 'name', $name);

            $this->addDomValue($shop, 'company', $company);
            $this->addDomValue($shop, 'url', preg_replace('@^https@', 'http', wa()->getRouteUrl('shop/frontend', array(), true)));
            if ($phone = $config->getGeneralSettings('phone')) {
                $shop->appendChild($this->dom->createElement('phone', $phone));
            }

            $this->addDomValue($shop, 'platform', 'Shop-Script');
            $this->addDomValue($shop, 'version', wa()->getVersion('shop'));

            $currencies = $this->dom->createElement('currencies');

            $model = new shopCurrencyModel();
            $this->data['currency'] = array();
            $allowed = array('RUR', 'RUB', 'UAH', 'USD', 'BYR', 'KZT', 'EUR',);
            /**
             * @todo use config
             */
            $this->data['primary_currency'] = $config->getCurrency();
            foreach ($model->getCurrencies() as $info) {
                if (in_array($info['code'], $allowed)) {
                    $this->data['currency'][] = $info['code'];
                    $this->addDomValue($currencies, 'currency', array(
                        'id'   => $info['code'],
                        'rate' => $this->format('rate', $info['rate']),
                    ));
                }
            }
            $shop->appendChild($currencies);

            $shop->appendChild($this->dom->createElement('categories'));

            $fields = array(
                'store'               => true,
                'pickup'              => true,
                'delivery'            => true,
                'deliveryIncluded'    => false,
                'local_delivery_cost' => '%0.2f',
                'adult'               => true,
            );
            foreach ($fields as $field => $include_value) {
                $value = ifset($profile_config['shop'][$field], '');
                if ($value || ($value !== '')) {
                    if ($include_value) {
                        $value = ($include_value === true) ? $value : $this->format($field, $value, array('format', $include_value));
                        $this->addDomValue($shop, $field, $value);
                    } else {
                        $shop->appendChild($this->dom->createElement($field));
                    }
                }
            }

            $shop->appendChild($this->dom->createElement('offers'));
            if (!$this->data['currency']) {
                throw new waException('Не задано ни одной поддерживаемой валюты');
            }
            if (!in_array($this->data['primary_currency'], $this->data['currency'])) {
                $this->data['primary_currency'] = reset($this->data['currency']);
            }

            $this->data['path'] = array(
                'offers' => shopYandexmarketPlugin::path($profile_id.'.xml'),
            );
        } catch (waException $ex) {
            $this->error($ex->getMessage());
            echo json_encode(array('error' => $ex->getMessage(),));
            exit;
        }
    }

    private function getStageName($stage)
    {
        $name = '';
        switch ($stage) {
            case 'category':
                $name = 'Дерево категорий';
                break;
            case 'product':
                $name = 'Товарные предложения';
                break;
        }
        return $name;
    }

    private function getStageReport($stage, $count)
    {
        $info = null;
        if (ifempty($count[$stage])) {
            switch ($stage) {
                case 'category':
                    $info = _w('%d категория', '%d категорий', $count[$stage]);
                    break;
                case 'product':
                    $info = _w('%d товарное предложение', '%d товарных предложения', $count[$stage]);
                    break;
            }
        }
        return $info;

    }

    public function execute()
    {
        try {
            parent::execute();
        } catch (waException $ex) {
            if ($ex->getCode() == '302') {
                echo json_encode(array('warning' => $ex->getMessage()));
            } else {
                echo json_encode(array('error' => $ex->getMessage()));
            }
        }
    }

    protected function isDone()
    {
        $done = true;
        foreach ($this->data['current'] as $stage => $current) {
            if ($current < $this->data['count'][$stage]) {
                $done = false;
                $this->data['stage'] = $stage;
                break;
            }
        }
        $this->data['stage_name'] = $this->getStageName($this->data['stage']);
        return $done;
    }


    /**
     * @uses shopYandexmarketPluginRunController::stepCategory()
     * @uses shopYandexmarketPluginRunController::stepProduct()
     */
    protected function step()
    {
        $stage = $this->data['stage'];

        $method_name = 'step'.ucfirst($stage);
        try {
            if (method_exists($this, $method_name)) {
                $this->$method_name($this->data['current'][$stage], $this->data['count'], $this->data['processed_count'][$stage]);
            } else {
                $this->error(sprintf("Unsupported stage [%s]", $stage));
                $this->data['current'][$stage] = $this->data['count'][$stage];
            }
        } catch (Exception $ex) {
            sleep(5);
            $this->error($stage.': '.$ex->getMessage()."\n".$ex->getTraceAsString());
        }
        return true;
    }

    protected function finish($filename)
    {
        $result = !!$this->getRequest()->post('cleanup');
        try {
            if ($result) {
                $file = $this->getTempPath();
                $target = $this->data['path']['offers'];
                if (file_exists($file)) {
                    waFiles::delete($target);
                    waFiles::copy($file, $target);
                    if (file_exists($target)) {
                        waFiles::delete($file);
                    }
                }
                $this->validate();
            }
        } catch (Exception $ex) {
            $this->error($ex->getMessage());
        }
        $this->info();
        return $result;
    }

    private function validate()
    {
        libxml_use_internal_errors(true);
        shopYandexmarketPlugin::path('shops.dtd');
        $this->loadDom($this->data['path']['offers']);
        $valid = $this->dom->validate();
        $strict = waSystemConfig::isDebug();
        if ((!$valid || $strict) && ($r = libxml_get_errors())) {
            $this->data['error'] = array();
            $error = array();
            if ($valid) {
                $this->data['error'][] = array(
                    'level'   => 'info',
                    'message' => 'YML-файл валиден',
                );
            } else {

                $this->data['error'][] = array(
                    'level'   => 'error',
                    'message' => 'YML-файл содержит ошибки',
                );
            }
            foreach ($r as $er) {
                /**
                 * @var libXMLError $er
                 */

                $level_name = '';
                switch ($er->level) {
                    case LIBXML_ERR_WARNING:
                        $level_name = 'LIBXML_ERR_WARNING';
                        break;
                    case LIBXML_ERR_ERROR:
                        $level_name = 'LIBXML_ERR_ERROR';
                        break;
                    case LIBXML_ERR_FATAL:
                        $level_name = 'LIBXML_ERR_FATAL';
                        break;

                }
                $this->data['error'][] = array(
                    'level'   => $valid?'warning':'error',
                    'message' => "{$level_name} #{$er->code} [{$er->line}:{$er->column}]: {$er->message}",
                );
                $error[] = "Error #{$er->code}[{$er->level}] at [{$er->line}:{$er->column}]: {$er->message}";

            }
            $this->error(implode("\n\t", $error));
        }
    }

    protected function report()
    {
        $report = '<div class="successmsg">';
        $report .= sprintf('<i class="icon16 yes"></i>%s ', _wp('Экспортировано'));
        $chunks = array();
        foreach ($this->data['current'] as $stage => $current) {
            if ($current) {
                if ($data = $this->getStageReport($stage, $this->data['processed_count'])) {
                    $chunks[] = htmlentities($data, ENT_QUOTES, 'utf-8');
                }
            }
        }
        $report .= implode(', ', $chunks);
        if (!empty($this->data['timestamp'])) {
            $interval = time() - $this->data['timestamp'];
            $interval = sprintf(_w('%02d hr %02d min %02d sec'), floor($interval / 3600), floor($interval / 60) % 60, $interval % 60);
            $report .= ' '.sprintf(_w('(total time: %s)'), $interval);
        }
        $report .= '</div>';
        return $report;
    }

    protected function info()
    {
        $interval = 0;
        if (!empty($this->data['timestamp'])) {
            $interval = time() - $this->data['timestamp'];
        }
        $response = array(
            'time'       => sprintf('%d:%02d:%02d', floor($interval / 3600), floor($interval / 60) % 60, $interval % 60),
            'processId'  => $this->processId,
            'stage'      => false,
            'progress'   => 0.0,
            'ready'      => $this->isDone(),
            'count'      => empty($this->data['count']) ? false : $this->data['count'],
            'memory'     => sprintf('%0.2fMByte', $this->data['memory'] / 1048576),
            'memory_avg' => sprintf('%0.2fMByte', $this->data['memory_avg'] / 1048576),
        );

        $stage_num = 0;
        $stage_count = count($this->data['current']);
        foreach ($this->data['current'] as $stage => $current) {
            if ($current < $this->data['count'][$stage]) {
                $response['stage'] = $stage;
                $response['progress'] = sprintf('%0.3f%%', 100.0 * (1.0 * $current / $this->data['count'][$stage] + $stage_num) / $stage_count);
                break;
            }
            ++$stage_num;
        }
        $response['stage_name'] = $this->data['stage_name'];
        $response['stage_num'] = $stage_num;
        $response['stage_count'] = $stage_count;
        $response['current_count'] = $this->data['current'];
        $response['processed_count'] = $this->data['processed_count'];
        if ($response['ready']) {
            $response['report'] = $this->report();
            $response['report'] .= $this->validateReport();
        }
        echo json_encode($response);
    }

    private function validateReport()
    {
        $report = '';
        if (!empty($this->data['error'])) {
            $report .= '<div><ul class="menu-v with-icons">';
            foreach ($this->data['error'] as $error) {

                if (is_object($error) && (get_class($error) == 'LibXMLError')) {
                    switch ($error->level) {
                        case LIBXML_ERR_WARNING:
                            $report .= '<li><i class="icon16 exclamation"></i>';
                            break;
                        case LIBXML_ERR_ERROR:
                            $report .= '<li class="errormsg"><i class="icon16 no"></i>';
                            break;
                        case LIBXML_ERR_FATAL:
                            $report .= '<li class="errormsg"><i class="icon16 status-red"></i>';
                            break;
                    }
                    $report .= "Ошибка валидации XML ({$error->code})<br>";
                    $report .= htmlentities($error->message, ENT_QUOTES, 'utf-8');
                    $report .= "<br>(строка {$error->line}, столбец {$error->column})";
                } elseif (is_array($error)) {
                    switch (ifset($error['level'])) {
                        case 'info':
                            $report .= '<li><i class="icon16 info"></i>';
                            break;
                        case 'warning':
                            $report .= '<li><i class="icon16 exclamation"></i>';
                            break;
                        case 'error':
                        default:
                            $report .= '<li class="errormsg"><i class="icon16 no"></i>';
                            break;
                    }
                    $report .= htmlentities(ifset($error['message']), ENT_QUOTES, 'utf-8');
                } else {
                    $report .= '<li class="errormsg"><i class="icon16 no"></i>';
                    $report .= htmlentities(is_object($error) ? get_class($error) : $error, ENT_QUOTES, 'utf-8');
                }
                $report .= '</li>';
            }
            $report .= '</ul></div>';
        }
        return $report;
    }

    private function loadDom($path = null)
    {

        switch ($this->encoding) {
            case 'windows-1251':
                setlocale(LC_CTYPE, 'ru_RU.CP-1251', 'ru_RU.CP1251', 'ru_RU.win');
                break;
        }
        if (!$path) {
            $path = $this->getTempPath();
        }
        if (!$this->dom) {
            if (!class_exists('DOMDocument')) {
                throw new waException('PHP extension DOM required');
            }
            $this->dom = new DOMDocument("1.0", $this->encoding);
            $this->dom->encoding = $this->encoding;
            $this->dom->preserveWhiteSpace = false;
            $this->dom->formatOutput = true;
            $this->dom->load($path);
            $this->dom->encoding = $this->encoding;
            $this->dom->preserveWhiteSpace = false;
            $this->dom->formatOutput = true;
            if (!$this->dom) {
                throw new waException("Error while read saved XML");
            }
        }
    }

    protected function restore()
    {
        $this->loadDom();
        $this->initRouting();
        $this->collection = null;
    }

    /**
     *
     * @internal param string $hash
     * @return shopProductsCollection
     */
    private function getCollection()
    {
        if (!$this->collection) {
            $options = array(
                'frontend' => true,
            );

            $hash = $this->data['hash'];
            if ($hash == '*') {
                $hash = '';
            }

            $this->collection = new shopProductsCollection($hash, $options);
        }
        return $this->collection;
    }

    /**
     *
     * @return shopYandexmarketPlugin
     */
    private function plugin()
    {
        static $plugin;
        if (!$plugin) {
            $plugin = wa()->getPlugin('yandexmarket');
        }
        return $plugin;
    }

    /**
     * @param $current_stage
     * @param $count
     * @param $processed
     *
     * @return bool
     * @usedby shopYandexmarketPluginRunController::step()
     */
    private function stepCategory(&$current_stage, &$count, &$processed)
    {
        static $categories;
        if (!$categories) {
            $model = new shopCategoryModel();
            $categories = $model->getFullTree('*', true);
            if ($current_stage) {
                $categories = array_slice($categories, $current_stage);
            }
        }
        $chunk = 50;
        while ((--$chunk > 0) && ($category = reset($categories))) {
            $category_xml = $this->dom->createElement("category", str_replace('&', '&amp;', $category['name']));
            $category_xml->setAttribute('id', $category['id']);
            if ($category['parent_id']) {
                $category_xml->setAttribute('parentId', $category['parent_id']);
            }
            $nodes = $this->dom->getElementsByTagName('categories');
            $nodes->item(0)->appendChild($category_xml);
            ++$processed;
            array_shift($categories);
            ++$current_stage;
        }
        return ($current_stage < $count['category']);
    }

    protected function save()
    {
        if ($this->dom) {
            $this->dom->save($this->getTempPath());
        }
    }

    private function getTempPath($file = null)
    {
        if (!$file) {
            $file = $this->processId.'.xml';
        }
        return wa()->getTempPath('plugins/yandexmarket/', 'shop').$file;
    }

    private function getProductFields()
    {
        $fields = array(
            '*',
        );
        foreach ($this->data['map'] as $map) {
            foreach ($map['fields'] as $info) {
                if (!empty($info['source']) && !ifempty($info['category'])) {
                    $value = null;

                    list($source, $param) = explode(':', $info['source'], 2);
                    switch ($source) {
                        case 'field':
                            $fields[] = $param;
                            break;
                    }
                }
            }
        }
        return implode(',', array_unique($fields));
    }

    /**
     * @param $current_stage
     * @param $count
     * @param $processed
     *
     * @return bool
     *
     * @usedby shopYandexmarketPluginRunController::step()
     */
    private function stepProduct(&$current_stage, &$count, &$processed)
    {
        static $products;
        static $sku_model;
        if (!$products) {
            $products = $this->getCollection()->getProducts($this->getProductFields(), $current_stage, 200, false);
            if (!$products) {
                $current_stage = $count['product'];
            }
        }
        $check_stock = !empty($this->data['export']['zero_stock']) || !empty($this->data['app_settings']['ignore_stock_count']);

        $chunk = 50;
        while ((--$chunk > 0) && ($product = reset($products))) {
            $check_type = empty($this->data['type_id']) || in_array($product['type_id'], $this->data['type_id']);


            $check_price = $product['price'] >= 0.5;
            $check_category = !empty($product['category_id']);
            if ($check_type && $check_price && $check_category) {
                if (!empty($this->data['export']['sku'])) {
                    if (empty($sku_model)) {
                        $sku_model = new shopProductSkusModel();
                    }
                    $skus = $sku_model->getDataByProductId($product['id']);
                    foreach ($skus as $sku) {
                        $check_sku_price = $sku['price'] >= 0.5;
                        if ($check_sku_price && ($check_stock || ($sku['count'] === null) || ($sku['count'] > 0))) {
                            if (count($skus) == 1) {
                                $product['price'] = $sku['price'];
                            }
                            $this->addOffer($product, ifempty($this->data['types'][$product['type_id']], 'simple'), (count($skus) > 1) ? $sku : null);
                            ++$processed;
                        }
                    }
                } else {
                    if ($check_stock || ($product['count'] === null) || ($product['count'] > 0)) {
                        $this->addOffer($product, ifempty($this->data['types'][$product['type_id']], 'simple'));
                        ++$processed;
                    }
                }

            }
            array_shift($products);
            ++$current_stage;
        }
        return ($current_stage < $count['product']);
    }

    private function addOffer($product, $type, $sku = null)
    {
        static $features_model;
        static $offers;
        if (empty($offers)) {
            $nodes = $this->dom->getElementsByTagName('offers');
            $offers = $nodes->item(0);
        }
        $product_xml = $this->dom->createElement("offer");
        $offer_map = $this->data['map'][$type]['fields'];
        foreach ($offer_map as $field => $info) {
            $field = preg_replace('/\\..*$/', '', $field);


            if (!empty($info['source']) && (!ifempty($info['category'], array()) || in_array('simple', $info['category']))) {
                $value = null;

                list($source, $param) = explode(':', $info['source'], 2);
                switch ($source) {
                    case 'field':

                        $value = ifset($product[$param]);
                        if (!empty($this->data['export']['sku'])) {
                            switch ($param) {
                                case 'id':
                                    if (!empty($sku['id']) && ($sku['id'] != $product['sku_id'])) {
                                        $value .= 's'.$sku['id'];
                                    }
                                    break;
                                case 'frontend_url':
                                    if (!empty($sku['id']) && ($sku['id'] != $product['sku_id'])) {
                                        if (strpos($value, '?')) {
                                            $value .= '&sku='.$sku['id'];
                                        } else {
                                            $value .= '?sku='.$sku['id'];
                                        }
                                    }

                                    break;
                                case 'name':
                                    if ($sku_value = ifset($sku[$param])) {
                                        $value .= " ({$sku_value})";
                                    }
                                    break;
                                case 'available':
                                    if (!empty($sku)) {

                                        if (empty($sku['available'])) {
                                            $value = false;
                                        } else {
                                            $value = ifset($product[$param]);
                                        }
                                    }
                                    break;
                                case 'price': //currency???
                                case 'count':
                                    $value = ifset($sku[$param], $value);
                                    break;
                            }
                        }
                        $value = $this->format($field, $value, $info, $product, $sku);
                        break;
                    case 'value':
                    case 'text':
                        $value = $this->format($field, $param, $info);
                        break;
                    case 'feature':
                        //TODO use SKU features if available
                        if (!isset($product['features'])) {
                            if (!$features_model) {
                                $features_model = new shopProductFeaturesModel();
                            }
                            $product['features'] = $features_model->getValues($product['id']);
                        }
                        $value = $this->format($field, ifempty($product['features'][$param]), $info);
                        break;
                }

                if (!in_array($value, array(null, false, ''), true)) {
                    $this->addDomValue($product_xml, $field, $value, !empty($info['attribute']));
                }
            }
        }
        $offers->appendChild($product_xml);
    }

    /**
     * @param DOMElement $dom
     * @param string $field
     * @param mixed $value
     * @param bool $is_attribute
     */
    private function addDomValue(&$dom, $field, $value, $is_attribute = false)
    {
        if (is_array($value)) {
            reset($value);
            if (key($value) !== 0) {
                $element = $this->dom->createElement($field, trim(ifset($value['value'])));
                unset($value['value']);

                foreach ($value as $attribute => $attribute_value) {
                    $element->setAttribute($attribute, $attribute_value);
                }
                $dom->appendChild($element);
            } else {
                foreach ($value as $value_item) {
                    $dom->appendChild($this->dom->createElement($field, trim($value_item)));
                }
            }
        } elseif (!$is_attribute) {
            $child = $this->dom->createElement($field, trim($value));
            if ($field == 'categoryId') {
                $child->setAttribute('type', 'Own');
            }
            $dom->appendChild($child);
        } else {
            $dom->setAttribute($field, trim($value));
        }
    }

    private function formatCustom($value, $format)
    {
        $result = null;
        switch ($format) {
            case 'ISO8601': #input value in seconds
                $result = 'P';
                $days = floor($value / (3600 * 24));
                $seconds = $value % (3600 * 24);
                if ($chunk_value = floor($days / 365)) {
                    $result .= sprintf('%dY', $chunk_value);
                    $days -= $chunk_value * 365;
                }
                if ($chunk_value = floor($days / 30)) {
                    $result .= sprintf('%dM', $chunk_value);
                    $days -= $chunk_value * 30;
                }
                if ($days) {
                    $result .= sprintf('%dD', $days);
                }
                if ($seconds) {
                    $result .= 'T';
                    if ($chunk_value = floor($seconds / 3600)) {
                        $result .= sprintf('%dH', $chunk_value);
                        $seconds -= $chunk_value * 3600;
                    }
                    if ($chunk_value = floor($seconds / 60)) {
                        $result .= sprintf('%dM', $chunk_value);
                        $seconds -= $chunk_value * 60;
                    }
                    if ($seconds) {
                        $result .= sprintf('%dS', $seconds);
                    }
                }

                break;
        }
        return $result;
    }

    /**
     * @param string $field
     * @param mixed $value
     * @param array $info
     * @param array $data
     * @param null $sku_data
     * @return mixed|string
     */
    private function format($field, $value, $info = array(), $data = array(), $sku_data = null)
    {
        /**
         * @todo cpa field
         * @todo param (name,unit,value)
         */
        /**
         * <yml_catalog>
         * <shop>
         * <currencies>
         * <categories>
         * <local_delivery_cost>
         * <offers>
         * <picture>
         * <description> и <name>
         * <delivery>, <pickup> и <store>
         * <adult>
         * <barcode>
         * <cpa> TODO
         * <rec>
         * <param>
         * <vendor>
         */

        static $currency_model;
        static $size;
        switch ($field) {
            case 'market_category':
                //it's product constant field
                break;
            case 'name':
                $value = preg_replace('/<br\/?\s*>/', "\n", $value);
                $value = preg_replace("/[\r\n]+/", "\n", $value);
                $value = strip_tags($value);

                $value = trim($value);
                if (mb_strlen($value) > 255) {
                    $value = mb_substr($value, 0, 252).'...';
                }
                break;
            case 'description':
                $value = preg_replace('/<br\/?\s*>/', "\n", $value);
                $value = preg_replace("/[\r\n]+/", "\n", $value);
                $value = strip_tags($value);

                $value = trim($value);
                if (mb_strlen($value) > 512) {
                    $value = mb_substr($value, 0, 509).'...';
                }
                break;
            case 'barcode':
                //может содержать несколько элементов
                $value = preg_replace('@\\D+@', '', $value);
                if (!in_array(strlen($value), array(8, 12, 13))) {
                    $value = null;
                }
                break;
            case 'sales_notes':
                $value = trim($value);
                if (mb_strlen($value) > 50) {
                    $value = mb_substr($value, 0, 50);
                }
                break;
            case 'typePrefix':
                $model = new shopTypeModel();
                if ($type = $model->getById($value)) {
                    $value = $type['name'];
                }
                break;
            case 'url':
                //max 512
                $value = preg_replace_callback('@([^\w\d_/-\?=%&]+)@i', array(__CLASS__, '_rawurlencode'), $value);
                if ($this->data['utm']) {
                    $value .= (strpos($value, '?') ? '&' : '?').$this->data['utm'];
                }
                $value = 'http://'.ifempty($this->data['base_url'], 'localhost').$value;

                break;
            case 'price':
                if ($sku_data) {
                    if (!in_array($data['currency'], $this->data['currency'])) {
                        if (!$currency_model) {
                            $currency_model = new shopCurrencyModel();
                        }

                        $value = $currency_model->convert($value, $data['currency'], $this->data['primary_currency']);
                        $data['currency'] = $this->data['primary_currency'];
                    }
                } else {
                    if (!in_array($data['currency'], $this->data['currency'])) {
                        if (!$currency_model) {
                            $currency_model = new shopCurrencyModel();
                        }
                        $value = $currency_model->convert($value, $this->data['primary_currency'], $data['currency']);
                        $data['currency'] = $this->data['primary_currency'];
                    } elseif ($this->data['primary_currency'] != $data['currency']) {
                        if (!$currency_model) {
                            $currency_model = new shopCurrencyModel();
                        }
                        $value = $currency_model->convert($value, $this->data['primary_currency'], $data['currency']);
                    }
                }
                break;
            case 'currencyId':
                if (!in_array($value, $this->data['currency'])) {
                    $value = $this->data['primary_currency'];
                }
                break;
            case 'rate':
                $info['format'] = '%0.2f';
                break;
            case 'available':
                if (is_object($value)) {
                    switch (get_class($value)) {
                        case 'shopBooleanValue':
                            /**
                             * @var $value shopBooleanValue
                             */
                            $value = $value->value ? 'true' : 'false';
                            break;
                    }
                }
                $value = ((($value <= 0) || ($value === 'false') || empty($value)) && ($value !== null) && ($value !== 'true')) ? 'false' : 'true';
                break;
            case 'store':
            case 'pickup':
            case 'delivery':
            case 'adult ':
                if (is_object($value)) {
                    switch (get_class($value)) {
                        case 'shopBooleanValue':
                            /**
                             * @var $value shopBooleanValue
                             */
                            $value = $value->value ? 'true' : 'false';
                            break;
                    }
                }
                $value = (empty($value) || ($value === 'false')) ? 'false' : 'true';
                break;
            case 'picture':
                //max 512
                $values = array();
                $limit = 10;
                if (!empty($sku_data['image_id'])) {
                    $value = array(ifempty($value[$sku_data['image_id']]));
                }
                while (is_array($value) && ($image = array_shift($value)) && $limit--) {
                    if (!$size) {
                        $shop_config = wa('shop')->getConfig();
                        /**
                         * @var $shop_config shopConfig
                         */
                        $size = $shop_config->getImageSize('big');
                    }
                    $values[] = 'http://'.ifempty($this->data['base_url'], 'localhost').shopImage::getUrl($image, $size);
                }
                $value = $values;
                break;
            case 'page_extent':
                $value = max(1, intval($value));
                break;
            case 'seller_warranty':
            case 'manufacturer_warranty':
            case 'expiry':
                /**
                 * ISO 8601, например: P1Y2M10DT2H30M
                 */
                $pattern = '@P((\d+S)?(\d+M)(\d+D)?)?(T(\d+H)?(\d+M)(\d+S)?)?@';
                $class = is_object($value) ? get_class($value) : false;
                switch ($class) {
                    case 'shopBooleanValue':
                        /**
                         * @var $value shopBooleanValue
                         */
                        $value = $value->value ? 'true' : 'false';
                        break;
                    case 'shopDimensionValue':
                        /**
                         * @var $value shopDimensionValue
                         */
                        $value = $value->convert('s', false);
                        /**
                         * @var $value int
                         */
                        if (empty($value)) {
                            $value = 'false';
                        } else {
                            $value = $this->formatCustom($value, 'ISO8601');
                        }
                        break;
                    default:
                        $value = (string)$value;
                        if (empty($value)) {
                            $value = 'false';
                        } elseif (preg_match('@^\d+$@', trim($value))) {
                            $value = $this->formatCustom(intval($value) * 3600 * 24, 'ISO8601');
                        } elseif (!preg_match($pattern, $value)) {
                            $value = 'true';
                        }
                        break;
                }
                break;
            case 'year':
                if (empty($value)) {
                    $value = null;
                }
                break;
            case 'ISBN':
                /**
                 * @todo verify format
                 * Код книги, если их несколько, то указываются через запятую.
                 * Форматы ISBN и SBN проверяются на корректность. Валидация кодов происходит не только по длине, также проверяется контрольная цифра (check-digit) – последняя цифра кода должна согласовываться с остальными цифрами по определенной формуле. При разбиении ISBN на части при помощи дефиса (например, 978-5-94878-004-7) код проверяется на соответствие дополнительным требованиям к количеству цифр в каждой из частей.
                 * Необязательный элемент.
                 **/
                break;
            case 'recording_length':
                /**
                 * Время звучания задается в формате mm.ss (минуты.секунды).
                 **/
                if (is_object($value)) {
                    switch (get_class($value)) {
                        case 'shopDimensionValue':
                            /**
                             * @var $value shopDimensionValue
                             */
                            $value = $value->convert('s', false);
                            break;
                        default:
                            $value = (int)$value;
                            break;
                    }
                }
                $value = sprintf('%02d.%02d', floor($value / 60), $value % 60);
                break;
            case 'weight':
                /**
                 * Элемент предназначен для указания веса товара. Вес указывается в килограммах с учетом упаковки.
                 * Формат элемента: положительное число с точностью 0.001, разделитель целой и дробной части — точка.
                 * При указании более высокой точности значение автоматически округляется следующим способом:
                 * — если 4-ый знак после разделителя меньше 5, то 3-й знак сохраняется, а все последующие обнуляются;
                 * — если 4-ый знак после разделителя больше или равен 5, то 3-й знак увеличивается на единицу, а все последующие обнуляются.
                 **/
                if (is_object($value)) {
                    switch (get_class($value)) {
                        case 'shopDimensionValue':
                            /**
                             * @var $value shopDimensionValue
                             */
                            if ($value->type == 'weight') {
                                $value = $value->convert('kg', '%0.3f');
                            }
                            break;
                        default:
                            $value = floatval($value);
                            break;
                    }

                } else {
                    $value = floatval($value);
                }
                break;
            case 'dimensions':
                /**
                 *
                 * Элемент предназначен для указания габаритов товара (длина, ширина, высота) в упаковке. Размеры указываются в сантиметрах.
                 * Формат элемента: три положительных числа с точностью 0.001, разделитель целой и дробной части — точка. Числа должны быть разделены символом «/» без пробелов.
                 * При указании более высокой точности значение автоматически округляется следующим способом:
                 * — если 4-ый знак после разделителя меньше 5, то 3-й знак сохраняется, а все последующие обнуляются;
                 * — если 4-ый знак после разделителя больше или равен 5, то 3-й знак увеличивается на единицу, а все последующие обнуляются.
                 **/
                /**
                 * @todo use cm
                 *
                 */
                $parsed_value = array();
                $class = is_object($value) ? get_class($value) : false;
                switch ($class) {
                    case 'shopCompositeValue':
                        /**
                         * @var $value shopCompositeValue
                         */
                        for ($i = 0; $i < 3; $i++) {
                            $value_item = $value[$i];
                            $class_item = is_object($value_item) ? get_class($value_item) : false;
                            switch ($class_item) {
                                case 'shopDimensionValue':
                                    /**
                                     * @var $value_item shopDimensionValue
                                     */
                                    if ($value_item->type == '3d.length') {
                                        $parsed_value[] = $value_item->convert('cm', '%0.4f');
                                    } else {
                                        $parsed_value[] = sprintf('%0.4f', (string)$value_item);
                                    }
                                    break;
                                default:
                                    $parsed_value[] = sprintf('%0.4f', (string)$value_item);
                                    break;
                            }

                        }
                        break;
                    default:
                        $parsed_value = array_map('floatval', explode(':', preg_replace('@[^\d\.,]+@', ':', $value), 3));
                        break;
                }
                foreach ($parsed_value as &$p) {
                    $p = str_replace(',', '.', sprintf('%0.4f', $p));
                    unset($p);
                }
                $value = implode('/', $parsed_value);

                break;
            case 'age':
                /**
                 * @todo
                 * unit="year": 0, 6, 12, 16, 18
                 * unit="month": 0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12
                 */
                if (is_object($value)) {
                    switch (get_class($value)) {
                        case 'shopDimensionValue':
                            /**
                             * @var $value shopDimensionValue
                             */
                            if ($value->type == 'time') {
                                $value = $value->convert('month', false);
                            }
                            break;
                        default:
                            $value = intval($value);
                            break;
                    }

                } else {
                    /**
                     * @var $value shopDimensionValue
                     */
                    if (preg_match('@^(year|month)s?:(\d+)$@', trim($value), $matches)) {
                        $value = array(
                            'unit'  => $matches[1],
                            'value' => intval($matches[2]),
                        );
                    } else {
                        $value = intval($value);
                    }
                }

                if (!is_array($value)) {
                    if ($value > 12) {
                        $value = array(
                            'unit'  => 'year',
                            'value' => floor($value / 12),
                        );
                    } else {
                        $value = array(
                            'unit'  => 'month',
                            'value' => intval($value),
                        );
                    }
                }
                break;
            case 'country_of_origin':
                /**
                 * @todo
                 * @see http://partner.market.yandex.ru/pages/help/Countries.pdf
                 */
                break;
            case 'local_delivery_cost':
                if ($value !== '') {
                    $value = max(0, floatval($value));
                }
                break;
            case 'days':
                $value = max(1, intval($value));
                break;
            case 'dataTour':
                /**
                 * @todo
                 * Даты заездов.
                 * Необязательный элемент. Элемент <offer> может содержать несколько элементов <dataTour>.
                 **/
                break;
            case 'hotel_stars':
                /**
                 * @todo
                 * Звезды отеля.
                 * Необязательный элемент.
                 **/
                break;
            case 'room':
                /**
                 * @todo
                 * Тип комнаты (SNG, DBL, ...).
                 * Необязательный элемент.
                 **/
                break;
            case 'meal':
                /**
                 * @todo
                 * Тип питания (All, HB, ...).
                 * Необязательный элемент.
                 **/
                break;
            case 'date':
                /**
                 * @todo
                 * Дата и время сеанса. Указываются в формате ISO 8601: YYYY-MM-DDThh:mm.
                 **/
                break;
            case 'hall':
                /**
                 * @todo
                 * max 512
                 * Ссылка на изображение с планом зала.
                 **/
                //plan - property
                break;
        }
        $format = ifempty($info['format'], '%s');
        if (is_array($value)) {
            /**
             * @var $value array
             */
            reset($value);
            if (key($value) == 0) {
                foreach ($value as & $item) {
                    $item = str_replace('&nbsp;', ' ', $item);
                    $item = str_replace('&', '&amp;', $item);
                    $item = $this->sprintf($format, $item);
                }
                unset($item);
            }
        } elseif ($value !== null) {
            /**
             * @var $value string
             */
            $value = str_replace('&nbsp;', ' ', $value);
            $value = str_replace('&', '&amp;', $value);
            $value = $this->sprintf($format, $value);
        }
        return $value;
    }

    private function sprintf($format, $value)
    {
        if (preg_match('/^%0\.\d+f$/', $format)) {
            if (strpos($value, ',') !== false) {
                $value = str_replace(',', '.', $value);
            }
            $value = str_replace(',', '.', sprintf($format, (double)$value));
        } else {
            $value = sprintf($format, $value);
        }
        return $value;
    }

    private function error($message)
    {
        $path = wa()->getConfig()->getPath('log');
        waFiles::create($path.'/shop/plugins/yandexmarket.log');
        waLog::log($message, 'shop/plugins/yandexmarket.log');
    }

    private static function _rawurlencode($a)
    {
        return rawurlencode(reset($a));
    }
}
