<?php
/**
 * @title InSales
 * @description migrate data via InSales API
 */

/**
 * Class shopMigrateInsalesTransport
 * @see https://wiki.insales.ru/wiki/%D0%9A%D0%BE%D0%BC%D0%B0%D0%BD%D0%B4%D1%8B_API
 * @todo currency settings
 */
class shopMigrateInsalesTransport extends shopMigrateTransport
{

    const API_PRODUCT_PER_PAGE = 100; //max 250
    const API_ORDERS_PER_PAGE = 25; //default 25
    const API_CUSTOMERS_PER_PAGE = 25; //default 25

    private static $financial_state_map = array(
        'new'        => 'new',// - новый
        'accepted'   => 'processing',// - в обработке
        'approved'   => 'processing',// - согласован
        'dispatched' => 'shipped',// - отгружен
        'delivered'  => 'completed',// - доставлен
        'declined'   => 'deleted',// - отменен
    );

    protected function initOptions()
    {
        parent::initOptions();
        waHtmlControl::registerControl('OptionsControl', array(&$this, "settingOptionsControl"));
        $options = array(
            'hostname' => array(
                'title'        => 'Домен *.myinsales.ru',
                'description'  => 'Введите полное доменное имя вашего интернет-магазина в зоне myinsales.ru, например, shop-12345.myinsales.ru',
                'placeholder'  => 'login.myinsales.ru',
                'control_type' => waHtmlControl::INPUT,
                'cache'        => true,
            ),
            'apikey'   => array(
                'class'        => 'long',
                'title'        => 'Идентификатор',
                'placeholder'  => md5(''),
                'description'  => 'Идентификатор ключа доступа InSales API (получить ключ необходимо в разделе Приложения > Разработчикам режима администрирования интернет-магазина на основе InSales)',
                'control_type' => waHtmlControl::INPUT,
                'cache'        => true,
            ),
            'password' => array(
                'class'        => 'long',
                'title'        => 'Пароль',
                'placeholder'  => md5(' '),
                'description'  => 'Пароль ключа доступа API (не администратора магазина)',
                'control_type' => waHtmlControl::INPUT,
            ),
        );
        foreach ($options as $name => $option) {
            $this->addOption($name, $option);
        }
    }

    public function validate($result, &$errors)
    {
        try {
            $hostname = $this->getOption('hostname');
            if (empty($hostname)) {
                $errors['hostname'] = 'Укажите домен .myinsales.ru полностью';
                $result = false;
            } elseif (!preg_match('@^([a-z0-9_\-]+)\.myinsales\.ru$@', $hostname)) {
                $errors['hostname'] = 'Укажите домен .myinsales.ru полностью';
                $result = false;
            }

            if ($result) {
                $apikey = $this->getOption('apikey');
                if (empty($apikey) || !preg_match('@^[a-f0-9]{32}$@', $apikey)) {
                    $errors['apikey'] = 'Неверный идентификатор ключа доступа API';
                    $result = false;
                }

                $password = $this->getOption('password');
                if (empty($password) || !preg_match('@^[a-f0-9]{32}$@', $password)) {
                    $errors['password'] = 'Некорректный пароль ключа доступа API';
                    $result = false;
                }
            }
            if ($result) {
                $domains = $this->query('domains');
                $options = array(
                    'hostname' => array(
                        'readonly' => true,
                        'valid'    => true,
                    ),
                    'apikey'   => array(
                        'readonly' => true,
                        'valid'    => true,
                    ),
                    'password' => array(
                        'readonly' => true,
                        'valid'    => true,
                    ),
                );

                $option = array(
                    'value'        => false,
                    'control_type' => waHtmlControl::SELECT,
                    'title'        => _wp('Storefront'),
                    'description'  => _wp('Shop-Script settlement for static info pages'),
                    'options'      => array(),
                );
                $this->getRouteOptions($option);
                $options['domain'] = $option;
                unset($option);

                #customer fields map
                $option = array(
                    'control_type' => 'CustomersControl',
                    'title'        => _wp('Contact fields map'),
                    'options'      => array(),
                );

                $fields = $this->query('fields');
                if (self::queryCount($fields)) {
                    foreach ($fields->xpath('//field') as $f) {
                        $id = (int)$f->{'id'};
                        $title = (string)$f->{'office-title'};
                        if (empty($title)) {
                            $title = (string)$f->{'system-name'};
                        }
                        $option['options'][$id] = array(
                            'title'       => $title,
                            'description' => (string)$f->{'system-name'},
                        );
                    }
                }
                $options['customer'] = $option;
                unset($option);

                $options['type'] = $this->getProductTypeOption();

                $option = array(
                    'value'        => 'kg',
                    'control_type' => waHtmlControl::SELECT,
                    'title'        => _wp('Weight unit'),
                    'description'  => 'Выберите единицу измерения, в которой задан вес товаров',
                    'options'      => shopDimension::getUnits('weight'),
                );

                $options['weight'] = $option;
                unset($option);
                //TODO check weight feature exists and has properly type

                if ($variants = $this->loadFeatures()) {
                    $options['options'] = array(
                        'title'        => 'Характеристики',
                        'description'  => '',
                        'options'      => $variants,
                        'control_type' => 'OptionsControl',

                    );
                }
                $option = array(
                    'value'        => $this->getConfig()->getCurrency(),
                    'control_type' => waHtmlControl::SELECT,
                    'title'        => _wp('Order currency'),
                    'description'  => 'Выберите базовую валюту заказов',
                    'options'      => array(),

                );
                foreach ($this->getConfig()->getCurrencies() as $currency) {
                    $option['options'][$currency['code']] = sprintf('%s (%s)', $currency['title'], $currency['code']);
                }
                $options['currency'] = $option;
                unset($option);

                $this->addOption($options);
            }
        } catch (Exception $ex) {
            $result = false;
            $errors['hostname'] = $errors['apikey'] = $errors['password'] = $ex->getMessage();
        }

        return parent::validate($result, $errors);
    }

