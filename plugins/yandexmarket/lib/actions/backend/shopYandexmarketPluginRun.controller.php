<?php

class shopYandexmarketPluginRunController extends waLongActionController
{
    const PRODUCT_PER_REQUEST = 500;
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

    private function initRouting($profile_config = null)
    {
        if ($profile_config) {
            $this->data['domain'] = $profile_config['domain'];
            $this->data['schema'] = empty($profile_config['ssl']) ? 'http://' : 'https://';
            $this->data['utm'] = array();
            foreach (array('utm_source', 'utm_medium', 'utm_campaign',) as $field) {
                if (!empty($profile_config[$field])) {
                    $this->data['utm'][$field] = $profile_config[$field];
                }
            }
            if ($this->data['utm']) {
                $this->data['utm'] = http_build_query(array_map('rawurlencode', $this->data['utm']));
            }

            $this->data['custom_url'] = ifset($profile_config['custom_url']);
        }

        $routing = wa()->getRouting();
        $app_id = $this->getAppId();
        $domain_routes = $routing->getByApp($app_id);
        $success = false;
        $current_domain = $routing->getDomain();
        foreach ($domain_routes as $domain => $routes) {
            foreach ($routes as $route) {
                if ($domain.'/'.$route['url'] == $this->data['domain']) {
                    waRequest::setParam($route);
                    $routing->setRoute($route, $domain);
                    $this->data['type_id'] = ifempty($route['type_id'], array());
                    if ($this->data['type_id']) {
                        $this->data['type_id'] = array_map('intval', $this->data['type_id']);
                    }

                    $base_url = $domain;
                    if (!preg_match('@^https?://@', $base_url)) {
                        $base_url = (waRequest::isHttps() ? 'https://' : 'http://').$base_url;
                    }
                    $this->data['base_url'] = parse_url($base_url, PHP_URL_HOST);
                    $success = true;
                    break;
                }
            }
        }
        if (!$success) {
            throw new waException('Error while select routing');
        }
    }

    /**
     * @param array $items
     * @param int $profile_id
     * @param string $currency
     * @param array $profile
     * @return array
     */
    public static function getCartItems($items, $profile_id, $currency, &$profile)
    {
        $order_items = array();

        $product_ids = array();
        foreach ($items as $item) {
            /**
             * @var array[string]mixed $item
             * @var array[string]int $item[feedId]
             * @var array[string]string $item['offerId']
             * @var array[string]string $item['offerName']
             * @var array[string]int $item['count']
             * @var array[string]string $item['feedCategoryId']
             */

            $product_ids[] = intval(preg_replace('@\D.*$@', '', $item['offerId']));
        }


        $self = new self();

        $hash = sprintf('id/%s', implode(',', array_unique($product_ids)));

        $profile = $self->initCartProfile($hash, $profile_id, $currency);
        $collection = $self->getCollection();
        $products = $collection->getProducts($self->getProductFields(), 0, count($product_ids));

        $sku_ids = array();

        foreach ($items as $item) {

            /**
             * @var int $item ['feedId']
             */
            $id = preg_split('@\D+@', $item['offerId'], 2);
            $product_id = reset($id);
            $product = ifset($products[$product_id]);
            if (count($id) == 2) {
                $sku_id = end($id);
            } else {
                $sku_id = $product['sku_id'];
            }

            $sku_ids[] = $sku_id;

            $order_items[$item['offerId']] = array(
                'create_datetime' => date('Y-m-d H:i:s'),
                'type'            => 'product',
                'product_id'      => $product_id,
                'sku_id'          => $sku_id,
                'sku_code'        => '',
                'name'            => ifset($product['name'], ifset($item['offerName'])),
                'quantity'        => 0,
                'price'           => false,
                'purchase_price'  => false,
                'vat'             => false,
                'profile_id'      => $profile_id,
                'currency'        => ifset($product['currency'], $currency),
                'tax_id'          => ifset($product['tax_id']),
                'raw_data'        => $item,
            );
        }

        $skus_model = new shopProductSkusModel();
        if (!empty($sku_ids)) {
            $skus = $skus_model->select('id,count,price,sku,purchase_price,compare_price,file_name')->where('id IN (i:sku_ids)', compact('sku_ids'))->fetchAll('id');
        } else {
            $skus = array();
        }

        $currency_model = new shopCurrencyModel();

        foreach ($order_items as &$item) {
            if (isset($products[$item['product_id']])) {
                $product = $products[$item['product_id']];

                $sku = $skus[$item['sku_id']];

                $converted = false;

                if (!empty($currency) && ($item['currency'] != $currency)) {
                    if ($sku['purchase_price']) {
                        $sku['purchase_price'] = $currency_model->convert($sku['purchase_price'], $item['currency'], $currency);
                    }
                    if ($sku['price']) {
                        $sku['price'] = $currency_model->convert($sku['price'], $item['currency'], $currency);

                    }
                    $converted = true;
                }

                if (count($product['sku_count']) == 1) {
                    $product['price'] = $sku['price'];
                    $product['compare_price'] = $sku['compare_price'];
                    $product['purchase_price'] = $sku['purchase_price'];
                    $product['file_name'] = $sku['file_name'];
                    $product['sku'] = $sku['sku'];
                    $product['count'] = $sku['count'];
                }

                $offer = $self->addOffer($product, (count($product['sku_count']) == 1) ? null : $sku);

                $item['purchase_price'] = ifset($sku['purchase_price']);
                $item['price'] = ifset($offer['price'], false);
                $item['vat'] = ifset($offer['vat'], false);

                if (class_exists('shopRounding') && $converted) {
                    $item['price'] = shopRounding::roundCurrency($item['price'], $currency);
                }
                $item['price'] = round($item['price']);
                $item['currency'] = ifset($offer['currencyId'], $item['currency']);
                $item['sku_code'] = $sku['sku'];

                if (isset($offer['delivery-options/option'])) {
                    $item['shipping'] = $offer['delivery-options/option'];
                }

                if (isset($offer['available.raw']['raw'])) {
                    if (in_array($offer['available.raw']['raw'], array('true', true, null, ''), true)) {
                        $item['quantity'] = $item['raw_data']['count'];
                    } elseif (in_array($offer['available.raw']['raw'], array('false', false, '0'), true)) {
                        $item['quantity'] = 0;
                    } else {
                        $item['quantity'] = max(0, min(intval($offer['available.raw']['raw']), $item['raw_data']['count']));
                    }
                } else {
                    if ($offer['available'] !== 'true') {
                        $item['quantity'] = 0;
                    }
                }

                $item['delivery'] = !isset($offer['delivery']) || ($offer['delivery'] !== 'false');
                $item['pickup'] = !isset($offer['pickup']) || ($offer['pickup'] !== 'false');

                if (false) {
                    //XXX debug option
                    $item['debug'] = compact('product', 'sku', 'offer');
                }
            }
            unset($item);
        }

        return array_values($order_items);
    }

    /**
     * @param $hash
     * @param $profile_id
     * @param $currency
     * @return shopProductsCollection
     */
    private function initCartProfile($hash, $profile_id, $currency = null)
    {
        $profile_config = $this->initProfile($profile_id);
        $this->data['hash'] = $hash;
        $this->data['currency'] = array($currency);
        $this->data['primary_currency'] = reset($this->data['currency']);
        /**
         * @var shopConfig $config
         */
        $config = wa('shop')->getConfig();
        $this->data['default_currency'] = $config->getCurrency();
        $this->createDom($profile_config, true);
        return $profile_config;
    }

    protected function init()
    {
        try {
            switch ($this->encoding) {
                case 'windows-1251':
                    setlocale(LC_CTYPE, 'ru_RU.CP-1251', 'ru_RU.CP1251', 'ru_RU.win');
                    break;
                case 'utf-8':
                    setlocale(LC_CTYPE, 'ru_RU.UTF-8', 'en_US.UTF-8');
                    break;
            }

            $locale = setlocale(LC_NUMERIC, 0);
            if ($locale !== 'C') {
                if (false === setlocale(LC_NUMERIC, 'C')) {
                    $this->error('setlocale LC_NUMERIC into C failed');
                }
            }

            $this->data['offset'] = array(
                'offers' => 0,
            );

            $this->data['timestamp'] = time();

            $profile_config = $this->initProfile();

            $this->data['hash'] = $profile_config['hash'];
            if (!isset($this->data['categories'])) {
                $this->data['categories'] = array();
            }

            $this->data['export'] = $profile_config['export'];

            $this->data['force_update'] = !empty($profile_config['force_update']);

            $this->data['trace'] = !empty($profile_config['trace']);
            $this->data['profile_id'] = $profile_config['profile_id'];

            $this->data['can_use_smarty'] = $this->getConfig()->getOption('can_use_smarty');

            $this->initRouting($profile_config);

            $this->initCount();

            $this->checkDtdFile();
            $this->createDom($profile_config);
            $this->loadDom();
        } catch (waException $ex) {
            if (class_exists('shopYandexmarketPluginExportCli', false)) {
                throw $ex;
            } else {
                $this->error($ex->getMessage());
                echo json_encode(array('error' => $ex->getMessage(),));
                exit;
            }
        }
    }

