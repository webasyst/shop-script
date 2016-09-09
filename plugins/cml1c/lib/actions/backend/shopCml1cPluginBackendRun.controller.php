<?php

/**
 * Class shopCml1cPluginBackendRunController
 */
class shopCml1cPluginBackendRunController extends waLongActionController
{
    private $debug = false;

    //Мой склад fix
    private $_convert_order_price = false;

    const STAGE_ORDER = 'order';
    const STAGE_CATEGORY = 'category';
    const STAGE_FEATURE = 'feature';
    const STAGE_PRODUCT = 'product';
    const STAGE_SKU = 'sku';
    const STAGE_PRICE = 'price';
    const STAGE_STOCK = 'stock';
    const STAGE_OFFER = 'offer';
    const STAGE_IMAGE = 'image';

    //TODO use self generated UUID for catalogue
    const UUID_OWNER = 'bd72d900-55bc-11d9-848a-00112f43529a';
    const UUID_OFFER = 'bd72d8f9-55bc-11d9-848a-00112f43529a';

    private static $node_map = array(
        //Импорт каталога (import.xml)
        "КоммерческаяИнформация/Классификатор/Группы"         => self::STAGE_CATEGORY,
        "КоммерческаяИнформация/Классификатор/Свойства"       => self::STAGE_FEATURE,
        "КоммерческаяИнформация/Каталог/Товары"               => self::STAGE_PRODUCT,
        //Импорт предложений (offers.xml)
        "КоммерческаяИнформация/ПакетПредложений/ТипыЦен"     => self::STAGE_PRICE,
        "КоммерческаяИнформация/ПакетПредложений/Склады"      => self::STAGE_STOCK,
        "КоммерческаяИнформация/ПакетПредложений/Предложения" => self::STAGE_OFFER,
        "КоммерческаяИнформация/ПакетПредложений/Свойства"    => self::STAGE_FEATURE,
        //Импорт заказов - поддержка не планируется
        "КоммерческаяИнформация/Документ-"                    => self::STAGE_ORDER,
    );

    private static $feature_xpath_map = array(
        '//ЗначениеРеквизита'                         => array(
            'field'     => 'code',
            'namespace' => 'value',
        ),
        '//ХарактеристикиТовара/ХарактеристикаТовара' => array(
            'field'     => 'name',
            'namespace' => 'feature',
        ),
        '//ЗначенияСвойств/ЗначенияСвойства'          => array(
            'field'     => 'id_1c',
            'namespace' => 'uuid',

        ),
        '//Свойства'                                  => array(
            'field'     => 'cml1c_id',
            'namespace' => 'uuid',
        ),
    );

    private static $feature_namespace_map = array(
        'value'   => array(
            'name'        => 'Реквизиты товаров - блок <ЗначениеРеквизита>',
            'description' => 'Реквизиты сопоставляются для синхронизации по наименованию ревизита (элемент <Наименование> блока <ЗначениеРеквизита> в файле CommerceML).
Характеристики артикулов (модификаций) будут импортированы только если они заданы в Shop-Script как характеристики типа checkbox.',
            'field'       => 'code',
            'default'     => 'skip',
        ),
        'feature' => array(
            'name'        => 'Характеристики товаров - блок <ХарактеристикиТовара>',
            'description' => 'Характеристики сопоставляются для синхронизации по наименованию характеристики (элемент <Наименование> блока <ХарактеристикаТовара> в файле CommerceML).
Характеристики артикулов (модификаций) будут импортированы только если они заданы в Shop-Script как характеристики типа checkbox.',
            'field'       => 'code',
            'default'     => 'add',
        ),
        'uuid'    => array(
            'name'        => 'Справочник свойств товаров - блок <Классификатор>',
            'description' => 'Свойства сопоставляются для синхронизации по идентификатору (элемент <Ид> блока <Свойство> в файле CommerceML).
Характеристики артикулов (модификаций) будут импортированы только если они заданы в Shop-Script как характеристики типа checkbox.',
            'field'       => 'cml1c_id',
            'default'     => 'add',
        ),
    );

    private static $chunk_map = array(
        self::STAGE_CATEGORY => 100,
        self::STAGE_PRODUCT  => 100,
        self::STAGE_OFFER    => 100,
        self::STAGE_ORDER    => 100,
    );

    private static $currency = array(
        'default' => null,
        'plugin'  => null,
    );

    private static $price_map = null;

    private $fp;
    /**
     *
     * @var shopProductsCollection
     */
    private $collection;

    /**
     *
     * @var XMLReader
     */
    private $reader;
    private static $read_method;
    private static $read_offset = array();

    private $path = array();
    /**
     * @var XMLWriter
     */
    private $writer;

    /**
     * @var shopCml1cPlugin
     */
    private static $plugin = null;

    /**
     *
     * @return shopCml1cPlugin
     */
    private function plugin()
    {
        if (!self::$plugin) {
            self::$plugin = wa('shop')->getPlugin('cml1c');
        }
        return self::$plugin;
    }

    private function pluginSettings($name, $value = null)
    {
        if ($value !== null) {
            $settings = $this->plugin()->getSettings();
            $settings[$name] = $value;
            $this->plugin()->saveSettings($settings);
        } elseif (is_array($name)) {
            $settings = $name;
            $settings += $this->plugin()->getSettings();
            $this->plugin()->saveSettings($settings);
        } else {
            return $this->plugin()->getSettings($name);
        }
        return $settings;
    }

    /**
     * @uses self::initExport()
     * @uses self::initImport()
     */
    protected function init()
    {
        try {
            self::$plugin = null;
            self::$is_done = false;

            $type_model = $this->getModel('type');
            /**
             * @var shopTypeModel $type_model
             */

            $this->data['encoding'] = 'windows-1251'; // 'windows-1251'/'utf-8'
            //validation option — since 2.07 enable extended stock features (not supported yet)
            $this->data['version'] = '2.05';
            $this->data['timestamp'] = time();
            $this->data['direction'] = waRequest::post('direction', 'import');
            $this->data['types'] = array_keys($type_model->getTypes());
            $this->data['map'] = array();
            switch ($this->data['direction']) {
                case 'export':
                    $this->initExport();
                    $processed = 0;
                    break;
                case 'import':
                default:
                    $this->data['direction'] = 'import';
                    $this->initImport();
                    $processed = array(
                        'new'    => 0,
                        'update' => 0,
                        'skip'   => 0,
                    );
                    if (waRequest::post('configure')) {
                        $processed['analyze'] = 0;
                    }
                    break;
            }

            $this->data['current'] = array_fill_keys(array_keys($this->data['count']), 0);

            $stages = array(
                self::STAGE_CATEGORY,
                self::STAGE_FEATURE,
                self::STAGE_PRODUCT,
                self::STAGE_PRICE,
                self::STAGE_SKU,
                self::STAGE_STOCK,
                self::STAGE_OFFER,
                self::STAGE_ORDER,
                self::STAGE_IMAGE,
            );
            $this->data['processed_count'] = array_fill_keys($stages, $processed);

            $this->data['stage'] = reset($stages);
            $this->data['error'] = null;
            $this->data['stage_name'] = $this->getStageName($this->data['stage']);
            $this->data['memory'] = memory_get_peak_usage();
            $this->data['memory_avg'] = memory_get_usage();
        } catch (waException $ex) {
            $this->error($ex->getMessage());
            echo json_encode(array('error' => $ex->getMessage(),));
            exit;
        }
    }

    /**
     *
     * @return shopProductsCollection
     */
    private function getCollection()
    {
        if (!$this->collection) {
            $hash = '';
            $this->collection = new shopProductsCollection($hash);
        }

        return $this->collection;
    }

    private function getProducts($offset, $limit = 50)
    {
        return $this->getCollection()->getProducts('*', $offset, $limit, false);
    }

    private function getFeatureRelation($code)
    {
        static $feature_relation = array();
        $model = $this->getModel('feature');
        /**
         * @var shopFeatureModel $model
         */


        $code_ = array_diff($code, array_keys($feature_relation));
        if ($code_) {
            $multiple_features = $model->getByField(
                array(
                    'code'     => $code_,
                    'multiple' => 1,
                ),
                'code'
            );
            foreach ($code_ as $c) {
                $feature_relation[$c] = !empty($multiple_features[$c]['multiple']);
            }
        }
        foreach ($code as $i => $c) {
            if (empty($feature_relation[$c])) {
                unset($code[$i]);
            }
        }
        return $code;
    }

    /**
     * @usedby self::init()
     */
    protected function initExport()
    {
        $this->data['orders'] = array();

        $this->data['price_type'] = $this->pluginSettings('price_type');
        $this->data['price_type_uuid'] = $this->pluginSettings('price_type_uuid');

        $this->data['export_product_name'] = $this->pluginSettings('export_product_name');

        $this->data['export_custom_properties'] = $this->pluginSettings('contact_fields');

        $this->data['purchase_price_type'] = $this->pluginSettings('purchase_price_type');
        $this->data['purchase_price_type_uuid'] = $this->pluginSettings('purchase_price_type_uuid');
        if ($order_state = $this->pluginSettings('order_state')) {
            $this->data['order_state'] = array_keys(array_filter($order_state));
        }

        $this->data['stock_id'] = max(0, $this->pluginSettings('stock'));

        $export = waRequest::post('export');
        if (!is_array($export)) {
            $export = array();
        }

        switch (waRequest::param('module', 'backend')) {
            case 'frontend':
                $name = $this->processId.'.xml';
                break;
            case 'backend':
            default:
                $name = array();
                if (!empty($export['order'])) {
                    $name[] = 'orders';
                }
                if (!empty($export['product'])) {
                    $name[] = 'offers';
                }
                if (empty($name)) {
                    $name[] = 'nothing';
                }
                $name = sprintf('%s_(%s).xml', implode('_', $name), date('Y-m-d'));
                break;
        }

        $this->data['filename'] = $this->plugin()->path($name);
        $this->data['fsize'] = 0;

        $this->initExportCount($export);

        $this->writer = new XMLWriter();
        $w = &$this->writer;
        $w->openMemory();
        $w->setIndent(true);
        $w->setIndentString("\t");
        $w->startDocument('1.0', $this->data['encoding']);
        if (wa()->getEnv() == 'backend') {
            $url = $this->plugin()->getPluginStaticUrl(true);
            $w->writePi('xml-stylesheet', 'type="text/xsl" href="'.$url.'xml/sale.xsl"');
        }
        $w->startElement('КоммерческаяИнформация');
        $w->writeAttribute('ВерсияСхемы', $this->data['version']);
        $w->writeAttribute('ДатаФормирования', date("Y-m-d\\TH:i:s"));
        $w->writeComment('Shop-Script: '.wa()->getVersion('shop'));
        $w->writeComment('1С плагин: '.$this->plugin()->getVersion());
        $this->write();
    }

    private function initExportCount($export)
    {
        $model = $this->getModel('order');
        /**
         * @var shopOrderModel $model
         */

        $count = array();
        if (!empty($export['order'])) {
            $this->data['orders_time'] = !empty($export['new_order']) ? $this->plugin()->exportTime() : 0;
            $where = array();
            $params = array();
            if (!empty($this->data['orders_time'])) {
                $this->data['orders_time'] = $this->data['orders_time'] - 3600;
                $params['orders_time'] = date("Y-m-d H:i:s", $this->data['orders_time']);
                $where[] = '(IFNULL(`update_datetime`,`create_datetime`) > s:orders_time)';
            }
            if (!empty($this->data['order_state'])) {
                $where[] = '(`state_id` IN (s:order_state))';
                $params['order_state'] = $this->data['order_state'];
            }

            if ($where) {
                $count[self::STAGE_ORDER] = $model->select('COUNT(*)')->where(implode(' AND ', $where), $params)->fetchField();
            } else {
                $count[self::STAGE_ORDER] = $model->countAll();
            }
        }
        if (!empty($export['product'])) {
            $count[self::STAGE_CATEGORY] = $this->getCategoryModel()->countByField('type', shopCategoryModel::TYPE_STATIC);
            $count[self::STAGE_PRODUCT] = $this->getCollection()->count();
            $count[self::STAGE_OFFER] = $count[self::STAGE_PRODUCT];
        }
        $this->data['count'] = $count;
    }

    /**
     * @throws waException
     * @usedby self::init()
     */
    protected function initImport()
    {
        self::$read_method = null;
        self::$read_offset = array();
        self::$price_map = null;

        #check requirements
        if (!extension_loaded('xmlreader')) {
            throw new waException('PHP extension xmlreader required');
        }

        #handle file paths
        if ($zip = waRequest::post('zipfile')) {
            $this->data['zipfile'] = $this->plugin()->path(basename($zip));
            $file = preg_replace('@([\\/]|^)\\.\\.[\\/]@', '/', waRequest::post('filename'));
        } else {
            $file = basename(waRequest::post('filename'));
        }

        $this->data['filename'] = $this->plugin()->path($file);
        $this->data['files'] = array(
            $this->data['filename'],
        );

        if (!empty($zip)) {
            $this->extract($file, $this->data['filename']);
            if (($file == 'offers.xml') || (wa()->getEnv() == 'backend')) {
                $this->data['files'][] = $this->data['zipfile'];
            }
            if (preg_match('@[\\/]@', $file)) {
                $this->data['base_path'] = dirname($file).'/';
            }
        }

        #init import params
        $this->initImportPrice();

        $this->data['update_product_fields'] = (array)$this->pluginSettings('update_product_fields');

        $this->data['default_type_id'] = $this->pluginSettings('product_type');
        $this->data['update_product_types'] = $this->pluginSettings('update_product_types');
        if ($this->data['default_type_id']) {
            $type_model = $this->getModel('type');
            /**
             * @var shopTypeModel $type_model
             */

            if (!$type_model->getById($this->data['default_type_id'])) {
                throw new waException('Выбранный тип товаров по умолчанию не существует');
            }
        }

        $this->data['use_product_currency'] = intval(wa('shop')->getSetting('use_product_currency'));

        $this->data['configure'] = !!waRequest::post('configure');

        $this->initImportStocks();
        $this->initImportFeatures();
        $this->initImportCount();
    }

    private function initImportCount()
    {
        $this->data['count'] = array();
        self::$read_method = null;

        $this->openXml();
        while ($this->read(self::$read_method)) {
            self::$read_method = 'unknown_count';
            if ($this->reader->depth >= 2) {
                if ($stage = $this->getStage()) {
                    $method_name = $this->getMethod($stage);
                    if (method_exists($this, $method_name)) {
                        list($node, self::$read_method) = self::$node_name_map[$stage];
                        if (self::$read_method == 'next') {
                            $map = array_flip(self::$node_map);
                            $path = ifset($map[$stage], '/').'/'.$node;
                        } else {
                            $path = null;
                        }
                        while (($current_stage = $this->getStage()) && ($current_stage == $stage)) {

                            if (!isset($this->data['count'][$stage])) {
                                $this->data['count'][$stage] = 0;
                                $method_ = 'read';
                            } else {
                                $method_ = self::$read_method;
                            }

                            if ($this->read($method_, $path)) {
                                if ($this->reader->nodeType == XMLReader::ELEMENT) {
                                    if ($this->reader->name == $node) {
                                        ++$this->data['count'][$stage];
                                    }
                                }
                            } else {
                                self::$read_method = 'end_count';
                                $this->read(self::$read_method);
                                break 2;
                            }

                        }
                    }
                }
                self::$read_method = 'next';
            }
        }
        if (empty($this->data['configure'])) {
            if (!empty($this->data['count'][self::STAGE_PRODUCT])) {
                $this->data['count'][self::STAGE_IMAGE] = null;
            }
            if (!empty($this->data['count'][self::STAGE_CATEGORY]) &&
                ($this->pluginSettings('update_product_categories') == 'skip')
            ) {
                $this->data['count'][self::STAGE_CATEGORY] = null;
            }
        }
        $this->reader->close();
        self::$read_method = null;
    }

    private function initImportPrice()
    {
        $this->data['price_type'] = mb_strtolower($this->pluginSettings('price_type'), 'utf-8');
        $this->data['price_type_uuid'] = $this->pluginSettings('price_type_uuid');

        $this->data['purchase_price_type'] = mb_strtolower($this->pluginSettings('purchase_price_type'), 'utf-8');
        $this->data['purchase_price_type_uuid'] = $this->pluginSettings('purchase_price_type_uuid');
    }

    private function initImportFeatures()
    {
        $this->data['features_map'] = array();

        $features_map = $this->pluginSettings('features_map');
        /**
         * array(
         *  'namespace'=>array(
         *      'key'=>'context:id(:extra_param)',
         *  ),
         *  ...
         *
         * context
         *  s = skip
         *  f = feature
         *  p = params
         */
        if (!is_array($features_map)) {
            $features_map = array();
        } else {
            foreach ($features_map as $namespace => $targets) {
                if (!isset($features_map[$namespace])) {
                    $features_map[$namespace] = array();
                }
                foreach ($targets as $name => $target) {
                    if (!isset($features_map[$namespace][$name])) {
                        $features_map[$namespace][$name] = array();
                    }
                    $this->data['features_map'][$namespace][$name]['target'] = $target;
                }
            }
        }

        $features = waRequest::post('features');
        if ($features) {
            $features_map_changed = false;
            foreach ($features as $namespace => $targets) {
                if (!isset($features_map[$namespace])) {
                    $features_map[$namespace] = array();
                }
                foreach ($targets as $name => $target) {
                    $target_namespace = ifset($target['target']);

                    $target_value = ifset($target[$target_namespace]);

                    if (empty($target_value)) {
                        if ($target_namespace == 's') {
                            if (ifset($features_map[$namespace][$name]) != $target_namespace) {
                                $features_map[$namespace][$name] = $target_namespace;
                                $features_map_changed = true;
                            }

                        }
                    } elseif ($target_value == 's') {
                        if (ifset($features_map[$namespace][$name]) != $target_value) {
                            $features_map[$namespace][$name] = $target_value;
                            $features_map_changed = true;
                        }

                    } else {
                        list($type, $target_value) = explode(':', $target_value, 2);
                        switch ($type) {

                            case 'f+': # add as new feature
                                $feature = array(
                                    'name'       => ifempty($target['name'], $name),
                                    'type'       => shopFeatureModel::TYPE_VARCHAR,
                                    'cml1c_id'   => ifempty($target['cml1c_id']),
                                    'multiple'   => 0,
                                    'selectable' => 0,
                                );
                                if (!empty($target['cml1c_id'])) {
                                    $feature['cml1c_id'] = $target['cml1c_id'];
                                    $feature['code'] = $target['cml1c_id'];
                                }
                                list($feature['type'], $feature['multiple'], $feature['selectable']) = explode(':', $target_value);
                                if (empty($feature_model)) {
                                    $feature_model = new shopFeatureModel();
                                }
                                if (empty($type_features_model)) {
                                    $type_features_model = new shopTypeFeaturesModel();
                                }
                                $feature['id'] = $feature_model->save($feature);
                                $insert = array(
                                    'feature_id' => $feature['id'],
                                    'type_id'    => $this->data['default_type_id'],
                                );

                                $type_features_model->insert($insert, 2);

                                $features_map[$namespace][$name] = 'f:'.$feature['code'];
                                if (!empty($target['dimension'])) {
                                    $features_map[$namespace][$name] .= ':'.$target['dimension'];
                                }

                                if (!isset($this->data['new_features'])) {
                                    $this->data['new_features'] = array();
                                }
                                $this->data['new_features'][$feature['code']] = array(
                                    'id'    => $feature['id'],
                                    'types' => array($this->data['default_type_id']),
                                );

                                $features_map_changed = true;
                                break;
                            case 'f':
                                if (!empty($target['dimension'])) {
                                    $target_value .= ':'.$target['dimension'];
                                }
                                if (ifset($features_map[$namespace][$name]) != 'f:'.$target_value) {
                                    $features_map[$namespace][$name] = 'f:'.$target_value;
                                    $features_map_changed = true;
                                }
                                break;
                            case 'p':
                                if (ifset($features_map[$namespace][$name]) != 'p:'.$target_value) {
                                    $features_map[$namespace][$name] = 'p:'.$target_value;
                                    $features_map_changed = true;
                                }
                                break;

                        }
                    }
                }
            }

            if ($features_map_changed) {
                $this->pluginSettings('features_map', $features_map);
            }
        }


    }