    private function loadFeatures()
    {
        $variants = array();
        if (($properties = $this->query('properties'))) {

            foreach ($properties->xpath('/properties/property') as $property) {
                $id = 'property.'.(int)$property->{'id'};
                $variants[$id] = array(
                    'description' => 'Параметр "'.(string)$property->{'permalink'}.'"',
                    'title'       => (string)$property->{'title'},
                    'code'        => (string)$property->{'handle'},
                );
            }
        }

        if (($features = $this->query('option_names'))) {
            foreach ($features->xpath('/option-names/option-name') as $option) {
                $id = 'option.'.(int)$option->{'id'};
                $variants[$id] = array(
                    'title'       => (string)$option->{'title'},
                    'description' => 'Свойство',
                );
            }
        }

        try {
            if (($fields = $this->query('product_fields'))) {
                foreach ($fields->xpath('//product-field') as $field) {
                    $id = 'field.'.(int)$field->{'id'};
                    $variants[$id] = array(
                        'description' => 'Дополнительное поле "'.(string)$field->{'handle'}.'"',
                        'title'       => (string)$field->{'title'},
                        'code'        => (string)$field->{'handle'},
                    );
                }
            }
        } catch (Exception $ex) {
            $this->log('Product fields skipped: '.$ex->getMessage());
        }
        return $variants;
    }

    public function count()
    {
        $count = array();
        $count[self::STAGE_CATEGORY] = $this->loadCategories();
        $count[self::STAGE_CATEGORY_REBUILD] = null;
        $this->map[self::STAGE_OPTIONS] = array();
        if ($variants = $this->loadFeatures()) {
            foreach ($variants as $id => $variant) {
                $this->map[self::STAGE_OPTIONS][$id] = array(
                    'name' => $variant['title'],
                    'code' => ifset($variant['code']),
                );
            }
            $count[self::STAGE_OPTIONS] = count($this->map[self::STAGE_OPTIONS]);
        }

        //fields.xml contact fields

        $count[self::STAGE_PRODUCT] = $this->loadProducts();
        if ($count[self::STAGE_PRODUCT]) {
            $count[self::STAGE_PRODUCT_CATEGORY] = $count[self::STAGE_CATEGORY];
        }

        $count[self::STAGE_CUSTOMER_CATEGORY] = $this->loadCustomerGroups();
        $count[self::STAGE_CUSTOMER] = $this->loadCustomers();

        $count[self::STAGE_ORDER] = $this->loadOrders();

        $count[self::STAGE_PAGES] = $this->loadPages();
        $count[self::STAGE_PRODUCT_IMAGE] = null;
        return $count;
    }

    public function step(&$current, &$count, &$processed, $stage, &$error)
    {
        $method_name = 'step'.ucfirst($stage);
        $result = false;
        try {
            if (method_exists($this, $method_name)) {
                $result = $this->$method_name($current[$stage], $count, $processed[$stage]);
                if ($result && ($processed[$stage] > 10) && ($current[$stage] == $count[$stage])) {
                    $result = false;
                }
            } else {
                $this->log(sprintf("Unsupported stage [%s]", $stage), self::LOG_ERROR);
                $current[$stage] = $count[$stage];
            }
        } catch (Exception $ex) {
            $this->stepException($current, $stage, $error, $ex);
        }

        return $result;
    }

    private function stepCategory(&$current_stage, &$count, &$processed)
    {
        static $xml = null;
        if (!$xml) {
            $xml = simplexml_load_file($this->getCategoriesPath());
        }

        if (!isset($this->map[self::STAGE_CATEGORY])) {
            $this->map[self::STAGE_CATEGORY] = array();
            $this->map[self::STAGE_CATEGORY_REBUILD] = array();
        }
        $map = &$this->map[self::STAGE_CATEGORY];
        $rebuild =& $this->map[self::STAGE_CATEGORY_REBUILD];

        foreach ($xml->xpath('/collections/collection') as $c) {
            /**
             * @var SimpleXMLElement $c
             */

            $this->log(__METHOD__, self::LOG_DEBUG, $c->asXML());
            $m = new shopCategoryModel();
            $data = array(
                'name'                   => (string)$c->{'title'},
                'description'            => (string)$c->{'description'},
                'meta_keywords'          => (string)$c->{'meta-keywords'},
                'meta_description'       => (string)$c->{'meta-description'},
                'type'                   => shopCategoryModel::TYPE_STATIC,
                'url'                    => (string)$c->{'permalink'},
                'status'                 => (string)$c->{'is-hidden'} == 'true' ? 0 : 1,
                'include_sub_categories' => 1,
            );
            $parent = (int)$c->{'parent-id'};

            $parent_id = 0;
            if (isset($map[$parent])) {
                $parent_id = $map[$parent];
            }


            if ($id = $m->add($data, $parent_id)) {
                $map[(int)$c->{'id'}] = (int)$id;
                ++$processed;

                if ($parent && !$parent_id) {
                    if (!isset($rebuild[$parent])) {
                        $rebuild[$parent] = array();
                    }
                    $rebuild[$parent][] = (int)$id;
                    $count[self::STAGE_CATEGORY_REBUILD] = count($rebuild);
                }
            }
            ++$current_stage;
        }
        unset($map);
        unset($rebuild);
        return true;
    }

