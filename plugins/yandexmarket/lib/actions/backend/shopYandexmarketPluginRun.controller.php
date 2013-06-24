<?php

class shopYandexmarketPluginRunController extends waLongActionController
{

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

    private function initRouting($save = false)
    {
        $routing = wa()->getRouting();
        $domain_routes = $routing->getByApp('shop');
        foreach ($domain_routes as $domain => $routes) {
            foreach ($routes as $route) {
                if ($domain.'/'.$route['url'] == $this->data['domain']) {
                    $routing->setRoute($route, $domain);
                    $this->data['type_id'] = ifempty($route['type_id'], array());
                    if ($this->data['type_id']) {
                        $this->data['type_id'] = array_map('intval', $this->data['type_id']);
                    }
                    waRequest::setParam($route);
                    $this->data['base_url'] = $domain;
                    if ($save) {
                        $this->plugin()->saveSettings(array('domain' => $this->data['domain']));
                    }
                    break;
                }
            }
        }
    }

    protected function init()
    {
        try {
            setlocale(LC_CTYPE, 'ru_RU.CP-1251', 'ru_RU.CP1251', 'ru_RU.win');
            $this->data['path'] = array(
                'offers' => shopYandexmarketPlugin::path(),
            );
            $this->data['offset'] = array(
                'offers' => 0,
            );
            $this->data['domain'] = waRequest::post('domain');
            $this->initRouting(true);

            $options = waRequest::post();
            $options['processId'] = $this->processId;

            $hash = null;
            switch (waRequest::post('hash')) {
                case 'sets':
                    $hash = 'set/'.waRequest::post('set_id', waRequest::TYPE_STRING_TRIM);
                    break;
                case 'types':
                    $hash = 'type/'.waRequest::post('type_id', waRequest::TYPE_INT);
                    break;
                default:
                    $hash = '';
                    break;
            }
            $this->data['timestamp'] = time();
            $this->data['hash'] = $hash;
            $model = new shopCategoryModel();
            $this->data['count'] = array(
                'category' => $model->select('COUNT(1) as `cnt`')->fetchField('cnt'),
                'product'  => $this->getCollection()->count(),
            );
            $stages = array_keys($this->data['count']);
            $this->data['map'] = $this->plugin()->map(waRequest::post('map'));
            $this->data['current'] = array_fill_keys($stages, 0);
            $this->data['processed_count'] = array_fill_keys($stages, 0);
            $this->data['stage'] = reset($stages);
            $this->data['stage_name'] = $this->getStageName($this->data['stage']);
            $this->data['memory'] = memory_get_peak_usage();
            $this->data['memory_avg'] = memory_get_usage();

            $this->dom = new DOMDocument("1.0", "windows-1251");
            /**
             * @var shopConfig $config
             */
            $config = wa('shop')->getConfig();
            $this->dom->encoding = 'windows-1251';
            $this->dom->preserveWhiteSpace = false;
            $this->dom->formatOutput = true;
            $this->dom->loadXML('<?xml version="1.0" encoding="windows-1251"?>
<!DOCTYPE yml_catalog SYSTEM "shops.dtd">
<yml_catalog/>');

            $this->dom->encoding = 'windows-1251';
            $this->dom->preserveWhiteSpace = false;
            $this->dom->formatOutput = true;
            if ($company = waRequest::post('company', $config->getGeneralSettings('name'), waRequest::TYPE_STRING_TRIM)) {
                $this->plugin()->saveSettings(array('company' => $company));
            } else {
                $company = $this->plugin()->getSettings('company');
            }

            $this->dom->lastChild->setAttribute('date', date("Y-m-d H:i"));
            $this->dom->lastChild->appendChild($shop = $this->dom->createElement("shop"));
            $name = $config->getGeneralSettings('name');
            $name = str_replace('&', '&amp;', $name);
            $name = str_replace("'", '&apos;', $name);
            $company = str_replace('&', '&amp;', $company);
            $company = str_replace("'", '&apos;', $company);
            $shop->appendChild($this->dom->createElement('name', $name));
            $shop->appendChild($this->dom->createElement('company', $company));
            $shop->appendChild($this->dom->createElement('url', wa()->getRouteUrl('shop/frontend', array(), true)));
            if ($phone = $config->getGeneralSettings('phone')) {
                $shop->appendChild($this->dom->createElement('phone', $phone));
            }

            $shop->appendChild($this->dom->createElement('platform', 'Webasyst Shop-Script 5'));
            $shop->appendChild($this->dom->createElement('version', wa()->getVersion('shop')));
            /*
             $shop->appendChild($this->dom->createElement('agency', ''));
             if ($email = $config->getGeneralSettings('email')) {
             $shop->appendChild($this->dom->createElement('email', $email));
             }
             */

            $currencies = $this->dom->createElement('currencies');

            $model = new shopCurrencyModel();
            $this->data['currency'] = array();
            $this->data['primary_currency'] = $config->getCurrency();
            foreach ($model->getCurrencies() as $info) {
                if (in_array($info['code'], array('RUR', 'RUB', 'USD', 'BYR', 'KZT', 'EUR', 'UAH'))) {
                    $this->data['currency'][] = $info['code'];
                    $currency = $this->dom->createElement('currency');
                    $currency->setAttribute('id', $info['code']);
                    $currency->setAttribute('rate', $this->format('rate', $info['rate']));
                    $currencies->appendChild($currency);
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
            $values = waRequest::post('shop');
            foreach ($fields as $field => $include_value) {
                if ($value = ifset($values[$field])) {
                    if ($include_value) {
                        $value = ($include_value === true) ? $value : $this->format($field, $value, array('format', $include_value));
                        $shop->appendChild($this->dom->createElement($field, $value));
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
        } catch (waException $ex) {
            $this->error($ex->getMessage());
            echo json_encode(array('error' => $ex->getMessage(), ));
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
        return !$this->isDone();
    }

    protected function finish($filename)
    {
        $result = false;
        if ($this->getRequest()->post('cleanup')) {
            $result = true;
            $this->validate();
        }
        $this->info();
        /**
         * @todo use temp files
         */
        return $result;
    }

    private function validate()
    {
        $this->restore();
        libxml_use_internal_errors(true);
        shopYandexmarketPlugin::path('shops.dtd');
        $valid = $this->dom->validate();
        $strict = waSystemConfig::isDebug();
        if ((!$valid || $strict) && ($r = libxml_get_errors())) {
            $this->data['error'] = array();
            $error = array();
            if ($valid) {
                $this->data['error'][] = array(
                    'level'   => 'info',
                    'message' => 'YML файл валиден',
                );
            } else {

                $this->data['error'][] = array(
                    'level'   => 'error',
                    'message' => 'YML файл содержит ошибки',
                );
            }
            foreach ($r as $er) {
                $this->data['error'][] = $er;
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
            $interval = sprintf(_wp('%02d hr %02d min %02d sec'), floor($interval / 3600), floor($interval / 60) % 60, $interval % 60);
            $report .= ' '.sprintf(_wp('(total time: %s)'), $interval);
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
        if ($this->getRequest()->post('cleanup')) {
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

    protected function restore()
    {

        if (!$this->dom) {
            $this->dom = new DOMDocument("1.0", "windows-1251");
            $this->dom->encoding = 'windows-1251';
            $this->dom->preserveWhiteSpace = false;
            $this->dom->formatOutput = true;
            $this->dom->load($this->data['path']['offers']);
            $this->dom->encoding = 'windows-1251';
            $this->dom->preserveWhiteSpace = false;
            $this->dom->formatOutput = true;
            if (!$this->dom) {
                throw new waException("Error while read saved XML");
            }
        }
        setlocale(LC_CTYPE, 'ru_RU.CP-1251', 'ru_RU.CP1251', 'ru_RU.win');
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
            $this->collection = new shopProductsCollection($this->data['hash']);
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

    private function stepCategory(&$current_stage, &$count, &$processed)
    {
        static $categories;
        if (!$categories) {
            $model = new shopCategoryModel();
            $categories = $model->getFullTree('*');
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

            array_shift($categories);
            ++$current_stage;
            ++$processed;
        }
        return ($current_stage < $count['category']);
    }

    protected function save()
    {
        if ($this->dom) {
            $this->dom->save($this->data['path']['offers']);
        }
    }

    private function getProductFields()
    {
        $fields = array(
            '*',
        );
        foreach ($this->data['map'] as $field => $info) {
            $field = preg_replace('/\..*$/', '', $field);

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
        return implode(',', $fields);
    }

    private function stepProduct(&$current_stage, &$count, &$processed)
    {
        static $products;
        static $features_model;
        if (!$products) {
            $products = $this->getCollection()->getProducts($this->getProductFields(), $current_stage, 200, false);
            if (!$products) {
                $current_stage = $count['product'];
            }
        }
        $nodes = $this->dom->getElementsByTagName('offers');
        $offers = $nodes->item(0);
        $chunk = 50;
        while ((--$chunk > 0) && ($product = reset($products))) {
            $product_xml = $this->dom->createElement("offer");
            $check_type = !$this->data['type_id'] || in_array($product['type_id'], $this->data['type_id']);
            $check_price = $product['price'] > 0;
            $check_category = !empty($product['category_id']);
            if ($check_type && $check_price && $check_category) {
                foreach ($this->data['map'] as $field => $info) {
                    $field = preg_replace('/\..*$/', '', $field);

                    if (!empty($info['source']) && (!ifempty($info['category'], array()) || in_array('simple', $info['category']))) {
                        $value = null;

                        list($source, $param) = explode(':', $info['source'], 2);
                        switch ($source) {
                            case 'field':
                                $value = $this->format($field, ifset($product[$param]), $info, $product);
                                break;
                            case 'value':
                                $value = $this->format($field, $param, $info);
                                break;
                            case 'feature':
                                if (!isset($product['features'])) {
                                    if (!$features_model) {
                                        $features_model = new shopProductFeaturesModel();
                                    }
                                    $product['features'] = $features_model->getValues($product['id']);
                                }
                                $value = $this->format($field, ifempty($product['features'][$param]), $info);
                                break;
                            case 'text':
                                /**
                                 * @todo
                                 */
                                break;
                        }

                        if (!empty($value)) {
                            if (is_array($value)) {
                                foreach ($value as $value_item) {
                                    $product_xml->appendChild($this->dom->createElement($field, $value_item));
                                }
                            } elseif (empty($info['attribute'])) {
                                $child = $this->dom->createElement($field, $value);
                                if ($field == 'categoryId') {
                                    $child->setAttribute('type', 'Own');
                                }
                                $product_xml->appendChild($child);
                            } else {
                                $product_xml->setAttribute($field, $value);
                            }

                        }
                    }
                }
                $offers->appendChild($product_xml);
                ++$processed;
            }
            array_shift($products);
            ++$current_stage;
        }
        return ($current_stage < $count['product']);
    }

    private function format($field, $value, $info = array(), $data = array())
    {

        static $currency_model;
        switch ($field) {
            case 'sales_notes':
                if (mb_strlen($value) > 50) {
                    $value = mb_substr($value, 0, 50);
                }
                break;
            case 'description':
                $value = preg_replace('/<br\/?\s*>/', "\n", $value);
                $value = preg_replace("/[\r\n]+/", "\n", $value);
                $value = strip_tags($value);

                if (mb_strlen($value) > 512) {
                    $value = mb_substr($value, 0, 509).'...';
                }
                break;
            case 'typePrefix':
                $model = new shopTypeModel();
                if ($type = $model->getById($value)) {
                    $value = $type['name'];
                }
                break;
            case 'url':
                $value = 'http://'.ifempty($this->data['base_url'], 'localhost').$value;
                break;
            case 'price':
                if (!in_array($data['currency'], $this->data['currency'])) {
                    if (!$currency_model) {
                        $currency_model = new shopCurrencyModel();
                    }
                    $value = $currency_model->convert($value, $data['currency'], $this->data['primary_currency']);
                }
                break;
            case 'rate':
                $info['format'] = '%0.2f';
                break;
            case 'available':
                $value = ((empty($value) || ($value === 'false')) && ($value !== null)) ? 'false' : 'true';
                break;
            case 'store':
            case 'pickup':
            case 'delivery':
            case 'adult ':
                $value = (empty($value) || ($value === 'false')) ? 'false' : 'true';
                break;
            case 'picture':
                $values = array();
                $limit = 10;
                while (is_array($value) && ($image = array_shift($value)) && !empty($image['url_thumb']) && $limit--) {
                    $values[] = 'http://'.ifempty($this->data['base_url'], 'localhost').$image['url_thumb'];
                }
                $value = $values;
                break;
        }
        $format = ifempty($info['format'], '%s');
        if (is_array($value)) {
            foreach ($value as & $item) {
                $item = str_replace('&nbsp;', ' ', $item);
                $item = str_replace('&', '&amp;', $item);
                $item = $this->sprintf($format, $item);
            }
            unset($item);
        } else {
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
            $value = str_replace(',', '.', sprintf($format, (double) $value));
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
}