    private function initImportStocks()
    {
        $this->data['stock_map'] = array();
        $stock_model = $this->getModel('stock');
        /**
         * @var shopStockModel $stock_model
         */
        $this->data['stock_id'] = max(0, waRequest::get('stock', $this->pluginSettings('stock'), waRequest::TYPE_INT));
        if ($this->data['stock_id']) {

            if (!$stock_model->stockExists($this->data['stock_id'])) {
                throw new waException('Выбранный склад не существует');
            }
        }


        $stock_map = $this->pluginSettings('stock_map');
        if (!is_array($stock_map)) {
            $stock_map = array();
        }

        $stocks = waRequest::post('stocks');
        $stock_map_changed = false;
        if ($stocks) {
            foreach ($stocks as $uuid => $stock_id) {
                if (($uuid) && ($uuid != 'default') && ($stock_id != 0) && (ifset($stock_map[$uuid]) != $stock_id)) {
                    $stock_map[$uuid] = $stock_id;
                    $stock_map_changed = true;
                }
            }
        }
        if ($stock_map) {
            $exists_stocks = $stock_model->getAll('id');

            foreach ($stock_map as $uuid => $stock_id) {
                if (($stock_id != -1) && !isset($exists_stocks[$stock_id])) {
                    if (isset($this->data['stock_map'][$uuid])) {
                        unset($this->data['stock_map'][$uuid]);
                    }
                    unset($stock_map[$uuid]);
                    $stock_map_changed = true;
                } else {
                    if (!isset($this->data['stock_map'][$uuid])) {
                        $this->data['stock_map'][$uuid] = array();
                    }
                    $this->data['stock_map'][$uuid]['stock_id'] = $stock_id;
                }
            }
            if ($stock_map_changed) {
                $this->pluginSettings('stock_map', $stock_map);
            }
        }

        if ($this->pluginSettings('stock_complement')) {

            $this->data['stock_complement'] = array();
            if (empty($exists_stocks)) {
                $exists_stocks = $stock_model->getAll('id');
            }
            foreach ($exists_stocks as $id => $stock) {
                $this->data['stock_complement'][] = $id;
            }
        } else {
            $this->data['stock_complement'] = false;
        }

        if ($this->pluginSettings('stock_setup')) {

            $this->data['stock_setup'] = array();
            if (empty($exists_stocks)) {
                $exists_stocks = $stock_model->getAll('id');
            }
            foreach ($exists_stocks as $id => $stock) {
                $this->data['stock_setup'][] = $id;
            }
        } else {
            $this->data['stock_setup'] = false;
        }
    }

    private function initData()
    {
        $feature_model = $this->getModel('feature');
        /**
         * @var shopFeatureModel $feature_model
         */
        if (!$feature_model->getByCode('weight')) {
            $feature = array(
                'name' => _w('Weight'),
                'code' => 'weight',
                'type' => shopFeatureModel::TYPE_DIMENSION.'.'.'weight',
            );

            if ($feature_model->save($feature)) {
                $feature_map =& $this->data['map'][self::STAGE_PRODUCT];
                $feature_map[$feature['name']] = $feature['code'];
            }
        }
    }

    private function read($method = 'read', $node = null)
    {
        $result = null;
        switch ($method) {
            case 'skip':
                $result = true;
                break;
            case 'next':
                if ($node) {
                    $base = explode('/', $node);
                    $name = array_pop($base);
                    $depth = count($base);
                    $base = implode('/', $base);

                    do {
                        $result = $this->read($method, false);
                        $path = implode('/', array_slice($this->path, 0, $depth));
                    } while ($result
                        && ($path == $base)
                        && (($this->reader->nodeType != XMLReader::ELEMENT) || ($this->reader->name != $name))
                    );
                } else {
                    $result = $this->reader->next();
                }
                break;
            case 'read':
            default:
                $result = $this->reader->read();
                break;
        }
        $this->path();
        return $result;
    }

    private function path()
    {
        $node = (string)$this->reader->name;
        $depth = (int)$this->reader->depth;

        $this->path = array_slice($this->path, 0, $depth);
        $this->path[$depth] = $node;
        if ($depth) {
            $this->path += array_fill(0, $depth, '—');
        }
        return $this->path;
    }


    private static $node_name_map = array(
        self::STAGE_CATEGORY => array('Группа', 'read'),
        self::STAGE_FEATURE  => array('Свойство', 'next'),
        self::STAGE_PRODUCT  => array('Товар', 'next'),
        self::STAGE_PRICE    => array('ТипЦены', 'next'),
        self::STAGE_STOCK    => array('Склад', 'next'),
        self::STAGE_OFFER    => array('Предложение', 'next'),
    );

    private function openXml()
    {
        if ($this->reader) {
            $this->reader->close();
        } else {
            $this->reader = new XMLReader();
        }

        if (!file_exists($this->data['filename'])) {
            throw new waException('XML file missed');
        }

        libxml_use_internal_errors(true);
        libxml_clear_errors();
        if (!@$this->reader->open($this->data['filename'], null, LIBXML_NONET)) {
            $this->error('Error while open XML '.$this->data['filename']);
            throw new waException('Ошибка открытия XML файла');
        }
    }

    /**
     * @param string[] $path XPath
     * @return string Import stage name
     */
    private function getStage($path = null)
    {
        $stage = null;
        $node_path = implode('/', array_slice($path ? $path : $this->path, 0, 3));
        if (isset(self::$node_map[$node_path])) {
            $stage = self::$node_map[$node_path];
        }
        return $stage;
    }

    private function getMethod($stage, $complete = false)
    {
        if ($complete) {
            $method_name = 'completeImport'.ucfirst($stage);
        } else {
            $method_name = 'stepImport'.ucfirst($stage);
        }
        if (!empty($this->data['configure'])) {
            $method_name .= 'Configure';
        }
        return $method_name;
    }

    private static $is_done;

    protected function isDone()
    {
        if (!self::$is_done) {
            self::$is_done = true;
            foreach ($this->data['current'] as $stage => $current) {
                if ($current < $this->data['count'][$stage]) {
                    self::$is_done = false;
                    $this->data['stage'] = $stage;
                    break;
                }
            }
            $this->data['stage_name'] = $this->getStageName($this->data['stage']);
            if (self::$is_done && ($this->data['direction'] == 'export') && empty($this->data['ready'])) {
                try {
                    $this->data['ready'] = true;
                    if (!empty($this->writer) && is_object($this->writer)) {

                        $this->writer->endElement(/*КоммерческаяИнформация*/);

                        if (!empty($this->data['timestamp'])) {
                            $interval = time() - $this->data['timestamp'];
                            $interval = sprintf('%02d ч %02d мин %02d с', floor($interval / 3600), floor($interval / 60) % 60, $interval % 60);
                            $this->writer->writeComment(sprintf(' Время формирования: %s ', $interval));

                        }

                        if (!empty($this->data['memory'])) {
                            $this->writer->writeComment(sprintf(' Использование памяти, максимум: %0.3f МБ ', $this->data['memory'] / 1048576));
                        }

                        $this->write();
                        unset($this->writer);
                    }
                } catch (waException $ex) {
                    $this->error($ex->getMessage());
                }
            }
        }
        if (self::$is_done) {
            $this->data['is_done'] = true;
        }
        return self::$is_done;
    }

    /**
     * @return bool
     * @uses self::stepImport
     * @uses self::stepExport
     */
    protected function step()
    {
        $result = false;
        try {
            if ($method_name = $this->getStepMethod()) {
                $result = $this->{$method_name}($this->data['current'], $this->data['count'], $this->data['processed_count']);
                if ($this->data['direction'] == 'export') {
                    $this->write();
                }
            }
        } catch (Exception $ex) {
            $this->error($this->data['direction'].'@'.$this->data['stage'].': '.$ex->getMessage()."\n".$ex->getTraceAsString());
            sleep(5);
        }
        $this->data['memory'] = memory_get_peak_usage();
        $this->data['memory_avg'] = memory_get_usage();

        return $result;
    }

    protected function getStepMethod()
    {
        $methods = array(
            'step'.ucfirst($this->data['direction']),
            'step'.ucfirst($this->data['direction']).ucfirst($this->data['stage']),
        );
        $method_name = null;
        foreach ($methods as $method) {
            if (method_exists($this, $method)) {
                $method_name = $method;
                break;
            }
        }
        if (!$method_name) {
            $this->error(sprintf("Unsupported actions %s", implode(', ', $methods)));
        }
        return $method_name;
    }

    private function getCategoryId(&$category)
    {
        if (empty($category['id_1c'])) {
            do {
                $category['id_1c'] = shopCml1cPlugin::makeUuid();
            } while ($this->getCategoryModel()->getByField('id_1c', $category['id_1c']));

            $this->getCategoryModel()->updateById($category['id'], array('id_1c' => $category['id_1c']));
        }
        return $category['id_1c'];
    }

    protected function finish($filename)
    {
        switch (ifset($this->data['direction'])) {
            case 'export':
                $result = $this->finishExport($filename);
                break;

            case 'import':
            default:
                $result = $this->finishImport($filename);
                break;
        }

        $this->info();

        return $result;
    }

    private function finishImport($filename)
    {
        $this->data['is_done'] = true;
        $result = false;
        if (waRequest::param('module') == 'frontend') {
            if ($this->data['filename']) {
                if ($this->reader) {
                    $this->reader->close();
                }
                if (empty($this->data['configure'])) {
                    waFiles::delete($this->data['filename']);
                }
                $result = true;
                //$this->info();
            }
        }
        if ($this->getRequest()->post('cleanup')) {
            if ($this->reader) {
                $this->reader->close();
            }
            if (empty($this->data['configure'])) {
                foreach ($this->data['files'] as $file) {
                    waFiles::delete($file, true);
                }
            }
        }
        return $result;
    }

    private function finishExport($filename)
    {
        $result = false;
        $path = $this->plugin()->path($this->processId.'.xml');
        if ($this->fp) {
            fclose($this->fp);
            $this->fp = null;
        }

        if ($this->getRequest()->post('cleanup')) {

            $this->plugin()->exportTime(true);
            $this->getStorage()->del('processId');

            if (waRequest::param('module') == 'frontend') {
                waFiles::delete($path);
            }
            $result = true;
        } else {
            if (file_exists($path) && false) {
                $this->plugin()->validate(file_get_contents($path), $path, $this->data['version']);
            }
        }
        return $result;
    }

    public function sendFile()
    {
        waFiles::readFile($this->plugin()->path($this->processId.'.xml'), null, false);
    }

    public function exchangeReport()
    {
        $interval = '—';
        if (!empty($this->data['timestamp'])) {
            $interval = time() - $this->data['timestamp'];
            $interval = sprintf('%02d ч %02d мин %02d с', floor($interval / 3600), floor($interval / 60) % 60, $interval % 60);
        }

        $report = sprintf('Автоматический обмен с IP %s завершен за %s. ', waRequest::getIp(), $interval);
        $chunks = array();
        foreach ($this->data['processed_count'] as $stage => $count) {
            if ($data = $this->getStageReport($stage, $this->data['processed_count'])) {
                $chunks[] = htmlentities($data, ENT_QUOTES, 'utf-8');
            }
        }
        if ($chunks) {
            switch ($this->data['direction']) {
                case 'import':
                    $report .= ' Импортировано: ';
                    break;
                case 'export':
                    $report .= ' Экспортировано: ';
                    break;
            }
            $report .= implode(', ', $chunks);

            if (!empty($this->data['memory'])) {
                $memory = $this->data['memory'] / 1048576;
                $report .= ' '.sprintf('(Потребление памяти, максимум: %0.3f МБ)', $memory);
            }
        }
        waLog::log($report, 'shop/plugins/cml1c/report.log');
        return $report;
    }


    private function getFeaturesControl($name, $params = array())
    {
        waHtmlControl::registerControl('OptionMapControl', array(&$this, "settingOptionMapControl"));
        $html = '';

        $codes = array();
        waHtmlControl::addNamespace($params, $name);
        $name_params = $params;
        unset($name_params['control_wrapper']);
        $name_params['style'] = 'display: none;';

        foreach ($this->data['features_map'] as $xpath => $features) {
            foreach ($features as $key => $feature) {
                if (!empty($feature['code'])) {
                    $codes[] = $feature['code'];
                }
                if (!empty($feature['target']) && preg_match('@^f:([^:]+)(:.+)?$@', $feature['target'], $matches)) {
                    $codes[] = $matches[1];
                }
            }
        }

        $params['description'] = '';

        foreach ($this->data['features_map'] as $namespace => $features) {

            if ($features) {
                $matches = 0;
                $title = htmlentities(ifset(self::$feature_namespace_map[$namespace]['name'], $namespace), ENT_QUOTES, waHtmlControl::$default_charset);
                $matches_head = <<<HTML
<thead>
    <tr>
        <th colspan="3"><h3>{$title}</h3></th>
    </tr>
</thead>
<tbody>
HTML;
                $xpath_params = $params;
                waHtmlControl::addNamespace($xpath_params, $namespace);
                foreach ($features as $key => $feature) {
                    if (isset($feature['target']) && (count($feature) == 1)) {
                        continue;
                    }
                    if (0 == $matches++) {
                        $html .= $matches_head;
                    }
                    $map_params = $xpath_params;
                    $map_params['title'] = ifset($feature['name'], $key);

                    $map_params['value'] = ifset($feature['target'], empty($feature['code']) ? '' : sprintf('f:%s', $feature['code']));

                    if ($map_params['value'] == '') {
                        switch (ifset(self::$feature_namespace_map[$namespace]['default'])) {
                            case 'add':
                                $map_params['value'] = 'f:';
                                break;
                            case 'skip':
                                $map_params['value'] = 's:';
                                break;
                        }
                    }

                    $map_params['feature_codes'] = $codes;

                    if (empty($feature['target'])) {
                        $map_params['description'] .= '<i class="icon16 new"></i>';
                    }

                    if (!empty($feature['id_1c'])) {
                        $map_params['description'] .= $feature['id_1c'];
                    }

                    $name_params_ = $name_params;
                    waHtmlControl::addNamespace($name_params_, $namespace);
                    waHtmlControl::addNamespace($name_params_, $key);
                    $name_params_['value'] = $name_params['placeholder'] = $map_params['title'];
                    $map_params['description'] .= waHtmlControl::getControl(waHtmlControl::HIDDEN, 'name', $name_params_);
                    if (!empty($feature['id_1c'])) {
                        $name_params_['value'] = $feature['id_1c'];
                        $map_params['description'] .= waHtmlControl::getControl(waHtmlControl::HIDDEN, 'cml1c_id', $name_params_);
                    }

                    $html .= waHtmlControl::getControl('OptionMapControl', $key, $map_params);
                }
                if ($matches) {
                    if (!empty(self::$feature_namespace_map[$namespace]['description'])) {
                        $description = nl2br(htmlentities(self::$feature_namespace_map[$namespace]['description'], ENT_QUOTES, waHtmlControl::$default_charset));
                        if (!empty(self::$feature_namespace_map[$namespace]['default'])) {
                            $description .= <<<HTML
<br/><i class="icon16 exclamation"></i>
HTML;
                            switch (self::$feature_namespace_map[$namespace]['default']) {
                                case 'add':
                                    $description .= 'По умолчанию во время обмена, значения новых еще не синхронизированных полей будут добавляться как новые характеристики.';
                                    break;
                                case 'skip':
                                    $description .= 'По умолчанию во время обмена, значения новых еще не синхронизированных полей будут игнорироваться.';
                                    break;
                            }
                        }
                        $html .= <<<HTML
<tr>
    <td colspan="3" class="hint"><i class="icon16 info"></i>{$description}<br/><br/></td>
</tr>
HTML;
                    }
                    $html .= '</tbody>';

                }
            }
        }

        return $html;
    }


    /**
     * @param $name
     * @param array $params
     * @return string
     */
    public function settingOptionMapControl($name, $params = array())
    {
        $control = '';

        foreach ($params as $field => $param) {
            if (strpos($field, 'wrapper')) {
                unset($params[$field]);
            }
        }

        if (empty($params['value'])) {
            $params['value'] = null;
        }

        unset($params['title']);
        $control_namespace = preg_replace("@([\\[\\]])@", '\\\\$1', waHtmlControl::getName($params, $name));
        waHtmlControl::addNamespace($params, $name);

        $params['title_wrapper'] = '%s';
        $params['description_wrapper'] = '<br><span class="hint">%s</span>';

        $params['options'] = array();

        $targets = ifset($params['target'], 'feature,params');
        if (!is_array($targets)) {
            $targets = preg_split('@,\s*@', $targets);
        }
        $target_params = $params;
        $target_params['description'] = null;
        $target_options = array(
            array(
                'value'       => 'f',
                'title'       => 'Характеристика',
                'description' => 'Характеристика товара в Shop-Script и ее размерность:',
            ),
            array(
                'value'       => 'p',
                'title'       => 'Дополнительный параметр товара',
                'description' => 'Дополнительный параметр товара в Shop-Script:',
            ),
            array(
                'value' => 's',
                'title' => 'Не импортировать',
            ),
        );
        if (!empty($params['value']) && preg_match('@^(\w):@', $params['value'], $matches)) {
            $target_params['value'] = $matches[1];
        }


        if (empty($target_params['value'])) {
            $target_params['value'] = reset($targets);
        }

        if (in_array('feature', $targets)) {
            $feature_params = $params;
            $filter = ifset($params['feature_filter'], array());


            $feature_options = $this->getFeaturesOptions(true, $filter, ifset($params['feature_codes'], array()));
            if (count($feature_options) > 1) {
                $feature_params['options'] = $feature_options;
                if (!empty($params['value']) && (strpos($params['value'], 'f:') === 0)) {
                    $feature_params['value'] = $params['value'];
                } else {
                    $feature_params['value'] = null;
                }
                $feature_control = waHtmlControl::SELECT;
                $feature_params['description'] = $target_options[0]['description'];
                $target_options[0]['description'] = '';

            } else {
                $value = reset($feature_options);
                $feature_params['value'] = $value['value'];
                $feature_control = waHtmlControl::HIDDEN;
            }
        } else {
            $feature_params = null;
            $feature_control = false;
        }

        $target_control = count($targets) > 1 ? waHtmlControl::RADIOGROUP : waHtmlControl::HIDDEN;
        if (in_array('feature', $targets)) {
            $dimension_params = $params;

            $target_params['options'] = array_slice($target_options, 0, 1);

            $control .= waHtmlControl::getControl($target_control, 'target', $target_params);

            if (preg_match('@^(f:[^:]+):(.+)$@', $feature_params['value'], $matches)) {
                $feature_params['value'] = $matches[1];
                $dimension_params['value'] = $matches[2];
            } else {
                $dimension_params['value'] = null;
            }


            if (!empty($feature_params['options']['autocomplete'])) {
                $control .= <<<HTML
<input type="search" class="js-autocomplete-cml1c" placeholder="Название характеристики для поиска" value="" style="display: none;">
<a href="#/cml1c/autocomplete/cancel/" class="js-autocomplete-cml1c-cancel inline" style="display: none;"><i class="icon10 close"></i></a>
HTML;
            }

            $control .= waHtmlControl::getControl($feature_control, 'f', $feature_params);


            $dimension_params['options'] = self::getFeatureDimensions();
            $dimension_params['description'] = null;
            $control .= waHtmlControl::getControl(waHtmlControl::SELECT, 'dimension', $dimension_params);

            $control .= $params['control_separator'];
        }

        if (in_array('params', $targets)) {
            $params_params = $params;
            $params_params['options'] = $this->getParamsOptions();

            if (!empty($params['value']) && (strpos($params['value'], 'p:') === 0)) {
                $params_params['value'] = $params['value'];
            } else {
                $params_params['value'] = null;
            }

            $params_params['description'] = null;
            $target_params['options'] = array_slice($target_options, 1, 1);
            if (count($params_params['options']) > 1) {
                $control .= waHtmlControl::getControl($target_control, 'target', $target_params);
                $control .= waHtmlControl::getControl(waHtmlControl::SELECT, 'p', $params_params);
            } else {
                $target_params_ = $target_params;
                $target_params_['disabled'] = 'disabled';
                $target_params_['description'] .= 'Необходимо сохранить хотя бы один пример параметра в настройках товара';
                $control .= waHtmlControl::getControl($target_control, 'target', $target_params_);
            }
            $control .= $params['control_separator'];
        }

        $target_params['options'] = array_slice($target_options, 2, 1);
        $control .= waHtmlControl::getControl($target_control, 'target', $target_params);
        $control .= $params['control_separator'];

        $control .= <<<HTML
<script type="text/javascript">
if (typeof($) == 'function') {
    $.importexport.plugins.cml1c.initMapControlRow('{$control_namespace}');
}
</script>
HTML;
        return $control;
    }