    private function stepPages(&$current_stage, &$count, &$processed)
    {
        static $xml = null;
        static $pages_model;
        if (!$xml) {
            $xml = simplexml_load_file($this->getPagesPath());
        }

        foreach ($xml->xpath('/pages/page') as $p) {
            $params = array(
                'keywords'    => (string)$p->{'meta-keywords'},
                'description' => (string)$p->{'meta-description'},
                'title'       => (string)$p->{'html-title'},
            );

            $data = array(
                'domain'          => 'localhost',
                'route'           => '*',
                'name'            => (string)$p->{'title'},
                'url'             => (string)$p->{'permalink'}.'/',
                'full_url'        => (string)$p->{'permalink'}.'/',
                'content'         => (string)$p->{'content'},
                'status'          => 1,
                'create_datetime' => date("Y-m-d H:i:s", strtotime((string)$p->{'created-at'})),

            );
            @list($data['domain'], $data['route']) = explode(':', $this->getOption('domain', 'localhost:*'));
            if (empty($pages_model)) {
                $pages_model = new shopPageModel();
            }
            if ($data['id'] = $pages_model->add($data)) {
                if ($params = array_filter($params)) {
                    $pages_model->setParams($data['id'], $params);
                }
                ++$processed;
            }

            ++$current_stage;
        }
        return true;
    }

    private function stepCategoryRebuild(&$current_stage, &$count, &$processed)
    {
        $result = false;
        $map = &$this->map[self::STAGE_CATEGORY];
        if ($rebuild = reset($this->map[self::STAGE_CATEGORY_REBUILD])) {
            $parent = key($this->map[self::STAGE_CATEGORY_REBUILD]);
            if (!empty($map[$parent])) {
                $category_id = $map[$parent];
                $category = new shopCategoryModel();
                foreach ($rebuild as $id) {
                    $category->move($id, null, $category_id);
                }
                $item = $category->getById($category_id);
                // update full_url of all descendant
                $category->correctFullUrlOfDescendants($item['id'], trim($item['full_url'], '/'));
            }

            unset($this->map[self::STAGE_CATEGORY_REBUILD][$parent]);
            ++$current_stage;
            $result = true;
        }
        unset($map);

        return $result;
    }

    private function stepOptions(&$current_stage, &$count, &$processed)
    {
        static $feature_model;
        static $type_features_model;

        $data = array_slice($this->map[self::STAGE_OPTIONS], $current_stage, 1);
        if ($option = reset($data)) {

            $id = key($data);
            $options = $this->getOption('options');
            $map = null;
            if (isset($options[$id])) {
                $target = $options[$id]['target'];
                if (isset($options[$id][$target])) {
                    $target = $options[$id][$target];
                }
                $target = explode(':', $target, 2);
                switch ($target[0]) {
                    case 'f+':
                        $feature = array(
                            'name'       => $option['name'],
                            'type'       => shopFeatureModel::TYPE_VARCHAR,
                            'multiple'   => 0,
                            'selectable' => 0,
                        );
                        list($feature['type'], $feature['multiple'], $feature['selectable']) = explode(':', $target[1]);

                        if (!empty($option['code'])) {
                            $feature['code'] = $option['code'];
                        }
                        if (empty($feature_model)) {
                            $feature_model = new shopFeatureModel();
                        }
                        if (empty($type_features_model)) {
                            $type_features_model = new shopTypeFeaturesModel();
                        }
                        $feature['id'] = $feature_model->save($feature);
                        $insert = array(
                            'feature_id' => $feature['id'],
                            'type_id'    => $this->getOption('type', 0)
                        );
                        $type_features_model->insert($insert, 2);
                        ++$processed;
                        $map = 'f:'.$feature['code'].':'.ifempty($options[$id]['dimension']);

                        $this->log('Import option as feature', self::LOG_INFO, $feature);
                        break;
                    case 'f':
                        $map = 'f:'.$target[1].':'.ifempty($options[$id]['dimension']);
                        break;
                    default:
                        $this->log('Option ignored', self::LOG_INFO, $options[$id]);
                        break;
                }
            }

            if ($map) {
                $this->map[self::STAGE_OPTIONS][$id] = $map;
            } else {
                $this->map[self::STAGE_OPTIONS][$id] = null;
            }
        }
        ++$current_stage;

        return true;
    }