    private function initProfile($profile_id = null)
    {
        $default_export_config = array(
            'zero_stock'        => 0,
            'compare_price'     => 0,
            'purchase_price'    => 0,
            'sku'               => 0,
            'sku_group'         => '',
            'hidden_categories' => 0,
            'min_price'         => 0.5,
            'skip_ignored'      => false,
            'shipping_methods'  => array(),
        );

        $backend = (wa()->getEnv() == 'backend');
        $profiles = new shopImportexportHelper('yandexmarket');
        if ($backend && empty($profile_id)) {
            $hash = shopImportexportHelper::getCollectionHash();
            $profile_config = array(
                'hash'             => $hash['hash'],
                'domain'           => waRequest::post('domain'),
                'ssl'              => waRequest::post('ssl'),
                'map'              => array(),
                'types'            => array_filter((array)waRequest::post('types')),
                'export'           => (array)waRequest::post('export', array()) + $default_export_config,
                'company'          => waRequest::post('company'),
                'company_name'     => waRequest::post('company_name'),
                'company_phone'    => waRequest::post('company_phone'),
                'shop'             => waRequest::post('shop'),
                'lifetime'         => waRequest::post('lifetime', 0, waRequest::TYPE_INT),
                'force_update'     => waRequest::post('force_update', 0, waRequest::TYPE_INT),
                'utm_source'       => waRequest::post('utm_source'),
                'utm_medium'       => waRequest::post('utm_medium'),
                'utm_campaign'     => waRequest::post('utm_campaign'),
                'custom_url'       => waRequest::post('custom_url'),
                'shipping_methods' => array(),
                'home_region_id'   => waRequest::post('home_region_id', 0, waRequest::TYPE_INT),
                'trace'            => !!waRequest::post('trace'),
            );

            $shipping_methods = waRequest::post('shipping_methods');

            if (!empty($shipping_methods) && is_array($shipping_methods)) {
                foreach ($shipping_methods as $id => $shipping_params) {
                    if (!empty($shipping_params['enabled'])) {
                        $shipping_params = array_map('trim', $shipping_params);
                        $profile_config['shipping_methods'][$id] = array(
                            'estimate' => $shipping_params['estimate'],
                        );

                        if (isset($shipping_params['cost']) && ($shipping_params['cost'] != '')) {
                            $profile_config['shipping_methods'][$id]['cost'] = $shipping_params['cost'];
                        }

                        if (isset($shipping_params['order-before']) && ($shipping_params['order-before'] != '')) {
                            $profile_config['shipping_methods'][$id]['order-before'] = $shipping_params['order-before'];
                        }
                    }
                }
            }

            $this->data['map'] = $this->plugin()->map(waRequest::post('map', array()), $profile_config['types']);
            foreach ($this->data['map'] as $type => $offer_map) {
                foreach ($offer_map['fields'] as $field => $info) {
                    if (!empty($info['source']) && preg_match('@^\\w+:(.+)$@', $info['source'], $matches) && ($matches[1] != '%s')) {
                        $profile_config['map'][$type][$field] = $info['source'];
                    }
                }
                if (empty($profile_config['map'][$type])) {
                    unset($profile_config['map'][$type]);
                }
            }

            $profile_id = $profiles->setConfig($profile_config);
            $this->plugin()->getHash($profile_id);
        } else {
            if (empty($profile_id)) {
                $profile_id = waRequest::param('profile_id');
            }

            if (!$profile_id) {
                throw new waException('Missed profile id', 404);
            }

            $profile = $profiles->getConfig($profile_id);

            if (empty($profile['config'])) {
                throw new waException(sprintf('Profile %d not found', $profile_id), 404);
            }

            $profile_config = $profile['config'];

            $profile_config['export'] += $default_export_config;
            $this->data['map'] = $this->plugin()->map($profile_config['map'], $profile_config['types']);

            foreach ($this->data['map'] as $type => &$offer_map) {
                foreach ($offer_map['fields'] as $field => &$info) {
                    $info['source'] = ifempty($profile_config['map'][$type][$field], 'skip:');
                    unset($info);
                }
                unset($offer_map);
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

        $taxes = false;

        $feature_model = new shopFeatureModel();

        $setup_fields = array('name', 'description', 'help', 'required', 'function', 'sort', 'type', 'values', 'params', 'test', 'available_options');

        foreach ($this->data['map'] as $type => &$offer_map) {
            if (isset($offer_map['name'])) {
                unset($offer_map['name']);
            }
            foreach ($offer_map['fields'] as $field => &$info) {
                if (empty($info['source']) || ($info['source'] == 'skip:')) {
                    unset($offer_map['fields'][$field]);
                } else {
                    foreach ($setup_fields as $info_field) {
                        if (isset($info[$info_field])) {
                            unset($info[$info_field]);
                        }
                    }

                    if (isset($info['source'])) {
                        if (strpos($info['source'], ':')) {
                            list($source, $value) = explode(':', $info['source'], 2);
                            switch ($source) {
                                case 'feature':
                                    if (strpos($field, 'param.') === 0) {
                                        $unit = null;
                                        if (strpos($value, ':')) {
                                            list($value, $unit) = explode(':', $value, 2);
                                            $info['source'] = 'feature:'.$value;
                                        }
                                        $info['source_unit'] = $unit;
                                        $feature_code = $value;
                                        if ($feature = $feature_model->getByCode($feature_code)) {
                                            $info['source_name'] = $feature['name'];
                                        }
                                    }
                                    break;
                                case 'field':
                                    if ($value == 'tax_id') {
                                        $taxes = true;
                                    }
                                    break;
                            }
                        }
                    }
                }
                unset($info);
            }
            unset($offer_map);
        }

        $this->data['types'] = array();
        foreach ($profile_config['types'] as $type => $type_map) {
            $this->data['types'] += array_fill_keys(array_filter(array_map('intval', $type_map)), $type);
        }

        $profile_config['profile_id'] = $profile_id;

        if (!empty($taxes)) {
            $this->initTaxes($profile_config);
        }

        return $profile_config;
    }

    private function initTaxes($profile_config)
    {
        $region_id = ifset($profile_config['home_region_id']);
        $address = array(
            'country' => null,
            'region'  => null,
        );

        if ($region_id) {
            //TODO use home region data
        }

        $result = array();
        $tm = new shopTaxModel();
        $trm = new shopTaxRegionsModel();
        $taxes = $tm->getAll();
        foreach ($taxes as $t) {

            $result[$t['id']] = array(
                'rate'     => 0.0,
                'included' => $t['included'],
                'name'     => $t['name'],
            );

            // Check if there are rates based on country and region
            $result[$t['id']]['rate'] = $trm->getByTaxAddress($t['id'], $address);
        }

        $tax_ids = array_keys($result);

        // Rates by zip code override rates by region, when applicable
        $main_country = wa()->getSetting('country', null, 'shop');
        foreach (array('shipping', 'billing') as $addr_type) {
            // ZIP-based rates are only applied to main shop country
            if (empty($address['zip']) || (!empty($address['country']) && $address['country'] !== $main_country)) {
                continue;
            }

            $tzcm = new shopTaxZipCodesModel();
            foreach ($tzcm->getByZip($address['zip'], $addr_type, $tax_ids) as $tax_id => $rate) {
                $result[$tax_id]['rate'] = $rate;
                $result[$tax_id]['name'] = $taxes[$tax_id]['name'];
            }
        }

        $this->data['taxes'] = $result;
    }

    private function getAvailableCurrencies()
    {
        $model = new shopCurrencyModel();
        $this->data['currency'] = array();

        $available_currencies = shopYandexmarketPlugin::settingsPrimaryCurrencies();
        if (empty($available_currencies)) {
            throw new waException('Экспорт не может быть выполнен: не задано ни одной валюты, которая могла бы использоваться в качестве основной.');
        }
        unset($available_currencies['auto']);
        unset($available_currencies['front']);

        $primary_currency = $this->plugin()->getSettings('primary_currency');
        /**
         * @var shopConfig $config
         */
        $config = wa('shop')->getConfig();
        $this->data['default_currency'] = $config->getCurrency();
        switch ($primary_currency) {
            case 'auto':
                $primary_currency = $this->data['default_currency'];
                break;
            case 'front':
                if (waRequest::param('currency')) {
                    $primary_currency = waRequest::param('currency');
                } else {
                    $primary_currency = $this->data['default_currency'];
                }
                break;
        }

        if (!isset($available_currencies[$primary_currency])) {
            $primary_currency = $this->data['default_currency'];
            if (!isset($available_currencies[$primary_currency])) {
                reset($available_currencies);
                $primary_currency = key($available_currencies);
            }
        }

        $this->data['primary_currency'] = $primary_currency;

        if ($this->plugin()->getSettings('convert_currency')) {
            $available_currencies = $model->getCurrencies($primary_currency);
        } else {
            $available_currencies = $model->getCurrencies(shopYandexmarketPlugin::getConfigParam('currency'));
        }

        return $available_currencies;
    }

    private function checkDtdFile()
    {
        $original = shopYandexmarketPlugin::path('shops.dtd');

        $target = $this->getTempPath('shops.dtd');
        if (!file_exists($target)
            ||
            ((filesize($target) != filesize($original)) && waFiles::delete($target))
        ) {
            waFiles::copy($original, $target);
        }
    }

    private function createDom($profile_config, $fast = false)
    {
        if (!class_exists('DOMDocument')) {
            throw new waException('PHP extension DOM required');
        }

        $this->dom = new DOMDocument("1.0", $this->encoding);
        $this->dom->encoding = $this->encoding;
        $this->dom->preserveWhiteSpace = false;
        $this->dom->formatOutput = true;

        $xml = <<<XML
<?xml version="1.0" encoding="{$this->encoding}"?>
<!DOCTYPE yml_catalog SYSTEM "shops.dtd">
<yml_catalog  date="%s">
</yml_catalog>
XML;

        $this->dom->loadXML(sprintf($xml, date("Y-m-d H:i")));


        #create yml_catalog/shop
        $shop = $this->dom->createElement("shop");
        $this->dom->lastChild->appendChild($shop);

        if (!$fast) {
            /**
             * @var shopConfig $config
             */
            $config = wa('shop')->getConfig();
            $name = ifempty($profile_config['company_name'], $config->getGeneralSettings('name'));
            $name = str_replace('&', '&amp;', $name);
            $name = str_replace("'", '&apos;', $name);
            $this->addDomValue($shop, 'name', $name);

            $company = str_replace('&', '&amp;', $profile_config['company']);
            $company = str_replace("'", '&apos;', $company);
            $this->addDomValue($shop, 'company', $company);

            $url = wa()->getRouteUrl('shop/frontend', array(), true);
            $this->addDomValue($shop, 'url', preg_replace('@^https?://@', $this->data['schema'], $url));
            $phone = !empty($profile_config['company_phone']) ? $profile_config['company_phone'] : $config->getGeneralSettings('phone');
            if ($phone) {
                $shop->appendChild($this->dom->createElement('phone', $phone));
            }

            $this->addDomValue($shop, 'platform', 'Shop-Script');
            $this->addDomValue($shop, 'version', wa()->getVersion('shop'));


            #create yml_catalog/currencies
            $currencies = $this->dom->createElement('currencies');
            $available_currencies = $this->getAvailableCurrencies();
            $rate = $available_currencies[$this->data['primary_currency']]['rate'];

            foreach ($available_currencies as $info) {
                if ($info['rate'] > 0) {
                    $info['rate'] = $info['rate'] / $rate;
                    $this->data['currency'][] = $info['code'];
                    if (abs(round($info['rate'], 4) - $info['rate']) / $info['rate'] > 0.01) {
                        $info['rate'] = shopYandexmarketPlugin::getConfigParam('currency_source');
                        if (!in_array($info['rate'], array('CB', 'CBRF', 'NBU', 'NBK'))) {
                            $info['rate'] = 'CB';
                        }
                    }

                    $value = array(
                        'id'   => $info['code'],
                        'rate' => $this->format('rate', $info['rate']),
                    );
                    $this->addDomValue($currencies, 'currency', $value);
                }
            }

            if (!$this->data['currency']) {
                throw new waException('Не задано ни одной поддерживаемой валюты');
            }
            if (!in_array($this->data['primary_currency'], $this->data['currency'])) {
                $this->data['primary_currency'] = reset($this->data['currency']);
            }
            $shop->appendChild($currencies);


            #create yml_catalog/categories
            $shop->appendChild($this->dom->createElement('categories'));

            $delivery_options = $this->dom->createElement('delivery-options');
        }

        $fields = array(
            'store'               => true,
            'pickup'              => true,
            'delivery'            => true,
            'deliveryIncluded'    => false,
            'local_delivery_cost' => '%0.2f',
            'adult'               => true,
        );

        $days = shopYandexmarketPlugin::getDays(ifset($profile_config['shop']['local_delivery_estimate']));

        if (count($days) == 2) {
            sort($days);
            $days = implode('-', $days);

        } elseif (count($days) == 1) {
            $days = max(0, $days);

        } else {
            $days = '';
        }

        $delivery_option = array(
            //стоимость доставки в рублях (wtf? а как же мультивалютность и рынки UAH/KAZ/etc?)
            'cost' => sprintf('%d', max(0, floatval($profile_config['shop']['local_delivery_cost']))),

            //срок доставки в рабочих днях;
            'days' => $days,
        );

        $order_before = null;

        if (!empty($profile_config['shop']['local_delivery_order_before'])) {
            //время (только часы) оформления заказа, до наступления которого действуют указанные сроки и условия доставки.
            $order_before = $profile_config['shop']['local_delivery_order_before'];
        }

        if ($order_before !== null) {
            $delivery_option['order-before'] = max(0, min(24, intval($order_before)));
        }

        $this->data['delivery-option'] = $delivery_option;

        if (!$fast) {
            $delivery_options_exists = false;
            if (ifset($profile_config['shop']['local_delivery_enabled']) !== 'skip') {
                $this->addDomValue($delivery_options, 'option', $delivery_option);
                $delivery_options_exists = true;
            }
            unset($delivery_option);

            $api_available = $this->plugin()->checkApi();

            if ($profile_config['shipping_methods'] && $api_available) {

                try {
                    $address = $this->plugin()->getAddress($profile_config);
                } catch (waException $ex) {
                    $this->error($ex->getMessage());
                    $address = array('data' => array());
                }

                $items = array(
                    array(
                        'weight'   => 1.0,//base unit - kg
                        'price'    => 0,
                        'quantity' => 1,
                    ),
                );
                $shipping_params = array(
                    'no_external' => true,
                );
                if (!empty($address['data'])) {
                    $shipping_methods = shopHelper::getShippingMethods($address['data'], $items, $shipping_params);
                    foreach ($profile_config['shipping_methods'] as $id => $shipping_params) {
                        if (isset($shipping_methods[$id])) {
                            $shipping_params['estimate'] = array_map('intval', preg_split('@\D+@', trim(ifset($shipping_params['estimate'], '32')), 2));
                            sort($shipping_params['estimate']);
                            $cost = $shipping_methods[$id]['rate'];
                            if (isset($shipping_params['cost']) && ($shipping_params['cost'] !== '')) {
                                $cost = $shipping_params['cost'];
                            }
                            $delivery_option = array(
                                'cost' => round(max(0, $cost)),
                                'days' => implode('-', array_unique($shipping_params['estimate'])),
                            );
                            //XXX use rounding options for delivery cost

                            if (isset($shipping_params['order-before']) && !in_array($shipping_params['order-before'], array(null, ''), true)) {
                                $delivery_option['order-before'] = max(0, min(24, intval($shipping_params['order-before'])));
                            } elseif ($order_before !== null) {
                                $delivery_option['order-before'] = max(0, min(24, intval($order_before)));
                            }

                            if ($delivery_option['cost'] !== '') {
                                $this->addDomValue($delivery_options, 'option', $delivery_option);
                                $delivery_options_exists = true;
                            }
                            unset($delivery_option);
                        }
                    }
                }
            }
            if ($delivery_options_exists && !empty($delivery_options)) {
                $shop->appendChild($delivery_options);
            }

            unset($fields['local_delivery_cost']);

            foreach ($fields as $field => $include_value) {
                $value = ifset($profile_config['shop'][$field], '');
                if ($value || ($value !== '')) {
                    if ($include_value) {
                        if ($include_value !== true) {
                            $value = $this->format($field, $value, array('format', $include_value));
                        }
                        $this->addDomValue($shop, $field, $value);
                    } else {
                        $shop->appendChild($this->dom->createElement($field));
                    }
                }
            }

        }
        // create yml_catalog/offers
        $shop->appendChild($this->dom->createElement('offers'));

        if (!$fast) {
            $this->data['path'] = array(
                'offers' => shopYandexmarketPlugin::path($profile_config['profile_id'].'.xml'),
            );
            $this->save();
            $this->dom = null;
        }
    }

    private function initCount()
    {
        $model = new shopCategoryModel();
        if (empty($this->data['export']['hidden_categories'])) {
            $sql = <<<SQL
SELECT COUNT(1) as `cnt`
FROM shop_category c
LEFT JOIN shop_category_routes cr ON (c.id = cr.category_id)
WHERE
  ((cr.route IS NULL) OR (cr.route = s:route))
   AND
  (`c`.`type`=i:type)
  AND
  (`c`.`status`=1)

SQL;
        } else {
            $sql = <<<SQL
SELECT COUNT(1) as `cnt`
FROM shop_category c
LEFT JOIN shop_category_routes cr ON (c.id = cr.category_id)
WHERE
  ((cr.route IS NULL) OR (cr.route = s:route))
   AND
  (`c`.`type`=i:type)

SQL;
        }

        $params = array(
            'route' => $this->data['domain'],
            'type'  => shopCategoryModel::TYPE_STATIC,
        );


        $this->data['count'] = array(
            'category'         => (int)$model->query($sql, $params)->fetchField('cnt'),
            'delivery_options' => 0,//
            'product'          => $this->getCollection()->count(),
        );
        $stages = array_keys($this->data['count']);

        $this->data['current'] = array_fill_keys($stages, 0);
        $this->data['processed_count'] = array_fill_keys($stages, 0);
        $this->data['stage'] = reset($stages);
        $this->data['stage_name'] = $this->getStageName($this->data['stage']);
        $this->data['memory'] = memory_get_peak_usage();
        $this->data['memory_avg'] = memory_get_usage();
    }

    private function getStageName($stage)
    {
        $name = '';
        switch ($stage) {
            case 'category':
                $name = 'Дерево категорий';
                break;
            case 'delivery_options':
                $name = 'Стоимость доставки';
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
                case 'delivery_options':
                    $info = _w('%d способ доставки', '%d способа доставки', $count[$stage]);
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

    public function fastExecute($profile_id)
    {
        $result = null;
        try {
            ob_start();
            $this->_processId = $profile_id;
            $this->init();
            $is_done = $this->isDone();
            while (!$is_done) {

                $this->step();
                $is_done = $this->isDone();
            }
            $_POST['cleanup'] = true;
            $this->save();
            $this->finish(null);

            $out = ob_get_clean();
            $result = array(
                'success' => $this->exchangeReport(),
            );
            if ($out) {
                $this->error("Error occurred during export profile %d: %s", $profile_id, $out);
                $result['notice'] = 'See error log for details';
            }


        } catch (waException $ex) {
            if ($ex->getCode() == '302') {
                $result = array(
                    'warning' => $ex->getMessage(),
                );
            } else {
                $result = array(
                    'error' => $ex->getMessage(),
                );
            }
        }
        return $result;
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
                $this->{$method_name}($this->data['current'][$stage], $this->data['count'], $this->data['processed_count'][$stage]);
            } else {
                $this->error("Unsupported stage [%s]", $stage);
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
                if (wa()->getEnv() == 'backend') {
                    $this->logAction('catalog_export', array('type' => 'YML',));
                }
                $file = $this->getTempPath();
                $target = $this->data['path']['offers'];
                if ($this->data['processed_count']['product'] || $this->data['force_update']) {
                    if (file_exists($file)) {
                        waFiles::delete($target);
                        waFiles::move($file, $target);
                    }
                    shopYandexmarketPlugin::path('shops.dtd');
                    if (wa()->getEnv() == 'backend') {
                        $this->validate();
                    }
                } else {
                    $this->error('Не выгружено ни одного товарного предложения, файл не обновлен');
                    $this->error(var_export($this->data, true));
                }
            }
        } catch (Exception $ex) {
            $this->error($ex->getMessage());
        }
        if ($filename !== null) {
            $this->info();
        }
        return $result;
    }

    private function validate()
    {
        libxml_use_internal_errors(true);
        shopYandexmarketPlugin::path('shops.dtd');
        $this->loadDom($this->data['path']['offers']);
        $valid = @$this->dom->validate();
        if ($valid) {
            $schema = dirname(dirname(dirname(__FILE__))).'/config/shops.xsd';
            $valid = @$this->dom->schemaValidate($schema);
        }
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
                if ($er->code != 505) {
                    $this->data['error'][] = array(
                        'level'   => $valid ? 'warning' : 'error',
                        'message' => "{$level_name} #{$er->code} [{$er->line}:{$er->column}]: {$er->message}",
                    );
                }
                $error[] = "Error #{$er->code}[{$er->level}] at [{$er->line}:{$er->column}]: {$er->message}";

            }
            if ($valid && (count($this->data['error']) == 1)) {
                $this->data['error'] = array();
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
                $data = $this->getStageReport($stage, $this->data['processed_count']);
                if ($data) {
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
            if (empty($this->data['processed_count']['product'])) {
                if ($this->data['force_update']) {
                    $response['report'] = '<i class="icon16 exclamation"></i>Не выгружено ни одного товарного предложения';
                    if (file_exists($this->data['path']['offers'])) {
                        $response['report'] .= ', но файл обновлен.';
                    } else {
                        $response['report'] .= ', но файл создан.';
                    }
                } else {
                    $response['report'] = '<div class="errormsg"><i class="icon16 no"></i>Не выгружено ни одного товарного предложения';
                    if (file_exists($this->data['path']['offers'])) {
                        $response['report'] .= ', файл не обновлен.';
                    } else {
                        $response['report'] .= ', файл не создан.';
                    }
                    $response['report'] .= '</div>';
                }
            } else {
                $response['report'] = $this->report();
                $response['report'] .= $this->validateReport();

            }
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
                'frontend'              => true,
                'round_prices'          => false,
                'correct_category_urls' => true,
            );

            if (!empty($this->data['export']['skip_ignored'])) {
                $options['params'] = true;
            } else {
                foreach ($this->data['map'] as $map) {
                    foreach ($map['fields'] as $info) {
                        if (!empty($info['source']) && !ifempty($info['category'])) {
                            $value = null;

                            if (strpos($info['source'], ':')) {
                                list($source, $param) = explode(':', $info['source'], 2);
                            } else {
                                $source = $info['source'];
                            }
                            switch ($source) {
                                case 'params':
                                    $options['params'] = true;
                                    break;
                            }
                        }
                    }
                }
            }

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
            /**
             * @var shopYandexmarketPlugin $plugin
             */
        }
        return $plugin;
    }

    /**
     * @param $current_stage
     * @param $count
     * @param $processed
     *
     * @usedby shopYandexmarketPluginRunController::step()
     */
    private function stepCategory(&$current_stage, &$count, &$processed)
    {
        static $categories = null;
        static $model;
        if ($categories === null) {
            $model = new shopCategoryModel();
            if (empty($this->data['export']['hidden_categories'])) {
                $categories = $model->getTree(0, null, false, $this->data['domain']);
            } else {
                $sql = <<<SQL
SELECT c.*
FROM shop_category c
LEFT JOIN shop_category_routes cr
ON (c.id = cr.category_id)
WHERE
(cr.route IS NULL) OR (cr.route = s:domain)
ORDER BY c.left_key
SQL;
                $categories = $model->query($sql, $this->data)->fetchAll($model->getTableId());
            }

            // экспортируется только список статических категорий, поэтому фильтруем данные
            foreach ($categories as $id => $category) {
                if ($category['type'] != shopCategoryModel::TYPE_STATIC) {
                    unset($categories[$id]);
                }
            }

            if ($current_stage) {
                $categories = array_slice($categories, $current_stage);
            }
        }

        $chunk = 100;
        while ((--$chunk >= 0) && ($category = reset($categories))) {
            if (empty($category['parent_id']) # это родительская категория
                || isset($this->data['categories'][$category['parent_id']]) # или родители этой категории попали в выборке
            ) {
                $category_xml = $this->dom->createElement("category", str_replace('&', '&amp;', $category['name']));
                $category['id'] = intval($category['id']);
                $category_xml->setAttribute('id', $category['id']);
                if ($category['parent_id']) {
                    $category_xml->setAttribute('parentId', $category['parent_id']);
                }
                $nodes = $this->dom->getElementsByTagName('categories');
                $nodes->item(0)->appendChild($category_xml);

                $this->data['categories'][$category['id']] = $category['id'];
                if (!empty($category['include_sub_categories'])
                    && (($category['right_key'] - $category['left_key']) > 1)//it's has descendants
                ) {
                    //remap hidden subcategories
                    $descendants = $model
                        ->descendants($category['id'], true)
                        ->where(
                            'type = i:type',
                            array(
                                'type' => shopCategoryModel::TYPE_STATIC,
                            )
                        )
                        ->fetchAll('id');
                    if ($descendants) {
                        $remap = array_fill_keys(array_map('intval', array_keys($descendants)), (int)$category['id']);
                        $this->data['categories'] += $remap;
                    }
                }
                ++$processed;
            }
            array_shift($categories);
            ++$current_stage;
        }
    }

    protected function save()
    {
        if ($this->dom && $this->processId) {
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
        if (empty($this->data['fields'])) {
            $fields = array(
                '*',
            );
            $this->data['stock_map'] = array();
            foreach ($this->data['map'] as $type => $map) {
                $this->data['stock_map'][$type] = array();
                foreach ($map['fields'] as $info) {
                    if (!empty($info['source']) && !ifempty($info['category'])) {
                        $value = null;

                        list($source, $param) = explode(':', $info['source'], 2);
                        switch ($source) {
                            case 'field':
                                $field = preg_replace('@\..+$@', '', $param);
                                $fields[] = $field;
                                if ($field == 'stock_counts') {
                                    if (empty($this->data['stock_id'])) {
                                        $this->data['stock_id'] = array();
                                    }
                                    $stock_id = intval(preg_replace('@^[^\.]+\.@', '', $param));
                                    $this->data['stock_id'][$stock_id] = $stock_id;
                                    $this->data['stock_map'][$type][$stock_id] = $stock_id;
                                } elseif ($field == 'virtual_stock_counts') {
                                    if (empty($this->data['stock_id'])) {
                                        $this->data['stock_id'] = array();
                                    }
                                    $virtualstock_id = intval(preg_replace('@^[^\.]+\.@', '', $param));
                                    if (class_exists('shopVirtualstockStocksModel')) {
                                        $model = new shopVirtualstockStocksModel();
                                        foreach ($model->getByField('virtualstock_id', $virtualstock_id) as $row) {
                                            $stock_id = intval($row['stock_id']);
                                            $this->data['stock_id'][$stock_id] = $stock_id;
                                            $this->data['stock_map'][$type][$stock_id] = $stock_id;
                                        }
                                    }
                                }

                                break;

                        }
                    }
                }
            }
            $this->data['stock_map'] = array_filter($this->data['stock_map']);
            if (in_array('stock_counts', $fields)) {
                unset($fields[array_search('stock_counts', $fields)]);
            }
            $fields = implode(',', array_unique($fields));
            $this->data['fields'] = $fields;
        }

        return $this->data['fields'];
    }

    /**
     * @param $current_stage
     * @param $count
     * @param $processed
     *
     * @usedby shopYandexmarketPluginRunController::step()
     */
    private function stepProduct(&$current_stage, &$count, &$processed)
    {
        static $products;
        static $sku_model;
        static $categories;
        static $stocks_model;
        if (!$products) {
            $products = $this->getCollection()->getProducts($this->getProductFields(), $current_stage, self::PRODUCT_PER_REQUEST, false);
            if (!$products) {
                $current_stage = $count['product'];
            } elseif (!empty($this->data['export']['sku'])) {
                if (empty($sku_model)) {
                    $sku_model = new shopProductSkusModel();
                }
                $skus = $sku_model->getDataByProductId(array_keys($products));
                foreach ($skus as $sku_id => $sku) {
                    if (isset($products[$sku['product_id']])) {
                        $product = &$products[$sku['product_id']];
                        $type = ifempty($this->data['types'][$product['type_id']], 'simple');

                        if (!isset($product['skus'])) {
                            $product['skus'] = array();
                        }

                        if (!empty($this->data['stock_id'])) {

                            if (!empty($this->data['stock_map'][$type])) {
                                $stock_map = $this->data['stock_map'][$type];
                                $sku['_count'] = $sku['count'];
                                $sku['count'] = false;
                                foreach ($stock_map as $stock_id) {
                                    if (isset($sku['stock'][$stock_id])) {
                                        $sku['count'] += $sku['stock'][$stock_id];
                                    } else {
                                        $sku['count'] = null;
                                        break;
                                    }
                                }
                            }
                        }

                        $product['skus'][$sku_id] = $sku;
                        if (count($product['skus']) > 1) {
                            $group = false;
                            switch (ifset($this->data['export']['sku_group'])) {
                                case 'all':
                                    $group = $sku['product_id'];
                                    break;
                                case 'category':
                                    // user primary product's category property
                                    if (!is_array($categories)) {
                                        $category_params_model = new shopCategoryParamsModel();
                                        $categories = $category_params_model->getByField(array(
                                            'name'  => 'yandexmarket_group_skus',
                                            'value' => 1,
                                        ), 'category_id');
                                        if ($categories) {
                                            $categories = array_fill_keys(array_keys($categories), true);
                                        }
                                    }
                                    if (isset($categories[$product['category_id']])) {
                                        $group = $sku['product_id'];
                                    }
                                    break;
                                case 'auto':
                                    $group = 'auto';
                                    //use product property yandex_category for it

                                    break;
                                default:
                                    break;
                            }

                            if ($group) {
                                $product['_group_id'] = $group;
                            }
                        }
                        unset($product);
                    }
                }

            } else {


                if (!empty($this->data['stock_id'])) {
                    if (empty($stocks_model)) {
                        $stocks_model = new shopProductStocksModel();
                    }
                    $sql_params = array(
                        'product_id' => array_keys($products),
                        'stock_id'   => array_merge(array(null), (array)$this->data['stock_id']),
                    );

                    foreach ($products as &$product) {
                        $product['_count'] = false;
                    }
                    unset($product);

                    $stocks = $stocks_model->getByField($sql_params, true);
                    foreach ($stocks as $stock) {
                        $product_id = $stock['product_id'];
                        $product = &$products[$product_id];

                        if ($product['_count'] !== null) {
                            $stock_id = intval($stock['stock_id']);
                            $type = ifempty($this->data['types'][$product['type_id']], 'simple');
                            if (!empty($this->data['stock_map'][$type][$stock_id])) {
                                if (in_array($stock['count'], array(null, ''), true)) {
                                    $product['_count'] = null;
                                } else {
                                    $product['_count'] += $stock['count'];
                                }
                            }
                        }
                        unset($product);
                    }

                    foreach ($products as &$product) {
                        if ($product['_count'] !== false) {
                            $product['count'] = $product['_count'];
                        }
                    }
                    unset($product);
                }


                $sql_params = array(
                    'product_id' => array_keys($products),
                );

                if (empty($sku_model)) {
                    $sku_model = new shopProductSkusModel();
                }

                $files = $sku_model->select('DISTINCT product_id, file_name')->where("product_id IN (i:product_id) AND file_name !=''", $sql_params)->fetchAll('product_id', true);
                foreach ($files as $id => $file_name) {
                    $products[$id]['file_name'] = $file_name;
                }

                $available = $sku_model->select('DISTINCT product_id, available')->where("product_id IN (i:product_id) AND available", $sql_params)->fetchAll('product_id', true);
                foreach ($products as $id => &$product) {
                    $product['available'] = !empty($available[$id]);
                    unset($product);
                }
            }
            $params = array(
                'products' => &$products,
                'type'     => 'YML',
            );
            wa('shop')->event('products_export', $params);

        }
        $check_stock = !empty($this->data['export']['zero_stock']);

        $chunk = 100;
        while ((--$chunk >= 0) && ($product = reset($products))) {
            $check_type = empty($this->data['type_id']) || in_array($product['type_id'], $this->data['type_id']);

            $check_price = $this->checkMinPrice($product['price']);

            $check_category = !empty($product['category_id']) && isset($this->data['categories'][$product['category_id']]);
            if (empty($this->data['export']['skip_ignored'])) {
                $check_filter = true;
            } else {
                $check_filter = (
                    !isset($product['params']['yandexmarket.ignored'])
                    || !intval($product['params']['yandexmarket.ignored'])
                );
            }


            if ($check_category && ($product['category_id'] != $this->data['categories'][$product['category_id']])) {
                // remap product category
                //TODO check available parent category
                $product['category_id'] = $this->data['categories'][$product['category_id']];
            }

            if (!$check_category && $check_type && $check_price && $check_filter) {
                // или проверять наличие урла, полученного из коллекции
                // и по нему принимать решение о том, что товар доступен на витрине
                // проверить доступность товара в других дополнительных категориях
                if (!empty($product['frontend_url'])) {
                    //     $check_category = true;
                }
            }

            $export = $check_type && $check_price && $check_category && $check_filter;

            if (!empty($this->data['trace']) && !$export) {
                $export_params = compact('check_type', 'check_price', 'check_category', 'check_filter');
                $export_params = array_keys(array_diff($export_params, array_filter($export_params)));
                $export_extra_params = array();

                if (!$check_type) {
                    $export_extra_params['type'] = array(
                        'product'  => $product['type_id'],
                        'expected' => ifset($this->data['type_id']),
                    );
                }

                if (!$check_category) {
                    $export_extra_params['category'] = array(
                        'product'  => $product['category_id'],
                        'expected' => ifset($this->data['categories']),
                    );
                }

                if (!$check_filter) {
                    $export_extra_params['params'] = array(
                        'product' => ifset($product['params'], array()),
                    );
                }

                $this->trace(
                    "Product #%d [%s] skipped because it's not available (%s)\n\tPARAMS:%s",
                    $product['id'],
                    $product['name'],
                    implode(', ', $export_params),
                    var_export($export_extra_params, true)
                );
            }

            if ($export) {
                if (!empty($this->data['export']['sku'])) {
                    $skus = $product['skus'];
                    unset($product['skus']);
                    foreach ($skus as $sku) {
                        //TODO use min_price && convert into default currency

                        $check_sku_price = $this->checkMinPrice($sku['primary_price']);

                        $check_available = !empty($sku['available']);

                        $check_count = $check_stock || ($sku['count'] === null) || ($sku['count'] > 0);
                        if ($check_available && $check_sku_price && $check_count) {
                            if (count($skus) == 1) {
                                $product['price'] = $sku['price'];
                                $product['compare_price'] = $sku['compare_price'];
                                $product['purchase_price'] = $sku['purchase_price'];
                                $product['file_name'] = $sku['file_name'];
                                $product['sku'] = $sku['sku'];
                                $product['count'] = $sku['count'];
                                $increment = false;
                            } else {
                                $increment = true;
                            }
                            $this->addOffer($product, (count($skus) > 1) ? $sku : null);
                            ++$processed;
                            if ($increment) {
                                ++$count['product'];
                            }
                        }
                    }
                } else {
                    $check_available = !empty($product['available']);
                    $check_count = $check_stock || ($product['count'] === null) || ($product['count'] > 0);
                    if ($check_available && $check_count) {
                        $this->addOffer($product);
                        ++$processed;
                    }
                }
            }
            array_shift($products);
            ++$current_stage;
        }
    }

    private function getValue(&$product, $sku, $field, $info)
    {
        static $features_model;
        $value = null;
        list($source, $param) = explode(':', $info['source'], 2);

        $info['options'] = shopYandexmarketPlugin::parseMapOptions($param);
        if (empty($info['options'])) {
            unset($info['options']);
        }
        switch ($source) {
            case 'field':
                if (strpos($param, 'stock_counts.') === 0) {
                    //it's already remapped
                    $value = $product['count'];
                } elseif (strpos($param, 'virtual_stock_counts.') === 0) {
                    //it's already remapped
                    $value = $product['count'];
                } else {
                    switch ($param) {
                        case 'tax_id':
                            if (!empty($product[$param])) {
                                if (isset($this->data['taxes'][$product[$param]])) {
                                    $value = $this->data['taxes'][$product[$param]]['rate'];
                                } else {
                                    $value = 0;
                                }
                            } else {
                                $value = -1;
                            }

                            break;
                        case 'description':
                            $value = isset($product[$param]) ? $product[$param] : null;
                            if ($value && !empty($this->data['can_use_smarty'])) {
                                try {
                                    $view = wa()->getView();
                                    $view->assign('product', $product);
                                    $value = $view->fetch('string:'.$value);
                                } catch (Exception $ex) {
                                    $this->error('Error during fetch template: %s', $ex->getMessage());
                                }
                            }
                            break;
                        default:
                            $value = isset($product[$param]) ? $product[$param] : null;
                            break;
                    }
                }

                if (!empty($this->data['export']['sku'])) {
                    switch ($param) {
                        case 'virtual_stock_counts':
                        case 'stock_counts':
                            $value = ifset($sku['count'], $value);
                            break;
                        case 'id':
                            if (!empty($sku['id']) && ($sku['id'] != $product['sku_id'])) {
                                $value .= 's'.$sku['id'];
                            }
                            break;
                        case 'frontend_url':
                            if (!empty($sku['id']) && ($sku['id'] != $product['sku_id'])) {
                                if (strpos($value, '?')) {
                                    $value .= '&';
                                } else {
                                    $value .= '?';
                                }
                                $value .= 'sku='.$sku['id'];
                            }
                            break;
                        case 'file_name':
                            if (!empty($sku)) {
                                $value = empty($sku[$param]) ? null : 'true';
                            } else {
                                $value = empty($value) ? null : 'true';
                            }
                            break;
                        case 'price':
                        case 'count':
                        case 'sku':
                        case 'group_id':
                        case 'compare_price':
                        case 'purchase_price':
                            $value = ifset($sku[$param], $value);
                            break;
                        default:
                            if (strpos($param, 'stock_counts.') === 0) {
                                //it's already remapped
                                $value = ifset($sku['count'], $value);
                            } elseif (strpos($param, 'virtual_stock_counts.') === 0) {
                                //it's already remapped
                                $value = ifset($sku['count'], $value);
                            }
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
                if (!isset($product['features'])) {
                    if (!$features_model) {
                        $features_model = new shopProductFeaturesModel();
                    }

                    $product['features'] = $features_model->getValues($product['id'], ifset($sku['id']));
                }
                if (strpos($param, ':')) {
                    $unit = null;
                    list($param, $unit) = explode(':', $param, 2);
                }
                if (($param == 'weight') && !empty($sku['id']) && !isset($product['features'][$param])) {
                    $features = $features_model->getValues($product['id']);
                    $product['features'] += array(
                        $param => ifset($features[$param]),
                    );
                }
                $value = $this->format($field, ifset($product['features'][$param]), $info, $product, $sku);
                break;
            case 'params':

                $value = $this->format($field, ifset($product['params']['yandexmarket.'.$param]), $info, $product, $sku);
                break;
            case 'function':
                $option = null;
                if (strpos($param, '.')) {
                    list($param, $option) = explode('.', $param, 2);
                }
                switch ($param) {
                    case 'prepaid':
                        $source = array(
                            'source' => 'field:count',
                        );

                        $_value = $this->getValue($product, $sku, 'available', $source);

                        if (is_array($_value) && isset($_value['value'])) {
                            $_value = $_value['value'];
                        }

                        if (in_array($_value, array('false', 0, '0', null), true)) {
                            $value = 'Заказ товара по предоплате';
                        }
                        break;
                    case 'group_category':
                        break;
                    case 'group_market_category':
                        break;
                    case 'cross_selling':
                        $p = new shopProduct($product);
                        if (($option != 'static') || (($cross_selling = $p->getData('cross_selling')) && ($cross_selling != 1))) {
                            $check_stock = !empty($this->data['export']['zero_stock']);

                            if ($value = $p->crossSelling(5, !$check_stock)) {
                                $value = implode(',', array_keys($value));
                            } else {
                                $value = null;
                            }
                        }

                        break;
                    case 'upselling':
                        $p = new shopProduct($product);
                        if (($option != 'static') || (($upselling = $p->getData('upselling')) && ($upselling != 1))) {
                            $check_stock = !empty($this->data['export']['zero_stock']);
                            if ($value = $p->upSelling(5, !$check_stock)) {
                                $value = implode(',', array_keys($value));
                            } else {
                                $value = null;
                            }
                        }
                        break;
                }
                break;
            case 'plugin':
                break;
        }


        return $value;
    }

    private function applyFormat($value, $info)
    {
        if (!empty($info['options']['format'])) {
            $value = trim($value);
            if ($value !== '') {
                $format = $info['options']['format'];
                $search = array(
                    '%value%',
                );
                $replace = array(
                    trim($value),
                );
                if (strpos($format, '%name%') !== false) {
                    $search[] = '%name%';
                    $replace[] = $this->getFieldName($info);
                }

                $value = str_replace($search, $replace, $format);
            }
        }
        return $value;
    }

    private function getFieldName($info)
    {
        static $fields = array();
        static $features = array();

        if (!$fields) {
            $fields = array(
                'name'        => _w('Product name'),
                'description' => _w('Description'),
                'summary'     => _w('Summary'),
                'sku'         => _w('SKU code'),
                'file_name'   => _w('Attachment'),
                'count'       => _w('In stock'),
                'type_id'     => _w('Product type'),
                'tax_id'      => _w('Tax rates'),
            );
        }

        list($source, $param) = explode(':', $info['source'], 2);
        switch ($source) {
            case 'feature':
                $param = preg_replace('/[@:].*$/', '', $param);
                if (!isset($features[$param])) {
                    $model = new shopFeatureModel();
                    if ($feature = $model->getByCode($param)) {
                        $features[$param] = $feature['name'];
                    } else {
                        $features[$param] = $param;
                    }
                }
                $name = $features[$param];
                break;
            default:
                $name = ifset($fields[$source]);
                break;

        }
        return $name;
    }

    /**
     * @param array $product
     * @param array $sku
     * @return array|null
     */
    private function addOffer($product, $sku = null)
    {
        $type = ifempty($this->data['types'][$product['type_id']], 'simple');
        $offer_map = $this->data['map'][$type]['fields'];
        $offer = array();
        $map = array();
        foreach ($offer_map as $field_id => $info) {
            $field = preg_replace('/\\..*$/', '', $field_id);

            if (!empty($info['source']) &&
                (!ifempty($info['category'], array()) || in_array('simple', $info['category']))
            ) {
                $value = $this->getValue($product, $sku, $field, $info);
                if (!in_array($value, array(null, false, ''), true)) {
                    $offer[$field_id] = $this->applyFormat($value, $info);
                    $map[$field_id] = array(
                        'attribute' => !empty($info['attribute']),
                        'callback'  => !empty($info['callback']),
                    );
                    if (!empty($info['path'])) {
                        $map[$field_id]['path'] = $info['path'];
                    }
                    if (!empty($info['virtual'])) {
                        $map[$field_id]['virtual'] = $info['virtual'];
                    }
                }
            }
        }

        if ($offer) {
            return $this->addOfferDom($offer, $map);
        }
        return null;
    }

    private function addOfferDom($offer, $map)
    {
        static $offers;
        if (empty($offers)) {
            $nodes = $this->dom->getElementsByTagName('offers');
            $offers = $nodes->item(0);
        }
        $product_xml = $this->dom->createElement("offer");
        $data = array();
        foreach ($offer as $field_id => $value) {
            $field = preg_replace('/\\..*$/', '', $field_id);
            if (!empty($map[$field_id]['callback'])) {
                $data[$field.'.raw'] = $value;
                $value = $this->format($field, $value, array(), $offer);
            }

            if (!in_array($value, array(null, false, ''), true)) {# non empty value
                $virtual = !empty($map[$field_id]['virtual']) && empty($map[$field_id]['callback']);
                if (!$virtual) {
                    if (!empty($map[$field_id]['path']) && (is_array($value) && !empty($value['path']))) {
                        $field = preg_replace('@\[[^\]]+\]@', '', $map[$field_id]['path']);
                        unset($value['path']);
                    }
                    $this->addDomValue($product_xml, $field, $value, $map[$field_id]['attribute']);
                    $data[$field] = $value;
                }
            }
        }
        $offers->appendChild($product_xml);
        return $data;
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
            if (!preg_match('@^\d+$@', key($value))) {
                $path = explode('/', $field);
                $field = array_pop($path);
                $element = $this->dom->createElement($field, trim(ifset($value['value'])));
                unset($value['value']);
                foreach ($value as $attribute => $attribute_value) {
                    if (is_array($attribute_value)) {
                        $attribute_value = reset($attribute_value);
                    }
                    $element->setAttribute($attribute, $attribute_value);
                }
                while ($field = array_pop($path)) {
                    $parent = $this->dom->createElement($field);
                    $parent->appendChild($element);
                    $element = $parent;
                }
                $dom->appendChild($element);
            } else {
                foreach ($value as $value_item) {
                    $dom->appendChild($this->dom->createElement($field, trim($value_item)));
                }
            }
        } elseif (!$is_attribute) {
            $path = explode('/', $field);
            $field = array_pop($path);
            if ($field == 'description') {
                $element = $this->dom->createElement($field);
                $cdata = $this->dom->createCDATASection(trim($value));
                $element->appendChild($cdata);
            } else {
                $element = $this->dom->createElement($field, trim($value));
            }
            while ($field = array_pop($path)) {
                $parent = $this->dom->createElement($field);
                $parent->appendChild($element);
                $element = $parent;
            }
            $dom->appendChild($element);
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
         * <cpa>
         * <rec>
         * <param> (name,unit,value)
         * <vendor>
         */

        static $size;
        switch ($field) {
            case 'group_id':
                if ($value === 'auto') {
                    if (!empty($data['market_category'])) {
                        $value = $this->plugin()->isGroupedCategory($data['market_category']) ? $data['id'] : null;
                    } else {
                        $info['format'] = false;
                    }
                }
                break;
            case 'market_category':
                //it's product constant field
                //TODO verify it
                break;
            case 'name':
                if (!empty($sku_data['name']) && !empty($data['name']) && ($sku_data['name'] != $data['name'])) {
                    $value = sprintf('%s (%s)', $value, $sku_data['name']);
                }
                $value = preg_replace('@<br\s*/?>@', "\n", $value);
                $value = preg_replace("@[\r\n]+@", "\n", $value);
                $value = strip_tags($value);

                $value = trim($value);
                if (mb_strlen($value) > 255) {
                    $value = mb_substr($value, 0, 252).'...';
                }
                break;
            case 'description':

                $html = !empty($info['options']['html']);
                $value = preg_replace('@(<br\s*/?>)+@', $html ? '<br/>' : "\n", $value);
                $value = preg_replace("@[\r\n]+@", "\n", $value);
                $value = strip_tags($value, $html ? '<h3><p><ul><li><br>' : null);

                $value = trim($value);
                if (mb_strlen($value) > 3000) {
                    $value = mb_substr($value, 0, 2997).'...';
                }
                break;
            case 'barcode':
                //может содержать несколько элементов
                $value = preg_replace('@\\D+@', '', $value);
                if (!in_array(strlen($value), array(8, 12, 13))) {
                    $value = null;
                }
                break;
            case 'cpa':
                if ($value !== null) {
                    if (in_array($value, array('false', '0', 'Нет', 'нет', 0, false), true)) {
                        $value = '0';
                    } else {
                        $value = $value ? '1' : '0';
                    }
                }
                break;
            case 'fee':
                if (!in_array($value, array('', 0, 0.0, '0', null), true)) {
                    $value = round(100 * min(100, max(0, floatval($value))));
                } else {
                    $value = null;
                }
                break;
            case 'bid':
            case 'cbid':
                if (!in_array($value, array('', 0, 0.0, '0', null), true)) {
                    $value = round(100 * max(0, floatval($value)));
                } else {
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
                $value = preg_replace_callback('@([^\[\]a-zA-Z\d_/-\?=%&,\.]+)@i', array(__CLASS__, 'rawurlencode'), $value);
                if (isset($this->data['utm']) && !empty($this->data['utm'])) {
                    $value .= (strpos($value, '?') ? '&' : '?').$this->data['utm'];
                }


                if (!empty($this->data['custom_url'])) {
                    $custom_url = $this->data['custom_url'];
                    if (preg_match_all('@%([a-z_]+)%@', $custom_url, $matches)) {
                        foreach ($matches[1] as $match) {
                            $replace = rawurlencode(ifset($sku_data[$match], ifset($data[$match])));
                            $custom_url = str_replace('%'.$match.'%', $replace, $custom_url);
                        }
                    }
                    $value .= (strpos($value, '?') ? '&' : '?').$custom_url;
                }

                $value = ifempty($this->data['schema'], 'http://').ifempty($this->data['base_url'], 'localhost').$value;
                break;
            case 'purchase_price':
                if (empty($value) || empty($this->data['export']['purchase_price'])) {
                    $value = null;
                } else {
                    $value = $this->convertCurrency($value, $data, $sku_data);
                }
                break;
            case 'oldprice':
                /**
                 * @see https://yandex.ru/support/partnermarket/oldprice.html
                 */
                if (empty($value) || empty($this->data['export']['compare_price'])) {
                    $value = null;
                } else {
                    if (empty($info) && !empty($data['price'])) {
                        //it's second stage
                        $rate = $data['price'] / $value;
                        if (($rate < 0.05) || ($rate > 0.95)) {
                            $value = null;
                        }
                    }
                    if (empty($value)) {
                        $value = null;
                    } else {
                        $value = $this->convertCurrency($value, $data, $sku_data);
                    }
                }
                break;
            case 'price':
                $value = $this->convertCurrency($value, $data, $sku_data);
                break;
            case 'currencyId':
                if (!in_array($value, $this->data['currency'])) {
                    $value = $this->data['primary_currency'];
                }
                break;
            case 'rate':
                if (!in_array($value, array('CB', 'CBRF', 'NBU', 'NBK'))) {
                    $value = round($value, 4);
                    $chunk = preg_replace('@[0]+$@', '', abs($value - floor($value)) * 10000);
                    $chunk = mb_strlen($chunk);
                    $info['format'] = $chunk ? sprintf('%%0.%df', $chunk) : '%d';
                }
                break;
            case 'available':
                if (!empty($sku_data) && isset($sku_data['available']) && empty($sku_data['available'])) {
                    $value = 'false';
                }
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
                $count = false;
                //XXX CPA bloody hack = used complex value
                if (is_array($value) && isset($value['raw'])) {
                    $value = $value['raw'];
                } elseif (is_int($value) || (is_string($value) && preg_match('@^\d+$@', $value))) {
                    $count = intval($value);
                } elseif (in_array($value, array(null, 'true', ''), true)) {
                    $count = 9999;// 100500;
                } else {
                    $count = 0;
                }
                if ($count === false) {
                    $value = (
                        (($value <= 0) || ($value === 'false') || empty($value))
                        && ($value !== '')
                        && ($value !== null)
                        && ($value !== 'true')
                    ) ? 'false' : 'true';
                } else {
                    $value = array(
                        'raw'   => $count,
                        'value' => $value,
                    );
                }
                break;
            case 'booking':
                if (!empty($sku_data) && isset($sku_data['available']) && empty($sku_data['available'])) {
                    $value = 'false';
                }
                if (is_object($value)) {
                    switch (get_class($value)) {
                        case 'shopBooleanValue':
                            /**
                             * @var $value shopBooleanValue
                             */
                            $value = $value->value ? 'true' : null;
                            break;
                    }
                }
                $value = (
                    (($value <= 0) || ($value === 'false') || empty($value))
                    && ($value !== '')
                    && ($value !== null)
                    && ($value !== 'true')
                ) ? null : 'true';
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
                if (!in_array($value, array(null, ''))) {
                    $value = (empty($value) || ($value === 'false')) ? 'false' : 'true';
                } else {
                    $value = null;
                }
                break;
            case 'vat':
                /**
                 * @see https://yandex.ru/support/partnermarket/elements/vat.html
                 */
                switch ($value) {
                    case 18:
                        $value = 'VAT_18';
                        break;
                    case 10:
                        $value = 'VAT_10';
                        break;
                    case 0:
                        $value = 'VAT_0';
                        break;
                    default:
                        $value = 'NO_VAT';
                        break;
                }
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
                    $values[] = ifempty($this->data['schema'], 'http://').ifempty($this->data['base_url'], 'localhost').shopImage::getUrl($image, $size);
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
                        if (empty($value) || ($value === 'false') || ($value === '0')) {
                            $value = 'false';
                        } elseif (($value === 'true') || ($value === '1') || ($value === true)) {
                            $value = 'true';
                        } elseif (preg_match('@^\d+$@', trim($value))) {
                            $value = $this->formatCustom(intval($value) * 3600 * 24, 'ISO8601');
                        } elseif (!preg_match($pattern, $value)) {
                            $value = 'true';
                        } else {
                            $value = (string)$value;
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
                 * Форматы ISBN и SBN проверяются на корректность. Валидация кодов происходит не только по длине,
                 * также проверяется контрольная цифра (check-digit) – последняя цифра кода должна согласовываться
                 * с остальными цифрами по определенной формуле. При разбиении ISBN на части при помощи дефиса
                 * (например, 978-5-94878-004-7) код проверяется на соответствие дополнительным требованиям к
                 * количеству цифр в каждой из частей.
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
                }
                $value = max(0, round(floatval(str_replace(',', '.', $value)), 3));
                if (empty($value)) {
                    $value = null;
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
                if (!in_array($value, array('', false, null), true)) {
                    if ($value === 'fixed') {
                        $value = ifset($this->data['delivery-option']['cost']);
                    }
                    $value = max(0, floatval($value));
                } else {
                    $value = null;
                }

                if (empty($info)) {
                    if (empty($data['delivery']) || ($data['delivery'] === 'true')) {
                        $option = isset($this->data['delivery-option']) ? $this->data['delivery-option'] : array();
                        if ($value === null) {
                            $value = max(0, floatval(ifset($option['cost'])));
                        }

                        $days = ifset($data['local_delivery_days'], ifset($option['days']));
                        $days = shopYandexmarketPlugin::getDays($days);
                        if (count($days) == 2) {
                            sort($days);
                            $days = implode('-', $days);
                        } elseif (count($days)) {
                            $days = max(0, max($days));
                        } else {
                            $days = 31;
                        }

                        $value = array(
                            'cost'         => $value,
                            'days'         => $days,
                            'order-before' => ifset($data['local_delivery_before'], ifset($option['order-before'], 24)),
                            'path'         => true,
                        );
                    } else {
                        $value = null;
                    }
                }

                break;
            case 'local_delivery_days':
                if (!in_array($value, array('', false, null), true)) {
                    $value = shopYandexmarketPlugin::getDays($value);
                    if (count($value) == 2) {
                        sort($value);
                        $value = implode('-', $value);
                    } else {
                        $value = max(0, max($value));
                    }
                } else {
                    $value = null;
                }
                break;
            case 'local_delivery_before':
                if (!in_array($value, array('', false, null), true)) {
                    $value = max(0, floatval($value));
                } else {
                    $value = null;
                }
                break;
            case 'min-quantity':
            case 'step-quantity':
                if (!in_array($value, array('', false, null), true)) {
                    $value = max(1, intval($value));
                }
                break;
            case 'days':
                $value = max(0, intval($value));
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
            case 'param':
                $unit = ifset($info['source_unit'], null);
                $name = ifset($info['source_name'], '');

                if ($value instanceof shopDimensionValue) {
                    $unit = $value->unit_name;
                    $value = $value->format('%s');
                } elseif ($value instanceof shopColorValue) {
                    $value = (string)$value->value;
                } elseif (is_array($value)) {
                    $_value = reset($value);
                    if ($_value instanceof shopDimensionValue) {
                        $unit = $_value->unit_name;
                        $dimension_unit = $_value->unit;
                        $values = array();
                        foreach ($value as $_value) {
                            /**
                             * @var shopDimensionValue $_value
                             */
                            $values[] = $_value->convert($dimension_unit, '%s');
                        }
                        $value = implode(', ', $values);
                    } elseif ($_value instanceof shopColorValue) {
                        $values = array();
                        foreach ($value as $_value) {
                            /**
                             * @var shopColorValue $_value
                             */
                            $values[] = (string)$_value->value;
                        }
                        $value = implode(', ', $values);
                    } else {
                        if (preg_match('@^(.+)\s*\(([^\)]+)\)\s*$@', $name, $matches)) {
                            if (empty($unit)) {
                                //feature name based unit
                                $unit = $matches[2];
                            }
                            $name = $matches[1];
                        }
                        $value = implode(', ', $value);
                    }

                } elseif (preg_match('@^(.+)\s*\(([^\)]+)\)\s*$@', $name, $matches)) {
                    if (empty($unit)) {
                        //feature name based unit
                        $unit = $matches[2];
                    }
                    $name = $matches[1];
                }
                $value = trim((string)$value);
                if (in_array($value, array(null, false, ''), true)) {
                    $value = null;
                } else {
                    $value = array(
                        'name'  => trim($name),
                        'value' => trim((string)$value),
                    );
                    $unit = trim($unit);
                    if ($unit) {
                        $value['unit'] = $unit;
                    }
                }
                break;
            case 'downloadable':
                $value = !empty($value) ? 'true' : null;
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

            # XXX use map.php for configure this fields
            # implode values for non multiple and non complex fields
            if (!in_array($field, array('email', 'picture', 'dataTour', 'additional', 'barcode', 'param', 'related_offer', 'local_delivery_cost', 'available','age'))) {
                $value = implode(', ', $value);
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

    private function checkMinPrice($price)
    {
        static $currency_model;
        if ($this->data['default_currency'] != $this->data['primary_currency']) {
            if (empty($currency_model)) {
                $currency_model = new shopCurrencyModel();
            }
            $price = $currency_model->convert($price, $this->data['default_currency'], $this->data['primary_currency']);
        }

        return $price >= ifempty($this->data['export']['min_price'], 0.5);
    }

    private function convertCurrency($value, &$data, $sku_data)
    {
        static $currency_model;
        $_currency_converted = false;

        if (!$currency_model) {
            $currency_model = new shopCurrencyModel();
        }

        if (isset($data['currency'])) {
            if ($sku_data) {
                if (!in_array($data['currency'], $this->data['currency'])) {

                    $_currency_converted = true;
                    $value = $currency_model->convert($value, $data['currency'], $this->data['primary_currency']);
                    $data['currency'] = $this->data['primary_currency'];
                }
            } else {
                if (!in_array($data['currency'], $this->data['currency'])) {
                    #value in default currency
                    if ($this->data['default_currency'] != $this->data['primary_currency']) {
                        $value = $currency_model->convert($value, $this->data['default_currency'], $this->data['primary_currency']);
                    }
                    $_currency_converted = true;
                    $data['currency'] = $this->data['primary_currency'];
                } elseif ($this->data['default_currency'] != $data['currency']) {
                    $_currency_converted = true;
                    $value = $currency_model->convert($value, $this->data['default_currency'], $data['currency']);
                }
            }

            if ($value && class_exists('shopRounding') && !empty($_currency_converted)) {
                $value = shopRounding::roundCurrency($value, $data['currency']);
            }
        }
        unset($_currency_converted);
        return $value;
    }

    public function exchangeReport()
    {
        $interval = '—';
        if (!empty($this->data['timestamp'])) {
            $interval = time() - $this->data['timestamp'];
            $interval = sprintf('%02d ч %02d мин %02d с', floor($interval / 3600), floor($interval / 60) % 60, $interval % 60);
        }

        $template = "Автоматическое формирование профиля %s.\nВремя выполнения:\t%s";
        $report = sprintf($template, $this->processId, $interval);
        if (!empty($this->data['memory'])) {
            $memory = $this->data['memory'] / 1048576;
            $report .= sprintf("\nПотребление памяти:\t%0.3f МБ", $memory);
        }
        $chunks = array();
        foreach ($this->data['processed_count'] as $stage => $count) {
            if ($data = $this->getStageReport($stage, $this->data['processed_count'])) {
                $chunks[] = htmlentities($data, ENT_QUOTES, 'utf-8');
            }
        }
        if ($chunks) {

            $report .= "\nЭкспортировано:\n\t";
            $report .= implode("\n\t", $chunks);
        }
        waLog::log($report, 'shop/plugins/yandexmarket/report.log');
        return $report;
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
        if (func_num_args() > 1) {
            $args = func_get_args();
            $format = array_shift($args);
            $message = vsprintf($format, $args);
        } elseif (is_array($message)) {
            $message = var_export($message, true);
        }
        $path = wa()->getConfig()->getPath('log');
        waFiles::create($path.'/shop/plugins/yandexmarket/export.error.log');
        waLog::log($message, 'shop/plugins/yandexmarket/export.error.log');
    }

    private function trace($message)
    {
        if (func_num_args() > 1) {
            $args = func_get_args();
            $format = array_shift($args);
            $message = vsprintf($format, $args);
        } elseif (is_array($message)) {
            $message = var_export($message, true);
        }
        $path = wa()->getConfig()->getPath('log');
        $file = sprintf('/shop/plugins/yandexmarket/export.trace.%d.log', $this->data['profile_id']);
        waFiles::create($path.$file);
        waLog::log($message, $file);
    }

    private static function rawurlencode($a)
    {
        return rawurlencode(reset($a));
    }
}