    private static function getFeatureDimensions()
    {
        static $options = null;
        if ($options === null) {
            $options = array();
            $options[] = array(
                'title' => 'Без размерности',
                'value' => '',
                'class' => 'js-type-null',
            );
            $dimension = shopDimension::getInstance();
            foreach ($dimension->getList() as $type => $info) {
                foreach ($info['units'] as $code => $unit) {
                    $options[] = array(
                        'value' => $code,
                        'title' => $unit['name'],
                        'class' => 'js-type-'.$type.(($info['base_unit'] == $code) ? ' js-base-type' : ''),
                        'style' => ($info['base_unit'] == $code) ? 'font-weight:bold;' : '',
                    );
                }
            }
        }

        return $options;
    }

    protected function getFeaturesOptions($multiple = true, $filter = array(), $codes = array())
    {

        $key = md5(var_export(compact('multiple', 'filter'), true));
        $cache = new waRuntimeCache(__METHOD__.$key);

        if (!$cache->isCached()) {
            $translates = array();
            $translates['Add as new feature'] = 'Добавить в качестве новой характеристики';
            $translates['Feature'] = 'Добавить к существующей';

            $features_options = array();
            $feature_types = shopFeatureModel::getTypes();
            foreach ($feature_types as $f) {
                if ($f['available'] && ($f['type'] != shopFeatureModel::TYPE_DIVIDER)) {
                    if (empty($f['subtype'])) {
                        if ($multiple || (empty($f['multiple']) && !preg_match('@^(range|2d|3d)\.@', $f['type']))) {
                            if (!$filter || self::filter($f, $filter)) {
                                $features_options[] = array(
                                    'group' => & $translates['Add as new feature'],
                                    'value' => sprintf("f+:%s:%d:%d", $f['type'], $f['multiple'], $f['selectable']),
                                    'title' => empty($f['group']) ? $f['name'] : ($f['group'].': '.$f['name']),
                                );
                            }
                        }
                    } else {
                        foreach ($f['subtype'] as $sf) {
                            if ($sf['available']) {
                                $type = $sf['type'];
                                if ($multiple || (empty($f['multiple']) && !preg_match('@^(range|2d|3d)\.@', $type))) {
                                    if (!$filter || self::filter($f, $filter)) {
                                        $features_options[] = array(
                                            'group' => & $translates['Add as new feature'],
                                            'value' => sprintf("f+:%s:%d:%d", $type, $f['multiple'], $f['selectable']),
                                            'title' => (empty($f['group']) ? $f['name'] : ($f['group'].': '.$f['name']))." — {$sf['name']}",

                                        );
                                    }
                                }
                            }
                        }
                    }
                }
            }


            $feature_model = new shopFeatureModel();
            $limit = 100;

            $auto_complete = false;

            $features = array();
            if ($feature_model->countByField(array('parent_id' => null)) < $limit) {
                $features = $feature_model->getFeatures(true); /*, true*/
            } else {
                $auto_complete = true;
                $codes = array_unique($codes);
                // $codes = array_slice($codes, 0, $limit);
                if ($codes) {
                    $features = $feature_model->getByCode($codes);
                }
                if (count($features) < $limit) {
                    //TODO get some more
                }
            }

            foreach ($features as $id => $feature) {
                if ($feature['type'] == shopFeatureModel::TYPE_DIVIDER) {
                    unset($features[$id]);
                }
            }


            if ($auto_complete) {
                $features_options['autocomplete'] = array(
                    'group'    => & $translates['Feature'],
                    'value'    => 'f:%s',
                    'title'    => 'Найти характеристику...',
                    'no_match' => true,
                );
            }

            foreach ($features as $feature) {
                if (empty($feature['parent_id'])
                    && ($multiple || (empty($feature['multiple']) && !preg_match('@^(range|2d|3d)\.@', $feature['type'])))
                ) {
                    if (!$filter || self::filter($feature, $filter)) {
                        $features_options[] = array(
                            'group'       => & $translates['Feature'],
                            'value'       => sprintf('f:%s', $feature['code']),
                            'title'       => $feature['name'],
                            'description' => $feature['code'],
                            'class'       => 'js-type-'.$feature['type'],
                        );
                    }
                }
            }
            $cache->set($features_options);
        } else {
            $features_options = $cache->get();
        }

        return $features_options;
    }

    protected function getParamsOptions()
    {
        static $options;
        if (!is_array($options)) {
            $options = array();


            $options[] = array(
                'value' => 'p:',
                'title' => 'Выбирете название дополнительного параметра',
            );

            $params_model = new shopProductParamsModel();
            foreach ($params_model->select('DISTINCT name')->fetchAll(null, true) as $param) {
                $options [] = array(
                    'value' => sprintf('p:%s', $param),
                    'title' => $param,
                );
            }
        }
        return $options;
    }

    private static function filter($feature, $filter)
    {
        $matched = true;
        foreach ($filter as $field => $value) {
            if (ifset($feature[$field]) != $value) {
                $matched = false;
                break;
            }
        }
        return $matched;
    }

    private function getStocksControl($name, $params = array())
    {
        $html = '';

        if (count($this->data['stock_map']) > 1) {

            $params['options'] = array(
                -1 => array(
                    'value' => -1,
                    'title' => 'Не импортировать остатки',
                ),
                0  => array(
                    'value' => 0,
                    'title' => 'Импорт в общие остатки товаров Shop-Script',
                ),
            );
            $params['description'] = '';
            waHtmlControl::addNamespace($params, $name);

            $stock_model = new shopStockModel();
            $stocks = (array)$stock_model->getAll('id');
            foreach ($stocks as $stock) {

                $params['options'][$stock['id']] = array(
                    'value' => $stock['id'],
                    'title' => $stock['name'],
                );
            }

            $exist = false;
            $title = 'Остатки товаров по складам - блок <Склады>';
            $title = htmlentities($title, ENT_QUOTES, waHtmlControl::$default_charset);
            $html .= <<<HTML
<thead>
    <tr>
        <th colspan="3"><h3>{$title}</h3></th>
    </tr>
</thead>
<tbody>
HTML;
            $default = null;
            if (isset($params['options'][$this->data['stock_id']])) {
                $default = $params['options'][$this->data['stock_id']]['title'];
            }
            foreach ($this->data['stock_map'] as $uuid => $stock) {
                if (!empty($stock['name'])) {
                    $exist = true;

                    $stock_params = $params;
                    $stock_params['title'] = $stock['name'];
                    $stock_params['value'] = $stock['stock_id'];

                    $stock_params['description'] = $uuid;
                    $html .= waHtmlControl::getControl(waHtmlControl::SELECT, $uuid, $stock_params);
                }
            }

            if (!$exist) {
                $html = '';
            } else {

                if ($default !== null) {
                    $default = htmlentities($default, ENT_QUOTES, waHtmlControl::$default_charset);
                    $hint = <<<HTML
<strong>Для импорта общих остатков из CommerceML (элемент &lt;Количество&gt;) выбран режим: %s</strong><br/>
Изменить режим импорта общих остатков можно во вкладке «Настройки обмена», выпадающий список «Общие остатки в CommerceML»
HTML;

                    $default = sprintf($hint, $default);
                }


                $html .= <<<HTML
<tr>
    <td colspan="3" class="hint">
{$default}
    </td>
</tr>
HTML;
                $html .= '</tbody>';
            }
        }

        return $html;
    }


    private static function findSimilar(&$params, $target = null, $options = array())
    {
        if ($target === null) {
            $target = empty($params['title']) ? ifset($params['description']) : $params['title'];
        }
        $params['value'] = ifset($params['value'], -1);
        $selected = null;
        if ($target && $params['value'] < 0) {
            $max = $p = 0;
            //init data structure
            foreach ($params['options'] as $id => & $column) {
                if (!is_array($column)) {
                    $column = array(
                        'title' => $column,
                        'value' => $id,
                    );
                }
                $column['like'] = 0;
            }

            if (!empty($options['similar'])) {
                foreach ($params['options'] as & $column) {
                    if (!empty($column['no_match'])) {
                        $column['like'] = 0;
                    } else {
                        similar_text($column['title'], $target, $column['like']);
                        if ($column['like'] >= 90) {
                            $max = $column['like'];
                            $selected =& $column;
                        } else {
                            $column['like'] = 0;
                        }
                        unset($column);
                    }
                }
            }

            if ($max < 90) {
                unset($selected);
                $max = 0;
                $to = mb_strtolower($target);
                foreach ($params['options'] as & $column) {
                    if (empty($column['no_match']) && ($column['like'] < 90)) {
                        $from = mb_strtolower($column['title']);
                        if ($from && $to && ((strpos($from, $to) === 0) || (strpos($to, $from) === 0))) {
                            $l_from = mb_strlen($from);
                            $l_to = mb_strlen($to);
                            $column['like'] = 100 * min($l_from, $l_to) / max($l_from, $l_to, 1);
                            if ($column['like'] > $max) {
                                $selected =& $column;
                                $max = $column['like'];
                            }
                        }
                    }
                    unset($column);
                }
            }
            if (!empty($params['sort'])) {
                uasort($params['options'], array(__CLASS__, 'sortSimilar'));
            }

            if (!empty($selected)) {
                $selected['style'] = 'font-weight:bold;text-decoration:underline;';
                $params['value'] = $selected['value'];
                unset($selected);
            } elseif ((func_num_args() < 2) && !empty($params['title']) && !empty($params['description'])) {
                self::findSimilar($params, $params['description'], $options);
            }
        }
        return $params['value'];
    }

    protected function report()
    {
        $report = '<div class="successmsg">';
        switch ($this->data['direction']) {
            case 'import':
                $report .= sprintf('<i class="icon16 yes"></i>%s: ', empty($this->data['configure']) ? 'Импорт завершен:' : 'Анализ завершен:');
                break;
            case 'export':
                $report .= sprintf('<i class="icon16 yes"></i>%s: ', 'Экспорт завершен:');
                break;
        }
        $chunks = array();
        foreach ($this->data['processed_count'] as $stage => $count) {
            if ($data = $this->getStageReport($stage, $this->data['processed_count'])) {
                $chunks[] = htmlentities($data, ENT_QUOTES, 'utf-8');
            }
        }
        $report .= implode(', ', $chunks);
        if (!empty($this->data['timestamp'])) {
            $interval = time() - $this->data['timestamp'];
            $interval = sprintf('%02d ч %02d мин %02d с', floor($interval / 3600), floor($interval / 60) % 60, $interval % 60);
            $report .= ' '.sprintf('(Общее время: %s)', $interval);
        }

        $report .= '</div>';
        switch ($this->data['direction']) {
            case 'export':
                if ((waRequest::param('module', 'backend') == 'backend')) {
                    $name = htmlentities(basename($this->data['filename']), ENT_QUOTES, 'utf-8');
                    $report .= <<<HTML
<br>
<a href="?plugin=cml1c&action=download&file={$name}"><i class="icon16 download"></i><strong>Скачать</strong></a>
 или
<a href="?plugin=cml1c&action=download&file={$name}&mode=view" target="_blank">просмотреть<i class="icon16 new-window"></i></a>
HTML;
                }
                break;

            case 'import':
                if (!empty($this->data['configure'])) {
                    $params = array(
                        'options'         => array(),
                        'control_wrapper' => '<tr><td>%1$s<span class="hint">%3$s</span></td><td>&rarr;</td><td>%2$s</td></tr>',
                        'title_wrapper'   => '%s'
                    );

                    $params['control_separator'] = '</td></tr>
            <tr><td>&nbsp;</td><td>&nbsp;</td><td>';
                    $report .= '<table class="zebra">';


                    $report .= $this->getFeaturesControl('features', $params);
                    $report .= $this->getStocksControl('stocks', $params);
                    $report .= '</table>';
                }
                break;
        }
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
            'ready'      => $this->isDone() && ifset($this->data['is_done']),
            'count'      => empty($this->data['count']) ? false : $this->data['count'],
            'memory'     => sprintf('%0.2fMByte', $this->data['memory'] / 1048576),
            'memory_avg' => sprintf('%0.2fMByte', $this->data['memory_avg'] / 1048576),
        );