    private function stepProduct(&$current_stage, &$count, &$processed)
    {
        static $xml = null;
        if (!$xml) {
            $xml = simplexml_load_file($this->getProductsPath($current_stage));
        }
        if (!isset($this->map[self::STAGE_PRODUCT])) {
            $this->map[self::STAGE_PRODUCT] = array();
        }


        if (!isset($this->map['skus'])) {
            $this->map['skus'] = array();
        }

        $features_map = $this->map[self::STAGE_OPTIONS];

        foreach ($xml->xpath('/products/product') as $p) {
            $this->log(__METHOD__, self::LOG_DEBUG, $p->asXML());

            $product = new shopProduct();
            $product->name = (string)$p->{'title'};
            $product->description = (string)$p->{'description'};
            $product->summary = (string)$p->{'short-description'};
            $product->meta_keywords = (string)$p->{'meta-keywords'};
            $product->meta_description = (string)$p->{'meta-description'};
            $product->meta_title = (string)$p->{'html-title'};
            $product->url = (string)$p->{'permalink'};

            $product->type_id = $this->getOption('type');
            //$product->status = 'is_hidden'

            $product->currency = $this->getConfig()->getCurrency(true);


            $features_data = array();
            foreach ($p->xpath('characteristics/characteristic') as $option) {
                $option_id = 'property.'.(int)$option->{'property-id'};
                $features_data[$option_id] = trim((string)$option->{'title'});
            }

            foreach ($p->xpath('product-field-values/product-field-value') as $option) {
                $option_id = 'field.'.(int)$option->{'product-field-id'};
                $features_data[$option_id] = trim((string)$option->{'value'});
            }

            $features = array();
            foreach ($features_data as $option_id => $value) {
                if (($value !== '') && !empty($features_map[$option_id])) {
                    $target = explode(':', $features_map[$option_id], 2);
                    switch ($target[0]) {
                        case 'f':
                            $code = $target[1];
                            if (strpos($code, ':')) {
                                @list($code, $dimension) = explode(':', $code, 2);
                                if ($dimension && !preg_match('@\d\s+\w+$@', $value)) {
                                    $value = doubleval($value).' '.$dimension;
                                }
                            }
                            $features[$code] = $value;
                            break;
                    }
                }
            }
            $this->log(compact('features', 'features_map'));
            if ($features) {
                $product->features = $features;
            }

            $skus = array();
            $id = 0;
            $variants = array();
            foreach ($p->xpath('variants/variant') as $variant) {
                $variants[] = (int)$variant->{'id'};
                $quantity = (string)$variant->{'quantity'};
                if ($quantity === '') {
                    $quantity = null;
                } else {
                    $quantity = intval($quantity);
                }
                $skus[--$id] = array(
                    'name'           => (string)$variant->{'title'},
                    'sku'            => (string)$variant->{'sku'},
                    'stock'          => array(
                        0 => $quantity,
                    ),
                    'price'          => (double)$variant->{'price'},
                    'available'      => ((string)$p->{'is-hidden'} == 'false') ? 1 : 0,
                    'compare_price'  => (double)$variant->{'old-price'},
                    'purchase_price' => (double)$variant->{'cost-price'},
                    'features'       => array(),
                );
                if ($weight = (double)$variant->{'weight'}) {
                    if ($weight_unit = $this->getOption('weight')) {
                        $weight .= ' '.$weight_unit;
                    }
                    $skus[$id]['features']['weight'] = $weight;
                }
                foreach ($variant->xpath('option-values/option-value') as $sku_option) {
                    $option_id = 'option.'.(string)$sku_option->{'option-name-id'};
                    $value = trim((string)$sku_option->{'title'});
                    if (($value !== '') && !empty($features_map[$option_id])) {
                        $target = explode(':', $features_map[$option_id], 2);
                        switch ($target[0]) {
                            case 'f':
                                $code = $target[1];
                                if (strpos($code, ':')) {
                                    @list($code, $dimension) = explode(':', $code, 2);
                                    if ($dimension && !preg_match('@\d\s+\w+$@', $value)) {
                                        $value = doubleval($value).' '.$dimension;
                                    }
                                }

                                if (!isset($skus[$id]['features'])) {
                                    $skus[$id]['features'] = array();
                                }
                                $skus[$id]['features'][$code] = $value;
                                break;
                        }
                    }
                }
                $skus[$id] = array_filter($skus[$id]);
            }

            $product->skus = $skus;
            if ($product->save()) {
                $this->map[self::STAGE_PRODUCT][(int)$p->{'id'}] = array(
                    'id'     => $product->getId(),
                    'sku_id' => $product->sku_id,
                    'skus'   => array_combine($variants, array_keys($product->skus)),
                );
                ++$processed;
            }
            ++$current_stage;


            foreach ($p->xpath('images/image') as $image) {
                if ($url = (string)$image->{'original-url'}) {
                    if (!isset($this->map[self::STAGE_PRODUCT_IMAGE])) {
                        $this->map[self::STAGE_PRODUCT_IMAGE] = array();
                    }
                    $this->map[self::STAGE_PRODUCT_IMAGE][] = array($product->getId(), $url);
                    $count[self::STAGE_PRODUCT_IMAGE] = count($this->map[self::STAGE_PRODUCT_IMAGE]);
                }
            }
        }
        return true;
    }

    private function stepProductCategory(&$current_stage, &$count, &$processed)
    {
        static $category_products_model;

        $result = false;
        $map = &$this->map[self::STAGE_CATEGORY];
        $product_map = $this->map[self::STAGE_PRODUCT];
        if ($category_id = reset($map)) {
            $collection_id = key($map);
            $response = $this->query('collects', compact('collection_id'));
            $product_ids = array();
            foreach ($response->xpath('/collects/collect') as $c) {
                $this->log(__METHOD__, self::LOG_DEBUG, $c->asXML());
                $product_id = (int)$c->{'product-id'};
                if (isset($product_map[$product_id])) {
                    $product_ids[] = $product_map[$product_id]['id'];
                }
            }
            if ($product_ids) {
                if (!$category_products_model) {
                    $category_products_model = new shopCategoryProductsModel();
                }
                $category_products_model->add($product_ids, $category_id);
                $processed += count($product_ids);
            }

            ++$current_stage;
            $result = true;
            unset($map[$collection_id]);
        }
        unset($map);

        return $result;
    }

    private function stepProductImage(&$current_stage, &$count, &$processed)
    {
        if ($item = reset($this->map[self::STAGE_PRODUCT_IMAGE])) {
            list($product_id, $url) = $item;
            try {
                $name = preg_replace('@[^a-zA-Zа-яА-Я0-9\._\-]+@', '', basename(urldecode($url)));
                $file = $this->getTempPath('pi');
                if (waFiles::delete($file) && waFiles::upload($url, $file) && file_exists($file)) {
                    $processed += $this->addProductImage($product_id, $file, $name);
                } elseif ($file) {
                    $this->log(sprintf('File %s not found', $file), self::LOG_ERROR);
                }
            } catch (Exception $e) {
                $this->log($e->getMessage(), self::LOG_ERROR);
            }
            array_shift($this->map[self::STAGE_PRODUCT_IMAGE]);
            ++$current_stage;
        }

        return true;
    }

    private function stepCustomerCategory(&$current_stage, &$count, &$processed)
    {
        static $xml = null;
        static $discount_model;
        static $category_model;
        if (!$xml) {
            $xml = simplexml_load_file($this->getCustomerGroupsPath());
        }
        if (!$current_stage) {
            $this->map[self::STAGE_CUSTOMER_CATEGORY] = array();
        }

        foreach ($xml->xpath('/client-groups/client-group') as $g) {
            $id = (int)$g->{'id'};
            if (!isset($this->map[self::STAGE_CUSTOMER_CATEGORY][$id])) {

                $category_data = array(
                    'name'   => (string)$g->{'title'},
                    'icon'   => 'contact',
                    'app_id' => 'shop',
                );
                if (!$category_model) {
                    $category_model = new waContactCategoryModel();
                }
                $category_id = $category_model->insert($category_data);
                if ($category_id) {
                    ++$processed;
                    $this->map[self::STAGE_CUSTOMER_CATEGORY][$id] = $category_id;
                    $discount = max(0.0, (double)$g->{'discount'});
                    if ($discount > 0) {
                        if (!$discount_model) {
                            $discount_model = new shopContactCategoryDiscountModel();
                        }
                        $discount_data = array(
                            'category_id' => $category_id,
                            'discount'    => $discount,
                        );
                        $discount_model->insert($discount_data);
                    }
                } else {
                    $this->log("Error while import customer", self::LOG_ERROR);
                }
                ++$current_stage;
            }
        }

        return true;
    }

    private function stepCustomer(&$current_stage, &$count, &$processed)
    {
        static $xml = null;
        if (!$xml) {
            $xml = simplexml_load_file($this->getCustomersPath($current_stage));
        }
        if (!$current_stage) {
            $this->map[self::STAGE_CUSTOMER] = array();
        }

        foreach ($xml->xpath('/clients/client') as $c) {
            $id = (int)$c->{'id'};
            if (!isset($this->map[self::STAGE_CUSTOMER][$id])) {
                $customer = new waContact();
                $customer['firstname'] = (string)$c->{'name'};
                $customer['lastname'] = (string)$c->{'surname'};
                $customer['email'] = (string)$c->{'email'};
                $customer['phone'] = (string)$c->{'phone'};
                $customer['create_datetime'] = date("Y-m-d H:i:s", strtotime((string)$c->{'created-at'}));
                $customer['create_app_id'] = 'shop';
                foreach ($c->xpath('fields-values/fields_value') as $f) {
                    if (($fields = $this->getOption('customer', array())) && (isset($fields[(int)$f->{'id'}]))) {
                        $value = (string)$f->{'value'};
                        if ($value !== '') {
                            $customer->set($fields[(int)$f->{'id'}], $value);
                        }
                    }
                }

                if ($errors = $customer->save()) {
                    $this->log("Error while import customer", self::LOG_ERROR, $errors);
                } else {
                    ++$processed;
                    $customer->addToCategory('shop');
                    $this->map[self::STAGE_CUSTOMER][$id] = $customer->getId();
                    $group = (int)$c->{'client-group-id'};
                    if ($group && !empty($this->map[self::STAGE_CUSTOMER_CATEGORY][$group])) {
                        $customer->addToCategory($this->map[self::STAGE_CUSTOMER_CATEGORY][$group]);
                    }
                }
                ++$current_stage;
            }
        }

        return true;
    }