        $stage_num = 0;
        $stage_count = count($this->data['count']);
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
            if (!empty($this->data['configure'])) {
                $response['configure'] = true;
            }
            $response['report_id'] = $this->processId;
        }

        switch (waRequest::param('module', 'backend')) {
            case 'backend':
                $this->getResponse()->addHeader('Content-type', 'application/json');
                $this->getResponse()->sendHeaders();
                echo json_encode($response);
                break;
            case 'frontend':
                $this->getResponse()->addHeader('Content-type', 'text/plain');
                $this->getResponse()->addHeader('Encoding', 'Windows-1251');

                if ($response['ready']) {
                    echo "success\n";
                    echo strip_tags($this->report());
                } else {
                    echo "progress\n";
                    echo sprintf('%0.3f%% %s', $response['progress'], $response['stage_name']);
                }
                break;
            default:
                break;
        }
    }

    private function getStageReport($stage, $count)
    {
        static $strings;
        if (!$strings) {
            switch ($this->data['direction']) {
                case 'export':
                    $strings = array(
                        0 => array(
                            self::STAGE_ORDER    => array/*_wp*/
                            (
                                                         '%d order',
                                                         '%d orders'
                            ),
                            self::STAGE_PRODUCT  => array/*_wp*/
                            (
                                                         '%d product',
                                                         '%d products'
                            ),
                            self::STAGE_OFFER    => array/*_wp*/
                            (
                                                         '%d offer',
                                                         '%d offers'
                            ),
                            self::STAGE_CATEGORY => array/*_wp*/
                            (
                                                         '%d category',
                                                         '%d categories'
                            ),
                        ),
                    );

                    break;
                case 'import':
                default:
                    $strings = array(
                        'new'     => array(
                            self::STAGE_IMAGE    => array/*_wp*/
                            (
                                                         'imported %d product image',
                                                         'imported %d product images'
                            ),
                            self::STAGE_CATEGORY => array/*_wp*/
                            (
                                                         'imported %d category',
                                                         'imported %d categories'
                            ),
                            self::STAGE_PRODUCT  => array/*_wp*/
                            (
                                                         'imported %d product',
                                                         'imported %d products'
                            ),
                            self::STAGE_SKU      => array/*_wp*/
                            (
                                                         'imported %d sku',
                                                         'imported %d skus'
                            ),
                            self::STAGE_OFFER    => array/*_wp*/
                            (
                                                         'imported %d offer',
                                                         'imported %d offers'
                            ),
                        ),
                        'update'  => array(/*_wp*/
                                           self::STAGE_ORDER    => array/*_wp*/
                                           (
                                                                        'updated %d order',
                                                                        'updated %d orders'
                                           ),
                                           self::STAGE_IMAGE    => array/*_wp*/
                                           (
                                                                        'updated %d product image',
                                                                        'updated %d product images'
                                           ),
                                           self::STAGE_CATEGORY => array/*_wp*/
                                           (
                                                                        'updated %d category',
                                                                        'updated %d categories'
                                           ),
                                           self::STAGE_PRODUCT  => array/*_wp*/
                                           (
                                                                        'updated %d product',
                                                                        'updated %d products'
                                           ),
                                           self::STAGE_SKU      => array/*_wp*/
                                           (
                                                                        'updated %d sku',
                                                                        'updated %d skus'
                                           ),
                                           self::STAGE_OFFER    => array/*_wp*/
                                           (
                                                                        'updated %d offer',
                                                                        'updated %d offers'
                                           ),
                        ),
                        'analyze' => array(
                            self::STAGE_PRODUCT => array/*_wp*/
                            (
                                                        'analyzed %d product',
                                                        'analyzed %d products'
                            ),
                            self::STAGE_SKU     => array/*_wp*/
                            (
                                                        'analyzed %d sku',
                                                        'analyzed %d skus'
                            ),
                            self::STAGE_OFFER   => array/*_wp*/
                            (
                                                        'analyzed %d offer',
                                                        'analyzed %d offers'
                            ),
                            self::STAGE_FEATURE => array/*_wp*/
                            (
                                                        'analyzed %d feature',
                                                        'analyzed %d features'
                            ),
                            self::STAGE_STOCK   => array/*_wp*/
                            (
                                                        'analyzed %d stock',
                                                        'analyzed %d stocks'
                            ),
                        ),
                        'skip'    => array(
                            self::STAGE_ORDER    => array/*_wp*/
                            (
                                                         'skipped %d order',
                                                         'skipped %d orders'
                            ),
                            self::STAGE_IMAGE    => array/*_wp*/
                            (
                                                         'skipped %d product image',
                                                         'skipped %d product images'
                            ),
                            self::STAGE_CATEGORY => array/*_wp*/
                            (
                                                         'skipped %d category',
                                                         'skipped %d categories'
                            ),
                            self::STAGE_PRODUCT  => array/*_wp*/
                            (
                                                         'skipped %d product',
                                                         'skipped %d products'
                            ),
                            self::STAGE_SKU      => array/*_wp*/
                            (
                                                         'skipped %d sku',
                                                         'skipped %d skus'
                            ),
                            self::STAGE_OFFER    => array/*_wp*/
                            (
                                                         'skipped %d offer',
                                                         'skipped %d offers'
                            ),
                        ),
                    );

                    break;
            }
        }
        $info = array();
        if (ifempty($count[$stage])) {
            foreach ((array)$count[$stage] as $type => $count) {
                if ($count) {
                    $args = $strings[$type][$stage];
                    $args[] = $count;
                    $info[] = call_user_func_array('_wp', $args);
                }
            }
        }
        return implode(', ', $info);

    }

    public function getStageName($stage)
    {
        if (isset($this->data['direction']) && ($this->data['direction'] == 'import')) {
            $name = empty($this->data['configure']) ? 'Импорт ' : 'Анализ ';
        } else {
            $name = 'Выгрузка ';
        }
        switch ($stage) {

            case self::STAGE_ORDER:
                $name .= 'заказов';
                break;

            case self::STAGE_PRODUCT:
                $name .= 'товаров';
                break;

            case self::STAGE_CATEGORY:
                $name .= 'категорий';
                break;

            case self::STAGE_STOCK:
                $name .= 'складов';
                break;

            case self::STAGE_PRICE:
                $name .= 'типов цен';
                break;

            case self::STAGE_FEATURE:
                $name .= 'свойств записей';
                break;

            case self::STAGE_OFFER:
                $name .= 'предложений';
                break;

            case self::STAGE_IMAGE:
                $name .= 'изображений';
                break;
        }

        if (!empty($this->data['count'][$stage]) && !empty($this->data['current'][$stage])) {
            $name .= sprintf(' (%d из %d)...', $this->data['current'][$stage], $this->data['count'][$stage]);
        }
        return $name;
    }

    protected function restore()
    {
        switch ($this->data['direction']) {
            case 'import':
                $this->openXml();
                break;
            case 'export':
                if (empty($this->data['ready'])) {
                    $writer = new XMLWriter();
                    $writer->openMemory();
                    $writer->setIndent(true);
                    $writer->setIndentString("\t");
                    $writer->startDocument('1.0', $this->data['encoding']);
                    $writer->startElement('КоммерческаяИнформация');
                    $writer->writeComment(__FUNCTION__);

                    $writer->flush();
                    $this->writer = $writer;
                }
                break;
        }
    }

    protected function getXmlError()
    {
        $messages = array();
        $errors = libxml_get_errors();
        /**
         * @var LibXMLError[] $errors
         */
        foreach ($errors as $error) {
            $messages[] = sprintf('#%d@%d:%d %s', $error->level, $error->line, $error->column, $error->message);
        }
        libxml_clear_errors();
        return implode("\n", $messages);
    }


    private function stepExportProduct(&$current_stage, &$count, &$processed)
    {
        static $products;
        $chunk = self::$chunk_map[self::STAGE_PRODUCT];
        if (!$products) {
            $products = $this->getProducts($current_stage[self::STAGE_PRODUCT], $chunk * 4);
        }

        $w = &$this->writer;
        if (!$current_stage[self::STAGE_PRODUCT]) {
            $this->data['map'][self::STAGE_PRODUCT] = shopCml1cPlugin::makeUuid();

            $w->startElement('Каталог');
            $w->writeElement('Ид', $this->data['map'][self::STAGE_PRODUCT]);
            $w->writeElement('ИдКлассификатора', $this->data['map'][self::STAGE_OFFER]);
            $w->writeElement('Наименование', "Каталог товаров от ".date("Y-m-d H:i"));
            $this->writeOwner();
            $w->startElement('Товары');
        }

        while (($chunk-- > 0) && ($product = reset($products))) {
            $exported = false;
            if (empty($product['id_1c'])) {
                $product['id_1c'] = $this->plugin()->makeProductUUID($product['id']);
            }
            $shop_product = new shopProduct($product);
            //$product['type_name'] = $shop_product->type['name'];
            #WORK
            $skus = $shop_product->skus;

            foreach ($skus as $sku) {
                if (empty($sku['id_1c'])) {
                    $sku['id_1c'] = $this->plugin()->makeSkuUUID($sku['id']);
                }
                $this->writeProduct($product, $sku);
                $exported = true;
            }

            array_shift($products);
            ++$current_stage[self::STAGE_PRODUCT];
            if ($exported) {
                ++$processed[self::STAGE_PRODUCT];
            }
        }
        if ($current_stage[self::STAGE_PRODUCT] == $count[self::STAGE_PRODUCT]) {
            $w->endElement(/*Товары*/);
            $w->endElement(/*Каталог*/);
        }
        return ($current_stage[self::STAGE_PRODUCT] < $count[self::STAGE_PRODUCT]);
    }


    private function stepExportOffer(&$current_stage, &$count, &$processed)
    {
        static $products;
        $chunk = self::$chunk_map[self::STAGE_OFFER];
        if (!$products) {
            $products = $this->getProducts($current_stage[self::STAGE_OFFER], $chunk * 4);
        }
        $w = &$this->writer;
        if (!$current_stage[self::STAGE_OFFER]) {


            $w->startElement('ПакетПредложений');
            $w->writeAttribute('СодержитТолькоИзменения', 'false');

            $w->writeElement('Ид', self::UUID_OFFER."#");
            $w->writeElement('Наименование', 'Пакет предложений');
            $w->writeElement('ИдКаталога', $this->data['map'][self::STAGE_PRODUCT]);
            $w->writeElement('ИдКлассификатора', $this->data['map'][self::STAGE_OFFER]);

            $this->writeOwner();

            $w->startElement('ТипыЦен');
            $w->startElement('ТипЦены');
            $w->writeElement('Ид', $this->data['price_type_uuid']);
            $w->writeElement('Наименование', $this->data['price_type']);
            $w->writeElement('Валюта', $this->currency());
            $w->endElement(/*ТипЦены*/);

            if (!empty($this->data['purchase_price_type_uuid'])) {
                $w->startElement('ТипЦены');
                $w->writeElement('Ид', $this->data['purchase_price_type_uuid']);
                $w->writeElement('Наименование', $this->data['purchase_price_type']);
                $w->writeElement('Валюта', $this->currency());
                $w->endElement(/*ТипЦены*/);
            }
            $w->endElement(/*ПакетПредложений*/);
            $w->startElement('Предложения');
        }

        while (($chunk-- > 0) && ($product = reset($products))) {
            $exported = false;
            $shop_product = new shopProduct($product);
            #WORK
            foreach ($shop_product->skus as $sku) {
                $this->writeOffer($shop_product, $sku);
                $exported = true;
            }

            array_shift($products);
            ++$current_stage[self::STAGE_OFFER];
            if ($exported) {
                ++$processed[self::STAGE_OFFER];
            }
        }
        if ($current_stage[self::STAGE_OFFER] == $count[self::STAGE_OFFER]) {
            $w->endElement(/*Предложения*/);
        }
        return ($current_stage[self::STAGE_OFFER] < $count[self::STAGE_OFFER]);
    }

    private function stepExportCategory(&$current_stage, &$count, &$processed)
    {
        static $categories;
        static $level = 0;
        if (!$categories) {

            $categories = $this->getCategoryModel()->getFullTree('*', true);
            if ($current_stage[self::STAGE_CATEGORY]) {
                $categories = array_slice($categories, $current_stage[self::STAGE_CATEGORY]);
            }
        }
        if (!$current_stage[self::STAGE_CATEGORY]) {
            $this->data['map'][self::STAGE_OFFER] = shopCml1cPlugin::makeUuid();

            $w = &$this->writer;
            $w->startElement('Классификатор');
            $w->writeElement('Ид', $this->data['map'][self::STAGE_OFFER]);
            $w->writeElement('Наименование', 'Классификатор (Каталог товаров)');
            $this->writeOwner();
            $w->startElement('Группы');
        }


        if (!isset($this->data['map'][self::STAGE_CATEGORY])) {
            $this->data['map'][self::STAGE_CATEGORY] = $this->getCategoryModel()->select('`id`, `id_1c`')->where('`id_1c` IS NOT NULL')->fetchAll('id', true);
        }
        $chunk = self::$chunk_map[self::STAGE_CATEGORY];
        $map =& $this->data['map'][self::STAGE_CATEGORY];

        while (($chunk-- > 0) && ($category = reset($categories))) {
            if (!$category['id_1c']) {
                $map[$category['id']] = $this->getCategoryId($category);
            }

            $category['parent_id_1c'] = null;
            if (!empty($category['parent_id'])) {
                $category['parent_id_1c'] = isset($map[$category['parent_id']]) ? $map[$category['parent_id']] : false;
            }

            $this->writeCategory($category, $level);
            array_shift($categories);

            ++$current_stage[self::STAGE_CATEGORY];
            ++$processed[self::STAGE_CATEGORY];
        }

        if ($current_stage[self::STAGE_CATEGORY] == $count[self::STAGE_CATEGORY]) {
            $level = 0;
            $this->writeCategory(null, $level);
            $this->writer->endElement(/*Группы*/);
            $this->writer->endElement(/*Классификатор*/);
        }
        return ($current_stage[self::STAGE_CATEGORY] < $count[self::STAGE_CATEGORY]);
    }

    private function getAddress($params, $type)
    {
        $address = array();
        $address[] = ifempty($params[$type.'_address.street']);
        $address[] = ifempty($params[$type.'_address.city']);
        $address[] = ifempty($params[$type.'_address.zip']);
        if (ifempty($params[$type.'_address.country'])) {
            $address[] = waCountryModel::getInstance()->name($params[$type.'_address.country']);
        }
        $address = array_filter(array_map('trim', $address));
        return implode(', ', $address);
    }

    private function getOrders($offset = 0, $limit = 50)
    {
        $model = $this->getModel('order');
        /**
         * @var shopOrderModel $model
         */
        $options = array(
            'offset' => $offset,
            'limit'  => $limit,
            'escape' => false,
        );
        if ($this->data['orders_time']) {
            $options['change_datetime'] = $this->data['orders_time'];
        }

        if (!empty($this->data['order_state'])) {
            $options['where'] = array(
                'state_id' => $this->data['order_state'],
            );
        }
        $fields = "*,items.name,items.type,items.sku_id,items.product_id,items.quantity,items.price,contact,params";
        return $model->getList($fields, $options);
    }

    private function stepExportOrder(&$current_stage, &$count, &$processed)
    {
        static $orders;
        static $states;
        static $region_model;
        static $rate;

        $chunk = self::$chunk_map[self::STAGE_ORDER];

        if (!$orders) {
            $orders = $this->getOrders($current_stage[self::STAGE_ORDER], $chunk * 4);
        }
        /**
         * @var waWorkflowState[] $states
         */
        if (!$states) {
            $workflow = new shopWorkflow();
            $states = $workflow->getAllStates();
        }

        $empty_address = array(
            'firstname' => '',
            'lastname'  => '',
            'country'   => '',
            'region'    => '',
            'city'      => '',
            'street'    => '',
            'zip'       => '',
        );

        while (($chunk-- > 0) && ($order = reset($orders))) {

            if (!isset($this->data['map'][self::STAGE_ORDER])) {
                $this->data['map'][self::STAGE_ORDER] = array();
            }

            $order['id_str'] = shopHelper::encodeOrderId($order['id']);
            $order['status_comment'] = ''; //TODO

            $params = &$order['params'];

            list($date, $time) = explode(" ", date("Y-m-d H:i:s", strtotime($order["create_datetime"])));

            $order['params']['shipping'] = shopHelper::getOrderAddress($params, 'shipping') + $empty_address;
            $shipping_address = $this->getAddress($params, 'shipping');
            $shipping_address_array = array(
                'Почтовый индекс' => 'shipping_address.zip',
                'Регион'          => 'shipping_address.region_name',
                'Город'           => 'shipping_address.city',
                'Улица'           => 'shipping_address.street',
            );

            if (!$region_model) {
                $region_model = new waRegionModel();
            }
            if (ifset($params['shipping_address.country']) && ifset($params['shipping_address.region'])) {
                if ($region = $region_model->get($params['shipping_address.country'], $params['shipping_address.region'])) {
                    $params['shipping_address.region_name'] = $region['name'];
                }
            }

            foreach ($shipping_address_array as $type => &$field) {
                if (!empty($params[$field])) {
                    $field = $params[$field];
                } else {
                    unset($shipping_address_array[$type]);
                }
                unset($field);
            }

            $order['params']['billing'] = shopHelper::getOrderAddress($params, 'billing') + $empty_address;
            $billing_address = $this->getAddress($params, 'billing');

            if (ifset($params['billing_address.country']) && ifset($params['billing_address.region'])) {
                if ($region = $region_model->get($params['billing_address.country'], $params['billing_address.region'])) {
                    $params['billing_address.region_name'] = $region['name'];
                }
            }
            $billing_address_array = array(
                'Почтовый индекс' => 'billing_address.zip',
                'Регион'          => 'billing_address.region_name',
                'Город'           => 'billing_address.city',
                'Улица'           => 'billing_address.street',
            );

            foreach ($billing_address_array as $type => &$field) {
                if (!empty($params[$field])) {
                    $field = $params[$field];
                } else {
                    unset($billing_address_array[$type]);
                }
                unset($field);
            }


            list($order['contact']['lastname'], $order['contact']['firstname']) = explode(' ', ifempty($order['contact']['name'], '-').' %', 2);
            $order['contact']['firstname'] = preg_replace('/\s+%$/', '', $order['contact']['firstname']);

            $w = &$this->writer;

            $w->startElement('Документ');
            //Идентификатор документа уникальный в рамках файла обмена
            $w->writeElement('Ид', $order['id']);
            //Номер документа
            $w->writeElement('Номер', $order['id_str']);
            //Дата документа
            $w->writeElement('Дата', $date);
            //Вид хозяйственной операции
            $w->writeElement('ХозОперация', 'Заказ товара');
            //Роль предприятия в документе
            $w->writeElement('Роль', 'Продавец');
            //Код валюты по международному классификатору валют (ISO 4217)
            $w->writeElement('Валюта', $this->currency($order['currency']));
            //Курс указанной валюты по отношению к национальной валюте. Для национальной валюты курс равен 1
            $currency = $this->pluginSettings('currency');
            if ($currency != $order['currency']) {
                if (!$rate) {
                    $rate = 1.0;
                    /**
                     * @var shopConfig $config
                     */
                    $config = $this->getConfig();
                    $default_currency = $config->getCurrency();
                    if ($default_currency != $currency) {
                        $rate = $this->convertPrice($rate, $default_currency, $currency);
                    }
                }
                $order['rate'] *= $rate;
            }
            $w->writeElement('Курс', $this->price($order['rate'], 4));
            //Общая сумма по документу. Налоги, скидки и дополнительные расходы включаются в
            // данную сумму в зависимости от установок "УчтеноВСумме"
            $w->writeElement('Сумма', $this->price($order['total']));

            $w->startElement('Контрагенты');

            $w->startElement('Контрагент');
            $w->writeElement('Ид', $order['contact_id']);
            $c = null;
            if ($c_id = ifset($order['contact']['id'])) {
                $c = new waContact($c_id);
            }


            $company_field = $this->pluginSettings('contact_company');

            $is_jur = !empty($company_field) && !empty($order['contact'][$company_field]);

            if (!$is_jur) {
                $w->writeElement('Наименование', ifempty($order['contact']['name'], '-'));
                $w->writeElement('ПолноеНаименование', ifempty($order['contact']['name'], '-'));
                $w->writeElement('Роль', 'Покупатель');
                $w->writeElement('Фамилия', $order['contact']['lastname']);
                $w->writeElement('Имя', $order['contact']['firstname']);


                $this->writeAddress($shipping_address_array, $shipping_address, 'АдресРегистрации');

            } else {
                $w->writeElement('Наименование', $order['contact'][$company_field]);
                $w->writeElement('ОфициальноеНаименование', $order['contact'][$company_field]);

                if (!empty($billing_address_array)) {
                    $this->writeAddress($billing_address_array, $billing_address, 'ЮридическийАдрес');
                } else {
                    $this->writeAddress($shipping_address_array, $shipping_address, 'ЮридическийАдрес');
                }

                $value = $this->getContactField($this->pluginSettings('contact_inn'), $order['contact'], $c);
                if ($value !== null) {
                    $w->writeElement('ИНН', $value);
                }

                $value = $this->getContactField($this->pluginSettings('contact_kpp'), $order['contact'], $c);
                if ($value !== null) {
                    $w->writeElement('КПП', $value);
                }

                $value = $this->getContactField($this->pluginSettings('contact_okpo'), $order['contact'], $c);
                if ($value !== null) {
                    $w->writeElement('ОКПО', $value);
                }

                $bank = array(
                    'НомерСчета' => $this->getContactField($this->pluginSettings('contact_bank_account'), $order['contact'], $c),

                    'СчетКорреспондентский' => $this->getContactField($this->pluginSettings('contact_bank_cor_account'), $order['contact'], $c),
                    'Наименование'          => $this->getContactField($this->pluginSettings('contact_bank_name'), $order['contact'], $c),
                    'БИК'                   => $this->getContactField($this->pluginSettings('contact_bank_bik'), $order['contact'], $c),
                );

                $bank = array_filter($bank);
                if (count($bank) && !empty($bank['НомерСчета'])) {
                    $w->startElement('РасчетныеСчета');
                    $w->startElement('РасчетныйСчет');
                    $w->writeElement('НомерСчета', $bank['НомерСчета']);
                    unset($bank['НомерСчета']);
                    $bank = array_filter($bank);
                    if ($bank) {
                        $w->startElement('Банк');

                        foreach ($bank as $field => $value) {
                            if ($value !== null) {
                                $w->writeElement($field, $value);
                            }
                        }
                        $w->endElement(/*Банк*/);
                    }

                    $w->endElement(/*РасчетныйСчет*/);
                    $w->endElement(/*РасчетныеСчета*/);
                }
            }

            #Адрес
            $this->writeAddress($shipping_address_array, $shipping_address, 'Адрес', 'Адрес доставки');

            if ($c) {
                $w->startElement('Контакты');
                if ($field = $this->pluginSettings('contact_email')) {
                    if ($value = $c->get($field, 'default')) {
                        $w->startElement('Контакт');
                        $w->writeElement('Тип', 'Почта');
                        $w->writeElement('Значение', $value);
                        $w->endElement(/*Контакт*/);
                    }
                }

                if ($field = $this->pluginSettings('contact_phone')) {
                    if ($value = $c->get($field, 'default')) {
                        $w->startElement('Контакт');
                        $w->writeElement('Тип', 'ТелефонРабочий');
                        $w->writeElement('Представление', $value);
                        $w->writeElement('Значение', $value);
                        $w->endElement(/*Контакт*/);
                    }
                }

                $w->endElement(/*Контакты*/);
            }
            $w->endElement(/*Контрагент*/);

            $w->endElement(/*Контрагенты*/);

            //Время документа
            $w->writeElement('Время', $time);
            $comment = trim(ifempty($order['comment'], ifset($order['status_comment'])));
            if ($comment !== '') {
                $w->writeElement('Комментарий', $comment);
            }

            if ($is_jur) {
                $w->startElement('Представители');
                $w->startElement('Представитель');
                $w->startElement('Контрагент');
                $w->writeElement('Отношение', 'Контактное лицо');
                $w->writeElement('Ид', $order['contact_id']);
                $w->writeElement('Наименование', ifempty($order['contact']['name'], '-'));
                $w->writeElement('ПолноеНаименование', ifempty($order['contact']['name'], '-'));
                $w->writeElement('Фамилия', $order['contact']['lastname']);
                $w->writeElement('Имя', $order['contact']['firstname']);
                $w->endElement(/*Контрагент*/);
                $w->endElement(/*Представитель*/);
                $w->endElement(/*Представители*/);

            }

            $items = ifempty($order['items'], array());
            $subtotal = 0;

            $ids = array();
            if ($order['discount']) {
                foreach ($items as $item) {
                    $subtotal += $item['price'] * $item['quantity'];
                    $ids[] = $item['product_id'];

                }
                $params['discount_rate'] = $subtotal ? ($order['discount'] / $subtotal) : 0;
            }
            $ids = array_unique($ids);
            if ($ids) {
                $product_model = $this->getModel('product');
                /**
                 * @var shopProductModel $product_model
                 */

                $tax_ids = $product_model
                    ->select('`tax_id`,`id`')
                    ->where('`id` IN (i:id)', array('id' => $ids))
                    ->query()
                    ->fetchAll('id', true);
            } else {
                $tax_ids = array();
            }
            foreach ($items as & $item) {
                $item['tax_id'] = ifset($tax_ids[$item['product_id']]);
                $item['currency'] = $order['currency'];
            }
            unset($item);

            $discount_rate = ifset($params['discount_rate'], 0);
            $taxes = shopTaxes::apply($items, $params, $order['currency']);
            $taxes = array_filter($taxes, create_function('$a', 'return !empty($a["name"]);'));
            //Вид, ставка и сумма налога
            $this->writeTaxes($taxes);
            if (!$items && ($order['discount'] > 0)) {
                //Скидка, сумма, проценты
                $this->writeDiscounts(
                    array(
                        array(
                            'name'     => 'Скидка',
                            'discount' => $order['discount'],
                            'included' => true,
                        ),
                    )
                );
            }

            $w->startElement('Товары');
            $products = $this->findOrderItems($items);
            foreach ($products as $product) {
                $this->writeOrderItem($product, $discount_rate, $product['tax_id'] ? ifempty($taxes[$product['tax_id']]) : null, $order['rate']);
            }

            //XXX Услуги

            if (!empty($order['shipping'])) {
                $this->writeOrderService(
                    array(
                        'id_1c' => 'ORDER_DELIVERY',
                        'name'  => 'Доставка заказа',
                        'price' => $order['shipping'],
                    ),
                    0,
                    $order['rate']
                );
            }
            $w->endElement(/*Товары*/);

            $data = array(
                'Способ оплаты'          => ifset($params['payment_name']),
                'Статус заказа'          => $states[$order['state_id']]->getName(), //XXX
                'Дата изменения статуса' => date("Y-m-dTH:i:s", strtotime(ifempty($order['update_datetime'], $order['create_datetime']))),
                'Способ доставки'        => ifset($params['shipping_name']),
                'Адрес доставки'         => $shipping_address,
                'Адрес платильщика'      => $billing_address,
                'Адрес плательщика'      => $billing_address,
                //XXX
                'Заказ оплачен'          => empty($order['paid_date']) ? 'false' : 'true',
                //'Доставка разрешена'     => null,
            );
            if ($order['state_id'] == 'deleted') {
                $data['ПометкаУдаления'] = 'true';
            }

            $contact_fields = $this->data['export_custom_properties'];
            foreach ($contact_fields as $id => $info) {
                if (!empty($info['enabled'])) {
                    $value = null;
                    if (strpos($id, 'address:') === 0) {
                        $id = str_replace('address:', 'shipping_address.', $id);
                        if (!empty($params[$id])) {
                            $value = $params[$id];
                        }
                    } elseif (!empty($params[$id])) {
                        $value = $params[$id];
                    } else {
                        if (!empty($c)) {
                            $contact_field = $c->get($id);
                            if (!in_array($contact_field, array('', null, false), true)) {
                                $value = $contact_field;
                            }
                        }
                    }

                    if ($value !== null) {
                        $tag = ifset($info['tag'], $id);
                        if (!empty($tag)) {
                            $data[$tag] = $value;
                        }
                    }
                }
            }

            /*ЗначенияРеквизитов*/
            $this->writeProperties($data);

            $w->endElement(/*Документ*/);
            $current_stage[self::STAGE_ORDER]++;
            $processed[self::STAGE_ORDER]++;
            array_shift($orders);
        }
        return ($current_stage[self::STAGE_ORDER] < $count[self::STAGE_ORDER]);
    }

    private function findOrderItems($items)
    {
        $map =& $this->data['map'][self::STAGE_ORDER];
        $skus = array();
        foreach ($items as $product) {
            if (!isset($map[$product['sku_id']])) {
                $skus[] = $product['sku_id'];
            }
        }

        if ($skus = array_unique(array_map('intval', $skus))) {

            $sku_model = $this->getModel('productSkus');
            /**
             * @var shopProductSkusModel $sku_model
             */
            $sql = <<<SQL
SELECT `s`.`id`, CONCAT(`p`.`id_1c`,"#",`s`.`id_1c`) `cml1c`
FROM `shop_product_skus` `s`
LEFT JOIN `shop_product` `p` ON (`p`.`id` = `s`.`product_id`)
WHERE `s`.`id` IN (i:skus)
SQL;

            $map += (array)$sku_model->query($sql, array('skus' => $skus))->fetchAll('id', true);
        }
        foreach ($items as &$product) {

            $uuid = explode('#', ifset($map[$product['sku_id']]));
            if (empty($uuid)) {
                //XXX deleted or not synced item
            } elseif ((count($uuid) > 1) && (reset($uuid) != end($uuid))) {
                $product['id_1c'] = reset($uuid).'#'.end($uuid);
            } else {
                $product['id_1c'] = reset($uuid);
            }
            unset($product);
        }

        $export_features = $this->pluginSettings('export_features');
        if ($export_features) {
            $features_model = new shopProductFeaturesModel();
            switch ($export_features) {
                case 'sync':
                    break;
                case 'all':
                    foreach ($items as &$item) {
                        if (($item['type'] == 'product') && !empty($item['sku_id']) && !empty($item['product_id'])) {
                            $item['features'] = $features_model->getValues($item['product_id'], $item['sku_id']);
                        }
                    }
                    unset($item);
                    break;
            }
        }
        return $items;
    }


    /**
     * @param mixed [string] $product
     * @param string [string] $product['sku_id']
     * @param string [string] $product['id_1c']
     * @param string [string] $product['name']
     * @param int [string] $product['quantity']
     * @param double [string] $product['price']
     * @param double [string] $product['tax']
     * @param bool [string] $product['tax_included']
     * @param $discount_rate
     * @param $tax
     */
    private function writeOrderItem($product, $discount_rate, $tax, $rate = 1.0)
    {
        #prepare
        static $features = array();
        if ($discount_rate > 0) {
            $product['discount'] = $discount_rate * $product['price'];
        }

        $product['total'] = $product['quantity'] * ($product['price'] - ifset($product['discount'], 0));
        if (!empty($product['tax']) && empty($product['tax_included'])) {
            $product['total'] += $product['tax'];
        }

        if ($this->_convert_order_price) {
            $product['price'] *= $rate;
            $product['total'] *= $rate;
        }

        #add element
        $this->writer->startElement('Товар');
        $this->writer->writeElement('Ид', ifset($product['id_1c'], '-'));
        //XXX ИдКаталога ?

        # fix name duplicates
        $product['name'] = preg_replace('@^(.+) \(\1\)$@', '$1', $product['name']);

        $this->writer->writeElement('Наименование', $product['name']);
        $this->writeUnit();


        //TODO check discount with rate
        if ($discount_rate > 0) {
            $product['discount'] = $discount_rate * $product['price'];
            $this->writeDiscounts(
                array(
                    array(
                        'name'     => 'Скидка на товар',
                        'discount' => $product['discount'],
                        'included' => true,
                    ),
                )
            );
        }

        $properties = array(
            'ВидНоменклатуры' => 'Товар',
            'ТипНоменклатуры' => 'Товар',
        );

        if (!empty($product['features'])) {

            if ($new = array_diff(array_keys($product['features']), array_keys($features))) {
                $model = $this->getModel('feature');
                /**
                 * @var shopFeatureModel $model
                 */

                $features += $model->select('code, name')->where('code IN (?)', array($new))->fetchAll('code', true);
            }

            if (true) {
                foreach ($product['features'] as $code => $feature) {
                    $properties[ifset($features[$code], $code)] = (string)$feature;
                }
            } else {
                $this->writer->startElement('ХарактеристикиТовара');
                //XXX or use properties?
                foreach ($product['features'] as $code => $feature) {
                    $this->writer->startElement('ХарактеристикаТовара');
                    //$this->writer->writeElement('Ид', '');
                    $this->writer->writeElement('Наименование', ifset($features[$code], $code));
                    $this->writer->writeElement('Значение', (string)$feature);
                    $this->writer->endElement(/*ХарактеристикаТовара*/);
                }
                $this->writer->endElement(/*ХарактеристикиТовара*/);
            }
        }

        $this->writeProperties($properties);

        $this->writer->writeElement('ЦенаЗаЕдиницу', $this->price($product['price']));
        $this->writer->writeElement('Количество', $product['quantity']);
        $this->writer->writeElement('Сумма', $this->price($product['total']));
        if ($tax) {
            $this->writeTaxes(array($tax));
        }

        $this->writer->endElement(/*Товар*/);
    }

    /**
     * @param $service
     * @param float|int $discount_rate
     * @internal param $mixed [string] $service
     * @internal param $string [string] $service['id_1c']
     * @internal param $string [string] $service['name']
     * @internal param $int [string] $service['quantity'] default is 1
     * @internal param $double [string] $service['price']
     * @internal param $double [string] $service['tax'] default is null
     * @internal param $bool [string] $service['tax_included'] default is null
     * @param float $rate
     */
    private function writeOrderService($service, $discount_rate = 0, $rate = 1.0)
    {
        if ($discount_rate > 0) {
            $product['discount'] = $discount_rate * $service['price'];
        }

        $service['total'] = ifset($service['quantity'], 1) * ($service['price'] - ifset($service['discount'], 0));
        if (!empty($service['tax']) && !empty($service['tax_included'])) {
            $service['total'] += $service['tax'];
        }

        if ($this->_convert_order_price) {
            $service['price'] *= $rate;
            $service['total'] *= $rate;
        }

        $this->writer->startElement('Товар');
        $this->writer->writeElement('Ид', ifset($service['id_1c'], '-'));
        $this->writer->writeElement('Наименование', $service['name']);
        if (!empty($service['sku'])) {
            $this->writer->writeElement('Артикул', $service['sku']);
        }
        $this->writeUnit();

        $this->writeProperties(
            array(
                'ВидНоменклатуры' => 'Услуга',
                'ТипНоменклатуры' => 'Услуга',
            )
        );

        $this->writer->writeElement('ЦенаЗаЕдиницу', $this->price($service['price']));
        $this->writer->writeElement('Количество', ifset($service['quantity'], 1));
        $this->writer->writeElement('Сумма', $this->price($service['total']));

        $this->writer->endElement(/*Товар*/);
    }

    private function writeDiscounts($discounts = array())
    {
        if ($discounts) {
            $this->writer->startElement('Скидки');
            foreach ($discounts as $discount) {
                $this->writer->startElement('Скидка');
                $this->writer->writeElement('Наименование', ifempty($discount['name'], 'Скидка'));
                $this->writer->writeElement('Сумма', $this->price($discount['discount']));
                $this->writer->writeElement('УчтеноВСумме', $discount['included'] ? 'true' : 'false');

                $this->writer->endElement(/*Скидка*/);
            }
            $this->writer->endElement(/*Скидки*/);
        }
    }


    private function writeTaxes($taxes = array())
    {
        if ($taxes) {
            $this->writer->startElement('Налоги');
            foreach ($taxes as $tax) {
                $this->writer->startElement('Налог');
                $this->writer->writeElement('Наименование', $tax['name']);
                $this->writer->writeElement('УчтеноВСумме', $tax['included'] ? 'true' : 'false');
                $this->writer->writeElement('Сумма', $this->price($tax['included'] ? $tax['sum_included'] : $tax['sum']));
                if (isset($tax['rate'])) {
                    $this->writer->writeElement('Ставка', $this->price($tax['rate']));
                }
                $this->writer->endElement(/*Налог*/);
            }
            $this->writer->endElement(/*Налоги*/);
        }
    }

    private function writeProperties($data)
    {
        $this->writer->startElement('ЗначенияРеквизитов');
        foreach ($data as $name => $value) {
            $value = trim($value);
            if ($value !== '') {
                $this->writeProperty($name, $value);
            }
        }
        $this->writer->endElement(/*ЗначенияРеквизитов*/);
    }

    private function writeProperty($name, $value)
    {
        if ($value !== '') {
            $this->writer->startElement('ЗначениеРеквизита');
            $this->writer->writeElement('Наименование', $name); //max 255
            $this->writer->writeElement('Значение', $value); // max 1000
            $this->writer->endElement(/*ЗначениеРеквизита*/);
        }
    }

    private function writeAddress($address, $full_address, $name = 'Адрес', $type = '')
    {
        $this->writer->startElement($name);
        if ($type) {
            $this->writer->writeElement('Вид', $type);
        }
        $this->writer->writeElement('Представление', $full_address);

        foreach ($address as $type => $field) {
            if (!empty($field)) {
                $this->writer->startElement('АдресноеПоле');
                $this->writer->writeElement('Тип', $type);
                $this->writer->writeElement('Значение', $field);
                $this->writer->endElement(/*АдресноеПоле*/);
            }
        }
        $this->writer->endElement(/*$name*/);
    }

    /**
     *
     * @return SimpleXMLElement
     * @throws waException
     */
    private function element()
    {
        if (!$this->reader) {
            throw new waException('Empty XML reader');
        }
        $element = $this->reader->readOuterXml();
        return simplexml_load_string(trim($element));
    }

    /**
     * @param SimpleXMLElement $element
     * @param string $xpath
     * @return SimpleXMLElement[]
     */
    private function xpath($element, $xpath)
    {
        if ($namespaces = $element->getNamespaces(true)) {
            $name = array();
            foreach ($namespaces as $id => $namespace) {
                $element->registerXPathNamespace($name[] = 'wa'.$id, $namespace);
            }
            $xpath = preg_replace('@(^[/]*|[/]+)@', '$1'.implode(':', $name).':', $xpath);
        }
        return $element->xpath($xpath);
    }

    /**
     *
     *
     * @param SimpleXMLElement $element
     * @param string $field
     * @param string $type
     *
     * @return mixed
     */
    private static function field(&$element, $field, $type = 'string')
    {
        $value = $element->{$field};
        switch ($type) {
            case 'xml':
                break;
            case 'intval':
            case 'int':
                $value = intval(str_replace(array(' ', ','), array('', '.'), (string)$value));
                break;
            case 'floatval':
            case 'float':
                $value = floatval(str_replace(array(' ', ','), array('', '.'), (string)$value));
                break;
            case 'doubleval':
            case 'double':
                $value = doubleval(str_replace(array(' ', ','), array('', '.'), (string)$value));
                break;
            case 'array':
                $value = (array)$value;
                break;
            case 'string':
            default:
                $value = trim((string)$value);
                break;
        }
        return $value;
    }

    /**
     * @param SimpleXMLElement $element
     * @param string $attribute
     * @return string
     */
    private static function attribute(&$element, $attribute)
    {
        $value = (string)$element[$attribute];
        $value = preg_replace_callback('/\\\\u([0-9a-f]{4})/i', array(__CLASS__, 'replaceUnicodeEscapeSequence'), $value);
        $value = preg_replace_callback('/\\\\u([0-9a-f]{4})/i', array(__CLASS__, 'htmlDereference'), $value);
        return $value;
    }

    private static function htmlDereference($match)
    {
        if (strtolower($match[1][0]) === 'x') {
            $code = intval(substr($match[1], 1), 16);
        } else {
            $code = intval($match[1], 10);
        }
        return mb_convert_encoding(pack('N', $code), 'UTF-8', 'UTF-32BE');
    }

    private static function replaceUnicodeEscapeSequence($match)
    {
        return mb_convert_encoding(pack('H*', $match[1]), 'UTF-8', 'UCS-2BE');
    }

    private function isBreak()
    {
        static $runtime;
        static $limit;
        if (wa()->getEnv() == 'frontend') {

            if (!$runtime) {
                $runtime = time();
                $limit = $this->max_exec_time ? min(20, max(5, $this->max_exec_time / 2)) : 20;
            }

            return ((time() - $runtime) > $limit);
        } else {
            return false;
        }
    }

    /**
     * @param $current_stage
     * @param $count
     * @param $processed
     *
     * @throws waException
     * @return bool
     * @uses shopCml1cPluginBackendRunController::stepImportCategory
     */
    private function stepImport(&$current_stage, &$count, &$processed)
    {
        $chunk = 30;
        $result = true;

        while ($while = $this->read(self::$read_method)) {
            self::$read_method = 'unknown_import';
            if ($this->reader->depth >= 2) {
                if ($stage = $this->getStage()) {
                    $method_name = $this->getMethod($stage);
                    if (method_exists($this, $method_name) && ($current_stage[$stage] < $count[$stage])) {
                        list($node, self::$read_method) = self::$node_name_map[$stage];
                        if (self::$read_method == 'next') {
                            $map = array_flip(self::$node_map);
                            $path = ifset($map[$stage], '/').'/'.$node;
                        } else {
                            $path = null;
                        }
                        while (($cur_stage = $this->getStage()) && ($cur_stage == $stage)) {

                            if (!isset(self::$read_offset[$stage])) {
                                self::$read_offset[$stage] = 0;
                                $method_ = 'read';
                            } else {
                                $method_ = self::$read_method;
                            }

                            if ($this->read($method_, $path)) {
                                if ($this->reader->nodeType == XMLReader::ELEMENT) {
                                    if ($this->reader->name == $node) {
                                        ++self::$read_offset[$stage];
                                        if ($current_stage[$stage] < self::$read_offset[$stage]) {
                                            $result = $this->$method_name($current_stage, $count, $processed);
                                            if ($current_stage[$stage] && ($current_stage[$stage] === $count[$stage])) {

                                                $complete_method = $this->getMethod($stage, true);
                                                if (method_exists($this, $complete_method)) {
                                                    $this->$complete_method();
                                                }
                                            }
                                            if (!$result) {
                                                break 2;
                                            }
                                            if (($break = $this->isBreak()) || (--$chunk <= 0)) {
                                                if ($break) {
                                                    $result = false;
                                                } else {
                                                    self::$read_method = 'skip';
                                                }
                                                break 2;
                                            }
                                        }
                                    }
                                }
                            } else {
                                self::$read_method = 'end';
                                $this->read(self::$read_method);
                                break 2;
                            }
                        }
                    }
                }
                self::$read_method = 'next';
            }
        }

        if (!$while) {
            $stage = self::STAGE_IMAGE;
            $method_name = 'stepImport'.ucfirst($stage);

            if (method_exists($this, $method_name) && !empty($count[$stage]) && ($current_stage[$stage] < $count[$stage])) {
                $result = true;
                do {
                    if (($break = $this->isBreak()) || (--$chunk <= 0)) {
                        if ($break) {
                            $result = false;
                        } else {
                            self::$read_method = 'skip';
                        }
                        break;
                    }
                } while ($this->$method_name($current_stage, $count, $processed));
            }
            return $result;
        }


        if ($r = $this->getXmlError()) {
            $this->error('XML errors while read: '.$r);
        }

        return $result;
    }

    private $category = array();

    /**
     * @return shopCategoryModel
     */
    private function getCategoryModel()
    {
        return $this->getModel('category');
    }

    private function &category($element = null)
    {
        if (empty($element)) {
            $element = $this->element();
        }
        $category = array(
            'name'         => self::field($element, 'Наименование'),
            'id_1c'        => self::field($element, 'Ид'),
            'id'           => null,
            'parent_id_1c' => self::field($element, 'Родитель'),
            'parent_id'    => 0,
        );

        if (!isset($this->data['map'][self::STAGE_CATEGORY])) {
            $this->data['map'][self::STAGE_CATEGORY] = $this->getCategoryModel()->select('`id`,`parent_id`, `id_1c`')->where('`id_1c` IS NOT NULL')->fetchAll('id_1c', true);
        }
        $map = $this->data['map'][self::STAGE_CATEGORY];

        if (!empty($category['parent_id_1c'])) {
            $category['parent_id'] = intval(ifset($map[$category['parent_id_1c']]));
            //TODO
            $depth = 0;
        } else {

            $depth = (int)floor(((int)$this->reader->depth - 3) / 2);
            $this->category = array_slice($this->category, 0, $depth);
            ksort($this->category);
            if ($parent = end($this->category)) {
                $category['parent_id'] = $parent['id'];
            }
        }

        if (isset($map[$category['id_1c']])) {
            $c = $map[$category['id_1c']];
            $category['id'] = intval($c['id']);
            if ($category['parent_id'] == $c['parent_id']) {
                unset($category['parent_id']);
            }
        }

        $this->category[$depth] = $category;
        return $this->category[$depth];
    }

    /**
     * @usedby self::stepImport
     * @param $current_stage
     * @param $count
     * @param $processed
     * @return bool
     */
    private function stepImportCategory(&$current_stage, &$count, &$processed)
    {
        $category = &$this->category();
        if (!empty($category['id'])) {
            $target = 'update';
            $update_fields = $this->pluginSettings('update_category_fields');

            #update category parent
            if (isset($category['parent_id'])) {
                if (!empty($update_fields['parent_id'])) {
                    $before_id = null;
                    if (empty($category['parent_id'])) {
                        //TODO find next top level category
                    }
                    $this->getCategoryModel()->move($category['id'], $before_id, $category['parent_id']);
                }
                unset($category['parent_id']);
            }

            foreach (array('name', 'description') as $field) {
                if (empty($update_fields[$field])) {
                    unset($category[$field]);
                }
            }
            $this->getCategoryModel()->update($category['id'], $category);
        } else {
            $target = 'new';
            $category['url'] = shopHelper::transliterate($category['name']);
            $category['id'] = $this->getCategoryModel()->add($category, $category['parent_id']);
            $this->data['map'][self::STAGE_CATEGORY][$category['id_1c']] = array(
                'id'        => $category['id'],
                'parent_id' => $category['parent_id'],
            );
        }
        ++$processed[self::STAGE_CATEGORY][$target];
        ++$current_stage[self::STAGE_CATEGORY];


        return true;

    }

    /**
     * @param $current_stage
     * @param $count
     * @param $processed
     *
     * @return bool
     */
    private function stepImportFeature(&$current_stage, &$count, &$processed)
    {
        $element = $this->element();
        $data = array(
            'id_1c'      => self::field($element, 'Ид'),
            'name'       => self::field($element, 'Наименование'),
            'values'     => array(),
            'type'       => null,
            'multiple'   => false,
            'selectable' => false,
        );

        switch ($t = self::field($element, 'ТипЗначений')) {
            case 'Справочник':
                $data['type'] = ifempty($data['type'], shopFeatureModel::TYPE_VARCHAR);
                $data['selectable'] = true;
                foreach ($this->xpath($element, '//Свойство/ВариантыЗначений/Справочник') as $xml) {
                    if ($uuid = self::field($xml, 'ИдЗначения')) {
                        $data['values'][$uuid] = self::field($xml, 'Значение');
                    }

                }
                break;
            case 'Строка':
                $data['type'] = ifempty($data['type'], shopFeatureModel::TYPE_VARCHAR);
                break;
            case 'Число':
                $data['type'] = ifempty($data['type'], shopFeatureModel::TYPE_DOUBLE);
                break;
            case 'Время':
                $data['type'] = ifempty($data['type'], shopFeatureModel::TYPE_DIMENSION.'.time');
                break;
            default:
                $data['type'] = ifempty($data['type'], shopFeatureModel::TYPE_VARCHAR);
                break;
        }


        if (!isset($this->data['map'][self::STAGE_FEATURE])) {
            $this->data['map'][self::STAGE_FEATURE] = array();
        }
        $this->data['map'][self::STAGE_FEATURE][$data['id_1c']] = $data;


        //at offers - it's generic features
        //at product - features map
        ++$current_stage[self::STAGE_FEATURE];
        ++$processed[self::STAGE_FEATURE];

        return true;

    }

    /**
     * @param $current_stage
     * @param $count
     * @param $processed
     *
     * @return bool
     */
    private function stepImportFeatureConfigure(&$current_stage, &$count, &$processed)
    {
        $element = $this->element();
        $data = array(
            'id_1c'      => self::field($element, 'Ид'),
            'name'       => self::field($element, 'Наименование'),
            'values'     => array(),
            'type'       => null,
            'multiple'   => false,
            'selectable' => false,
            'code'       => null,
        );

        switch ($t = self::field($element, 'ТипЗначений')) {
            case 'Справочник':
                $data['type'] = ifempty($data['type'], shopFeatureModel::TYPE_VARCHAR);
                $data['selectable'] = true;
                foreach ($this->xpath($element, '//Свойство/ВариантыЗначений/Справочник') as $xml) {
                    if ($uuid = self::field($xml, 'ИдЗначения')) {
                        $data['values'][$uuid] = self::field($xml, 'Значение');
                    }
                }
                break;
            case 'Строка':
                $data['type'] = ifempty($data['type'], shopFeatureModel::TYPE_VARCHAR);
                break;
            case 'Число':
                $data['type'] = ifempty($data['type'], shopFeatureModel::TYPE_DOUBLE);
                break;
            case 'Время':
                $data['type'] = ifempty($data['type'], shopFeatureModel::TYPE_DIMENSION.'.time');
                break;
            default:
                $data['type'] = ifempty($data['type'], shopFeatureModel::TYPE_VARCHAR);
                break;
        }

        $data['code'] = $this->findFeature($data['name'], $data);

        $xpath = '//Свойства';

        $this->addFeatureMap($xpath, $data['id_1c'], $data);

        //at offers - it's generic features
        //at product - features map
        ++$current_stage[self::STAGE_FEATURE];
        ++$processed[self::STAGE_FEATURE]['analyze'];

        return true;

    }

    /**
     *
     * @param array $uuid
     *
     * @return shopProduct
     */
    private function findProduct($uuid)
    {
        static $currency;
        $product_uuid = reset($uuid);

        $model = $this->getModel('product');
        /**
         * @var shopProductModel $model
         */
        if ($data = $model->getByField('id_1c', $product_uuid)) {
            $product = new shopProduct($data['id']);
        } else {
            $product = new shopProduct();

            if (!$currency) {
                $currency = wa('shop')->getSetting('currency', 'USD', 'shop');
            }
            $product->currency = $currency;
            $product->type_id = $this->data['default_type_id'];
        }

        return $product;
    }

    /**
     * @param string $name
     * @param array $data
     * @param string $xpath
     * @return string feature code
     */
    private function findFeature($name, $data = null, $xpath = null)
    {
        static $feature_model;
        $feature_map =& $this->data['map'][self::STAGE_PRODUCT];
        $uuid = is_array($data) ? ifset($data['id_1c']) : null;
        if (!empty($uuid)) {
            $key = 'u:'.$uuid;
        } else {
            $key = 'n:'.$name;
        }

        if (!isset($feature_map[$key])) {

            if (!$feature_model) {
                $feature_model = $this->getModel('feature');
            }
            /**
             * @var shopFeatureModel $feature_model
             */

            $uuid = is_array($data) ? ifset($data['id_1c']) : null;
            if (!empty($uuid) && ($features = $feature_model->getFeatures('cml1c_id', $uuid))) {
                $feature = reset($features);
                $key = '*'.$uuid;
                $feature_map[$key] = $feature['code'];
            } elseif ($features = $feature_model->getFeatures('lname', $name)) {
                $feature = reset($features);
                if (count($features) > 1) {
                    //XXX check for collisions (multiple features with same name
                    sprintf("Features collision for name %s", $name);
                }
                $feature_map[$key] = $feature['code'];
            } else {
                $feature = array(
                    'name' => $name,
                    'code' => strtolower(waLocale::transliterate($name, 'ru_RU')),
                    'type' => ifempty($data['type'], shopFeatureModel::TYPE_VARCHAR),
                );
                $feature += ifempty($data, array());

                if (empty($this->data['configure'])) {
                    //save new features only for import
                    if ($feature['id'] = $feature_model->save($feature)) {
                        $feature_map[$key] = $feature['code'];

                        if (!isset($this->data['new_features'])) {
                            $this->data['new_features'] = array();
                        }
                        $this->data['new_features'][$feature['code']] = array(
                            'id'    => $feature['id'],
                            'types' => array(),
                        );
                    }
                }
            }
        }

        return ifset($feature_map[$key]);
    }

    private function setFeatureType($codes, $product_type = null)
    {
        if ($product_type) {
            foreach ($codes as $code) {
                if (isset($this->data['new_features'][$code])) {
                    $types = &$this->data['new_features'][$code]['types'];
                    if (!in_array($product_type, $types)) {
                        $types[] = $product_type;
                    }
                    unset($types);
                }
            }
        }
    }

    /**
     * Determine tax data
     * @param array $tax
     * @return mixed
     */
    private function findTax($tax)
    {
        static $taxes;
        if (ifset($tax['name'])) {
            $key = mb_strtolower($tax['name'], 'utf-8');
            if (!isset($taxes[$key])) {

                $tax_model = $this->getModel('tax');
                /**
                 * @var shopTaxModel $tax_model
                 */
                if ($row = $tax_model->getByName($key)) {
                    $taxes[$key] = (int)$row['id'];
                } else {
                    $taxes[$key] = null;
                }
            }
            $tax['id'] = $taxes[$key];
        } else {
            $tax['id'] = null;
        }
        return $tax;
    }


    /**
     * @param string $name
     * @param shopProduct $product
     * @return array|mixed|null|string
     */
    private function findType($name, &$product)
    {
        static $types = array();
        /**
         * @var shopTypeModel $model
         */
        //Новые товары относятся к типу по умолчанию
        $type_id = $product->getId() ? null : $this->data['default_type_id'];
        $update_type_id = ifset($this->data['update_product_types']);
        if (!empty($name)
            &&
            ($update_type_id != 'skip')//не пропускать импорт типов товаров
            &&
            (
                !$product->getId()//новые товары
                ||
                ($update_type_id == 'sync')//обновлять для всех товаров
            )
        ) {
            $name = mb_strtolower($name, 'utf-8');
            if (!isset($types[$name])) {
                $model = $this->getModel('type');
                /**
                 * @var shopTypeModel $model
                 */

                if ($type_row = $model->getByName($name)) {
                    $types[$name] = intval($type_row['id']);
                } elseif (in_array($update_type_id, array('update', 'sync'), true)) {
                    $types[$name] = intval(
                        $model->insert(
                            array(
                                'name' => $name,
                                'icon' => 'ss pt box',
                            )
                        )
                    );
                }
            }

            if (isset($types[$name])) {
                $type_id = $types[$name];
            }
        }
        return $type_id;
    }

    private function stepImportPrice(&$current_stage, &$count, &$processed)
    {
        $map =& $this->data['map'][self::STAGE_PRICE];

        $element = $this->element();

        $currency = array(
            'id'       => self::field($element, 'Ид'),
            'currency' => $this->findCurrency(self::field($element, 'Валюта')),
        );

        $map[mb_strtolower(self::field($element, 'Наименование'), 'utf-8')] = $currency;


        ++$processed[self::STAGE_PRICE];
        ++$current_stage[self::STAGE_PRICE];
        return true;
    }

    private function stepImportStockConfigure(&$current_stage, &$count, &$processed)
    {
        $element = $this->element();
        $id = self::field($element, 'Ид');
        if (!isset($this->data['stock_map'][$id])) {
            $this->data['stock_map'][$id] = array();
        }
        $this->data['stock_map'][$id] += array(
            'stock_id' => -1,
            'name'     => self::field($element, 'Наименование'),
        );
        ++$processed[self::STAGE_STOCK]['analyze'];
        ++$current_stage[self::STAGE_STOCK];
        return true;
    }

    private function completeImportPrice()
    {
        $map =& $this->data['map'][self::STAGE_PRICE];
        $settings = array();

        $purchase_price = mb_strtolower($this->data['purchase_price_type'], 'utf-8');
        if (isset($map[$purchase_price])) {
            if ($map[$purchase_price]['id'] != $this->data['purchase_price_type_uuid']) {
                $settings['purchase_price_type_uuid'] = $map[$purchase_price]['id'];
            }
        }

        $price = mb_strtolower($this->data['price_type'], 'utf-8');
        if (isset($map[$price])) {
            if ($map[$price]['id'] != $this->data['price_type_uuid']) {
                $settings['price_type_uuid'] = $map[$price]['id'];
            }
        } elseif ($map) {
            $map[$price] = reset($map);
        }

        if ($settings) {
            $this->pluginSettings($settings);
        }
    }

    private function convertPrice($price, $from, $to)
    {
        $model = $this->getModel('currency');
        /**
         * @var shopCurrencyModel $model
         */
        return $model->convert($price, $from, $to);
    }

    /**
     * @param $type
     * @return shopCurrencyModel|shopProductSkusModel|shopTypeFeaturesModel|shopProductImagesModel|shopFeatureModel|shopOrderModel|shopTypeModel|shopCategoryModel|shopStockModel
     */
    private function getModel($type)
    {
        static $models = array();
        if (!isset($models[$type])) {
            switch ($type) {
                case 'currency':
                    $models[$type] = new shopCurrencyModel();
                    break;
                case 'product':
                    $models[$type] = new shopProductModel();
                    break;
                case 'productSkus':
                    $models[$type] = new shopProductSkusModel();
                    break;
                case 'typeFeatures':
                    $models[$type] = new shopTypeFeaturesModel();
                    break;
                case 'productImages':
                    $models[$type] = new shopProductImagesModel();
                    break;
                case 'feature':
                    $models[$type] = new shopFeatureModel();
                    break;
                case 'order':
                    $models[$type] = new shopOrderModel();
                    break;
                case 'type':
                    $models[$type] = new shopTypeModel();
                    break;
                case 'category':
                    $models[$type] = new shopCategoryModel();
                    break;
                case 'stock':
                    $models[$type] = new shopStockModel();
                    break;
                case 'tax':
                    $models[$type] = new shopTaxModel();
                    break;
            }
        }
        return ifset($models[$type]);
    }

    /**
     * @param $current_stage
     * @param $count
     * @param $processed
     * @return bool
     * @throws waException
     */
    private function stepImportOffer(&$current_stage, &$count, &$processed)
    {
        if (self::$price_map === null) {
            $map = $this->data['map'][self::STAGE_PRICE];
            self::$price_map = array();
            foreach (array('price', 'purchase_price') as $type) {
                if (!empty($this->data[$type.'_type'])) {
                    $price_name = mb_strtolower($this->data[$type.'_type'], 'utf-8');
                    if (!empty($map[$price_name])) {
                        $map_ = $map[$price_name];
                        if (isset(self::$price_map[$map_['id']])) {
                            self::$price_map[$map_['id']]['name'][] = $price_name;
                        } else {
                            self::$price_map[$map_['id']] = array(
                                'type'     => $type,
                                'currency' => $this->findCurrency($map_['currency']),
                                'name'     => array($price_name),
                            );
                        }
                    }
                }
            }
        }

        $element = $this->element();
        $uuid = explode('#', self::field($element, 'Ид'));
        $product = $this->findProduct($uuid);
        if ($product->getId()) {

            $skus = $product->skus;
            $skus[-1] = array(
                'id_1c'     => end($uuid),
                'sku'       => self::field($element, 'Артикул'),
                'name'      => self::field($element, 'Наименование'),
                'available' => 1,
                'stock'     => array(),
            );

            $sku = &$skus[-1];

            if (mb_strtolower(self::attribute($element, 'Статус')) == 'удален') {
                $sku['available'] = false;
            }

            #get offer prices
            $prices = array(
                'price' => null,
            );

            foreach ($this->xpath($element, '//Цены/Цена') as $p) {
                $value = self::field($p, 'ЦенаЗаЕдиницу', 'doubleval');
                if ($k = self::field($p, 'Коэффициент', 'doubleval')) {
                    $value = $value / $k;
                }
                if ($currency = self::field($p, 'Валюта')) {
                    $currency = $this->findCurrency($currency);
                }

                $price_id = self::field($p, 'ИдТипаЦены');

                if ($price_info = ifset(self::$price_map[$price_id])) {
                    $prices[$price_info['type']] = array(
                        'value'    => $value,
                        'currency' => ifempty($currency, ifempty($price_info['currency'])),
                    );

                } elseif (empty($prices['price'])) {
                    $prices['price'] = array(
                        'value' => $value,
                    );
                    if (!empty($currency)) {
                        $prices['price']['currency'] = $currency;
                    }
                }
            }


            #setup primary currency
            if (!empty($prices['price']['currency'])
                && ($prices['price']['currency'] != $product->currency)
                && ($this->data['use_product_currency'])
            ) {
                $product->currency = $prices['price']['currency'];
            }

            #convert and setup prices
            foreach ($prices as $type => $price) {
                if (!empty($price['currency']) && ($price['currency'] != $product->currency)) {
                    $sku[$type] = $this->convertPrice($price['value'], $price["currency"], $product->currency);
                } else {
                    $sku[$type] = $price['value'];
                }
            }

            #read features
            $features = array();
            $params = array();
            $xpath = '//ХарактеристикиТовара/ХарактеристикаТовара';
            foreach ($this->xpath($element, $xpath) as $property) {
                $name = self::field($property, 'Наименование');
                switch ($name) {
                    case "Модель":
                        if ($sku_code = self::field($property, 'Значение')) {
                            $sku['sku'] = $sku_code;
                        }
                        break;
                    default:
                        $value = self::field($property, 'Значение');
                        $this->applyMapping($features, $params, $name, $value, null, $xpath);
                        break;
                }
            }

            if (!empty($features)) {
                $sku['features'] = $features;
            }

            if (empty($sku['sku']) && $product->sku_id && isset($skus[$product->sku_id])) {
                $sku['sku'] = $skus[$product->sku_id]['sku'];
            }

            # import stock counts
            $stock = false;
            if (isset($this->data['stock_map']) && !empty($this->data['stock_map'])) {
                $xpaths = array(
                    '//Склад',
                    '//ОстаткиПоСкладу',
                );
                foreach ($xpaths as $xpath) {
                    foreach ($this->xpath($element, $xpath) as $s) {
                        $stock_uuid = self::attribute($s, 'ИдСклада');
                        if ($stock_uuid && isset($this->data['stock_map'][$stock_uuid])) {
                            $stock_id = $this->data['stock_map'][$stock_uuid];
                            if (is_array($stock_id)) {
                                $stock_id = $stock_id['stock_id'];
                            }
                            if ($stock_id >= 0) {
                                $sku['stock'][$stock_id] = intval(self::attribute($s, 'КоличествоНаСкладе'));
                            }
                            $stock = true;
                        }
                    }
                }
            }

            if (!count($sku['stock']) && !$stock) {
                $sku['stock'][$this->data['stock_id']] = self::field($element, 'Количество', 'intval');
            } elseif (!empty($this->data['stock_complement'])) {
                $sku['stock'] += array_fill_keys($this->data['stock_complement'], 0);
            }

            unset($sku);

            #find & merge data
            $this->mergeSkus($skus);

            $delete_sku = false;
            if (count($uuid) > 1 && (end($uuid) != reset($uuid))) {
                $count_sku = 0;
                $dummy_id = false;
                foreach ($skus as $id => $sku) {
                    if ($sku['id_1c'] != $product['id_1c']) {
                        ++$count_sku;
                        if ($dummy_id) {
                            break;
                        }
                    } elseif (($sku['count'] === null) && (!$sku['price'])) {
                        $dummy_id = $id;
                        if ($count_sku) {
                            break;
                        }
                    }
                }

                if ($count_sku && ($dummy_id !== false)) {
                    $delete_sku = $skus[$dummy_id]['id'];
                    unset($skus[$dummy_id]);
                }
            }

            $this->fixSkuBasePriceSelectable($product, $skus);
            $product->skus = $skus;
            shopProductStocksLogModel::setContext(shopProductStocksLogModel::TYPE_IMPORT, 'Обмен через CommerceML');
            try {

                $product->save();

                if ($delete_sku) {
                    #remove empty default SKU
                    $this->getModel('productSkus')->delete($delete_sku);
                }

                ++$processed[self::STAGE_OFFER]['update'];
            } catch (waException $ex) {
                ++$processed[self::STAGE_OFFER]['skip'];
                $this->error(sprintf('Error during import product with Ид %s:%s', implode('#', $uuid), $ex->getMessage()));
            }
            shopProductStocksLogModel::clearContext();
        } else {
            ++$processed[self::STAGE_OFFER]['skip'];
            $this->error(sprintf('Product with Ид %s not found', implode('#', $uuid)));
        }

        unset($element);
        ++$current_stage[self::STAGE_OFFER];
        return true;
    }

    /**
     * @param $current_stage
     * @param $count
     * @param $processed
     * @return bool
     * @throws waException
     */
    private function stepImportOfferConfigure(&$current_stage, &$count, &$processed)
    {
        $element = $this->element();

        foreach ($this->xpath($element, '//ОстаткиПоСкладу') as $s) {
            $stock_uuid = self::field($s, 'ИдСклада');
            if (!isset($this->data['stock_map'][$stock_uuid])) {
                $this->data['stock_map'][$stock_uuid] = 0;
            }
        }
        foreach ($this->xpath($element, '//Склад') as $s) {
            $stock_uuid = self::field($s, 'ИдСклада');
            if (!isset($this->data['stock_map'][$stock_uuid])) {
                $this->data['stock_map'][$stock_uuid] = 0;
            }
        }

        if (false) {
            foreach ($this->xpath($element, '//Цены/Цена') as $p) {
                $value = self::field($p, 'ЦенаЗаЕдиницу', 'doubleval');
                if ($k = self::field($p, 'Коэффициент', 'doubleval')) {
                    $value = $value / $k;
                }
                if ($currency = self::field($p, 'Валюта')) {
                    $currency = $this->findCurrency($currency);
                }

                $price_id = self::field($p, 'ИдТипаЦены');

                if ($price_info = ifset(self::$price_map[$price_id])) {
                    $prices[$price_info['type']] = array(
                        'value'    => $value,
                        'currency' => ifempty($currency, ifempty($price_info['currency'])),
                    );

                } elseif (empty($prices['price'])) {
                    $prices['price'] = array(
                        'value' => $value,
                    );
                    if (!empty($currency)) {
                        $prices['price']['currency'] = $currency;
                    }
                }
            }
        }

        #read features
        $xpath = '//ХарактеристикиТовара/ХарактеристикаТовара';
        foreach ($this->xpath($element, $xpath) as $property) {
            $name = self::field($property, 'Наименование');
            switch ($name) {
                case "Модель":
                    if ($sku_code = self::field($property, 'Значение')) {
                        $sku['sku'] = $sku_code;
                    }
                    break;
                default:
                    $code = $this->findFeature($name);
                    $data = array(
                        'code' => $code,
                        'name' => $name,
                    );

                    $this->addFeatureMap($xpath, $name, $data);
                    break;
            }
        }

        unset($element);
        ++$current_stage[self::STAGE_OFFER];
        ++$processed[self::STAGE_OFFER]['analyze'];
        return true;
    }

    private function addFeatureMap($xpath, $name, $data)
    {
        $map = &$this->data['features_map'];
        $namespace = ifset(self::$feature_xpath_map[$xpath]['namespace']);
        if (!isset($map[$namespace])) {
            $map[$namespace] = array();
        }
        if (!isset($map[$namespace][$name])) {
            $map[$namespace][$name] = array();
        }

        $map[$namespace][$name] = array_merge($map[$namespace][$name], array_filter($data)) + $data;

        unset($map);
    }

    private function stepImportProductConfigure(&$current_stage, &$count, &$processed)
    {
        /**
         * xpath = /КоммерческаяИнформация/Каталог/Товары/Товар
         */
        $element = $this->element();
        $xpath = '//ХарактеристикиТовара/ХарактеристикаТовара';
        foreach ($this->xpath($element, $xpath) as $property) {
            $feature_name = self::field($property, 'Наименование');
            $code = $this->findFeature($feature_name);
            $data = array(
                'code' => $code,
                'name' => $feature_name,
            );
            $this->addFeatureMap($xpath, $feature_name, $data);
        }

        $xpath = '//ЗначенияСвойств/ЗначенияСвойства';
        foreach ($this->xpath($element, $xpath) as $property) {
            //Ид по Ид получать код фичи из карты или наименование
            //Значение | ИдЗначения - undocumented feature?
            $id = self::field($property, 'Ид');
            $feature = ifset($this->data['map'][self::STAGE_FEATURE][$id]);
            $feature_name = mb_strtolower($feature['name'], 'utf-8');

            switch ($feature_name) {
                case 'вид номенклатуры':
                case 'вид товара':
                    break;
                default:
                    if ($feature['name']) {
                        $code = $this->findFeature($feature['name'], $feature);

                        $data = array(
                            'code' => $code,
                            'name' => $feature['name'],
                        );

                        $this->addFeatureMap($xpath, $feature['name'], $data);

                    }
                    break;
            }
        }
        /**
         * xpath = ЗначенияРеквизитов/ЗначениеРеквизита Наименование/  = Значение/
         */

        $xpath = '//ЗначениеРеквизита';
        foreach ($this->xpath($element, $xpath) as $property) {
            $property_name = self::field($property, 'Наименование');
            switch ($property_name) {
                case "Полное наименование":
                case "ПолноеНаименование":
                case "НаименованиеПолное":
                case "Вес": //fixed feature
                case 'ОписаниеВФорматеHTML':
                case 'ВидНоменклатуры': // Товар/услуга или тип товаров
                case 'ОписаниеФайла':
                case '___':
                case 'ТипНоменклатуры':
                    //fields to ignore
                    break;
                default:
                    $code = $this->findFeature($property_name);

                    $data = array(
                        'code' => $code,
                        'name' => $property_name,
                    );

                    $this->addFeatureMap($xpath, $property_name, $data);
                    break;
            }
        }

        ++$current_stage[self::STAGE_PRODUCT];
        ++$processed[self::STAGE_PRODUCT]['analyze'];
        unset($element);
        return true;
    }

    private function completeImportProductConfigure()
    {
        $this->trace($this->data['features_map']);
    }

    private function applyMapping(&$features, &$params, $name, $value, $data, $xpath)
    {
        $result = false;

        $target = null;
        $namespace = ifset(self::$feature_xpath_map[$xpath]['namespace']);
        $field = ifset(self::$feature_xpath_map[$xpath]['field']);
        $default = ifset(self::$feature_namespace_map[$namespace]['default']);
        if (is_array($data) && isset($data[$field])) {
            $namespace_key = $data[$field];
        } else {
            $namespace_key = $name;
        }

        if ($namespace && isset($this->data['features_map'][$namespace][$namespace_key])) {
            if (!empty($this->data['features_map'][$namespace][$namespace_key]['target'])) {
                $target = $this->data['features_map'][$namespace][$namespace_key]['target'];
            }
        }

        if (empty($target)) {
            switch ($default) {
                case 'add':
                    if ($code = $this->findFeature($name, $data, $xpath)) {
                        $target = sprintf('f:%s', $code);
                    }
                    break;
                case 'skip':
                    //optional log it
                    break;
            }

        }

        if ($target) {
            $code = null;
            $field = 's';
            $dimension = null;

            if ($data && isset($data['values'][$value])) {
                if (true || shopCml1cPlugin::isGuid($value)) {
                    //TODO add optional settings (strip_tags/etc)
                    $value = $data['values'][$value];
                }
            }

            if (preg_match('@^(f|p|s):([^:]+)(:(.+))?$@', $target, $matches)) {
                $field = $matches[1];
                $code = $matches[2];
                $dimension = ifset($matches[4]);
            } elseif (preg_match('@^([^:]+):(.+)$@', $target, $matches)) {
                $code = $matches[1];
                $dimension = ifset($matches[2]);
            }

            if ($dimension && !preg_match('@\d\s+\w+@', $value)) {
                $value = doubleval($value).' '.$dimension;
            }
            if ($code) {
                switch ($field) {
                    case 's':
                        $result = true;
                        //it's skip
                        break;
                    case 'f':
                        $features[$code] = $value;
                        $result = 'f';
                        break;
                    case 'p':
                        $params[$code] = $value;
                        $result = 'p';
                        break;
                }
            }
        }
        return $result;
    }

    /**
     * @param $current_stage
     * @param $count
     * @param $processed
     * @return bool
     */
    private function stepImportProduct(&$current_stage, &$count, &$processed)
    {
        /**
         * xpath = /КоммерческаяИнформация/Каталог/Товары/Товар
         */
        $element = $this->element();
        $uuid = explode('#', self::field($element, 'Ид'));

        $subject = ((count($uuid) < 2) || (reset($uuid) == end($uuid))) ? self::STAGE_PRODUCT : self::STAGE_SKU;

        $product = $this->findProduct($uuid);

        $product['id_1c'] = reset($uuid);

        if (!isset($this->data['map'][self::STAGE_PRODUCT])) {
            $this->data['map'][self::STAGE_PRODUCT] = array();
        }

        $update_fields = array(
            'summary'     => null,
            'description' => null,
            'name'        => self::field($element, 'Наименование'),
            'tax_id'      => null,
            'type_id'     => null,
        );

        #fill product features data

        $features = array();
        $params = array();
        $xpath = '//ХарактеристикиТовара/ХарактеристикаТовара';
        foreach ($this->xpath($element, $xpath) as $property) {
            //Ид
            $feature_name = self::field($property, 'Наименование');
            $value = self::field($property, 'Значение');
            if (!$this->applyMapping($features, $params, $feature_name, $value, null, $xpath)) {
                $this->error(sprintf('Feature %s not found', $feature_name));
            }
        }

        if (true) {
            $xpath = '//ЗначенияСвойств/ЗначенияСвойства';
            foreach ($this->xpath($element, $xpath) as $property) {
                //Ид по Ид получать код фичи из карты или наименование
                //Значение | ИдЗначения - undocumented feature?
                $id = self::field($property, 'Ид');
                $feature = ifset($this->data['map'][self::STAGE_FEATURE][$id]);
                $value = self::field($property, 'Значение');

                switch (mb_strtolower($feature['name'], 'utf-8')) {
                    case 'вид номенклатуры':
                    case 'вид товара':
                        //значение из справочника "номенклатурные группы"
                        $update_fields['type_id'] = $this->findType($value, $product);
                        break;
                    default:
                        if ($feature['name']) {
                            $this->applyMapping($features, $params, $feature['name'], $value, $feature, $xpath);
                        }
                        break;
                }
            }
        }

        /**
         *
         * Extend product categories
         */
        #init category map alias
        if (!isset($this->data['map'][self::STAGE_CATEGORY])) {
            $this->data['map'][self::STAGE_CATEGORY] = array();
        }
        $map = $this->data['map'][self::STAGE_CATEGORY];

        switch ($this->pluginSettings('update_product_categories')) {
            case 'skip':#Импорт категорий будет пропущен
                $categories = null;
                break;
            case 'none':#Только для новых товаров
                $categories = $product->getId() ? null : array();
                break;
            case 'sync':#Добавлять в новые и удалять из устаревших
                $categories = array();
                break;
            case 'update':#Только добавлять товар в новые категории
            default:
                $categories = array_keys($product->categories);
                break;

        }
        if (is_array($categories)) {
            foreach ($this->xpath($element, '//Группы/Ид') as $category) {
                if ($category = ifset($map[(string)$category])) {
                    $categories[] = intval($category['id']);
                }
            }
            $product->categories = $categories;
        }

        $update_fields['summary'] = self::field($element, 'Описание');
        $update_fields['description'] = nl2br($update_fields['summary']);
        if ($this->pluginSettings('description_is_html')) {
            $update_fields['summary'] = htmlspecialchars($update_fields['summary'], ENT_NOQUOTES, 'utf-8');
        }
        $image_descriptions = array();

        /**
         * xpath = ЗначенияРеквизитов/ЗначениеРеквизита Наименование/  = Значение/
         */

        $xpath = '//ЗначениеРеквизита';
        foreach ($this->xpath($element, $xpath) as $property) {
            $property_name = self::field($property, 'Наименование');
            switch ($property_name) {
                case "Полное наименование":
                case "ПолноеНаименование":
                case "НаименованиеПолное":
                    if ($value = self::field($property, 'Значение')) {
                        $update_fields['summary'] = $value;
                    }
                    break;
                case "Вес":
                    if ($value = self::field($property, 'Значение', 'doubleval')) {
                        $features['weight'] = $value.' '.$this->pluginSettings('weight_unit');
                    }
                    break;
                case 'ОписаниеВФорматеHTML':
                    if ($value = self::field($property, 'Значение')) {
                        $update_fields['description'] = $value;
                    }
                    break;
                case 'ВидНоменклатуры': // Товар/услуга или тип товаров
                    $value = self::field($property, 'Значение');
                    if (!in_array($value, array('Товар', 'Услуга'))) {
                        $update_fields['type_id'] = $this->findType($value, $product);
                    }
                    break;
                case 'ОписаниеФайла':
                    //Описание изображения *
                    $value = self::field($property, 'Значение');
                    if (strpos($value, '#')) {
                        list($image, $description) = explode('#', $value, 2);
                        $image_descriptions[$image] = $description;
                    }
                    break;
                case '___':
                case 'ТипНоменклатуры':
                    //fields to ignore
                    break;
                default:

                    if (false) {
                        $value = self::field($property, 'Значение');
                        $this->applyMapping($features, $params, $property_name, $value, null, $xpath);
                    }
                    break;
            }
        }

        $taxes = array();
        foreach ($this->xpath($element, '//СтавкиНалогов/СтавкаНалога') as $tax) {
            $taxes[] = $this->findTax(
                array(
                    'name' => self::field($tax, 'Наименование'),
                    'rate' => self::field($tax, 'Ставка', 'doubleval'),
                )
            );
        }
        if (($tax = reset($taxes)) && !empty($tax['id'])) {
            $update_fields['tax_id'] = $tax['id'];
        }

        if ($code = $this->pluginSettings('base_unit')) {
            if ($xml_value = self::field($element, 'БазоваяЕдиница', 'xml')) {
                /**
                 * @var SimpleXMLElement $xml_value
                 */

                $value = self::attribute($xml_value, 'НаименованиеПолное');
                if (empty($value)) {
                    $value = (string)$xml_value;
                }

                if (!empty($value)) {
                    $features[$code] = $value;
                }
            }
        }
        $sku_features = array();
        if ($features) {
            foreach ($this->getFeatureRelation(array_keys($features)) as $code) {
                $sku_features[$code] = $features[$code];
                unset($features[$code]);
            }
        }


        //TODO f: ignore|extend|override
        $skus = $product->skus;
        $name = $update_fields['name'];
        if ((count($skus) > 1) && count($sku_features)) {
            $name .= ' ('.implode(', ', $sku_features).')';
        }

        $skus[-1] = array(
            'sku'       => self::field($element, 'Артикул'),
            'name'      => $name,
            'available' => 1,
            'id_1c'     => end($uuid),
        );

        $target = 'update';
        if (!$product->getId()) {
            # update name/summary/description only for new items
            $product->status = ($this->pluginSettings('product_hide')) ? 0 : 1;
            if (mb_strtolower(self::attribute($element, 'Статус')) == 'удален') {
                if ($subject == self::STAGE_PRODUCT) {
                    $product->status = 0;
                } else {
                    $skus[-1]['available'] = false;
                }
            }

            $target = 'new';
            $product->name = $update_fields['name'];
            $product->url = shopHelper::transliterate($product->name);

            foreach ($update_fields as $field => $value) {
                if (!empty($value)) {
                    $product->{$field} = $value;
                }
            }


            if ($sku_features) {
                $skus[-1]['features'] = $sku_features;
            }

            if ($features) {
                $product->features = $features;
            }

            if ($params) {
                $product->params = $params;
            }
        } else {
            if (mb_strtolower(self::attribute($element, 'Статус')) == 'удален') {
                if ($subject == self::STAGE_PRODUCT) {
                    $product->status = 0;
                } else {
                    $skus[-1]['available'] = false;
                }
            }
            foreach ($update_fields as $field => $value) {
                if (!empty($value) && !empty($this->data['update_product_fields'][$field])) {
                    $product->{$field} = $value;
                }
            }

            if (!empty($this->data['update_product_fields']['features'])) {
                if ($features) {
                    $product->features = $features;
                }
                if ($sku_features) {
                    if (count($uuid) > 1) {
                        $skus[-1]['features'] = $sku_features;
                    } else {
                        /* ignore empty SKU for exists products */
                        unset($skus[-1]);
                        $sku_features = array();
                    }
                }
            } else {
                $features = array();
                $sku_features = array();
            }

            if ($params && !empty($this->data['update_product_fields']['params'])) {
                $product->params = array_merge($product->params, $params);
            }
        }


        $this->mergeSkus($skus);

        $product->skus = $skus;
        try {
            $product->save();

            if (!empty($features) || !empty($sku_features)) {
                $this->setFeatureType(array_merge(array_keys($features), array_keys($sku_features)), $product->type_id);
            }

            #add product images tasks
            foreach ($this->xpath($element, '//Картинка') as $image) {
                $this->data['map'][self::STAGE_IMAGE][] = array(
                    (string)$image,
                    $product->getId(),
                    ifset($image_descriptions[(string)$image]),
                );
                if (!isset($count[self::STAGE_IMAGE])) {
                    $count[self::STAGE_IMAGE] = 0;
                }
                ++$count[self::STAGE_IMAGE];
            }
        } catch (waException $ex) {
            $this->error(sprintf('Error during import product with Ид %s:%s', implode('#', $uuid), $ex->getMessage()));
            $target = 'skip';
        }
        ++$processed[$subject][$target];

        ++$current_stage[self::STAGE_PRODUCT];
        unset($product);
        return true;
    }

    private function completeImportProduct()
    {
        //add new features into related product types
        if (!empty($this->data['new_features'])) {
            $feature_types_model = $this->getModel('typeFeatures');
            /**
             * @var shopTypeFeaturesModel $feature_types_model
             */
            foreach ($this->data['new_features'] as $feature) {
                if (!empty($feature['types'])) {
                    $feature_types_model->updateByFeature($feature['id'], $feature['types'], false);
                }
            }
        }
        $this->fixSkuName();
    }

    private function completeImportOffer()
    {
        $this->fixSkuName();
    }

    private function stepImportImage(&$current_stage, &$count, &$processed)
    {

        $result = false;
        if (!is_array($this->data['map'][self::STAGE_IMAGE]) && $this->data['map'][self::STAGE_IMAGE]) {
            $this->data['map'][self::STAGE_IMAGE] = array($this->data['map'][self::STAGE_IMAGE]);
        }

        if ($image = reset($this->data['map'][self::STAGE_IMAGE])) {
            $result = true;
            list($file, $product_id, $description) = $image;
            if (!empty($this->data['base_path'])) {
                $file = $this->data['base_path'].$file;
            }
            if ($file) {
                try {
                    $file = $this->extract($file);
                } catch (waException $ex) {
                    $this->error(sprintf("Ошибка при получении файла изображения: ", $ex->getMessage()));
                    $file = false;
                }
            }

            if ($file && is_file($file)) {
                $model = $this->getModel('productImages');
                /**
                 * @var shopProductImagesModel $model
                 */

                try {
                    $target = 'new';
                    $name = basename($file);

                    if ($image = new waImage($file)) {

                        $data = array(
                            'product_id'        => $product_id,
                            'description'       => $description,
                            'upload_datetime'   => date('Y-m-d H:i:s'),
                            'width'             => $image->width,
                            'height'            => $image->height,
                            'size'              => filesize($file),
                            'original_filename' => $name,
                            'ext'               => pathinfo($file, PATHINFO_EXTENSION),
                        );
                        $search = array(
                            'product_id'        => $product_id,
                            'original_filename' => $name,
                            'ext'               => pathinfo($file, PATHINFO_EXTENSION),
                        );
                        if ($exists = $model->getByField($search)) {
                            $data = array_merge($exists, $data);
                            $thumb_dir = shopImage::getThumbsPath($data);
                            $back_thumb_dir = preg_replace('@(/$|$)@', '.back$1', $thumb_dir, 1);
                            $paths[] = $back_thumb_dir;
                            waFiles::delete($back_thumb_dir); // old backups
                            if (!(file_exists($thumb_dir) && waFiles::move($thumb_dir, $back_thumb_dir) || waFiles::delete($back_thumb_dir)) && !waFiles::delete($thumb_dir)) {
                                throw new waException(_w("Error while rebuild thumbnails"));
                            }
                        }

                        if (empty($data['id'])) {
                            $image_id = $data['id'] = $model->add($data);
                        } else {
                            $image_id = $data['id'];
                            $target = 'update';
                            $model->updateById($image_id, $data);
                        }

                        if (!$image_id) {
                            throw new waException("Database error");
                        }

                        $image_path = shopImage::getPath($data);
                        if ((file_exists($image_path) && !is_writable($image_path)) || (!file_exists($image_path) && !waFiles::create($image_path))) {
                            $model->deleteById($image_id);
                            throw new waException(
                                sprintf("The insufficient file write permissions for the %s folder.", substr($image_path, strlen($this->getConfig()->getRootPath())))
                            );
                        }

                        waFiles::copy($file, $image_path);
                        $result = true;

                        $processed[self::STAGE_IMAGE][$target]++;
                        if (false) {
                            $config = wa('shop')->getConfig();
                            /**
                             * @var shopConfig $config
                             */
                            shopImage::generateThumbs($data, $config->getImageSizes());
                        }
                    } else {
                        $this->error(sprintf('Invalid image file %s', $file));
                    }
                } catch (waException $e) {
                    $this->error($e->getMessage());
                }
                if ($file) {
                    waFiles::delete($file);
                }
            } else {
                $target = 'skip';
                $processed[self::STAGE_IMAGE][$target]++;
            }
            array_shift($this->data['map'][self::STAGE_IMAGE]);
            ++$current_stage[self::STAGE_IMAGE];
        } else {
            $current_stage[self::STAGE_IMAGE] = $count[self::STAGE_IMAGE];
        }
        return $result;
    }

    private function mergeSkus(&$skus)
    {
        static $update_fields = null;
        if ($update_fields === null) {
            $update_fields = (array)$this->pluginSettings('update_product_fields');
        }
        //XXX update base price
        foreach ($skus as $id => & $sku) {
            if (($id > 0) && !count($sku['stock']) && ($sku['count'] !== null)) {
                $sku['stock'][0] = $sku['count'];
            }
            if (($id > 0) && isset($skus[-1]) && ($sku['id_1c'] == $skus[-1]['id_1c'])) {
                if (in_array($skus[-1]['available'], array(false, true), true)) {
                    $skus[-1]['available'] = intval($skus[-1]['available']);
                } else {
                    unset($skus[-1]['available']);
                }
                if (empty($update_fields['sku_name'])) {
                    unset($skus[-1]['name']);
                }
                if (empty($update_fields['sku'])) {
                    unset($skus[-1]['sku']);
                }
                if (isset($skus[-1]['features']) && empty($update_fields['features'])) {
                    unset($skus[-1]['features']);
                }
                $sku = array_merge($sku, $skus[-1]);
                $sku['virtual'] = 0;
                unset($skus[-1]);
            }
            unset($sku);
        }

        if (isset($skus[-1])) {
            if (!isset($skus[-1]['stock'])) {
                $skus[-1]['stock'] = array();
            }
            if (!empty($this->data['stock_setup'])) {
                $skus[-1]['stock'] += array_fill_keys($this->data['stock_setup'], 0);
            }
        }
        return $skus;
    }


    /**
     * @param string $filename
     * @param string $target
     * @return bool|string
     * @throws waException
     */
    protected function extract($filename, $target = null)
    {
        if (empty($target)) {
            $target = $this->plugin()->path($filename);
        }
        $result = false;
        if (empty($this->data['zipfile'])) {
            if (file_exists($target)) {
                $result = $target;
            } else {
                throw new waException(sprintf("Файл %s не найден.", $filename));
            }
        } elseif (!empty($this->data['zipfile'])) {
            if (function_exists('zip_open')
                && function_exists('iconv')
                && ($zip = zip_open($this->data['zipfile']))
                && is_resource($zip)
            ) {
                while ($zip_entry = zip_read($zip)) {
                    if ($filename == iconv('CP866', 'UTF-8', zip_entry_name($zip_entry))) {
                        $exists = file_exists($target);
                        if ($exists) {
                            if (zip_entry_filesize($zip_entry) != filesize($target)) {
                                $exists = false;
                                waFiles::delete($target);
                            }
                        }
                        if ($exists) {
                            $result = $target;
                        } else {
                            if ($z = fopen($target, "wb")) {
                                $zip_fs = zip_entry_filesize($zip_entry);
                                $size = 0;
                                while ($zz = zip_entry_read($zip_entry, $size_ = max(0, min(4096, $zip_fs - $size)))) {
                                    fwrite($z, $zz);
                                    $size += $size_;
                                }
                                fclose($z);
                                zip_entry_close($zip_entry);
                                $result = $target;
                                break;
                            } else {
                                zip_entry_close($zip_entry);
                                zip_close($zip);
                                throw new waException("Ошибка извлечения файла из архива.");
                            }
                        }

                        if (preg_match('@[\\/]@', $filename)) {
                            $this->data['files'][] = dirname($result).'/';
                            $this->data['files'] = array_unique($this->data['files']);
                        } else {
                            $this->data['files'] [] = $result;
                        }
                        break;
                    }
                }
                zip_close($zip);
                if (!$result) {
                    throw new waException(sprintf("Файл %s не найден в архиве.", $filename));
                }

            } else {
                $hint = '';
                if (!function_exists('iconv')) {
                    $hint .= ' Требуется наличие PHP расширения iconv;';
                }
                if (!function_exists('zip_open')) {
                    $hint .= ' Требуется наличие PHP расширения zlib;';
                }
                throw new waException("Ошибка чтения архива.".$hint);
            }
        }

        return $result;
    }

    private function write()
    {
        if (!$this->fp) {
            $this->fp = fopen($this->data['filename'], empty($this->data['fsize']) ? 'wb' : 'ab');
            if (!empty($this->data['fsize'])) {
                ftruncate($this->fp, $this->data['fsize']);
                fseek($this->fp, $this->data['fsize']);
            }
        }

        $size = fwrite($this->fp, $this->writer->flush());
        $this->data['fsize'] = ftell($this->fp);
        $this->data['memory'] = max(memory_get_peak_usage(true), ifset($this->data['memory'], 0));
        return $size;
    }

    private function writeOwner()
    {
        $config = $this->getConfig();
        /**
         * @var shopConfig $config
         */
        $name = $config->getGeneralSettings('name');
        $this->writer->startElement('Владелец');
        $this->writer->writeElement('Ид', self::UUID_OWNER);
        $this->writer->writeElement('ПолноеНаименование', $name);
        $this->writer->writeElement('Наименование', $name);
        $this->writer->endElement(/*Владелец*/);
    }

    private function writeCategory($category, &$level)
    {
        static $current = null;
        if ($category) {
            $level = $category['depth'];
        }
        $first = false;
        if ($current === null) {
            $current = $level;
            $first = true;
        }

        if ($level > $current) {
            $this->writer->startElement('Группы');
        } elseif (!$first) {
            $this->writer->endElement(/*Группа*/);
            while ($level < $current--) {
                $this->writer->endElement(/*Группы*/);
                $this->writer->endElement(/*Группа*/);
            }
        }
        $current = $level;
        if ($category) {
            $this->writer->startElement('Группа');
            $this->writer->writeElement('Ид', $category['id_1c']);
            $this->writer->writeElement('Наименование', $category['name']);
            if (!empty($category['parent_id_1c'])) {
                $this->writer->writeElement('Родитель', $category['parent_id_1c']);
            }
        }
    }

    private function writeProduct($product, $sku)
    {
        $uuid = ($product['id_1c'] != $sku['id_1c']) ? $product['id_1c'].'#'.$sku['id_1c'] : $sku['id_1c'];
        $group = false;
        if (!empty($product['category_id']) && isset($this->data['map'][self::STAGE_CATEGORY][$product['category_id']])) {
            $group = $this->data['map'][self::STAGE_CATEGORY][$product['category_id']];

        }

        $w = &$this->writer;
        $w->startElement('Товар');
        $w->writeElement('Ид', $uuid);
        $w->writeElement('Артикул', $sku['sku']);

        if ($group) {
            $w->startElement('Группы');
            foreach ((array)$group as $id) {
                $w->writeElement('Ид', $id);
            }
            $w->endElement(/*Группы*/);
        }

        $this->writeName($product, $sku);
        $this->writeUnit();
        $w->writeElement('Описание', $product["description"]);

        $this->writeProperties(
            array(
                'ВидНоменклатуры' => 'Товар',
                'ТипНоменклатуры' => 'Товар',
            )
        );
        $w->endElement(/*Товар*/);
    }

    private function writeName($product, $sku)
    {

        switch ($this->data['export_product_name']) {
            case 'brackets':
                if (strlen($sku['name']) && ($sku['name'] != $product['name'])) {
                    $name = sprintf('%s (%s)', $product['name'], $sku['name']);
                } else {
                    $name = $product['name'];
                }
                break;
            case 'name':
            default:
                $name = $product['name'];
                break;
        }

        $this->writer->writeElement('Наименование', $name);
    }

    /**
     * @param shopProduct $product
     * @param $sku
     */
    private function writeOffer($product, $sku)
    {
        $w = &$this->writer;
        $w->startElement('Предложение');
        if ($product['id_1c'] != $sku['id_1c']) {
            $uuid = $product['id_1c'].'#'.$sku['id_1c'];
        } else {
            $uuid = $sku['id_1c'];
        }
        $w->writeElement('Ид', $uuid);

        $this->writeName($product, $sku);
        $this->writeUnit();
        $prices = array(
            $this->data['price_type_uuid'] => $sku['price'],
        );

        if ($sku['purchase_price'] && !empty($this->data['purchase_price_type_uuid'])) {
            $prices[$this->data['purchase_price_type_uuid']] = $sku['purchase_price'];
        }
        $this->writePrices($prices, $product->currency);
        if ($this->data['stock_id']) {
            $w->writeElement('Количество', ifset($sku['stock'][$this->data['stock_id']]));
        } else {
            $w->writeElement('Количество', $sku['count']);
        }

        $w->endElement(/*Предложение*/);
    }

    private function writeUnit()
    {
        $this->writer->startElement('БазоваяЕдиница');
        $this->writer->writeAttribute('Код', 796);
        $this->writer->startAttribute('НаименованиеПолное');
        $this->writer->text('Штука');
        $this->writer->endAttribute();
        $this->writer->writeAttribute('МеждународноеСокращение', 'PCE');
        $this->writer->text('шт');
        $this->writer->endElement(/*БазоваяЕдиница*/);
    }

    private function writePrices($prices, $currency)
    {
        $this->writer->startElement('Цены');
        foreach ($prices as $uuid => $price) {
            $this->writer->startElement('Цена');
            $this->writer->writeElement('ИдТипаЦены', $uuid);
            $this->writer->writeElement('ЦенаЗаЕдиницу', $this->price($price));
            $this->writer->writeElement('Валюта', $this->currency($currency));
            $this->writer->writeElement('Единица', 'шт');
            $this->writer->writeElement('Коэффициент', 1);
            $this->writer->endElement(/*Цена*/);
        }
        $this->writer->endElement(/*Цены*/);
    }

    /**
     * @param $field
     * @param $contact
     * @param waContact $c
     * @return null|string
     */
    private function getContactField($field, $contact, $c = null)
    {
        $value = null;
        if (($field !== null) && isset($contact[$field]) && (trim($contact[$field]) !== '')) {
            $value = trim($contact[$field]);
        } else {
            $value = $c->get($field);
        }
        return $value;
    }

    private function error($message)
    {
        $path = wa()->getConfig()->getPath('log');
        waFiles::create($path.'/shop/plugins/cml1c/error.log');
        waLog::log($message, 'shop/plugins/cml1c/error.log');
        $this->data['error'] = $message;
    }

    private function trace()
    {
        if ($this->debug) {
            $args = func_get_args();
            foreach ($args as &$arg) {
                if (is_array($arg)) {
                    $arg = var_export($arg, true);
                }
                unset($arg);
            }
            $path = wa()->getConfig()->getPath('log');
            waFiles::create($path.'/shop/plugins/cml1c/debug.log');
            waLog::log(implode("\t", $args), 'shop/plugins/cml1c/debug.log');
        }
    }

    private function price($price, $precision = 2)
    {
        return number_format(floatval($price), $precision, '.', '');
    }

    private function initCurrency()
    {

    }

    /**
     * Get currency code ISO 4217
     * @param string $currency
     * @return string
     */
    private function currency($currency = null)
    {
        static $default_currency;
        static $plugin_currency;
        static $replace;
        if (empty($currency)) {
            if (!$default_currency) {
                /**
                 * @var shopConfig $config
                 */
                $config = $this->getConfig();
                $default_currency = $config->getCurrency();
            }
            $currency = $default_currency;
        }
        if (empty($plugin_currency)) {
            $plugin_currency = $this->pluginSettings('currency');
            $replace = $this->pluginSettings('currency_map');
        }
        if ($replace && ($plugin_currency == $currency)) {
            $currency = $replace;
        }

        return $currency;
    }

    private function findCurrency($currency)
    {
        static $map;
        if (!$map) {
            $map = array(
                'ГРН.' => 'UAH',
                'ГРН'  => 'UAH',
                '980'  => 'UAH',
                'РУБ'  => 'RUB',
                'РУБ.' => 'RUB',
                'RUR'  => 'RUB',
                '643'  => 'RUB',
                '810'  => 'RUB',
            );
            $key = mb_strtoupper($this->pluginSettings('currency_map'), 'UTF-8');
            $map[$key] = $this->pluginSettings('currency');
        }
        $currency = mb_strtoupper($currency, 'UTF-8');
        if (isset($map[$currency])) {
            $currency = $map[$currency];
        }
        return $currency;
    }

    private function fixSkuName()
    {
        $model = $this->getModel('productSkus');
        /**
         * @var shopProductSkusModel $model
         */
        //unset sku name where it is same
        $sql = <<<SQL
SELECT p.id id
FROM shop_product p
JOIN shop_product_skus s ON (s.product_id=p.id)
WHERE
  p.id_1c
  AND
  (p.sku_count=1)
  AND
  (p.name != '')
  AND
  (p.name = s.name)
SQL;

        if ($duplicates = $model->query($sql)->fetchAll('id')) {
            $model->updateByField('product_id', array_keys($duplicates), array('name' => ''));
        }
    }

    /**
     * @param shopProduct $p
     * @param array $skus
     */
    private function fixSkuBasePriceSelectable(&$p, &$skus)
    {
        if (true || ($p->type == shopProductModel::SKU_TYPE_SELECTABLE)) {
            $changed = false;
            if (isset($p->skus[$p->sku_id]) && isset($skus[$p->sku_id])) {
                $exists_sku = $p->skus[$p->sku_id];
                $sku = $skus[$p->sku_id];
                if (!empty($sku['price'])
                    && ($exists_sku['price'] == $p->base_price_selectable) && ($exists_sku['price'] != $sku['price'])
                ) {
                    $p->base_price_selectable = $sku['price'];
                    $changed = true;
                }

                if (!empty($sku['purchase_price'])
                    && ($exists_sku['purchase_price'] == $p->purchase_price_selectable)
                    && ($exists_sku['purchase_price'] != $sku['purchase_price'])
                ) {
                    $p->purchase_price_selectable = $sku['purchase_price'];
                    $changed = true;
                }

                if (!empty($sku['compare_price']) && ($exists_sku['compare_price'] == $p->compare_price_selectable) && ($exists_sku['compare_price'] != $sku['compare_price'])) {
                    $p->compare_price_selectable = $sku['compare_price'];
                    $changed = true;
                }

            }
            if ($changed) {
                foreach ($skus as &$sku) {
                    if (!empty($sku['virtual'])) {

                        $sku['price'] = $p->base_price_selectable;
                        $sku['purchase_price'] = $p->purchase_price_selectable;
                        $sku['compare_price'] = $p->compare_price_selectable;
                    }
                    unset($sku);
                }
            }
        }
    }

    public function __destruct()
    {
        if ($this->reader) {
            $this->reader->close();
        }
    }
}