    private function stepOrder(&$current_stage, &$count, &$processed)
    {
        static $xml = null;
        static $order_model = null;
        static $order_params_model = null;
        static $order_items_model = null;
        static $order_log_model = null;
        static $workflow = null;
        static $workflow_map = array();

        static $map = array(
            'comment'                     => 'comment',
            //client
            'client/middlename'           => 'params:contact_name',
            'client/name'                 => 'params:contact_name',
            'client/surname'              => 'params:contact_name',
            'client/email'                => 'params:contact_email',
            //shipping
            'delivery-price'              => 'shipping',
            'delivery-title'              => 'params:shipping_name',
            'shipping-address/address'    => 'params:shipping_address.street',
            'shipping-address/city'       => 'params:shipping_address.city',
            'shipping-address/country'    => 'params:shipping_address.country',
            'shipping-address/middlename' => 'params:shipping_contact_name',
            'shipping-address/name'       => 'params:shipping_contact_name',
            'shipping-address/phone'      => 'params:shipping_address.phone',
            'shipping-address/state'      => 'params:shipping_address.region',
            'shipping-address/surname'    => '',
            'shipping-address/zip'        => 'params:shipping_address.zip',
            //payment
            'payment-title'               => 'params:payment_name',
            //'shipping-address/full-delivery-address' =>'',
            //'first-current-location'=>'st

            'current-location'            => '',
            'client/ip_addr'              => 'params:ip',
            'discounts'                   => '',
            'total-price'                 => 'total',
            'currency'                    => '',
        );
        if (!$xml) {
            $xml = simplexml_load_file($this->getOrdersPath($current_stage));
        }

        foreach ($xml->xpath('/orders/order') as $o) {
            $contact_id = isset($this->map[self::STAGE_CUSTOMER][(int)$o->{'client'}->{'id'}]) ? $this->map[self::STAGE_CUSTOMER][(int)$o->{'client'}->{'id'}] : null;
            $order = array(
                'contact_id'      => $contact_id,
                'create_datetime' => date("Y-m-d H:i:s", strtotime((string)$o->{'created-at'})),
                'params'          => array(),
                'currency'        => $this->getOption('currency'),
                'state_id'        => 'new',
                'rate'            => 1.0,
                'discount'        => 0.0,
                'tax'             => 0.0,//XXX
                //'is_first'=>1,
            );

            self::dataMap($order, $o, $map);


            $discounts = array();
            foreach ($o->xpath('discounts/discount') as $d) {
                $o['discount'] += (double)$d->{'amount'};
                $discounts[] = $d->{'description'};
            }

            if ($paid = (string)$o->{'financial-status'} == 'paid') {
                $paid_time = strtotime((string)$o->{'paid-at'});
                $order['paid_date'] = date('Y-m-d', $paid_time);
                $order['paid_year'] = date('Y', $paid_time);
                $order['paid_month'] = date('n', $paid_time);
                $order['paid_quarter'] = floor((date('n', $paid_time) - 1) / 3) + 1;

            }


            #state
            // financial-status paid|not_paid
            // fulfillment-status new, accepted, approved, dispatched, delivered, declined


            if (empty($order_model)) {
                $order_model = new shopOrderModel();
            }

            foreach ($o->xpath('fields-values/fields_value') as $f) {
                //extra field values
                if (($fields = $this->getOption('customer', array())) && (isset($fields[(int)$f->{'id'}]))) {
                    $value = (string)$f->{'value'};
                    if ($value !== '') {
                        $order['params'][$fields[(int)$f->{'id'}]] = $value;
                    }
                }
            }


            if ($order['id'] = $order_model->insert($order)) {
                $order['params']['auth_code'] = shopWorkflowCreateAction::generateAuthCode($order['id']);
                $order['params']['auth_pin'] = shopWorkflowCreateAction::generateAuthPin();


                if (!empty($order['params'])) {
                    if (empty($order_params_model)) {
                        $order_params_model = new shopOrderParamsModel();
                    }
                    $order_params_model->set($order['id'], $order['params']);
                }

                foreach ($o->xpath('order-lines/order-line') as $i) {
                    $product_map = $this->map[self::STAGE_PRODUCT][(int)$i->{'product-id'}];
                    $item = array(
                        'order_id'   => $order['id'],
                        'product_id' => $this->map[self::STAGE_PRODUCT][(int)$i->{'product-id'}]['id'],
                        'quantity'   => (int)$i->{'quantity'},
                        'price'      => (double)$i->{'sale-price'},
                        'name'       => (string)$i->{'title'},
                        'type'       => 'product',
                        'stock_id'   => null,//TODO
                        'sku_id'     => ifempty($product_map['skus'][$i->{'variant-id'}], $product_map['sku_id']),

                        //                        ''           => (double)$i->{'product-id'},
                    );
                    if (empty($order_items_model)) {
                        $order_items_model = new shopOrderItemsModel();
                    }
                    $order_items_model->insert($item);

                }


                $logs = array();
                $after_state = $state = 'new';
                foreach ($o->xpath('order-changes/order-change') as $c) {
//action financial_status_changed|fulfillment_status_changed
                    switch ((string)$c->{'action'}) {
                        case 'order_created':
                            foreach ($discounts as $discount) {
                                $logs[] = array(
                                    'order_id'        => $order['id'],
                                    'contact_id'      => $order['contact_id'],
                                    'before_state_id' => 'new',
                                    'after_state_id'  => 'new',
                                    'text'            => nl2br(htmlspecialchars($discount)),
                                    'action_id'       => '',
                                );
                            }
                            $logs[] = array(
                                'contact_id'      => $order['contact_id'],
                                'order_id'        => $order['id'],
                                'datetime'        => date('Y-m-d H:i:s', strtotime((string)$c->{'created-at'})),
                                'before_state_id' => null,
                                'after_state_id'  => 'new',
                                'action_id'       => '',
                                'text'            => _w('Order was placed'),
                            );

                            break;
                        case 'financial_status_changed':
                            break;
                        case 'fulfillment_status_changed':
                            $after_state = isset(self::$financial_state_map[(string)$c->{'value-is'}]) ? self::$financial_state_map[(string)$c->{'value-is'}] : $state;
                            $state = isset(self::$financial_state_map[(string)$c->{'value-was'}]) ? self::$financial_state_map[(string)$c->{'value-was'}] : $after_state;


                            if ($after_state != $state) {
                                if (empty($workflow)) {
                                    $workflow = new shopWorkflow();
                                    $workflow_map = array();
                                    foreach ($workflow->getAvailableActions() as $action_id => $action) {
                                        if (!isset($workflow_map[$action['state']])) {
                                            $workflow_map[$action['state']] = $action_id;
                                        }
                                    }
                                }
                                if (isset($workflow_map[$after_state])) {
                                    $text = $workflow->getActionById($workflow_map[$after_state])->getOption('log_record');
                                } else {
                                    $text = '';
                                }

                                $logs[] = array(
                                    'order_id'        => $order['id'],
                                    'datetime'        => date('Y-m-d H:i:s', strtotime((string)$c->{'created-at'})),
                                    'before_state_id' => $state,
                                    'after_state_id'  => $after_state,
                                    'action_id'       => '',
                                    'text'            => $text,
                                );
                            }
                            break;
                    }


                }
                if ($logs) {
                    if (empty($order_log_model)) {
                        $order_log_model = new shopOrderLogModel();
                    }
                    $logs = array_reverse($logs);
                    foreach ($logs as $log) {
                        $order_log_model->add($log);
                    }
                    $log = end($logs);
                    if ($after_state != 'new') {
                        $update = array(
                            'update_datetime' => $log['datetime'],
                            'state_id'        => $after_state,
                        );
                        $order_model->updateById($order['id'], $update);
                    }
                }

                if ($contact_id) {
                    $shop_customer_model = new shopCustomerModel();
                    $shop_customer_model->updateFromNewOrder($contact_id, $order['id']);
                    shopCustomer::recalculateTotalSpent($contact_id);
                }


                ++$processed;

            } else {
                $this->log("Error while import order", self::LOG_ERROR);
            }
            ++$current_stage;
        }

        return true;
    }

    private function getProductsPath($offset)
    {
        return $this->getTempPath().sprintf('/products.%05d.xml', floor($offset / self::API_PRODUCT_PER_PAGE));
    }

    private function getOrdersPath($offset)
    {
        return $this->getTempPath().sprintf('/orders.%05d.xml', floor($offset / self::API_ORDERS_PER_PAGE));
    }

    private function getCustomersPath($offset)
    {
        return $this->getTempPath().sprintf('/customers.%05d.xml', floor($offset / self::API_CUSTOMERS_PER_PAGE));
    }

    private function getCustomerGroupsPath()
    {
        return $this->getTempPath().'/customer_groups.xml';
    }

    private function getCategoriesPath()
    {
        return $this->getTempPath().'/categories.xml';
    }

    private function getPagesPath()
    {
        return $this->getTempPath().'/pages.xml';
    }

    /**
     * @return int count loaded product records
     * @throws waException
     */
    private function loadProducts()
    {
        $total_count = 0;
        $params = array(
            'per_page' => self::API_PRODUCT_PER_PAGE,
        );
        do {
            $path = $this->getProductsPath((isset($params['page']) ? $params['page'] - 1 : 0) * $params['per_page']);
            $xml = $this->query('products', $params, $path);

            $params['page'] = ifempty($params['page'], 1) + 1;
            $count = self::queryCount($xml);
            $total_count += $count;
        } while ($count == self::API_PRODUCT_PER_PAGE);
        return $total_count;
    }

    /**
     * @return int count loaded customer records
     * @throws waException
     */
    private function loadCustomers()
    {
        $total_count = 0;
        $params = array(
            'per_page' => self::API_CUSTOMERS_PER_PAGE,
        );
        do {
            $path = $this->getCustomersPath((isset($params['page']) ? $params['page'] - 1 : 0) * $params['per_page']);
            $xml = $this->query('clients', $params, $path);

            $params['page'] = ifempty($params['page'], 1) + 1;
            $count = self::queryCount($xml);
            $total_count += $count;
        } while ($count == self::API_CUSTOMERS_PER_PAGE);
        return $total_count;
    }

    /**
     * @return int count loaded customer's group records
     * @throws waException
     */
    private function loadCustomerGroups()
    {
        $params = array();
        $path = $this->getCustomerGroupsPath();
        $xml = $this->query('client_groups', $params, $path);
        return self::queryCount($xml);
    }

    /**
     * @return int
     * @throws waException
     */
    private function loadOrders()
    {
        $total_count = 0;
        $params = array(
            'per_page' => self::API_ORDERS_PER_PAGE,
            //    deleted - получить удаленные заказы
            //    status - статус заказа, значения: open/closed
            //    fulfillment_status - статус доставки, значения: new, accepted, approved, dispatched, delivered, declined
            //    payment_status - статус оплаты, значения: paid/not_paid
            //    delivery_variant - id способа доставки
            //    updated_since - время в UTC для получения списка измененных заказов с этого времени

        );
        do {
            $path = $this->getOrdersPath((isset($params['page']) ? $params['page'] - 1 : 0) * $params['per_page']);
            $xml = $this->query('orders', $params, $path);

            $params['page'] = ifempty($params['page'], 1) + 1;
            $count = self::queryCount($xml);
            $total_count += $count;
        } while ($count == self::API_ORDERS_PER_PAGE);
        return $total_count;
    }

    /**
     * @return int
     * @throws waException
     */
    private function loadCategories()
    {
        $path = $this->getCategoriesPath();
        $xml = $this->query('collections', array(), $path);
        return self::queryCount($xml);
    }

    /**
     * @return int|null
     */
    private function loadPages()
    {
        try {
            $path = $this->getPagesPath();
            $xml = $this->query('pages', array(), $path);
            return self::queryCount($xml);
        } catch (Exception $ex) {
            $this->log('Pages will skipped: '.$ex->getMessage());
            return null;
        }
    }

    private function rewind($current)
    {

    }

    /**
     * @param $query
     * @param string[] $params
     * @param string $file
     * @throws waException
     * @return SimpleXMLElement
     */
    private function query($query, $params = array(), $file = null)
    {
        waSessionStorage::close();
        $apikey = $this->getOption('apikey');
        $hostname = $this->getOption('hostname');
        $password = $this->getOption('password');

        $url = "http://{$apikey}:{$password}@{$hostname}/admin/{$query}.xml";
        $params = array_filter($params);
        if ($params) {
            $url .= '?'.http_build_query($params);
        }
        if (function_exists('curl_init')) {
            $ch = @curl_init($url);
            @curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $response = curl_exec($ch);
            @curl_close($ch);
        } elseif (ini_get('allow_url_fopen')) {
            $response = @file_get_contents($url);
            if (!$response) {
                $error = error_get_last();
                if ($error && ($error['file'] == __FILE__)) {
                    $this->log($error['message'], self::LOG_ERROR, compact('query', 'params', 'url'));
                }
            }
        } else {
            throw new waException('PHP cUrl extension or PHP ini option allow_url_fopen required');
        }

        $this->log(var_export(compact('query', 'params', 'response'), true), self::LOG_DEBUG);

        $json = null;
        if ($response) {
            if ($xml = @simplexml_load_string($response)) {
                if ($file) {
                    file_put_contents($file, $response);
                }
            } else {
                throw new waException('Invalid XML response');
            }
        } else {
            throw new waException('Empty server response');
        }
        return $xml;
    }

    /**
     * @param SimpleXMLElement $xml
     * @return int
     */
    private static function queryCount($xml)
    {
        if (method_exists($xml, 'count')) {
            return $xml->count();
        } else {
            return count($xml->children());
        }
    }

    public function settingOptionsControl($name, $params = array())
    {
        $control = '';

        $options = ifset($params['options'], array());
        unset($params['options']);
        foreach ($params as $field => $param) {
            if (strpos($field, 'wrapper')) {
                unset($params[$field]);
            }
        }
        if (!isset($params['value']) || !is_array($params['value'])) {
            $params['value'] = array();
        }

        waHtmlControl::addNamespace($params, $name);
        $params['control_wrapper'] = '<tr><td>%1$s<br/><span class="hint">%3$s</span></td><td>&rarr;</td><td>%2$s</td></tr>';
        $params['control_separator'] = '</td></tr>
            <tr><td>&nbsp;</td><td>&nbsp;</td><td>';
        $params['title_wrapper'] = '%s';

        $control .= "<table class = \"zebra\"><tbody>";
        foreach ($options as $code => $option) {
            $option_params = $params;
            if (preg_match('@^option\..+@', $code)) {
                $option_params['feature_filter'] = array(
                    'multiple' => 1,
                );
            }
            $option_params['target'] = 'feature';
            $option_params['title'] = ifset($option['title'], $code);
            $option_params['description'] = ifset($option['description'], $code);
            $option_params['value'] = isset($params['value'][$code]) ? $params['value'][$code] : array('feature' => $code,);
            $control .= waHtmlControl::getControl('OptionMapControl', $code, $option_params);
        }
        $control .= "</tbody>";
        $control .= "</table>";
        return $control;
    }

    protected function getContextDescription()
    {
        $url = $this->getOption('hostname');
        return empty($url) ? '' : sprintf(_wp('Import data from %s'), $url);
    }


    private static function dataMap(&$result, $xml, $map)
    {
        foreach ($map as $field => $target) {

            if ($target) {
                $x = $xml;
                if (strpos($field, '/')) {
                    while (strpos($field, '/')) {
                        list($sub_field, $field) = explode('/', $field, 2);
                        $x = $x->{$sub_field};
                    }
                }

                if ($x) {
                    $data = (string)$x->{$field};
                    if (strpos($target, ':')) {
                        if (!empty($data)) {
                            list($target, $sub_target) = explode(':', $target, 2);
                            if (empty($result[$target][$sub_target])) {
                                $result[$target][$sub_target] = '';
                            } else {
                                $result[$target][$sub_target] .= ' ';
                            }
                            $result[$target][$sub_target] .= $data;
                        }
                    } else {
                        $result[$target] = $data;
                    }
                }
            }
        }
    }
}
