<?php

/**
 * Class shopCml1cPluginBackendRunController
 */
class shopCml1cPluginBackendRunController extends waLongActionController
{
    private $debug = false;

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
        "КоммерческаяИнформация/Классификатор/Группы"                   => self::STAGE_CATEGORY,
        "КоммерческаяИнформация/Классификатор/Свойства"                 => self::STAGE_FEATURE,
        "КоммерческаяИнформация/Классификатор/ТипыЦен"                  => self::STAGE_PRICE,
        "КоммерческаяИнформация/Классификатор/Склады"                   => self::STAGE_STOCK,
        "КоммерческаяИнформация/Каталог/Товары"                         => self::STAGE_PRODUCT,
        //Импорт предложений (offers.xml)
        "КоммерческаяИнформация/ПакетПредложений/ТипыЦен"               => self::STAGE_PRICE,
        "КоммерческаяИнформация/ПакетПредложений/Склады"                => self::STAGE_STOCK,
        "КоммерческаяИнформация/ПакетПредложений/Предложения"           => self::STAGE_OFFER,
        // экспериментальная опция
        "КоммерческаяИнформация/ИзмененияПакетаПредложений/Предложения" => self::STAGE_OFFER,
        "КоммерческаяИнформация/ПакетПредложений/Свойства"              => self::STAGE_FEATURE,
        //Импорт заказов - поддержка не планируется
        "КоммерческаяИнформация/Документ-"                              => self::STAGE_ORDER,
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
            'name'        => 'Реквизиты товаров — блок <ЗначениеРеквизита>',
            'description' => 'Реквизиты сопоставляются для синхронизации по наименованию реквизита (элемент <Наименование> блока <ЗначениеРеквизита> в файле CommerceML).
Характеристики артикулов (модификаций) будут импортированы, только если они заданы в Shop-Script как характеристики с форматом «Выбор нескольких значений из списка».',
            'field'       => 'code',
            'default'     => 'skip',
        ),
        'feature' => array(
            'name'        => 'Характеристики товаров — блок <ХарактеристикиТовара>',
            'description' => 'Характеристики сопоставляются для синхронизации по наименованию характеристики (элемент <Наименование> блока <ХарактеристикаТовара> в файле CommerceML).
Характеристики артикулов (модификаций) будут импортированы, только если они заданы в Shop-Script как характеристики с форматом «Выбор нескольких значений из списка».',
            'field'       => 'code',
            'default'     => 'add',
        ),
        'uuid'    => array(
            'name'        => 'Справочник свойств товаров — блок <Классификатор>',
            'description' => 'Свойства сопоставляются для синхронизации по идентификатору (элемент <Ид> блока <Свойство> в файле CommerceML).
Характеристики артикулов (модификаций) будут импортированы, только если они заданы в Shop-Script как характеристики с форматом «Выбор нескольких значений из списка».',
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

    public function execute()
    {
        try {
            parent::execute();
        } catch (waException $ex) {
            if (wa()->getEnv() == 'backend') {
                $options = 0;
                if (defined('JSON_UNESCAPED_UNICODE')) {
                    $options |= JSON_UNESCAPED_UNICODE;
                }
                if ($ex->getCode() == '302') {
                    $data = array('warning' => $ex->getMessage());
                } else {
                    $data = array('error' => $ex->getMessage());

                }

                if ($options) {
                    echo json_encode($data, $options);
                } else {
                    echo json_encode($data);
                }
            }
        }
    }


    public function fastExecute(&$id = null)
    {
        $result = null;
        try {
            ob_start();
            $this->_processId = $id;
            $this->init();
            $is_done = $this->isDone();

            if (empty($_POST['processId'])) {
                $_POST['processId'] = $this->processId;
                $this->getStorage()->set('processId', $_POST['processId']);
            }

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
                $this->error(sprintf("Error occurred during execute: %s", $out));
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
        $log = null;
        if ($value !== null) {
            $settings = $this->plugin()->getSettings();
            $settings[$name] = $value;
            $this->plugin()->saveSettings($settings);

            $log = array($name => $value);
        } elseif (is_array($name)) {
            $settings = $name;
            $settings += $this->plugin()->getSettings();
            $this->plugin()->saveSettings($settings);

            $log = $name;
        } else {
            return $this->plugin()->getSettings($name);
        }

        if ($log !== null) {
            $message = sprintf('Settings was changed during [%s]: %s', $this->processId, var_export($log, true));
            waLog::log($message, 'shop/plugins/cml1c/settings.log');
        }
        return $settings;
    }

    /**
     * @uses shopCml1cPluginBackendRunController::initExport()
     * @uses shopCml1cPluginBackendRunController::initImport()
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

            $this->data['encoding'] = $this->pluginSettings('encoding'); // 'windows-1251'/'utf-8'
            //validation option — since 2.07 enable extended stock features (not supported yet)
            $this->data['version'] = '2.05';
            $this->data['timestamp'] = time();
            $this->data['direction'] = waRequest::post('direction', 'import');
            $this->data['types'] = array_keys($type_model->getTypes());
            $this->data['map'] = array();
            $this->data['error'] = null;
            $this->data['last_error'] = null;
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
            $this->data['fractional'] = class_exists('shopLicensing');
            $this->data['units'] = [];
            $this->data['stage'] = reset($stages);
            $this->data['error'] = null;
            $this->data['stage_name'] = $this->getStageName($this->data['stage']);
            $this->data['memory'] = memory_get_peak_usage();
            $this->data['memory_avg'] = memory_get_usage();
        } catch (waException $ex) {
            $this->error($ex->getMessage());
            if (class_exists('shopCml1cPluginRunTest', false)) {
                throw $ex;
            } else {
                echo json_encode(array('error' => $ex->getMessage(),));
            }
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
            $module = waRequest::param('module', 'backend');
            waRequest::setParam(compact('module'));
            $hash = '';
            $options = array(
                'frontend' => false,
            );
            $this->collection = new shopProductsCollection($hash, $options);
        }

        return $this->collection;
    }

    private function getProducts($offset, $limit = 50)
    {
        return $this->getCollection()->getProducts('*', $offset, $limit, false);
    }

    /**
     * @param string[]    $code
     * @param shopProduct $product
     * @return mixed
     * @throws waException
     */
    private function getFeatureRelation($code, $product = null)
    {
        static $feature_relation = array();
        if ($product) {

            $related = array();
            if ($code) {
                /** @var shopProductFeaturesSelectableModel $model */
                $model = $this->getModel('product_features_selectable');

                if ($product->id && ($product->sku_type == shopProductModel::SKU_TYPE_SELECTABLE)) {
                    $selected = $model->getProductFeatureIds($product->id);
                    /** @var shopFeatureModel $model */
                    $feature_model = $this->getModel('feature');

                    $raw_features = $feature_model->getById($selected);
                    $features = array();
                    foreach ($raw_features as $feature_id => $feature) {
                        $feature_code = $feature['code'];
                        if (in_array($feature_code, $code, true)) {
                            $features[$feature_code] = $feature;
                        }
                    }

                    if ($features && $product->type_id) {
                        $types = array($product->type_id => true);
                        $type_features_model = new shopTypeFeaturesModel();
                        $type_features_model->fillTypes($features, $types);
                        foreach ($features as &$feature) {
                            unset($feature['types']);
                            $feature['sort'] = ifset($feature['sort'][$product->type_id]);
                            unset($feature);
                        }
                        uasort($features, wa_lambda('$a,$b', 'return max(-1,min(1,$a["sort"]-$b["sort"]));'));
                    }
                    $related = array_keys($features);
                }
            }
            return $related;

        } else {


            $new_code = array_diff($code, array_keys($feature_relation));
            if ($new_code) {
                $search = array(
                    'code'     => $new_code,
                    'multiple' => 1,
                );

                /** @var shopFeatureModel $model */
                $feature_model = $this->getModel('feature');

                $multiple_features = $feature_model->getByField($search, 'code');
                foreach ($new_code as $c) {
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

        $this->data['export_orders_mask'] = $this->pluginSettings('export_orders_mask');

        $this->data['export_contacts_mask'] = $this->pluginSettings('export_contacts_mask');

        $this->data['stock_id'] = max(0, $this->pluginSettings('stock'));

        $export = waRequest::post('export');
        if (!is_array($export)) {
            $export = array();
        }

        switch (waRequest::param('module', 'backend')) {
            case 'frontend':
                $name = $this->processId.'.xml';
                if (!empty($export['virtual_product'])) {
                    $this->data['virtual'] = true;
                }
                break;
            case 'backend':
            default:
                $name = array();
                if (!empty($export['order'])) {
                    $name[] = 'orders';
                }
                if (!empty($export['virtual_product'])) {
                    $this->data['virtual'] = true;
                    $name[] = 'virtual_orders';
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
        if (!empty($this->data['virtual_product'])) {
            $w->writeAttribute('СинхронизацияТоваров', 'true');
        }
        $w->writeAttribute('ВерсияСхемы', $this->data['version']);
        $w->writeAttribute('ДатаФормирования', date("Y-m-d\\TH:i:sO"));
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
        if (!empty($export['virtual_product'])) {
            $this->data['virtual_product'] = true;
            $count[self::STAGE_PRODUCT] = $this->getCollection()->count();
        } else {
            if (!empty($export['order'])) {
                $this->data['orders_time'] = !empty($export['new_order']) ? $this->plugin()->exportTime() : 0;
                $where = array();
                $params = array();
                if (!empty($this->data['orders_time'])) {
                    $this->plugin();
                    $time_gap = max(0, $this->plugin()->getSettings('time_gap'));
                    $this->data['orders_time'] = $this->data['orders_time'] - $time_gap;
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
        if (empty($this->data['default_type_id'])) {
            $types = (new shopTypeModel())->getAll('id');
            $_type = reset($types);
            $this->data['default_type_id'] = ifset($_type, 'id', '0');
        }

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

        $this->data['configure'] = !!waRequest::post('configure');

        $this->data['expert'] = !!waRequest::post('expert') || !!$this->pluginSettings('expert');

        $this->data['empty_count'] = array(
            null,
        );

        if ($this->pluginSettings('stock_setup') || $this->pluginSettings('stock_complement')) {
            $this->data['empty_count'][] = 0;
        }

        if ($this->pluginSettings('sku_from_good')) {
            $this->data['sku_from_good'] = true;
        }

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
                            $path = ifset($map[$stage], '/').'/'.implode('|', (array)$node);
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
                                    if (in_array($this->reader->name, (array)$node)) {
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
        $price_types = array(
            'price',
            'purchase_price',
            'compare_price',
        );

        foreach ($price_types as $type) {
            $type .= '_type';
            $this->data[$type] = mb_strtolower($this->pluginSettings($type), 'utf-8');
            $type .= '_uuid';
            $this->data[$type] = $this->pluginSettings($type);
        }

        $this->data['use_product_currency'] = intval(wa('shop')->getSetting('use_product_currency'));

        $settings = array();
        if ($prices = waRequest::post('prices')) {
            foreach ($prices as $type => $value) {
                if (!empty($value) && strpos($value, ':')) {

                    list($uuid, $name) = explode(':', $value, 2);
                    $lname = mb_strtolower($name, 'utf-8');

                    if (($this->data[$type.'_type_uuid'] != $uuid) || ($this->data[$type.'_type'] != $lname)) {
                        $settings[$type.'_type_uuid'] = $uuid;
                        $this->data[$type.'_type_uuid'] = $uuid;
                        $settings[$type.'_type'] = $name;
                        $this->data[$type.'_type'] = $lname;
                    }
                } else {
                    $settings[$type.'_type_uuid'] = false;
                    $this->data[$type.'_type_uuid'] = false;
                    $settings[$type.'_type'] = false;
                    $this->data[$type.'_type'] = false;
                }
            }
        }
        if ($settings) {
            $this->pluginSettings($settings);
        }
    }

    private function initImportFeatures()
    {
        $this->data['sku_features'] = $this->pluginSettings('sku_features');
        $this->data['features_map'] = array();
        $this->data['feature_types'] = [];

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
                                    'name'         => ifempty($target['name'], $name),
                                    'type'         => shopFeatureModel::TYPE_VARCHAR,
                                    'cml1c_id'     => ifempty($target['cml1c_id']),
                                    'default_unit' => ifempty($target, 'dimension', ''),
                                    'multiple'     => 0,
                                    'selectable'   => 0,
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
                                $this->data['features'][$feature['code']] = $feature;
                                $this->data['new_features'][$feature['code']] = array(
                                    'id'         => $feature['id'],
                                    'types'      => array($this->data['default_type_id']),
                                    'cml1c_id'   => $feature['cml1c_id'],
                                    'multiple'   => $feature['multiple'],
                                    'selectable' => $feature['selectable']
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
                                if (empty($feature_model)) {
                                    $feature_model = new shopFeatureModel();
                                }
                                $_f = $feature_model->getByField(['code' => $target_value]);
                                $this->data['features'][$target_value] = ifset($_f, []);
                                break;
                            case 'p':
                                if (ifset($features_map[$namespace][$name]) != 'p:'.$target_value) {
                                    if ($target_value == '%s') {
                                        $features_map[$namespace][$name] = 'p:'.$target['param'];
                                    } else {
                                        $features_map[$namespace][$name] = 'p:'.$target_value;
                                    }
                                    $features_map_changed = true;
                                }
                                break;
                            case 'm':
                                if (ifset($features_map[$namespace][$name]) != 'm:'.$target_value) {
                                    $features_map[$namespace][$name] = 'm:'.$target_value;
                                    $features_map_changed = true;
                                }
                                break;

                        }
                    }
                }
            }

            if ($features_map_changed) {
                $this->pluginSettings('features_map', $features_map);
                foreach ($features_map as $namespace => $targets) {
                    foreach ($targets as $name => $target) {
                        $this->data['features_map'][$namespace][$name]['target'] = $target;
                    }
                }
            }
        }
        if (!empty($this->data['expert'])) {
            $xpath = '//ЗначениеРеквизита';
            $namespace = ifset(self::$feature_xpath_map[$xpath]['namespace']);
            $predefined = array(
                'Полное наименование'  => 'm:name:default_mapping',
                'ПолноеНаименование'   => 'm:name:default_mapping',
                'НаименованиеПолное'   => 'm:name:default_mapping',
                'ОписаниеВФорматеHTML' => 'm:description:default_mapping',
                'ВидНоменклатуры'      => 'm:type_name:default_mapping',
                'ТипНоменклатуры'      => 's',

            );
            foreach ($predefined as $name => $target) {
                if (!isset($this->data['features_map'][$namespace])) {
                    $this->data['features_map'][$namespace] = array();
                }
                if (!isset($this->data['features_map'][$namespace][$name])) {
                    $this->data['features_map'][$namespace][$name] = array(
                        'target' => $target,
                    );
                }
            }
        }

        if (($this->pluginSettings('expert') != ifset($this->data['expert'])) && !empty($this->data['configure'])) {
            $this->pluginSettings('expert', true);
        }
    }

    private function initImportStocks()
    {
        $this->data['stock_map'] = array();
        $stock_model = $this->getModel('stock');
        /**
         * @var shopStockModel $stock_model
         */
        $this->data['stock_id'] = max(0, waRequest::get('stock', 0, waRequest::TYPE_INT));
        if (!$this->data['stock_id']) {
            $this->data['stock_id'] = max(0, intval($this->pluginSettings('stock')));
        }
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
                if (($uuid) && ($uuid != 'default') && (ifset($stock_map[$uuid]) != $stock_id)) {
                    $stock_map[$uuid] = $stock_id;
                    $stock_map_changed = true;
                }
            }
        }
        $exists_stocks = $stock_model->getAll('id');

        if ($stock_map) {
            foreach ($stock_map as $uuid => $stock_id) {
                if (!in_array($stock_id, array(-1, 0)) && !isset($exists_stocks[$stock_id])) {
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

        if ($this->pluginSettings('stock_setup')) {
            // Создавать новые артикулы с нулевыми остатками
            $this->data['stock_setup'] = array_keys($exists_stocks);
        } else {
            $this->data['stock_setup'] = false;
        }

        if ($this->pluginSettings('stock_complement')) {
            // Обнулять остатки в несинхронизированных складах
            $this->data['stock_complement'] = array_keys($exists_stocks);
        } else {
            $this->data['stock_complement'] = false;
        }

        if ($this->pluginSettings('stock_forced')) {
            // Обнулять складские остатки при отсутствии значений в файле обмена
            $this->data['stock_forced'] = array_keys($exists_stocks);
        } else {
            $this->data['stock_forced'] = false;
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
                    $name = explode('|', array_pop($base));
                    $depth = count($base);
                    $base = implode('/', $base);

                    do {
                        $result = $this->read($method, false);
                        $path = implode('/', array_slice($this->path, 0, $depth));
                    } while ($result
                    && ($path == $base)
                    && (($this->reader->nodeType != XMLReader::ELEMENT) || (!in_array($this->reader->name, $name)))
                    );
                } else {
                    $result = @$this->reader->next();
                }
                break;
            case 'read':
            default:
                $result = @$this->reader->read();
                break;
        }
        $this->path();
        if (!$result) {
            if ($error = $this->getXmlError(LIBXML_ERR_ERROR)) {
                throw new waException($error);
            }
        }
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
        self::STAGE_FEATURE  => array(array('Свойство', 'СвойствоНоменклатуры'), 'next'),
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

                        $info = array();
                        if (!empty($this->data['timestamp'])) {
                            $interval = time() - $this->data['timestamp'];
                            $interval = sprintf('%02d ч %02d мин %02d с', floor($interval / 3600), floor($interval / 60) % 60, $interval % 60);
                            $info[] = sprintf('Время формирования: %s.', $interval);
                        }

                        if (!empty($this->data['memory'])) {
                            $info[] = sprintf('Использование памяти, максимум: %0.3f МБ.', $this->data['memory'] / 1048576);
                        }

                        if ($info) {
                            $this->writer->writeComment(implode(' ', $info));
                        }

                        $this->writer->endElement(/*КоммерческаяИнформация*/);
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
     * @uses shopCml1cPluginBackendRunController::stepImport()
     * @uses shopCml1cPluginBackendRunController::stepExportCategory()
     * @uses shopCml1cPluginBackendRunController::stepExportProduct()
     * @uses shopCml1cPluginBackendRunController::stepExportOffer()
     * @uses shopCml1cPluginBackendRunController::stepExportOrder()
     * @uses shopCml1cPluginBackendRunController::stepExportVirtualProduct()
     */
    protected function step()
    {
        $result = false;
        $method_name = null;
        try {
            if ($method_name = $this->getStepMethod()) {
                $result = $this->{$method_name}($this->data['current'], $this->data['count'], $this->data['processed_count']);
                if ($this->data['direction'] == 'export') {
                    $this->write();
                }
            }
        } catch (Exception $ex) {
            $this->stepException($method_name, $ex);
            sleep(5);
        }
        $this->data['memory'] = memory_get_peak_usage();
        $this->data['memory_avg'] = memory_get_usage();

        return $result;
    }

    /**
     * @param string                $method_name
     * @param Exception|waException $ex
     * @throws Exception|waException
     */
    protected function stepException($method_name, Exception $ex)
    {
        $repeated = false;
        $break = false;

        $error = &$this->data['last_error'];
        $stage = isset($this->data['stage']) ? $this->data['stage'] : null;
        $iteration = ($stage && isset($this->data['current'][$stage])) ? $this->data['current'][$this->data['stage']] : null;

        $template = "Error during %s %s process: %s";

        if (!empty($error)) {
            if (($error['stage'] == $method_name)
                && ($error['iteration'] == $iteration)
                && ($error['code'] == $ex->getCode())
                && ($error['message'] == $ex->getMessage())
            ) {
                $repeated = true;
                $n = intval(++$error['counter']);
                if ($n > 5) {
                    $break = true;
                    $template = "The %s %s process is interrupted due to repeated mistakes: %s";

                } else {
                    $template = "Repeated ({$n}) the error during %s %s process: %s";
                }
            } else {
                $error = null;
            }
        }

        $message = sprintf($template, $this->data['direction'], $this->data['stage'], $ex->getMessage());
        if (!$repeated) {
            $root = wa()->getConfig()->getPath('root');
            $trace = $ex->getTraceAsString();
            $message .= "\nTRACE:\n".str_replace($root, '', $trace);
        }
        $this->error($message);

        if (empty($error)) {
            $this->data['last_error'] = array(
                'stage'     => $method_name,
                'iteration' => $iteration,
                'code'      => $ex->getCode(),
                'message'   => $ex->getMessage(),
                'counter'   => 1,
            );
        }
        unset($error);

        if ($break) {
            throw $ex;
        }
    }

    protected function getStepMethod()
    {
        $virtual = !empty($this->data['virtual']) ? 'Virtual' : '';
        $methods = array(
            'step'.ucfirst($this->data['direction']),
            'step'.ucfirst($this->data['direction']).$virtual.ucfirst($this->data['stage']),
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
        if (!strlen($category['id_1c'])) {
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

    public function mapSetting()
    {
        $html = '';
        $params = array(
            'options'         => array(),
            'control_wrapper' => '<tr><td>%1$s<span class="hint">%3$s</span></td><td>&rarr;</td><td>%2$s</td></tr>',
            'title_wrapper'   => '%s',
            'disabled'        => 'disabled',
            'readonly'        => 'readonly',
            'translate'       => false,
        );
        $this->data['overview'] = true;


        $params['control_separator'] = '</td></tr>
            <tr><td>&nbsp;</td><td>&nbsp;</td><td>';

        $this->initImportFeatures();
        $html .= $this->getFeaturesControl('features', $params);

        $html .= $this->getStocksControl('stocks', $params);

        if (false) {
            $params['control_wrapper'] = '<tr><td>%2$s<span class="hint">%3$s</span></td><td>&rarr;</td><td>%1$s</td></tr>';
            $html .= $this->getPriceControl('prices', $params);
        }

        if ($html) {
            $html = '<table class="zebra">'.$html.'</table>';
        }

        return $html;
    }

    public function exchangeReport($details = array())
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
        if ($this->processId) {
            $report .= sprintf(' ID процесса обмена: "%s"', $this->processId);
        }
        $extended_report = $report;
        if (!empty($details)) {
            $extended_report .= sprintf("\nДетали обмена данными:\n%s;\n", var_export($details, true));
        }
        if ($this->data['direction'] == 'import') {
            if (!empty($this->data['processed_count'][self::STAGE_OFFER])) {
                if (self::$price_map === null) {
                    $this->initPriceMap();
                }
                $extended_report .= sprintf("\nПараметры импорта цен:\n%s;\n", var_export(self::$price_map, true));
                $extended_report .= sprintf("\nПараметры импорта складов:\n%s;\n", var_export($this->data['stock_map'], true));
            }
        }

        waLog::log($extended_report, 'shop/plugins/cml1c/report.log');
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
                    if (isset($feature['target']) && (count($feature) == 1) && empty($this->data['overview'])) {
                        continue;
                    }

                    if (!isset($feature['name']) && ($namespace == 'uuid')) {
                        $feature['name'] = $this->guid2name($key);
                    }

                    if (0 == $matches++) {
                        $html .= $matches_head;
                    }
                    $map_params = $xpath_params;
                    $map_params['title'] = ifset($feature['name'], $key);

                    $map_params['value'] = ifset($feature['target'], empty($feature['code']) ? '' : sprintf('f:%s', $feature['code']));

                    if ($map_params['value'] === '') {
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
                    } elseif (!empty($this->data['overview']) && ($namespace == 'uuid')) {
                        $map_params['description'] .= $key;
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
                                    $description .= 'По умолчанию во время обмена значения новых, еще не синхронизированных полей будут добавляться в качестве новых характеристик.';
                                    break;
                                case 'skip':
                                    $description .= 'По умолчанию во время обмена значения новых, еще не синхронизированных полей будут игнорироваться.';
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
     * @param       $name
     * @param array $params
     * @return string
     */
    public function settingOptionMapControl($name, $params = array())
    {
        $control = '';
        $name = htmlentities((string) $name, ENT_QUOTES, 'utf-8');
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

        $targets = ifset($params['target'], 'feature,params,main');
        if (!is_array($targets)) {
            $targets = preg_split('@,\s*@', $targets);
        }
        $targets[] = 'skip';
        $target_params = $params;
        $target_params['description'] = null;
        $target_options = array(
            'feature' => array(
                'value'       => 'f',
                'title'       => 'Характеристика',
                'description' => 'Характеристика товара в Shop-Script и ее размерность:',
            ),
            'main'    => array(
                'value'       => 'm',
                'title'       => 'Основное поле товара',
                'description' => 'Основные поля товара в Shop-Script:',
            ),
            'params'  => array(
                'value'       => 'p',
                'title'       => 'Дополнительный параметр товара',
                'description' => 'Дополнительный параметр товара в Shop-Script:',
            ),
            'skip'    => array(
                'value'       => 's',
                'title'       => 'Не импортировать',
                'description' => '',
            ),
        );
        if (!empty($params['value']) && preg_match('@^(\w):@', $params['value'], $matches)) {
            $target_params['value'] = $matches[1];
        }

        if (empty($target_params['value'])) {
            if (empty($params['disabled'])) {
                $target_params['value'] = reset($targets);
            } else {
                $target_params['value'] = substr(end($targets), 0, 1);
            }
        }

        if (!empty($params['disabled'])) {
            foreach ($target_options as $target => $option) {
                if (in_array($target, $targets) && ($option['value'] == $target_params['value'])) {
                    $targets = array($target);
                    break;
                }
            }
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
                $feature_params['description'] = $target_options['feature']['description'];
                $target_options['feature']['description'] = '';

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

            if (!empty($feature_params['value']) && preg_match('@^(f:[^:]+):(.+)$@', $feature_params['value'], $matches)) {
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

            if (count($targets) > 1) {
                $control .= $params['control_separator'];
            }
        }

        if (in_array('main', $targets)) {
            $main_params = $params;
            $main_params['options'] = array(
                'm:name'             => _w('Product name'),
                'm:summary'          => _w('Summary'),
                'm:description'      => _w('Description'),
                'm:badge'            => _w('Badge'),
                'm:type_name'        => _w('Product type'),
                'm:tags'             => _w('Tags'),
                'm:tax_name'         => _w('Taxable'),
                'm:meta_title'       => _w('Title'),
                'm:meta_keywords'    => _w('META Keyword'),
                'm:meta_description' => _w('META Description'),
                'm:url'              => _w('Storefront link'),
                //  'video_url'        => _w('Video URL on YouTube or Vimeo'),
            );

            if (!empty($params['value']) && (strpos($params['value'], 'm:') === 0)) {
                $main_params['value'] = $params['value'];
            } else {
                $main_params['value'] = null;
            }

            $main_params['description'] = null;
            $target_params['options'] = array_slice($target_options, 1, 1);
            $control .= waHtmlControl::getControl($target_control, 'target', $target_params);
            $control .= waHtmlControl::getControl(waHtmlControl::SELECT, 'm', $main_params);
            if (count($targets) > 1) {
                $control .= $params['control_separator'];
            }
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
            $params_params_param = $params_params;
            $params_params_param['style'] = 'display: none;';
            $target_params['options'] = array_slice($target_options, 2, 1);
            $control .= waHtmlControl::getControl($target_control, 'target', $target_params);
            $control .= waHtmlControl::getControl(waHtmlControl::SELECT, 'p', $params_params);

            $control .= <<<HTML

<a href="#/cml1c/custom_param/cancel/" class="js-customparams-cml1c-cancel inline" style="display: none;"><i class="icon10 close"></i></a>
HTML;


            $control .= waHtmlControl::getControl(waHtmlControl::INPUT, 'param', $params_params_param);


            if (count($targets) > 1) {
                $control .= $params['control_separator'];
            }
        }

        if (in_array('skip', $targets)) {
            $target_params['options'] = array_slice($target_options, 3, 1);
            if (count($targets) > 1) {
                $control .= waHtmlControl::getControl($target_control, 'target', $target_params);
            } else {
                $target_params_ = $target_params;
                $target_params_['description'] = $target_options['skip']['title'];
                $control .= waHtmlControl::getControl($target_control, 'target', $target_params_).$target_options['skip']['title'];
            }
            if (count($targets) > 1) {
                $control .= $params['control_separator'];
            }
        }

        if (empty($params['disabled'])) {

            $control .= <<<HTML
<script type="text/javascript">
if (typeof($) === 'function') {
    if($.importexport.plugins.cml1c){
        $.importexport.plugins.cml1c.initMapControlRow('{$control_namespace}');
    }
}
</script>
HTML;
        }
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
                'title' => 'Выберите название дополнительного параметра',
            );

            $params_model = new shopProductParamsModel();
            foreach ($params_model->select('DISTINCT name')->fetchAll(null, true) as $param) {
                $options [] = array(
                    'value' => sprintf('p:%s', $param),
                    'title' => $param,
                );
            }

            $options [] = array(
                'value' => sprintf('p:%s', '%s'),
                'title' => 'Добавить новый параметр',
            );
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

        if (empty($this->data['overview'])) {
            $source_stocks = $this->data['stock_map'];
            foreach ($source_stocks as $uuid => &$stock) {
                if (empty($stock['met'])) {
                    unset($source_stocks[$uuid]);
                } elseif (empty($stock['name'])) {
                    $stock['name'] = $this->guid2name($uuid);
                }
                unset($stock);
            }
        } else {
            $source_stocks = array();
            $stock_map = $this->pluginSettings('stock_map');
            if (!is_array($stock_map)) {
                $stock_map = array();
            }

            foreach ($stock_map as $uuid => $stock_id) {
                $source_stocks[$uuid] = array(
                    'name'     => $this->guid2name($uuid),
                    'stock_id' => $stock_id,
                );
            }
        }

        if (count($source_stocks) > 0) {

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
            $title = 'Остатки товаров по складам — блок <Склады>';
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
            if (isset($this->data['stock_id']) && isset($params['options'][$this->data['stock_id']])) {
                $default = $params['options'][$this->data['stock_id']]['title'];
            }
            foreach ($source_stocks as $uuid => $stock) {
                if (!empty($stock['name']) || !empty($this->data['overview'])) {
                    $exist = true;

                    $stock_params = $params;
                    $stock_params['title'] = ifempty($stock['name'], '');
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

    private function getPriceControl($name, $params = array())
    {
        $html = '';
        if (!empty($this->data['map'][self::STAGE_PRICE]) || !empty($this->data['overview'])) {
            $prices = array(
                'price'          => _w('Price'),
                'purchase_price' => _w('Purchase price'),
                'compare_price'  => _w('Compare at price'),
            );

            waHtmlControl::addNamespace($params, $name);

            $params['description'] = '';
            $params['options'] = array(
                '' => '—',
            );

            if (!empty($this->data['overview'])) {
                foreach ($prices as $type => $title) {
                    $type .= '_type';
                    $name = $this->pluginSettings($type);
                    $type .= '_uuid';
                    $uuid = $this->pluginSettings($type);

                    if ($name || $uuid) {
                        $params['options'][] = array(
                            'value'       => sprintf('%s:%s', $uuid, $name),
                            'title'       => $name,
                            'description' => $uuid,
                        );
                    }
                }
            } else {
                foreach ($this->data['map'][self::STAGE_PRICE] as $price) {
                    $params['options'][$price['id']] = array(
                        'value'       => sprintf('%s:%s', $price['id'], $price['name']),
                        'title'       => trim(sprintf('%s (%s) %s', $price['name'], $price['currency'], ifset($price['tax_name']))),
                        'description' => trim(sprintf('%s %s', $price['id'], ifset($price['tax_name']))),
                    );
                };
            }

            $title = 'Типы цен товаров — блок <Цены>';
            $title = htmlentities($title, ENT_QUOTES, waHtmlControl::$default_charset);
            $html .= <<<HTML
<thead>
    <tr>
        <th colspan="3"><h3>{$title}</h3></th>
    </tr>
</thead>
<tbody>
HTML;


            foreach ($prices as $field => $title) {
                $price_params = $params;
                $price_params['title'] = $title;
                $uuid = $this->pluginSettings($field.'_type_uuid');
                $name = $this->pluginSettings($field.'_type');

                $price_params['value'] = sprintf('%s:%s', $uuid, $name);

                $html .= waHtmlControl::getControl(waHtmlControl::SELECT, $field, $price_params);
            }

            $html .= <<<HTML
<tr>
    <td colspan="3" class="hint">
        Типы цен из файла CommerceML, связанные здесь с типами цен Shop-Script, будут видны в соответствующих полях в
        <a href="?action=plugins#/cml1c/" class="inline">настройках плагина</a>.
        В отличие от других настроенных параметров обмена, возможность удалить связи цен на вкладке «Синхронизированные параметры» недоступна.
    </td>
</tr>
HTML;
            $html .= '</tbody>';
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
                $report .= sprintf('<i class="icon16 yes"></i>%s: ', empty($this->data['configure']) ? 'Импорт завершен' : 'Анализ завершен');
                break;
            case 'export':
                $report .= sprintf('<i class="icon16 yes"></i>%s: ', 'Экспорт завершен');
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
                        'title_wrapper'   => '%s',
                        'translate'       => false,
                    );

                    $params['control_separator'] = '</td></tr>
            <tr><td>&nbsp;</td><td>&nbsp;</td><td>';
                    $report .= '<table class="zebra">';


                    $report .= $this->getFeaturesControl('features', $params);
                    $report .= $this->getStocksControl('stocks', $params);

                    $params['control_wrapper'] = '<tr><td>%2$s<span class="hint">%3$s</span></td><td>&rarr;</td><td>%1$s</td></tr>';
                    $report .= $this->getPriceControl('prices', $params);
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
                            self::STAGE_ORDER    => array(
                                '%d order',
                                '%d orders',
                            ),
                            self::STAGE_PRODUCT  => array(
                                '%d product',
                                '%d products',
                            ),
                            self::STAGE_OFFER    => array(
                                '%d offer',
                                '%d offers',
                            ),
                            self::STAGE_CATEGORY => array(
                                '%d category',
                                '%d categories',
                            ),
                        ),
                    );

                    break;
                case 'import':
                default:
                    $strings = array(
                        'new'     => array(
                            self::STAGE_IMAGE    => array(
                                'imported %d product image',
                                'imported %d product images',
                            ),
                            self::STAGE_CATEGORY => array(
                                'imported %d category',
                                'imported %d categories',
                            ),
                            self::STAGE_PRODUCT  => array(
                                'imported %d product',
                                'imported %d products',
                            ),
                            self::STAGE_FEATURE => array(
                                'imported %d feature',
                                'imported %d features',
                            ),
                            self::STAGE_SKU      => array(
                                'imported %d sku',
                                'imported %d skus',
                            ),
                            self::STAGE_OFFER    => array(
                                'imported %d offer',
                                'imported %d offers',
                            ),
                        ),
                        'update'  => array(
                            self::STAGE_ORDER    => array(
                                'updated %d order',
                                'updated %d orders',
                            ),
                            self::STAGE_IMAGE    => array(
                                'updated %d product image',
                                'updated %d product images',
                            ),
                            self::STAGE_CATEGORY => array(
                                'updated %d category',
                                'updated %d categories',
                            ),
                            self::STAGE_PRODUCT  => array(
                                'updated %d product',
                                'updated %d products',
                            ),
                            self::STAGE_FEATURE => array(
                                'updated %d feature',
                                'updated %d features',
                            ),
                            self::STAGE_SKU      => array(
                                'updated %d sku',
                                'updated %d skus',
                            ),
                            self::STAGE_OFFER    => array(
                                'updated %d offer',
                                'updated %d offers',
                            ),
                        ),
                        'analyze' => array(
                            self::STAGE_PRODUCT => array(
                                'analyzed %d product',
                                'analyzed %d products',
                            ),
                            self::STAGE_SKU     => array(
                                'analyzed %d sku',
                                'analyzed %d skus',
                            ),
                            self::STAGE_OFFER   => array(
                                'analyzed %d offer',
                                'analyzed %d offers',
                            ),
                            self::STAGE_FEATURE => array(
                                'analyzed %d feature',
                                'analyzed %d features',
                            ),
                            self::STAGE_STOCK   => array(
                                'analyzed %d stock',
                                'analyzed %d stocks',
                            ),
                            self::STAGE_PRICE   => array(
                                'analyzed %d price',
                                'analyzed %d prices',
                            ),
                        ),
                        'skip'    => array(
                            self::STAGE_ORDER    => array(
                                'skipped %d order',
                                'skipped %d orders',
                            ),
                            self::STAGE_IMAGE    => array(
                                'skipped %d product image',
                                'skipped %d product images',
                            ),
                            self::STAGE_CATEGORY => array(
                                'skipped %d category',
                                'skipped %d categories',
                            ),
                            self::STAGE_PRODUCT  => array(
                                'skipped %d product',
                                'skipped %d products',
                            ),
                            self::STAGE_SKU      => array(
                                'skipped %d sku',
                                'skipped %d skus',
                            ),
                            self::STAGE_OFFER    => array(
                                'skipped %d offer',
                                'skipped %d offers',
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

    protected function getXmlError($level = LIBXML_ERR_NONE)
    {

        $messages = array();
        $errors = libxml_get_errors();
        /**
         * @var LibXMLError[] $errors
         */
        foreach ($errors as $error) {
            if ($error->level >= $level) {

                switch ($error->level) {
                    case LIBXML_ERR_FATAL:
                        $_level = 'LIBXML_ERR_FATAL';
                        break;
                    case LIBXML_ERR_ERROR:
                        $_level = 'LIBXML_ERR_ERROR';
                        break;
                    case LIBXML_ERR_WARNING:
                        $_level = 'LIBXML_ERR_WARNING';
                        break;
                    case LIBXML_ERR_NONE:
                        $_level = 'LIBXML_ERR_NONE';
                        break;
                    default:
                        $_level = $error->level;
                }
                $messages[] = sprintf('%s at line %d row %d: %s', $_level, $error->line, $error->column, $error->message);
            }
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
            $this->data['map']['catalogue_id'] = shopCml1cPlugin::makeUuid();

            $w->startElement('Каталог');
            $w->writeElement('Ид', $this->data['map']['catalogue_id']);
            $w->writeElement('ИдКлассификатора', $this->data['map'][self::STAGE_OFFER]);
            $w->writeElement('Наименование', "Каталог товаров от ".date("Y-m-d H:i"));
            $this->writeOwner();
            $w->startElement('Товары');
        }

        while (($chunk-- > 0) && ($product = reset($products))) {
            $exported = false;
            if (!strlen($product['id_1c'])) {
                $this->fixProductUUID($product);
            }
            $shop_product = new shopProduct($product);
            //$product['type_name'] = $shop_product->type['name'];
            #WORK
            $skus = $shop_product->skus;

            foreach ($skus as $sku) {
                if (!strlen($sku['id_1c'])) {
                    if ($sku['id'] == $product['sku_id']) {
                        $sku['id_1c'] = $product['id_1c'];
                        $sku_model = $this->getModel('productSkus');
                        /**
                         * @var shopProductSkusModel $sku_model
                         */
                        $sku_model->updateById($sku['id'], array('id_1c' => $product['id_1c']));
                    } else {
                        $sku['id_1c'] = $this->plugin()->makeEntryUUID($sku['id'], 'sku');
                    }
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
            $w->writeElement('ИдКаталога', $this->data['map']['catalogue_id']);
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
            $w->endElement(/*ТипыЦен*/);
            $w->startElement('Предложения');
        }

        while (($chunk-- > 0) && ($product = reset($products))) {
            $exported = false;
            $shop_product = new shopProduct($product);
            #WORK
            foreach ($shop_product->skus as $sku) {
                if (!strlen($sku['id_1c'])) {
                    if ($sku['id'] == $product['sku_id']) {
                        $sku['id_1c'] = $product['id_1c'];
                        $sku_model = $this->getModel('productSkus');
                        /**
                         * @var shopProductSkusModel $sku_model
                         */
                        $sku_model->updateById($sku['id'], array('id_1c' => $product['id_1c']));
                    } else {
                        $sku['id_1c'] = $this->plugin()->makeEntryUUID($sku['id'], 'sku');
                    }
                }
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
            $w->endElement(/*ПакетПредложений*/);
        }
        return ($current_stage[self::STAGE_OFFER] < $count[self::STAGE_OFFER]);
    }


    private function stepExportVirtualProduct(&$current_stage, &$count, &$processed)
    {
        static $products;
        $chunk = self::$chunk_map[self::STAGE_PRODUCT];
        if (!$products) {
            $products = $this->getProducts($current_stage[self::STAGE_PRODUCT], $chunk * 4);
        }
        $w = &$this->writer;


        while (($chunk-- > 0) && ($product = reset($products))) {
            $exported = false;

            $w->startElement('Документ');
            //Идентификатор документа уникальный в рамках файла обмена
            $w->writeElement('Ид', '');
            //Номер документа
            $w->writeElement('Номер', '');
            //Дата документа
            $w->writeElement('Дата', '');
            //Вид хозяйственной операции
            $w->writeElement('ХозОперация', 'Заказ товара');
            $w->writeElement('Роль', 'Покупатель');

            $w->writeElement('Валюта', $this->currency());
            $w->writeElement('Курс', '1');
            $w->writeElement('Сумма', '0');
            $w->writeElement('Время', '00:00');

            $w->startElement('Товары');

            $shop_product = new shopProduct($product);
            $items = array();
            foreach ($shop_product->skus as $sku) {

                $item = array(
                    'product_id' => $shop_product->id,
                    'sku_id'     => $sku['id'],
                );
                $item += $sku;
                $items[] = $item;
            }
            $items = $this->findOrderItems($items);


            $export_features = $this->pluginSettings('export_product_features');
            if ($export_features) {
                $features_model = new shopProductFeaturesModel();

                foreach ($items as &$item) {
                    $item['features'] = $features_model->getValues($product['id'], $item['id']);
                }
                unset($item);

            }

            foreach ($items as $item) {
                $this->writeVirtualOrderItem($shop_product, $item);
                $exported = true;
            }

            $w->endElement(/*'Товары'*/);
            $w->endElement(/*'Документ'*/);

            array_shift($products);
            ++$current_stage[self::STAGE_PRODUCT];
            if ($exported) {
                ++$processed[self::STAGE_PRODUCT];
            }
        }
        if ($current_stage[self::STAGE_PRODUCT] == $count[self::STAGE_PRODUCT]) {
            $w->endElement(/*Предложения*/);
            $w->endElement(/*ПакетПредложений*/);
        }
        return ($current_stage[self::STAGE_PRODUCT] < $count[self::STAGE_PRODUCT]);
    }

    private function stepExportCategory(&$current_stage, &$count, &$processed)
    {
        static $categories;
        static $level = 0;
        if (!$categories) {

            $categories = $this->getCategoryModel()->getFullTree('*', true);
            if (!empty($current_stage[self::STAGE_CATEGORY])) {
                $categories = array_slice($categories, $current_stage[self::STAGE_CATEGORY]);
            }
        }
        if (empty($current_stage[self::STAGE_CATEGORY])) {
            $current_stage[self::STAGE_CATEGORY] = 0;
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

    private function getAddress(&$params, $type)
    {
        /**
         * @var waRegionModel $region_model
         */
        static $region_model;

        if (!empty($params[$type.'_address.country'])) {
            if (!empty($params[$type.'_address.region']) && empty($params[$type.'_address.region_name'])) {
                if (!$region_model) {
                    $region_model = new waRegionModel();
                }

                if ($region = $region_model->get($params[$type.'_address.country'], $params[$type.'_address.region'])) {
                    $params[$type.'_address.region_name'] = $region['name'];
                }
            }

            $params[$type.'_address.country_name'] = waCountryModel::getInstance()->name($params[$type.'_address.country']);
        }

        $address = array(
            'Улица'           => $type.'_address.street',
            'Город'           => $type.'_address.city',
            'Регион'          => $type.'_address.region_name',
            'Почтовый индекс' => $type.'_address.zip',
            'Страна'          => $type.'_address.country_name',
        );

        foreach ($address as $type => &$field) {
            if (!empty($params[$field]) && strlen(trim($params[$field]))) {
                $field = trim($params[$field]);
            } else {
                unset($address[$type]);
            }
            unset($field);
        }

        return $address;
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
        $fields = "*,items.name,items.type,items.sku_id,items.sku_code,items.product_id,items.quantity,items.price,items.service_id,items.service_variant_id,contact,params";
        $fields .= ($this->data['fractional'] ? ',items.stock_unit_id' : '');
        return $model->getList($fields, $options);
    }

    private function stepExportOrder(&$current_stage, &$count, &$processed)
    {
        static $orders;
        static $states;
        static $rate;

        $empty = array(null, false, '', array());

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

            $order['id_str'] = shopHelper::encodeOrderId($order['id']);
            $order['id_guid'] = $this->formatOrderId($order['id']);

            $order['status_comment'] = ''; //TODO

            $params = &$order['params'];

            list($date, $time) = explode(" ", date("Y-m-d H:i:s", strtotime($order["create_datetime"])));

            $order['params']['shipping'] = shopHelper::getOrderAddress($params, 'shipping') + $empty_address;
            $shipping_address = $this->getAddress($params, 'shipping');

            $order['params']['billing'] = shopHelper::getOrderAddress($params, 'billing') + $empty_address;
            $billing_address = $this->getAddress($params, 'billing');

            if (empty($order['contact']['lastname']) && empty($order['contact']['firstname'])) {
                list($order['contact']['lastname'], $order['contact']['firstname']) = explode(' ', ifempty($order['contact']['name'], '-').' %', 2);
                $order['contact']['firstname'] = preg_replace('/\s*%$/', '', $order['contact']['firstname']);
            }

            $w = &$this->writer;

            $w->startElement('Документ');
            //Идентификатор документа уникальный в рамках файла обмена
            $w->writeElement('Ид', $order['id_guid']);
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
            $guid = null;
            $c = null;
            $c_id = ifset($order['contact']['id']);
            if (!$c_id) {
                $c_id = ifset($order['contact_id']);
            }
            if ($c_id) {
                $c = new waContact($c_id);
                $cml1c_field = $this->plugin()->getConfigParam('contact_guid');
                if ($cml1c_field) {
                    $guid = $c->get($cml1c_field);
                }
            }

            if (empty($guid)) {
                $order['contact_id'];
                $guid = $this->formatContactId($order['contact_id']);
            }

            $w->writeElement('Ид', $guid);

            $company_field = $this->pluginSettings('contact_company');

            $is_jur = !empty($company_field) && !empty($order['contact'][$company_field]);

            if (!$is_jur) {
                $w->writeElement('Наименование', ifempty($order['contact']['name'], '-'));
                $w->writeElement('ПолноеНаименование', ifempty($order['contact']['name'], '-'));
                $w->writeElement('Роль', 'Покупатель');
                $w->writeElement('Фамилия', $order['contact']['lastname']);
                $w->writeElement('Имя', $order['contact']['firstname']);

                $this->writeAddress($shipping_address, 'АдресРегистрации');

                $contact_map = array(
                    'ИНН' => 'contact_inn',
                );
                foreach ($contact_map as $target => $source) {
                    if ($field = $this->pluginSettings($source)) {
                        $value = $this->getContactField($field, $order['contact'], $c);
                        if (!in_array($value, $empty, true)) {
                            $w->writeElement($target, $value);
                        }
                        unset($value);
                    }
                }

            } else {
                $w->writeElement('Наименование', $order['contact'][$company_field]);
                $w->writeElement('ОфициальноеНаименование', $order['contact'][$company_field]);
                $w->writeElement('Роль', 'Покупатель');

                if (!empty($billing_address)) {
                    $this->writeAddress($billing_address, 'ЮридическийАдрес');
                } else {
                    $this->writeAddress($shipping_address, 'ЮридическийАдрес');
                }

                $contact_map = array(
                    'ИНН'  => 'contact_inn',
                    'КПП'  => 'contact_kpp',
                    'ОКПО' => 'contact_okpo',
                );
                foreach ($contact_map as $target => $source) {
                    if ($field = $this->pluginSettings($source)) {
                        $value = $this->getContactField($field, $order['contact'], $c);
                        if (!in_array($value, $empty, true)) {
                            $w->writeElement($target, $value);
                        }
                        unset($value);
                    }
                }

                $bank_map = array(
                    'НомерСчета'            => 'contact_bank_account',
                    'СчетКорреспондентский' => 'contact_bank_cor_account',
                    'Наименование'          => 'contact_bank_name',
                    'БИК'                   => 'contact_bank_bik',
                );
                $bank = array();
                foreach ($bank_map as $target => $source) {
                    if ($field = $this->pluginSettings($source)) {
                        $bank[$target] = $this->getContactField($field, $order['contact'], $c);
                    }
                }

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
                            if (!in_array($value, $empty, true)) {
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
            $this->writeAddress($shipping_address, 'Адрес', 'Адрес доставки');

            if ($c) {
                $contacts = array();

                if ($field = $this->pluginSettings('contact_email')) {
                    if ($value = $c->get($field, 'default')) {
                        $contacts[] = array(
                            'Тип'      => 'Почта',
                            'Значение' => $value,
                        );
                    }
                }

                if ($field = $this->pluginSettings('contact_phone')) {
                    if ($value = $c->get($field, 'default')) {
                        $contacts[] = array(
                            'Тип'           => 'ТелефонРабочий',
                            'Представление' => $value,
                            'Значение'      => $value,
                        );
                    }
                }

                if ($contacts) {
                    $w->startElement('Контакты');
                    foreach ($contacts as $contact) {
                        $w->startElement('Контакт');
                        foreach ($contact as $field => $value) {
                            $w->writeElement($field, $value);
                        }
                        $w->endElement(/*Контакт*/);
                    }
                    $w->endElement(/*Контакты*/);
                }


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

                $extend_products = $product_model
                    ->select('`tax_id`,`id`')
                    ->where('`id` IN (i:id)', array('id' => $ids))
                    ->query()
                    ->fetchAll('id', true);
            } else {
                $extend_products = array();
            }
            foreach ($items as & $item) {
                $item['tax_id'] = ifset($extend_products[$item['product_id']]);
                $item['tax_id'] = ifset($extend_products[$item['product_id']]);
                $item['currency'] = $order['currency'];
            }
            unset($item);

            $discount_rate = ifset($params['discount_rate'], 0);
            $taxes = shopTaxes::apply($items, $params, $order['currency']);
            $taxes = array_filter($taxes, wa_lambda('$a', 'return !empty($a["name"]);'));
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
                //TODO fix tax calculation per order item
                if ($product['type'] == 'product') {
                    $this->writeOrderItem($product, $discount_rate, false && $product['tax_id'] ? ifempty($taxes[$product['tax_id']]) : null, $order['rate']);
                } else {
                    $this->writeOrderService($product, $discount_rate, $order['rate']);
                }
            }

            $export_delivery = $this->pluginSettings('export_delivery');
            $order['shipping'] = self::castValue($order['shipping'], 'double');
            if ($export_delivery && (($export_delivery !== '42') || !empty($order['shipping']))) {
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
                'Статус заказа'          => (isset($states[$order['state_id']])) ? $states[$order['state_id']]->getName() : $order['state_id'], //XXX
                'Дата изменения статуса' => date("Y-m-dTH:i:s", strtotime(ifempty($order['update_datetime'], $order['create_datetime']))),
                'Способ доставки'        => ifset($params['shipping_name']),
                'Адрес доставки'         => $this->formatAddress($shipping_address),
                'Адрес платильщика'      => $this->formatAddress($billing_address),
                'Адрес плательщика'      => $this->formatAddress($billing_address),
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
                            $value = trim($params[$id]);
                        }
                    } elseif (!empty($params[$id])) {
                        $value = trim($params[$id]);
                    } else {
                        if (!empty($c)) {
                            $contact_field = strip_tags($c->get($id, 'html'));
                            if (!in_array($contact_field, $empty, true)) {
                                $value = $contact_field;
                            }
                        }
                    }

                    if (!in_array($value, $empty, true)) {
                        $tag = ifset($info['tag'], $id);
                        if (!empty($tag)) {
                            $data[$tag] = $value;
                        }
                    }
                }
            }

            /*Время доставки*/
            if (method_exists('shopHelper', 'getOrderShippingInterval')) {
                $value = null;
                list($date, $time_start, $time_end) = shopHelper::getOrderShippingInterval($params);
                if ($date) {
                    $date = wa_date('date', $date, waDateTime::getDefaultTimezone());

                    $time = array(
                        $time_start,
                        $time_end,
                    );

                    $value = trim(sprintf('%s %s', $date, implode(' - ', array_unique($time))));
                } elseif (method_exists('shopHelper', 'getOrderCustomerDeliveryTime')) {
                    list($date, $delivery_time) = shopHelper::getOrderCustomerDeliveryTime($params);
                    if ($date) {
                        $date = wa_date('date', $date, waDateTime::getDefaultTimezone());

                        if ($delivery_time) {
                            $time = array(
                                sprintf('%02d:%02d', $delivery_time['from_hours'], $delivery_time['from_minutes']),
                                sprintf('%02d:%02d', $delivery_time['to_hours'], $delivery_time['to_minutes']),
                            );

                            $value = trim(sprintf('%s %s', $date, implode(' - ', array_unique($time))));
                        } else {
                            $value = $date;
                        }
                    } elseif (!empty($params['shipping_params_desired_delivery.date_str'])) {
                        $value = $params['shipping_params_desired_delivery.date_str'];
                    }
                }
                if ($value) {
                    $data['Дата доставки'] = $value;
                }
            }

            if (!empty($params['tracking_number'])) {
                $data['Идентификатор отправления'] = $params['tracking_number'];
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
        $map = $this->getOrderItemMap($items);

        foreach ($items as &$item) {
            switch (ifset($item['type'], 'product')) {
                case 'product':
                    $sku_id = (!isset($item['sku_id']) && isset($item['product_id'])) ? $item['id'] : $item['sku_id'];
                    $item_map = isset($map['skus'][$sku_id]) ? $map['skus'][$sku_id] : array();

                    if (isset($item_map['name'])) {
                        $item['name'] = $item_map['name'];
                    }
                    if (isset($item_map['description'])) {
                        $item['description'] = $item_map['description'];
                    }
                    $uuid = array_map('trim', explode('#', ifset($item_map['cml1c'], '')));
                    $this->fixOrderProductItem($item, $uuid);
                    break;
                case 'service':
                    $variant_id = ifset($item['service_variant_id']);
                    $item_map = isset($map['services'][$variant_id]) ? $map['services'][$variant_id] : '';
                    $uuid = array_map('trim', explode('#', $item_map));
                    $this->fixOrderServiceItem($item, $uuid);
                    break;
            }
            unset($item);
        }

        $export_features = $this->pluginSettings('export_product_features');
        if ($export_features) {
            $features_model = new shopProductFeaturesModel();

            foreach ($items as &$item) {
                if (($item['type'] == 'product') && !empty($item['sku_id']) && !empty($item['product_id'])) {
                    $item['features'] = $features_model->getValues($item['product_id'], $item['sku_id']);
                }
            }
            unset($item);

        }
        return $items;
    }

    private function fixOrderProductItem(&$product, $uuid)
    {
        $count = count(array_filter($uuid, 'strlen'));

        $sku_id = $product['sku_id'];

        switch ($count) {
            case 2:
                $product['id_1c'] = implode('#', array_unique($uuid));
                break;
            case 1:
                if (!strlen(reset($uuid))) {
                    //bad case
                    $this->fixProductUUID($product);
                } else {
                    $product['id_1c'] = reset($uuid);
                    $this->fixSkuUUID($product);
                }
                break;
            case 0:
                if (count($uuid) == 2) {
                    if (!empty($sku_id) && !empty($product['product_id'])) {
                        $this->fixProductUUID($product);
                        //FIXME
                        $this->fixSkuUUID($product);
                    } else {
                        $this->error(sprintf('Missed GUID for order item with sku_id=%s', ifset($sku_id)));
                    }

                } else {
                    // it's deleted products
                    $product_id = ifempty($product['product_id'], $product['name']);
                    $uuid = array(
                        shopCml1cPlugin::makeUuid($product_id),
                        shopCml1cPlugin::makeUuid(ifempty($sku_id, $product_id)),
                    );
                    $product['id_1c'] = sprintf('%s#%s', reset($uuid), end($uuid));
                    $product['_deleted_'] = true;
                }

                break;
        }
    }

    private function fixOrderServiceItem(&$service, $uuid)
    {
        $count = count(array_filter($uuid, 'strlen'));
        $variant_id = $service['service_variant_id'];
        switch ($count) {
            case 2:
                $service['cml1c_id'] = implode('#', array_unique($uuid));
                break;
            case 1:
                if (!strlen(reset($uuid))) {
                    //bad case
                    //TODO
                } else {
                    $service['cml1c_id'] = reset($uuid).'#';
                    $this->fixServiceUUID($service);
                }
                break;
            case 0:
                if (count($uuid) == 2) {
                    if (!empty($variant_id) && !empty($service['service_id'])) {
                        $this->fixServiceUUID($service, 'service');
                    } else {
                        $this->error(sprintf('Missed GUID for order item with service_variant_id=%s', ifset($variant_id)));
                    }
                } else {
                    // it's deleted service
                    $service_id = ifempty($service['service_id'], $service['name']);
                    $uuid = array(
                        shopCml1cPlugin::makeUuid($service_id),
                        shopCml1cPlugin::makeUuid(ifempty($variant_id, $service_id)),
                    );
                    $service['cml1c_id'] = sprintf('%s#%s', reset($uuid), end($uuid));
                    $service['_deleted_'] = true;
                }

                break;
        }
    }

    private function initOrderItemMap($type = null)
    {
        if (!isset($this->data['map'][self::STAGE_ORDER])) {
            $this->data['map'][self::STAGE_ORDER] = array(
                'skus'     => array(),
                'services' => array(),
            );
        }
    }

    private function getOrderItemMap($items)
    {
        $this->initOrderItemMap();

        $map = &$this->data['map'][self::STAGE_ORDER];

        $skus = array();
        $services = array();
        foreach ($items as $product) {
            if (!empty($product['sku_id']) && !isset($map['skus'][$product['sku_id']])) {
                $skus[] = $product['sku_id'];
            }
            if (!empty($product['service_variant_id']) && !isset($map['services'][$product['service_variant_id']])) {
                $services[] = $product['service_variant_id'];
            }
        }

        if ($services || $skus) {
            $sku_model = $this->getModel('productSkus');
            if ($skus = array_unique(array_map('intval', $skus))) {
                $fields = array();
                if ($this->plugin()->getSettings('export_product_name') == 'name') {
                    $fields[] = '`p`.`name`';
                }


                $fields[] = '`p`.`sku_id`';
                $fields = implode(",\n", $fields);
                $sql = <<<SQL
SELECT
  `s`.`id`,
  CONCAT(IFNULL(`p`.`id_1c`,''),"#",IFNULL(`s`.`id_1c`,'')) `cml1c`,
  {$fields}
FROM `shop_product_skus` `s`
LEFT JOIN `shop_product` `p` ON (`p`.`id` = `s`.`product_id`)
WHERE `s`.`id` IN (i:skus)
SQL;

                $map['skus'] += (array)$sku_model->query($sql, compact('skus'))->fetchAll('id', true);
            }

            if ($services = array_unique(array_map('intval', $services))) {
                $sql = <<<SQL
SELECT
  `v`.`id`,
  CONCAT(IFNULL(`s`.`cml1c_id`,''),"#",IFNULL(`v`.`cml1c_id`,'')) `cml1c`
FROM `shop_service_variants` `v`
LEFT JOIN `shop_service` `s` ON (`s`.`id` = `v`.`service_id`)
WHERE `v`.`id` IN (i:services)
SQL;

                $map['services'] += (array)$sku_model->query($sql, compact('services'))->fetchAll('id', true);

            }
        }
        return $map;
    }

    private function fixSkuUUID(&$item)
    {
        $this->initOrderItemMap();

        //XXX TODO Remap primary SKU id
        $map = &$this->data['map'][self::STAGE_ORDER]['skus'];
        $sku_id = ifset($item['sku_id'], $item['id']);
        if (!empty($map[$sku_id]['sku_id']) && ($sku_id != $map[$sku_id]['sku_id'])) {
            $item['id_1c'] = rtrim($item['id_1c'], '#').'#'.$this->plugin()->makeEntryUUID($sku_id, 'sku');

            $map[$sku_id]['cml1c'] = $item['id_1c'];
            $this->error(sprintf('Generate missed GUID for sku with sku_id=%d at product_id=%d', $sku_id, $item['product_id']));
        } else {
            $sku_model = $this->getModel('productSkus');
            /**
             * @var shopProductSkusModel $sku_model
             */
            $sku_model->updateById($sku_id, array('id_1c' => $item['id_1c']));
            $this->error(sprintf('Update missed GUID for sku with sku_id=%d at product_id=%d', $sku_id, $item['product_id']));
            $map[$sku_id]['cml1c'] = rtrim($item['id_1c'], '#').'#'.$item['id_1c'];
            $item['id_1c'] = $map[$sku_id]['cml1c'];
        }
        unset($map);
    }

    private function fixProductUUID(&$product)
    {
        $this->initOrderItemMap();

        $id = ifempty($product['product_id'], $product['id']);
        $key = sprintf('p.%d', $id);

        $map = &$this->data['map'][self::STAGE_ORDER]['skus'];
        if (isset($map[$key])) {
            $product['id_1c'] = $map[$key];
        } else {
            $product['id_1c'] = $this->plugin()->makeEntryUUID($id, 'product');
            $this->error(sprintf('Generate missed GUID for product with id=%d', $id));
            $map[$key] = $product['id_1c'];
        }

        if (!empty($product['sku_id'])) {
            $sku_id = $product['sku_id'];
            if (!isset($map[$sku_id])) {
                $map[$sku_id] = array();
            }
            $map[$sku_id]['cml1c'] = $product['id_1c'].'#';
        }
        unset($map);
    }

    private function fixServiceUUID(&$service, $type = 'variant')
    {
        $this->initOrderItemMap();
        $map = &$this->data['map'][self::STAGE_ORDER]['services'];
        if ($type == 'service') {
            $id = $service['service_id'];
            $key = 's'.$service['service_id'];
            if (!strlen(trim(ifset($map[$key]), '#'))) {
                $map[$key] = $this->plugin()->makeEntryUUID($id, 'service');
                $this->error(sprintf('Generate missed GUID for service with id=%d', $id));
            }

            $service['cml1c_id'] = $map[$key].'#';
        }

        $id = $service['service_variant_id'];
        if (!strlen(preg_replace('@^[^#]+#@', '', ifset($map[$id], '#')))) {
            $map[$id] = $service['cml1c_id'].$this->plugin()->makeEntryUUID($id, 'service_variant');
        }
        $service['cml1c_id'] = $map[$id];
        unset($map);
    }


    /**
     * @param mixed  [string] $product
     * @param string [string] $product['sku_id']
     * @param string [string] $product['id_1c']
     * @param string [string] $product['name']
     * @param int    [string] $product['quantity']
     * @param double [string] $product['price']
     * @param double [string] $product['tax']
     * @param bool   [string] $product['tax_included']
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

        #add element
        $this->writer->startElement('Товар');
        if (!empty($product['_deleted_'])) {
            $this->writer->writeAttribute('Статус', 'Удален');
        }
        $uuid = self::getGuid($product, '-');
        $this->writer->writeElement('Ид', $this->formatGuid($uuid));
        if (!empty($product['sku_code'])) {
            $this->writer->writeElement('Артикул', $product['sku_code']);
        }

        # fix name duplicates
        $product['name'] = preg_replace('@^(.+) \(\1\)$@', '$1', $product['name']);

        $this->writer->writeElement('Наименование', $product['name']);

        $this->writeUnit($product);


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
            $export_features = $this->pluginSettings('export_product_features');
            if ($export_features === 'properties') {
                foreach ($product['features'] as $code => $feature) {
                    if ($feature instanceof shopColorValue) {
                        $value = $feature->value;
                    } else {
                        $value = (string)$feature;
                    }
                    $properties[ifset($features[$code], $code)] = $value;
                }
            } else {
                $this->writer->startElement('ХарактеристикиТовара');
                //XXX or use properties?
                foreach ($product['features'] as $code => $feature) {
                    $this->writer->startElement('ХарактеристикаТовара');
                    //$this->writer->writeElement('Ид', '');
                    $this->writer->writeElement('Наименование', ifset($features[$code], $code));
                    if ($feature instanceof shopColorValue) {
                        $value = $feature->value;
                    } else {
                        $value = (string)$feature;
                    }
                    $this->writer->writeElement('Значение', $value);
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
     * @param mixed  [string] $product
     * @param string [string] $product['sku_id']
     * @param string [string] $product['id_1c']
     * @param string [string] $product['name']
     * @param int    [string] $product['quantity']
     * @param double [string] $product['price']
     * @param double [string] $product['tax']
     * @param bool   [string] $product['tax_included']
     * @param $sku
     * @param $tax
     */
    private function writeVirtualOrderItem($product, $sku, $tax = null)
    {
        #prepare
        static $features = array();

        $product['total'] = 0;

        #add element
        $this->writer->startElement('Товар');

        $this->writer->writeElement('Ид', $this->formatGuid(self::getGuid($sku)));
        if (!empty($sku['sku'])) {
            $this->writer->writeElement('Артикул', $sku['sku']);
        }

        $this->writeName($product, $sku);

        $this->writeUnit($product);

        $properties = array(
            'ВидНоменклатуры' => 'Товар',
            'ТипНоменклатуры' => 'Товар',
        );

        if (!empty($sku['features'])) {

            if ($new = array_diff(array_keys($sku['features']), array_keys($features))) {
                $model = $this->getModel('feature');
                /**
                 * @var shopFeatureModel $model
                 */

                $features += $model->select('code, name')->where('code IN (?)', array($new))->fetchAll('code', true);
            }

            $this->writer->startElement('ХарактеристикиТовара');
            foreach ($sku['features'] as $code => $feature) {
                $this->writer->startElement('ХарактеристикаТовара');
                $this->writer->writeElement('Наименование', ifset($features[$code], $code));
                if ($feature instanceof shopColorValue) {
                    $value = $feature->value;
                } else {
                    $value = (string)$feature;
                }
                $this->writer->writeElement('Значение', $value);
                $this->writer->endElement(/*ХарактеристикаТовара*/);
            }
            $this->writer->endElement(/*ХарактеристикиТовара*/);
        }

        $this->writeProperties($properties);

        if ($product['currency'] != $this->defaultPluginCurrency()) {
            $sku['price'] = $this->convertPrice($sku['price'], $product['currency'], $this->defaultPluginCurrency());
        }
        $this->writer->writeElement('ЦенаЗаЕдиницу', $this->price($sku['price']));
        $this->writer->writeElement('Количество', 0);
        $this->writer->writeElement('Сумма', $this->price(0));
        if ($tax) {
            $this->writeTaxes(array($tax));
        }

        $this->writer->endElement(/*Товар*/);
    }

    /**
     * @param           $service
     * @param float|int $discount_rate
     * @param float     $rate
     * @internal param $mixed [string] $service
     * @internal param $string [string] $service['id_1c']
     * @internal param $string [string] $service['name']
     * @internal param $int [string] $service['quantity'] default is 1
     * @internal param $double [string] $service['price']
     * @internal param $double [string] $service['tax'] default is null
     * @internal param $bool [string] $service['tax_included'] default is null
     */
    private function writeOrderService($service, $discount_rate = 0, $rate = 1.0)
    {
        if ($discount_rate > 0) {
            $product['discount'] = $discount_rate * $service['price'];
        }

        $service['total'] = ifset($service['quantity'], 1) * ($service['price'] - ifset($service['discount'], 0));
        if (!empty($service['tax']) && empty($service['tax_included'])) {
            $service['total'] += $service['tax'];
        }

        $this->writer->startElement('Товар');
        if (!empty($service['_deleted_'])) {
            $this->writer->writeAttribute('Статус', 'Удален');
        }
        $this->writer->writeElement('Ид', preg_replace('@^[^#]+#@', '', self::getGuid($service, '-')));
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
                    $this->writer->writeElement('Ставка', $this->price($tax['rate'], 0));
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
            $this->writeProperty($name, $value);
        }
        $this->writer->endElement(/*ЗначенияРеквизитов*/);
    }

    private function writeProperty($name, $value)
    {
        if (is_array($value)) {
            $value = array_filter(array_map('trim', $value));
            $value = implode(', ', $value);
        }
        $value = trim($value);
        if ($value !== '') {
            $this->writer->startElement('ЗначениеРеквизита');
            $this->writer->writeElement('Наименование', $name); //max 255
            $this->writer->writeElement('Значение', $value); // max 1000
            $this->writer->endElement(/*ЗначениеРеквизита*/);
        }
    }

    private function writeAddress($address, $name = 'Адрес', $type = '')
    {
        if (!empty($address)) {
            $this->writer->startElement($name);
            if ($type) {
                $this->writer->writeElement('Вид', $type);
            }

            $this->writer->writeElement('Представление', $this->formatAddress($address));

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
    }

    private function formatAddress($address)
    {
        $address_view = $address;

        if (!empty($address['Регион'])
            && !empty($address['Город'])
            && (strcasecmp($address['Регион'], $address_view['Город']) === 0)
        ) {
            unset($address['Регион']);
        }

        return implode(', ', $address);
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
     * @param string           $xpath
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
     * @param SimpleXMLElement|mixed $value
     * @param string                 $type
     * @return float|int|mixed|string
     */
    private static function castValue($value, $type = 'string')
    {
        if ($value !== null) {
            if (!in_array($type, array('array', 'xml'))) {
                $value = (string)$value;
                $value = preg_replace_callback('/\\\\u([0-9a-f]{4})/i', array(__CLASS__, 'replaceUnicodeEscapeSequence'), $value);
                $value = preg_replace_callback('/\\\\u([0-9a-f]{4})/i', array(__CLASS__, 'htmlDereference'), $value);
            }
            switch ($type) {
                case 'xml':
                    break;
                case 'intval':
                case 'int':
                    if (trim($value) === '') {
                        $value = null;
                    } else {
                        $value = intval(str_replace(array(' ', ','), array('', '.'), (string) $value));
                    }
                    break;
                case 'floatval':
                case 'float':
                    if (trim($value) === '') {
                        $value = null;
                    } else {
                        $value = floatval(str_replace(array(' ', ','), array('', '.'), (string) $value));
                    }
                    break;
                case 'doubleval':
                case 'double':
                    if (trim($value) === '') {
                        $value = null;
                    } else {
                        $value = doubleval(str_replace(array(' ', ','), array('', '.'), (string) $value));
                    }
                    break;
                case 'array':
                    $value = (array)$value;
                    break;
                case 'l_string':
                    $value = mb_strtolower(trim((string)$value), 'utf-8');
                    break;
                case 'string':
                default:
                    $value = trim((string)$value);
                    break;
            }
        }
        return $value;
    }

    /**
     *
     *
     * @param SimpleXMLElement $element
     * @param string|string[]  $field
     * @param string           $type
     *
     * @return mixed
     */
    private static function field(&$element, $field, $type = 'string')
    {
        $value = null;
        if (is_array($field)) {
            foreach ($field as $_field) {
                $value = self::field($element, $_field, $type);
                if ($value !== null) {
                    break;
                }
            }
        } elseif ($element instanceof SimpleXMLElement) {
            $values = array();
            foreach ($element->children() as $child) {
                /**
                 * @var SimpleXMLElement $child
                 */
                if ($child->getName() == $field) {
                    $values[] = self::castValue($child, $type);
                }
            }

            if (empty($values)) {
                $value = null;
            } elseif (count($values) == 1) {
                $value = reset($values);
            } else {
                $value = $values;
            }
        }
        return $value;
    }

    /**
     * @param SimpleXMLElement $element
     * @param string|string[]  $attribute
     * @param string           $type
     * @return string
     */
    private static function attribute(&$element, $attribute, $type = 'string')
    {
        if (is_array($attribute)) {
            $value = null;
            foreach ($attribute as $_attribute) {
                $value = @$element[$_attribute];
                if ($value !== null) {
                    break;
                }
            }
        } else {
            $value = @$element[$attribute];
        }

        return self::castValue($value, $type);
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
     * @return bool
     * @throws waException
     * @uses shopCml1cPluginBackendRunController::stepImportStockConfigure()
     * @uses shopCml1cPluginBackendRunController::completeImportStockConfigure()
     * @uses shopCml1cPluginBackendRunController::stepImportPriceConfigure()
     * @uses shopCml1cPluginBackendRunController::stepImportPrice()
     * @uses shopCml1cPluginBackendRunController::completeImportPrice()
     * @uses shopCml1cPluginBackendRunController::stepImportImage()
     * @uses shopCml1cPluginBackendRunController::stepImportCategory()
     * @uses shopCml1cPluginBackendRunController::stepImportFeature()
     * @uses shopCml1cPluginBackendRunController::stepImportFeatureConfigure()
     * @uses shopCml1cPluginBackendRunController::stepImportProductConfigure()
     * @uses shopCml1cPluginBackendRunController::completeImportProductConfigure()
     * @uses shopCml1cPluginBackendRunController::stepImportProduct()
     * @uses shopCml1cPluginBackendRunController::completeImportProduct()
     * @uses shopCml1cPluginBackendRunController::stepImportOfferConfigure()
     * @uses shopCml1cPluginBackendRunController::completeImportOfferConfigure()
     * @uses shopCml1cPluginBackendRunController::stepImportOffer()
     * @uses shopCml1cPluginBackendRunController::completeImportOffer()
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
                            $path = ifset($map[$stage], '/').'/'.implode('|', (array)$node);
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
                                    if (in_array($this->reader->name, (array)$node)) {
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
        $id = self::field($element, 'Ид');
        $name = self::field($element, 'Наименование');
        $data = array(
            'id_1c'      => $id,
            'name'       => $name,
            'values'     => array(),
            'type'       => null,
            'multiple'   => false,
            'selectable' => false,
        );

        $types = $this->xpath($element, '//ТипЗначений');
        $type = reset($types);
        $t = self::field($type, 'Тип');
        if (!$t) {
            $t = (string)$type;
        }

        switch ($t) {
            case 'Справочник':
                $data['type'] = ifempty($data['type'], shopFeatureModel::TYPE_VARCHAR);
                $data['selectable'] = true;
                foreach ($this->xpath($element, '//ВариантыЗначений/Справочник') as $xml) {
                    if ($uuid = self::field($xml, array('ИдЗначения', 'Ид'))) {
                        $data['values'][$uuid] = self::field($xml, 'Значение');
                    }
                }
                foreach ($this->xpath($element, '//ВариантыЗначений/ВариантЗначения') as $xml) {
                    if ($uuid = self::field($xml, array('ИдЗначения', 'Ид'))) {
                        $data['values'][$uuid] = self::field($xml, 'Значение');
                    }
                }
                if (!empty($this->data['new_features'])) {
                    $sf_model = new shopFeatureModel();
                    $_type = preg_replace('#\..*$#', '', ifempty($this->data, 'feature_types', $sf_model->getCode($id), ''));
                    foreach ($this->data['new_features'] as $new_ft) {
                        if ($id === $new_ft['cml1c_id'] && !in_array($_type, array(shopFeatureModel::TYPE_2D, shopFeatureModel::TYPE_3D))) {
                            $data['multiple'] = !!$new_ft['multiple'];
                            $values = array_combine(range(-1, - count($data['values'])), array_values($data['values']));
                            $feature = $sf_model->getById($new_ft['id']);
                            $data = $feature + $data;
                            $sf_model->setValues($data, $values);
                            break;
                        }
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
        $t = 'new';
        if (!empty($data['values'])) {
            $xpath = '//ХарактеристикиТовара/ХарактеристикаТовара';
            if ($target = $this->getTarget(null, $data, $xpath)) {
                $target = $this->explainTarget($target);
                if ($target['field'] === 'f') {
                    //update feature values
                    $t = 'update';
                    $data['code'] = $this->findFeature($data['name'], $data, $xpath);
                }
            }

        }
        $this->data['map'][self::STAGE_FEATURE][$data['id_1c']] = $data;


        //at offers - it's generic features
        //at product - features map
        ++$current_stage[self::STAGE_FEATURE];
        ++$processed[self::STAGE_FEATURE][$t];

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
        if ($multiple = self::field($element, 'Множественное')) {
            if (in_array($multiple, array('true', true, 1, '1',), true)) {
                $data['multiple'] = true;
            }
        }
        $types = $this->xpath($element, '//ТипЗначений');
        $type = reset($types);
        $t = self::field($type, 'Тип');
        if (!$t) {
            $t = (string)$type;
        }

        switch ($t) {
            case 'Справочник':
                $data['type'] = ifempty($data['type'], shopFeatureModel::TYPE_VARCHAR);
                $data['selectable'] = true;
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

        /** @var shopProductModel $model */
        $model = $this->getModel('product');

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
     * @param $sku
     * @return bool
     */
    private function isDummySku($sku, $skus = array())
    {
        $count = true;
        if (in_array($sku['count'], $this->data['empty_count'], true)) {
            $count = false;
        } elseif (is_array($sku['count'])) {
            if (count($sku['count']) == 0) {
                $count = false;
            } else {
                $count = false;
                foreach ($sku['count'] as $sku_count) {
                    if (!in_array($sku_count, $this->data['empty_count'], true)) {
                        $count = true;
                        break;
                    }
                }
            }
        }
        if (!empty($this->data['sku_from_good'])) {
            $dummy_sku = $sku['sku'];
            foreach ($skus as $_sku) {
                if ($_sku['sku'] !== $dummy_sku) {
                    $dummy_sku = '';
                    break;
                }
            }

        } else {
            $dummy_sku = '';
        }

        $dummy = (!$count)//
            && ($sku['sku'] === $dummy_sku) // empty or dummy SKU
            && ($sku['name'] === '')//empty name
            && empty($sku['price']) //empty price
            && empty($sku['purchase_price']) //empty price
            && empty($sku['compare_price']) //empty price
        ;
        return $dummy;
    }

    /**
     * @param string $name
     * @param array  $data
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
            /** @var shopFeatureModel $feature_model */

            $uuid = is_array($data) ? ifset($data['id_1c']) : null;
            if (!empty($uuid) && ($features = $feature_model->getFeatures('cml1c_id', $uuid))) {
                $feature = reset($features);
                $key = '*'.$uuid;
                $feature_map[$key] = $feature['code'];
                if (is_array($data)
                    && !empty($data['values'])
                    && ($this->pluginSettings('features_references') === 'update')
                ) {
                    $values_model = $feature_model->getValuesModel($feature['type']);
                    $values_uuid = array_keys($data['values']);
                    $uuid_map = $values_model
                        ->select('id,cml1c_id')
                        ->where('cml1c_id IN (s:values_uuid)', compact('values_uuid'))
                        ->fetchAll('cml1c_id', true);

                    foreach ($data['values'] as $value_uuid => &$value) {
                        $value_id = ifset($uuid_map[$value_uuid]);
                        $row = $values_model->addValue($feature['id'], $value, $value_id, $feature['type']);
                        if (!empty($row['error'])) {
                            if (!empty($row['error']['original_id'])) {
                                if (strcmp($row['error']['original_value'], $value) === 0) {
                                    if (empty($value_id)) {
                                        $uuid_map[$value_uuid] = $row['error']['original_id'];
                                        $values_model->updateById($row['error']['original_id'], array('cml1c_id' => $value_uuid));
                                        $row['new_cml1c_id'] = $value_uuid;
                                        $hint = "Ошибка при добавлении новой записи в справочник. Конфликт с существующим значением.\n".var_export($row, true);
                                        $this->error($hint);
                                    } else {
                                        $hint = "Ошибка при обновлении записи в справочнике. Конфликт с существующим значением.\n".var_export($row, true);
                                        $this->error($hint);
                                    }
                                } else {
                                    $row['new_value'] = $value;
                                }
                            } else {
                                $row['new_value'] = $value;
                                $hint = "Ошибка добавления записи в справочнике.\n".var_export($row, true);
                                $this->error($hint);
                            }

                        } elseif (empty($uuid_map[$value_uuid])) {
                            $uuid_map[$value_uuid] = $row['id'];
                            $values_model->updateById($row['id'], array('cml1c_id' => $value_uuid));
                        }

                        unset($value);
                    }

                }
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
                    'type' => is_array($data) ? ifempty($data['type'], shopFeatureModel::TYPE_VARCHAR) : shopFeatureModel::TYPE_VARCHAR,
                );

                if (is_array($data)) {
                    $feature += ifempty($data, array());
                }

                if (empty($this->data['configure'])) {
                    //save new features only for import
                    if ($feature['id'] = $feature_model->save($feature)) {
                        $code = $feature['code'];
                        $feature_map[$key] = $code;

                        if (!isset($this->data['new_features'])) {
                            $this->data['new_features'] = array();
                        }
                        $this->data['new_features'][$code] = array(
                            'id'    => $feature['id'],
                            'types' => array(),
                        );

                        if (!empty($feature['values'])) {
                            if (!empty($uuid) && ($this->pluginSettings('features_references') == 'update')) {
                                $values_model = $feature_model->getValuesModel($feature['type']);
                                $sort = 0;
                                $values = array();
                                foreach ($feature['values'] as $value_uuid => $value) {
                                    $row = $values_model->addValue($feature['id'], $value, null, $feature['type'], $sort++);
                                    if (empty($row['error']) && shopCml1cPlugin::isGuid($value_uuid)) {
                                        $values_model->updateById($row['id'], array('cml1c_id' => $value_uuid));
                                    }
                                    $values[] = $row;

                                }
                            } else {
                                $values = $feature_model->setValues($feature, $feature['values'], false, true);
                            }
                            $this->data['new_features'][$code]['values'] = array_combine(array_keys($feature['values']), $values);
                        }
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
        if (!empty($tax['name'])) {
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
     * @param string      $name
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
            $key = mb_strtolower($name, 'utf-8');

            if (!isset($types[$key])) {
                $model = $this->getModel('type');
                /**
                 * @var shopTypeModel $model
                 */

                if ($type_row = $model->getByName($name)) {
                    $types[$key] = intval($type_row['id']);
                } elseif (in_array($update_type_id, array('update', 'sync'), true)) {
                    $types[$key] = intval(
                        $model->insert(
                            array(
                                'name' => $name,
                                'icon' => 'ss pt box',
                            )
                        )
                    );
                }
            }

            if (isset($types[$key])) {
                $type_id = $types[$key];
            }
        }
        return $type_id;
    }

    private function stepImportPrice(&$current_stage, &$count, &$processed)
    {
        $map =& $this->data['map'][self::STAGE_PRICE];

        $element = $this->element();

        $currency = $this->parsePrice($element);

        $map[$currency['key']] = $currency;

        ++$current_stage[self::STAGE_PRICE];
        return true;
    }

    /**
     * @param SimpleXMLElement $p
     * @return array()
     */
    private function getCurrencyInfo(&$p)
    {
        if (self::$price_map === null) {
            $this->initPriceMap();
        }

        $id = self::field($p, 'ИдТипаЦены');

        $price_info = ifset(self::$price_map[$id], false);
        if ($price_info === false) {
            $currency = array(
                'id'       => $id,
                'currency' => $this->findCurrency(self::field($p, 'Валюта')),
            );

            $currency['name'] = $this->guid2name($currency['id'], ifempty($currency['name']));
            $currency['key'] = mb_strtolower($currency['name'], 'utf-8');
            $this->data['map'][self::STAGE_PRICE][$currency['key']] = $currency;

            $this->initPriceMap();
            $price_info = ifset(self::$price_map[$id], array());
        }

        return $price_info;
    }

    /**
     * @param SimpleXMLElement $element
     * @return array
     */
    private function parsePrice($element)
    {
        $currency = array(
            'id'       => self::field($element, 'Ид'),
            'currency' => $this->findCurrency(self::field($element, 'Валюта')),
            'name'     => self::field($element, 'Наименование'),
        );

        $this->guid2name($currency['id'], $currency['name']);

        $currency['key'] = mb_strtolower($currency['name'], 'utf-8');

        if ($xml_value = self::field($element, 'Налог', 'xml')) {
            $tax = array(
                'name' => self::field($xml_value, 'Наименование'),
            );
            if ($tax['name'] === null) {
                $tax['name'] = self::attribute($xml_value, 'Наименование');
            }
            $tax = $this->findTax($tax);
            if ($tax['id']) {
                $currency['tax_id'] = $tax['id'];
                $currency['tax_name'] = $tax['name'];
            }
        }
        return $currency;
    }

    private function stepImportPriceConfigure(&$current_stage, &$count, &$processed)
    {
        $element = $this->element();

        $currency = $this->parsePrice($element);

        $this->data['map'][self::STAGE_PRICE][$currency['key']] = $currency;

        ++$processed[self::STAGE_PRICE]['analyze'];
        ++$current_stage[self::STAGE_PRICE];
        return true;
    }

    private function stepImportStockConfigure(&$current_stage, &$count, &$processed)
    {
        $element = $this->element();
        $id = self::field($element, array('ИдСклада', 'Ид'));
        if (!isset($this->data['stock_map'][$id])) {
            $this->data['stock_map'][$id] = array();
        }

        $this->data['stock_map'][$id] += array(
            'stock_id' => -1,
            'met'      => 1,
        );
        $name = self::field($element, 'Наименование');
        if (empty($this->data['stock_map'][$id]['name'])) {
            $this->data['stock_map'][$id]['name'] = $this->guid2name($id, ifempty($name));
        } elseif (!empty($name)) {
            $this->data['stock_map'][$id]['name'] = $this->guid2name($id, ifempty($name));
        }
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

    private function completeImportStockConfigure()
    {
        foreach ($this->data['stock_map'] as $id => $stock) {
            if (!empty($stock['name'])) {
                $this->guid2name($id, $stock['name']);
            }
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
                case 'product_features_selectable':
                    $models[$type] = new shopProductFeaturesSelectableModel();
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

    private function initPriceMap()
    {
        if (self::$price_map === null) {
            self::$price_map = array();
        }
        $map = $this->data['map'][self::STAGE_PRICE];
        foreach (array('price', 'purchase_price', 'compare_price') as $type) {
            if (!empty($this->data[$type.'_type'])) {
                $price_name = mb_strtolower($this->data[$type.'_type'], 'utf-8');
                if (!empty($map[$price_name])) {
                    $map_ = $map[$price_name];
                    if (isset(self::$price_map[$map_['id']])) {
                        self::$price_map[$map_['id']]['name'][] = $price_name;
                        self::$price_map[$map_['id']]['name'] = array_unique(self::$price_map[$map_['id']]['name']);
                        self::$price_map[$map_['id']]['type'][] = $type;
                        self::$price_map[$map_['id']]['type'] = array_unique(self::$price_map[$map_['id']]['type']);
                    } else {
                        self::$price_map[$map_['id']] = array(
                            'type'     => array($type),
                            'currency' => $this->findCurrency($map_['currency']),
                            'name'     => array($price_name),
                            'tax_id'   => ifset($map_['tax_id']),
                        );
                    }
                }
            }
        }
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
        $element = $this->element();
        $uuid = array_filter(explode('#', self::field($element, 'Ид')), 'strlen');

        $product = $this->findProduct($uuid);
        if ($product->getId()) {
            try {
                $skus = $product->skus;

                if ($this->pluginSettings('import_business_ru') && count($skus) === 1) {
                    /** для "Бизнес.ру", у которого нет главного артикула */
                    foreach ($skus as $s_id => $_sk) {
                        if ($_sk['id_1c'] === $uuid[0] && !empty($uuid[1])) {
                            $skus[$s_id]['id_1c'] = $uuid[1];
                        }
                    }
                }

                $skus[-1] = array(
                    'id_1c'     => end($uuid),
                    'sku'       => self::field($element, 'Артикул'),
                    'name'      => self::field($element, 'Наименование'),
                    'available' => true,
                    'stock'     => array(),
                );


                $sku = &$skus[-1];

                if ($sku['sku'] === null) {
                    unset($sku['sku']);
                }
                if ($sku['name'] === null) {
                    unset($sku['name']);
                }

                $deleted = false;

                if (self::attribute($element, 'Статус', 'l_string') === 'удален') {
                    $deleted = true;
                } elseif (self::field($element, 'Статус', 'l_string') === 'удален') {
                    $deleted = true;
                }

                if ($deleted) {
                    $sku['available'] = false;
                }

                #get offer prices
                $prices = array(
                    'price' => null,
                );

                foreach ($this->xpath($element, '//Цены/Цена') as $p) {
                    $value = self::field($p, 'ЦенаЗаЕдиницу', 'doubleval');
                    if ($value === null) {
                        continue;
                    }

                    if ($k = self::field($p, 'Коэффициент', 'doubleval')) {
                        $value = $value / $k;
                    }
                    if ($currency = self::field($p, 'Валюта')) {
                        $currency = $this->findCurrency($currency);
                    }


                    $price_info = $this->getCurrencyInfo($p);

                    if ($price_info) {
                        foreach ($price_info['type'] as $price_type) {
                            $prices[$price_type] = array(
                                'value'    => $value,
                                'currency' => ifempty($currency, ifempty($price_info['currency'])),
                            );
                            if (!empty($this->data['update_product_fields']['tax_id'])) {
                                if (($price_type == 'price') && !empty($price_info['tax_id'])) {
                                    $product->tax_id = $price_info['tax_id'];
                                }
                            }
                        }

                    } elseif (empty($prices['price']) && empty(self::$price_map)) {
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
                    if (is_array($price) && isset($price['value'])) {
                        if (!empty($price['currency']) && ($price['currency'] != $product->currency)) {
                            $sku[$type] = $this->convertPrice($price['value'], $price['currency'], $product->currency);
                        } else {
                            $sku[$type] = $price['value'];
                        }
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
                            $this->applyMapping($product, $update_fields, $features, $params, $name, $value, null, $xpath);
                            break;
                    }
                }

                $xpath = '//ЗначенияСвойств/ЗначенияСвойства';
                foreach ($this->xpath($element, $xpath) as $property) {
                    //Ид по Ид получать код фичи из карты или наименование
                    //Значение | ИдЗначения - undocumented feature?
                    $id = self::field($property, 'Ид');

                    $feature = ifset($this->data['map'][self::STAGE_FEATURE][$id]);
                    if (empty($feature)) {
                        $feature = $this->findFeature($id, null, $xpath);
                    }

                    if (!empty($feature['name'])) {
                        $value = self::field($property, array('Значение', 'ИдЗначения'));
                        $this->applyMapping($product, $update_fields, $features, $params, $feature['name'], $value, $feature, $xpath);
                    }
                }

                if (!empty($features)) {
                    $sku['features'] = $features;
                }

                if ($this->pluginSettings('import_sku_name_rule') == 'features') {
                    $sku['name'] = empty($features) ? '' : implode(', ', $features);
                }

                if (empty($sku['sku']) && $product->sku_id && isset($skus[$product->sku_id])) {
                    $sku['sku'] = $skus[$product->sku_id]['sku'];
                }

                # import stock counts

                $stock = false;
                if (isset($this->data['stock_map']) && !empty($this->data['stock_map'])) {
                    $xpaths = array(
                        '//Склад',
                        '//ОстаткиПоСкладу', //
                        '//ОстатокПоСкладу', // ОстаткиПоСкладам/ОстатокПоСкладу
                        '//КоличествоНаСкладе', //**КоличествоНаСкладах/КоличествоНаСкладе
                    );
                    foreach ($xpaths as $xpath) {
                        foreach ($this->xpath($element, $xpath) as $s) {
                            $stock_uuid = self::attribute($s, array('ИдСклада', 'Ид'));
                            $_in_fields = false;
                            if (empty($stock_uuid)) {
                                $stock_uuid = self::field($s, array('ИдСклада', 'Ид'));
                                if ($stock_uuid) {
                                    $_in_fields = true;
                                }
                            }
                            if ($stock_uuid && isset($this->data['stock_map'][$stock_uuid])) {
                                $stock = true;
                                $stock_id = $this->data['stock_map'][$stock_uuid];
                                if (is_array($stock_id)) {
                                    $stock_id = $stock_id['stock_id'];
                                }
                                if ($stock_id >= 0) {
                                    if ($_in_fields) {
                                        $quantity_in_stock = self::field($s, array('Количество', 'Остаток', 'КоличествоНаСкладе', 'ОстаточекПоСкладику'), 'floatval');
                                        if (!is_null($quantity_in_stock)) {
                                            $sku['stock'][$stock_id] = $quantity_in_stock;
                                        }
                                    } else {
                                        $quantity_in_stock = self::attribute($s, 'КоличествоНаСкладе', 'floatval');
                                        if (!is_null($quantity_in_stock)) {
                                            $sku['stock'][$stock_id] = $quantity_in_stock;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }

                $total = self::field($element, 'Количество', 'intval');
                $stock_complement = $this->data['stock_complement'];
                $stock_forced = $this->data['stock_forced'];

                /** @var $stock_map array карта распределения складов */
                $stock_map = isset($this->data['stock_map']) ? $this->data['stock_map'] : array();

                /** @var $stock_1c bool есть ли склады 1с в файле импорта */
                $stock_1c = !empty($stock_map);

                foreach ($stock_map as $stock_uuid => &$stock_id) {
                    if (is_array($stock_id)) {
                        $stock_id = $stock_id['stock_id'];
                    }
                    if ($stock_id < 0) {
                        /** < 0 — не импортировать остатки */
                        unset($stock_map[$stock_uuid]);
                    }
                }

                if ($stock_complement === false && $stock_forced === false) {
                    /**
                     * ВЫКЛЮЧЕНЫ галки
                     * - Обнулять остатки в несинхронизированных складах
                     * - Обнулять складские остатки при отсутствии значений в файле обмена
                     */
                    if ($total !== null && !count($sku['stock']) && !$stock) {
                        /** остатки сохраняются для склада, указанного в настройках плагина */
                        $sku['stock'][$this->data['stock_id']] = $total;
                    }
                } elseif ($stock_complement !== false && $stock_forced === false) {
                    /**
                     * ВКЛЮЧЕНА галка
                     * - Обнулять остатки в несинхронизированных складах
                     */
                    $unsync_stock = array_diff($stock_complement, array_values($stock_map));
                    if ($total !== null && !count($sku['stock']) && !$stock) {
                        /** остатки сохраняются для склада, указанного в настройках плагина */
                        $sku['stock'] = array_fill_keys($unsync_stock, 0);
                        $sku['stock'][$this->data['stock_id']] = $total;
                    } elseif ($unsync_stock) {
                        $sku['stock'] += array_fill_keys($unsync_stock, 0);
                    }
                } elseif ($stock_complement === false && $stock_forced !== false) {
                    /**
                     * ВКЛЮЧЕНА галка
                     * - Обнулять складские остатки при отсутствии значений в файле обмена
                     */
                    $sync_stock = array_intersect($stock_forced, array_values($stock_map));
                    if ($total !== null && !count($sku['stock'])) {
                        $sku['stock'] = array_fill_keys($sync_stock, 0);
                        if (!$stock) {
                            /** остатки сохраняются для склада, указанного в настройках плагина */
                            $sku['stock'][$this->data['stock_id']] = $total;
                        }
                    } elseif ($sync_stock) {
                        $sku['stock'] += array_fill_keys($sync_stock, 0);
                    } elseif ($total === null) {
                        if ($this->data['stock_id']) {
                            $sku['stock'][$this->data['stock_id']] = 0;
                        } elseif (!$stock_1c || !$stock) {
                            $sku['stock'] = [0 => 0];
                        }
                    }
                } elseif ($stock_complement !== false && $stock_forced !== false) {
                    /**
                     * ВКЛЮЧЕНЫ обе галки
                     * - Обнулять остатки в несинхронизированных складах
                     * - Обнулять складские остатки при отсутствии значений в файле обмена
                     */
                    $_stocks = $stock_forced + $stock_complement;
                    if ($total !== null && !count($sku['stock'])) {
                        $sku['stock'] = array_fill_keys($_stocks, 0);
                        if (!$stock) {
                            /** остатки сохраняются для склада, указанного в настройках плагина */
                            $sku['stock'][$this->data['stock_id']] = $total;
                        }
                    } elseif ($_stocks) {
                        $sku['stock'] += array_fill_keys($_stocks, 0);
                    } elseif ($total === null) {
                        if (!$stock_1c || !$stock) {
                            $sku['stock'] = [0 => 0];
                        }
                    }
                }

                unset($sku);

                #find & merge data
                $this->mergeSkus($skus);

                $delete_sku = false;
                if ((count($uuid) > 1) && (count($skus) > 1) && (end($uuid) != reset($uuid))) {
                    $dummy_id = false;
                    foreach ($skus as $id => $sku) {
                        if (($id > 0) && ($sku['id_1c'] == $product['id_1c']) && $this->isDummySku($sku, $skus)) {
                            $dummy_id = $id;
                            break;
                        }
                    }

                    if ($dummy_id !== false) {
                        $delete_sku = $skus[$dummy_id]['id'];
                        unset($skus[$dummy_id]);
                    }
                }

                $this->fixSkuBasePriceSelectable($product, $skus);
                $product->skus = $skus;

                if (!$deleted && $this->pluginSettings('product_show')) {
                    $product->status = 1;
                }

                shopProductStocksLogModel::setContext(shopProductStocksLogModel::TYPE_IMPORT, 'Обмен через CommerceML');

                $product->save();

                if ($delete_sku) {
                    #remove empty default SKU
                    $this->getModel('productSkus')->delete($delete_sku);
                }
                ++$processed[self::STAGE_OFFER]['update'];
            } catch (waException $ex) {
                ++$processed[self::STAGE_OFFER]['skip'];
                $this->error(sprintf('Error during import product with Ид %s:%s', implode('#', $uuid), $ex->getMessage()));
                $this->stepException(__METHOD__, $ex);
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

        $xpaths = array(
            '//Склад',
            '//ОстаткиПоСкладу', //
            '//ОстатокПоСкладу', // ОстаткиПоСкладам/ОстатокПоСкладу
            '//КоличествоНаСкладе', //**КоличествоНаСкладах/КоличествоНаСкладе
        );
        foreach ($xpaths as $xpath) {

            foreach ($this->xpath($element, $xpath) as $s) {
                $stock_uuid = self::field($s, array('ИдСклада', 'Ид'));
                if (empty($stock_uuid)) {
                    $stock_uuid = self::attribute($s, array('ИдСклада', 'Ид'));
                }
                if ($stock_uuid) {
                    if (!isset($this->data['stock_map'][$stock_uuid])) {
                        $this->data['stock_map'][$stock_uuid] = array();
                    }
                    $this->data['stock_map'][$stock_uuid] += array(
                        'stock_id' => -1,
                        'met'      => 1,
                    );
                }
            }
        }

        foreach ($this->xpath($element, '//Цены/Цена') as $p) {
            $currency = array(
                'id'       => self::field($p, 'ИдТипаЦены'),
                'currency' => $this->findCurrency(self::field($p, 'Валюта')),
            );

            $currency['name'] = $this->guid2name($currency['id'], ifempty($currency['name']));
            $currency['key'] = mb_strtolower($currency['name'], 'utf-8');
            $this->data['map'][self::STAGE_PRICE][$currency['key']] = $currency;
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
        $expert = !empty($this->data['expert']);
        /**
         * xpath = /КоммерческаяИнформация/Каталог/Товары/Товар
         */
        if ($element = $this->element()) {
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
                $feature_name = ifset($feature, 'name', '');
                $feature_name_lowercase = mb_strtolower($feature_name, 'utf-8');

                switch ($feature_name_lowercase) {
                    case 'вид номенклатуры':
                    case 'вид товара':
                        if (!$expert) {
                            break;
                        }
                    //no-break
                    default:
                        if ($feature_name) {
                            $code = $this->findFeature($feature_name, $feature);

                            $data = array(
                                'code' => $code,
                                'name' => $feature_name,
                            );

                            $this->addFeatureMap($xpath, $feature_name, $data);

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
                    case "Вес": //fixed feature
                    case 'ВидНоменклатуры': // Товар/услуга или тип товаров
                    case 'ОписаниеФайла':
                    case 'ТипНоменклатуры':
                        break;
                    case "Полное наименование":
                    case "ПолноеНаименование":
                    case "НаименованиеПолное":

                    case 'ОписаниеВФорматеHTML':
                    case '___':
                        //fields to ignore
                        if (!$expert) {
                            break;
                        }
                    //no break
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


            ++$processed[self::STAGE_PRODUCT]['analyze'];
            unset($element);
        }
        ++$current_stage[self::STAGE_PRODUCT];
        return true;
    }

    private function completeImportProductConfigure()
    {
        if (!empty($this->data['features_map']['uuid'])) {
            foreach ($this->data['features_map']['uuid'] as $uuid => $data) {
                if (!empty($data['name'])) {
                    $this->guid2name($uuid, $data['name']);
                }
            }
        }
    }

    private function completeImportOfferConfigure()
    {
        if (!empty($this->data['features_map']['uuid'])) {
            foreach ($this->data['features_map']['uuid'] as $uuid => $data) {
                if (!empty($data['name'])) {
                    $this->guid2name($uuid, $data['name']);
                }
            }
        }
    }

    private function getTarget($name, $data, $xpath)
    {
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
                case 'update':
                    break;
            }

        }
        return $target;
    }

    private function explainTarget($target)
    {
        $code = null;
        $field = 's';
        $dimension = null;

        if (preg_match('@^(f|p|s|m):([^:]+)(:(.+))?$@', $target, $matches)) {
            $field = $matches[1];
            $code = $matches[2];
            $dimension = ifset($matches[4]);
        } elseif (preg_match('@^([^:]+):(.+)$@', $target, $matches)) {
            $code = $matches[1];
            $dimension = ifset($matches[2]);
        }

        return compact('code', 'field', 'dimension');
    }

    private function applyMapping(&$product, &$update_fields, &$features, &$params, $name, $value, $data, $xpath)
    {
        $result = false;

        $target = $this->getTarget($name, $data, $xpath);

        if ($target) {

            $target = $this->explainTarget($target);

            if ($data && is_array($data) && isset($data['values'])) {
                if (is_array($value)) {
                    foreach ($value as &$_value) {
                        if (isset($data['values'][$_value])) {
                            $_value = $data['values'][$_value];
                        }
                        unset($_value);
                    }

                } else {
                    /** @var string $value */
                    $value = (string)$value;
                    if (isset($data['values'][$value])) {
                        $value = $data['values'][$value];
                    }
                }
            }

            if ($target['dimension'] && ($target['field'] !== 'm')) {
                if (is_array($value)) {
                    foreach ((array) $value as &$_value) {
                        $_value = $this->clearSpaces($_value);
                        $_value = [
                            'value' => doubleval($_value),
                            'unit'  => $target['dimension']
                        ];
                        unset($_value);
                    }
                } else {
                    $value = $this->clearSpaces($value);
                    $value = [
                        'value' => (is_numeric($value) ? sprintf('%.10f', floatval($value)) : $value),
                        'unit'  => $target['dimension']
                    ];
                }
            } elseif (ifset($data, 'type', '') === shopFeatureModel::TYPE_DOUBLE) {
                $value = $this->clearSpaces($value);
            }

            if ($target['code']) {
                $code = $target['code'];

                switch ($target['field']) {
                    case 's':
                        $result = true;
                        //it's skip
                        break;
                    case 'm':
                        switch ($code) {
                            case 'type_name':
                                $value = $this->findType($value, $product);
                                if ($value) {
                                    $product->type_id = $value;
                                }
                                break;
                            case 'tax_name':
                                $value = $this->findTax(array('name' => $value));
                                if ($value) {
                                    $update_fields['tax_id'] = $value;
                                }
                                break;
                            case 'name':
                            case 'summary':
                            case 'description':
                                $update_fields[$code] = $value;
                                break;
                            case 'sku':
                            case 'sku_name':
                            default:
                                $product[$code] = $value;
                                break;
                        }

                        $result = 'm';
                        break;
                    case 'f':
                        $features[$code] = $this->formatFeature($features, $code, $value);
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
     * @param $value
     * @return string
     */
    private function clearSpaces($value)
    {
        /** отрезаем единицу измерения, если есть */
        $value = preg_replace('#([.,\d\s]+)(?:\s\S+)?$#', '$1', (string) $value);
        $spaces = [' ', ' ', "\r\n", "\n", "\r"];
        $value = str_replace($spaces, '', $value);
        $value = str_replace(',', '.', $value);

        return $value;
    }

    /**
     * @param $features
     * @param $code
     * @param $value
     * @return array|string[]
     */
    private function formatFeature($features, $code, $value)
    {
        $feature_type = ifset($this->data, 'feature_types', $code, shopFeatureModel::TYPE_VARCHAR);
        switch ($feature_type) {
            case shopFeatureModel::TYPE_COLOR:
                $feature = ifset($features, $code, []);
                if (is_string($feature)) {
                    $feature = [$feature];
                }
                $feature[] = $value;
                break;
            default:
                $feature = $value;
                if (!empty($this->data['expert'])) {
                    $multiple_separator = trim($this->pluginSettings('multiple_separator'));
                    if (!empty($multiple_separator) && !empty($value)) {
                        $feature = explode($multiple_separator, $value);
                        if (count($feature) === 1) {
                            $feature = reset($feature);
                        }
                    }
                }
        }

        return $feature;
    }

    /**
     * @param      $field
     * @param      $xpath
     * @param null $node_name
     * @param null $data
     * @return false|string
     */
    private function isRemapped($field, $xpath, $node_name = null, $data = null)
    {
        if (!isset($this->data['remapped_main_fields'])) {
            $this->data['remapped_main_fields'] = array();

            foreach ($this->data['features_map'] as $namespaces) {
                foreach ($namespaces as $features_map) {
                    if (!empty($features_map['target'])) {
                        $target = $this->explainTarget($features_map['target']);
                        if ($target['field'] == 'm') {
                            if (empty($this->data['remapped_main_fields'][$target['code']])
                                ||
                                ($this->data['remapped_main_fields'][$target['code']] === 'default_mapping')
                            ) {
                                $this->data['remapped_main_fields'][$target['code']] = empty($target['dimension']) ? true : 'default_mapping';
                            }
                        }
                    }
                }
            }
        }

        $remapped = !empty($this->data['remapped_main_fields'][$field]) ? $this->data['remapped_main_fields'][$field] : false;
        $target = null;
        if ($xpath) {
            if (!$remapped) {

                $namespace = ifset(self::$feature_xpath_map[$xpath]['namespace']);
                $xpath_field = ifset(self::$feature_xpath_map[$xpath]['field']);

                if (is_array($data) && isset($data[$xpath_field])) {
                    $namespace_key = $data[$xpath_field];
                } else {
                    $namespace_key = $node_name;
                }

                if ($namespace && isset($this->data['features_map'][$namespace][$namespace_key])) {
                    if (!empty($this->data['features_map'][$namespace][$namespace_key]['target'])) {
                        $target = $this->data['features_map'][$namespace][$namespace_key]['target'];
                    }
                }
            }
        } elseif ($remapped === 'default_mapping') {
            $remapped = false;
        }

        return $target ? $target : $remapped;
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
        $uuid = array_filter(explode('#', self::field($element, 'Ид')), 'strlen');

        $subject = ((count($uuid) < 2) || (reset($uuid) == end($uuid))) ? self::STAGE_PRODUCT : self::STAGE_SKU;

        $product = $this->findProduct($uuid);


        $product['id_1c'] = reset($uuid);

        if (!isset($this->data['map'][self::STAGE_PRODUCT])) {
            $this->data['map'][self::STAGE_PRODUCT] = array();
        }

        $update_fields = array(
            'summary'     => null,
            'description' => null,
            'name'        => null,
            'tax_id'      => null,
            'type_id'     => null,
        );

        if (!$this->isRemapped('name', false)) {
            $update_fields['name'] = self::field($element, 'Наименование');
        }

        if (!$this->isRemapped('summary', false)) {
            $update_fields['summary'] = self::field($element, 'Описание');
            if ($this->pluginSettings('description_is_html')) {
                $update_fields['summary'] = htmlspecialchars($update_fields['summary'], ENT_NOQUOTES, 'utf-8');
            }
        }

        if (!$this->isRemapped('description', false)) {
            $description = self::field($element, 'Описание');
            if ($this->pluginSettings('description_is_html')) {
                $description = htmlspecialchars($description, ENT_NOQUOTES, 'utf-8');
            }
            $update_fields['description'] = nl2br($description);
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
        if (($tax = reset($taxes)) && !empty($tax['id']) && !$this->isRemapped('tax_name', false)) {
            $update_fields['tax_id'] = $tax['id'];
        }

        #fill product features data

        $features = array();
        $params = array();

        /**
         *
         * Extend product categories
         */

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
            #init category map alias
            if (!isset($this->data['map'][self::STAGE_CATEGORY])) {
                $this->data['map'][self::STAGE_CATEGORY] = array();
            }

            $map = &$this->data['map'][self::STAGE_CATEGORY];

            foreach ($this->xpath($element, '//Группы/Ид') as $category) {
                $category = (string)$category;
                if (!isset($map[$category])) {
                    $category_row = $this->getModel('category')->getByField('id_1c', $category);
                    if ($category_row) {
                        $map[$category] = array(
                            'id' => $category_row['id'],
                        );
                    } else {
                        $map[$category] = false;
                    }
                }
                if ($category = ifset($map[$category])) {
                    $categories[] = intval($category['id']);
                }
            }
            $product->categories = $categories;
        }
        $image_descriptions = array();

        /**
         * xpath = ЗначенияРеквизитов/ЗначениеРеквизита Наименование/  = Значение/
         */
        $xpath = '//ЗначениеРеквизита';
        foreach ($this->xpath($element, $xpath) as $property) {
            $property_name = self::field($property, 'Наименование');
            switch ($property_name) {
                case 'ОписаниеФайла':
                    //Описание изображения *
                    $value = self::field($property, 'Значение');
                    if (strpos($value, '#')) {
                        list($image, $description) = explode('#', $value, 2);
                        $image_descriptions[$image] = $description;
                    }
                    break;
                case "Вес":
                    if ($value = self::field($property, 'Значение', 'doubleval')) {
                        if (!in_array($value, array('', null)) && !empty($this->data['update_product_fields']['weight'])) {
                            $features['weight'] = trim($value.' '.$this->pluginSettings('weight_unit'));
                        }
                    }
                    break;
                case "Полное наименование":
                case "ПолноеНаименование":
                case "НаименованиеПолное":
                    if (!$this->isRemapped('summary', $xpath, $property_name)) {
                        if ($value = self::field($property, 'Значение')) {
                            $update_fields['summary'] = $value;
                        }
                        break;
                    }
                //no break
                case 'ОписаниеВФорматеHTML':
                case 'Описание':
                    if (!$this->isRemapped('description', $xpath, $property_name)) {
                        if ($value = self::field($property, 'Значение')) {
                            $update_fields['description'] = $value;
                        }
                        break;
                    }
                //no break
                case 'ВидНоменклатуры': // Товар/услуга или тип товаров
                    if (!$this->isRemapped('type_name', $xpath, $property_name)) {
                        $value = self::field($property, 'Значение');
                        if (!in_array($value, array('Товар', 'Услуга'))) {
                            $value = $this->findType($value, $product);
                            if ($value) {
                                $product->type_id = $value;
                            }
                        }
                        break;
                    }
                //no break
                case '___':
                case 'ТипНоменклатуры':
                    //fields to ignore
                    if (empty($this->data['expert'])) {
                        break;
                    }
                //no break
                default:

                    if (!empty($this->data['expert'])) {
                        $value = self::field($property, 'Значение');
                        $this->applyMapping($product, $update_fields, $features, $params, $property_name, $value, null, $xpath);
                    }
                    break;
            }
        }

        if ($this->data['fractional'] && shopUnits::isEnabled()) {
            if ($xml_value = self::field($element, 'БазоваяЕдиница', 'xml')) {
                $kod_okei  = self::attribute($xml_value, 'Код');
                $full_name = self::attribute($xml_value, 'НаименованиеПолное');
                $product->setData('stock_unit_id', $this->getUnit($kod_okei, $full_name));
            }
        } elseif ($code = $this->pluginSettings('base_unit')) {
            if ($xml_value = self::field($element, 'БазоваяЕдиница', 'xml')) {
                /**
                 * @var SimpleXMLElement $xml_value
                 */

                $value = self::attribute($xml_value, 'НаименованиеПолное');
                if ($value === null) {
                    $value = (string)$xml_value;
                }

                if (!empty($value)) {
                    $features[$code] = $value;
                }
            }
        }

        $xpath = '//ХарактеристикиТовара/ХарактеристикаТовара';
        foreach ($this->xpath($element, $xpath) as $property) {
            //Ид
            $feature_name = self::field($property, 'Наименование');
            $value = self::field($property, 'Значение');
            if (!$this->applyMapping($product, $update_fields, $features, $params, $feature_name, $value, null, $xpath)) {
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
                if (empty($feature)) {
                    $feature = $this->findFeature($id, null, $xpath);
                }
                $value = self::field($property, array('Значение', 'ИдЗначения'));

                $feature_name = $feature ? (is_array($feature) ? mb_strtolower($feature['name'], 'utf-8') : $feature) : null;

                switch ($feature_name) {
                    case 'вид номенклатуры':
                    case 'вид товара':
                        //значение из справочника "номенклатурные группы"
                        $value = $this->findType($value, $product);
                        if ($value) {
                            $product->type_id = $value;
                        }
                        break;
                    default:
                        if ($feature_name) {
                            $this->applyMapping($product, $update_fields, $features, $params, $feature_name, $value, $feature, $xpath);
                        }
                        break;
                }
            }
        }


        $sku_features = array();
        if ($features && $this->data['sku_features']) {
            $multiple_features = $this->getFeatureRelation(
                array_keys($features),
                ($this->data['sku_features'] == 'selectable') ? $product : null
            );
            foreach ($multiple_features as $code) {
                if (is_array($features[$code])) {
                    if (count($features[$code]) == 1) {
                        $sku_features[$code] = $features[$code];
                        unset($features[$code]);
                    }
                } else {
                    $sku_features[$code] = $features[$code];
                    unset($features[$code]);
                }

            }
        }

        $deleted = false;

        if (mb_strtolower(self::attribute($element, 'Статус')) === 'удален') {
            $deleted = true;
        } elseif (mb_strtolower(self::field($element, 'Статус')) === 'удален') {
            $deleted = true;
        }


        //TODO f: ignore|extend|override
        $skus = $product->skus;
        if (!count($skus) || count($sku_features) || ($subject == self::STAGE_SKU) || !empty($this->data['sku_from_good'])) {
            $skus[-1] = array(
                'available' => ($subject == self::STAGE_PRODUCT) ? ($deleted ? false : true) : 1,
                'id_1c'     => end($uuid),
            );

            if (!empty($this->data['sku_from_good']) || ($subject == self::STAGE_SKU)) {
                $sku = self::field($element, 'Артикул');
                if ($sku !== null) {
                    $skus[-1]['sku'] = $sku;
                }
            }

            if (count($sku_features)) {
                $_f = [];
                foreach ($sku_features as $_sk_features) {
                    $_f[] = (is_array($_sk_features) ? reset($_sk_features) : $_sk_features);
                }
                $skus[-1]['name'] = $update_fields['name'].' ('.implode(', ', $_f).')';
            }
        }

        $target = 'update';
        $is_new_product = !$product->getId();
        if ($is_new_product) {
            # update name/summary/description only for new items

            $product->status = ($this->pluginSettings('product_hide')) ? 0 : 1;
            $skus[-1]['available'] = 0;

            $skus[-1]['stock'][0] = 0;

            if ($deleted) {
                if ($subject == self::STAGE_PRODUCT) {
                    $product->status = 0;
                } else {
                    $skus[-1]['available'] = false;
                }
            }

            $target = 'new';
            $product->name = $update_fields['name'];


            foreach ($update_fields as $field => $value) {
                if (!in_array($value, array('', null))) {
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

            if (!$product->url) {
                if (!$this->pluginSettings('create_unique_product_url')) {
                    $product->url = shopHelper::transliterate($product->name);
                } else {
                    $product->url = shopHelper::genUniqueUrl($product->name, $this->getModel('product'));
                }
            }

        } else {
            // set available if it dummy SKU
            $skus[-1]['available'] = 0;

            //$skus[-1]['stock'][0] = 0;

            if ($deleted) {
                if ($subject == self::STAGE_PRODUCT) {
                    $product->status = 0;
                } else {
                    $skus[-1]['available'] = false;
                }
            } elseif ($this->pluginSettings('product_show')) {
                if ($subject == self::STAGE_PRODUCT) {
                    $product->status = 1;
                } else {
                    $skus[-1]['available'] = true;
                }
            }

            foreach ($update_fields as $field => $value) {
                if (!in_array($value, array('', null)) && !empty($this->data['update_product_fields'][$field])) {
                    $product->{$field} = $value;
                }
            }

            if (!empty($this->data['update_product_fields']['features'])) {

                if ($sku_features) {
                    $apply_features = (count($uuid) > 1);
                    if ($apply_features) {
                        $skus[-1]['features'] = $sku_features;
                    } else {
                        /* ignore empty SKU for exists products */
                        unset($skus[-1]);

                        //attempt merge features with exists product features
                        $product_features = $product->features;
                        foreach ($sku_features as $code => $feature) {
                            if (!isset($product_features[$code])) {
                                $features[$code] = $feature;
                            } elseif (false) {
                                //XXX спорный момент
                                $features[$code] = array_unique(array_merge((array)$feature, $product_features[$code]));
                            }
                        }
                        $sku_features = array();
                    }
                }
                if ($features) {
                    $product->features = $features;
                }
            } else {
                $features = array();
                $sku_features = array();
            }

            if ($params && !empty($this->data['update_product_fields']['params'])) {
                $product->params = array_merge($product->params, $params);
            }
        }


        $this->mergeSkus($skus, $is_new_product);

        $product->skus = $skus;
        try {
            shopProductStocksLogModel::setContext(shopProductStocksLogModel::TYPE_IMPORT, 'Обмен через CommerceML');
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
            $this->error(sprintf('Error during import product with Ид "%s": %s', implode('#', $uuid), $ex->getMessage()));
            $this->stepException(__METHOD__, $ex);
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

        $exists_stocks = array();
        if (is_array($this->data['stock_setup'])) {
            $exists_stocks = $this->data['stock_setup'];
        } elseif (is_array($this->data['stock_forced'])) {
            $exists_stocks = $this->data['stock_forced'];
        }

        $sku_fields = array(
            'sku_name' => 'name',
            'sku'      => 'sku',
            'features' => 'features',
            //XXX update base price
        );

        if (isset($skus[-1]) && !isset($skus[-1]['id_1c'])) {
            if (count($skus) > 1) {
                unset($skus[-1]);
            } else {
                $this->error('invalid dummy sku: '.var_export($skus[-1], true));
            }
        }

        foreach ($skus as $id => & $sku) {
            if (($id > 0) && !count($sku['stock']) && ($sku['count'] !== null)) {
                $sku['stock'][0] = $sku['count'];
            }
            if (($id > 0) && isset($skus[-1]) && isset($sku['id_1c']) && isset($skus[-1]['id_1c']) && ($sku['id_1c'] == $skus[-1]['id_1c'])) {

                $_sku = $skus[-1];
                unset($skus[-1]);

                //update available property if it specified as boolean
                if (in_array($_sku['available'], array(false, true), true)) {
                    $_sku['available'] = intval($_sku['available']);
                } else {
                    unset($_sku['available']);
                }

                if ($this->pluginSettings('import_business_ru')) {
                    $update_fields = array_merge($update_fields, [
                        'sku'      => 'sku',
                        'sku_name' => 'sku_name',
                        //'features' => 'features'
                    ]);
                }

                foreach ($sku_fields as $setting => $field) {
                    if (empty($update_fields[$setting])) {
                        unset($_sku[$field]);
                    }
                }
                foreach ($_sku as $name => $value) {
                    if (empty($value) && in_array($name, array('name',))) {
                        unset($_sku[$name]);
                    }
                }
                if (!empty($_sku['stock'])
                    && isset($sku['stock'][0])
                    && !isset($_sku['stock'][0])
                    && (!empty($this->data['stock_setup']) || !empty($this->data['stock_forced']))
                ) {
                    // Создавать новые артикулы с нулевыми остатками
                    $_sku['stock'] += array_fill_keys($exists_stocks, 0);
                }
                $sku = array_merge($sku, $_sku);
                $sku['virtual'] = 0;
            }
            unset($sku);
        }

        if (isset($skus[-1])) {
            $skus[-1]['available'] = !!$skus[-1]['available'];
            if (!empty($stock_setup)) {
                // Создавать новые артикулы с нулевыми остатками
                if (isset($skus[-1]['stock'])) {
                    if (!isset($skus[-1]['stock'][0])) {
                        $skus[-1]['stock'] += array_fill_keys($stock_setup, 0);
                    }
                } else {
                    $skus[-1]['stock'] = array(
                        0 => 0,
                    );
                }
            }
            if (!isset($skus[-1]['stock'])) {
                $skus[-1]['stock'] = array();
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
        $encodings = array(
            'CP866',
        );

        if (false) {
            $extra_encodings = array(
                'CP932',
                'CP936',
                'CP950',
                'CP1251',
                'Windows-1251',
                'CP1252',
                'KOI8-R',
                'KOI8-U',
            );
            $encodings = array_merge($encodings, $extra_encodings);
        }
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
            $hint = '';
            if (!function_exists('iconv')) {
                $hint .= ' Требуется наличие PHP расширения iconv;';
            }
            if (!function_exists('zip_open') || !class_exists('ZipArchive')) {
                $hint .= ' Требуется наличие PHP расширения zlib или ZipArchive;';
            }
            if ($hint) {
                throw new waException("Ошибка чтения архива.".$hint);
            }
            if (class_exists('ZipArchive')) {
                $zip_archive = new ZipArchive();
                if ($zip_archive->open($this->data['zipfile']) === true) {
                    for ($i = 0; $i < $zip_archive->numFiles; $i++) {
                        $stat = $zip_archive->statIndex($i);
                        $entry_name = $stat['name'];
                        if (substr($entry_name, -1, 1) === '/') {
                            continue;
                        }
                        $matched = false;
                        if ($entry_name == $filename) {
                            $matched = true;
                        } else {
                            foreach ($encodings as $encoding) {
                                $entry_name_decoded = iconv($encoding, 'utf-8//IGNORE', $entry_name);
                                if ($entry_name_decoded && ($filename == $entry_name_decoded)) {
                                    $matched = true;
                                    break;
                                }
                            }
                        }

                        if ($matched) {
                            $exists = file_exists($target);
                            if ($exists) {
                                if ($stat['size'] != filesize($target)) {
                                    $exists = false;
                                    waFiles::delete($target);
                                }
                            }
                            if ($exists) {
                                $result = $target;
                            } else {
                                $stream = $zip_archive->getStream($entry_name);
                                if ($stream) {
                                    $extract_file = fopen($target, "wb");
                                    if ($extract_file) {
                                        while (!feof($stream)) {
                                            $content = fread($stream, 4096);
                                            fwrite($extract_file, $content);
                                        }
                                        fclose($extract_file);
                                        $result = $target;
                                        break;
                                    } else {
                                        $zip_archive->close();
                                        throw new waException("Ошибка извлечения файла из архива.");
                                    }
                                }
                            }

                            if (preg_match('@[\\/]@', $filename)) {
                                $this->data['files'][] = dirname($result).'/';
                                $this->data['files'] = array_unique($this->data['files']);
                            } else {
                                $this->data['files'][] = $result;
                            }
                            break;
                        }
                    }
                    $zip_archive->close();
                    if (!$result) {
                        throw new waException(sprintf("Файл %s не найден в архиве.", $filename));
                    }
                }
            } else {
                if (($zip = zip_open($this->data['zipfile'])) && is_resource($zip)) {
                    while ($zip_entry = zip_read($zip)) {
                        $entry_name = zip_entry_name($zip_entry);
                        if (substr($entry_name, -1, 1) === '/') {
                            continue;
                        }
                        $matched = false;
                        if ($entry_name == $filename) {
                            $matched = true;
                        } else {
                            foreach ($encodings as $encoding) {
                                $entry_name_decoded = iconv($encoding, 'utf-8//IGNORE', $entry_name);
                                if ($entry_name_decoded && ($filename == $entry_name_decoded)) {
                                    $matched = true;
                                    break;
                                }
                            }
                        }

                        if ($matched) {
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
                                $this->data['files'][] = $result;
                            }
                            break;
                        }
                    }
                    zip_close($zip);
                    if (!$result) {
                        throw new waException(sprintf("Файл %s не найден в архиве.", $filename));
                    }
                }
            }
        }

        return $result;
    }

    private function extractFile()
    {

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
        $group = false;
        if (!empty($product['category_id']) && isset($this->data['map'][self::STAGE_CATEGORY][$product['category_id']])) {
            $group = $this->data['map'][self::STAGE_CATEGORY][$product['category_id']];

        }

        $uuid = $this->formatGuid($product['id_1c'], $sku['id_1c']);

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
        $this->writeUnit($product);
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
     * @param             $sku
     */
    private function writeOffer($product, $sku)
    {
        $w = &$this->writer;
        $w->startElement('Предложение');
        $uuid = $this->formatGuid($product['id_1c'], $sku['id_1c']);
        $w->writeElement('Ид', $uuid);

        $this->writeName($product, $sku);
        $this->writeUnit($product);
        $prices = array(
            $this->data['price_type_uuid'] => $sku['price'],
        );

        if ($sku['purchase_price'] && !empty($this->data['purchase_price_type_uuid'])) {
            $prices[$this->data['purchase_price_type_uuid']] = $sku['purchase_price'];
        }
        $this->writePrices($prices, $product);
        if ($this->data['stock_id']) {
            $w->writeElement('Количество', ifset($sku['stock'][$this->data['stock_id']]));
        } else {
            $w->writeElement('Количество', $sku['count']);
        }

        $w->endElement(/*Предложение*/);
    }

    private function getAllUnits()
    {
        if (empty($this->data['units']) && class_exists('shopUnitModel')) {
            $this->data['units'] = (new shopUnitModel)->getAll();
        }

        return $this->data['units'];
    }

    private function getUnit($okei = '796', $f_name = 'Штука')
    {
        $unit = [];

        foreach ($this->getAllUnits() as $_unit) {
            if ($_unit['name'] === $f_name || (int) $_unit['okei_code'] === (int) $okei) {
                $unit = $_unit;
            }
        }

        if (empty($unit) && class_exists('shopUnitModel')) {
            $unit = [
                'okei_code'       => $okei,
                'name'            => $f_name,
                'short_name'      => $f_name,
                'storefront_name' => $f_name,
                'status'          => 1
            ];
            $un_id = (new shopUnitModel)->insert($unit);
            $_un['id'] = (string) $un_id;
            $this->data['units'][] = $_un;
        }

        return ifset($unit, 'id', '0');
    }

    private function writeUnit($product = [])
    {
        $unit = [
            'okei_code'  => '796',
            'name'       => 'Штука',
            'short_name' => 'шт',
        ];

        $stock_unit_id = ifset($product, 'stock_unit_id', '0');
        if ($this->data['fractional'] && !empty($stock_unit_id)) {
            $units = $this->getAllUnits();
            foreach ($units as $_unit) {
                if ((int) $_unit['id'] === (int) $stock_unit_id) {
                    $unit = $_unit;
                    break;
                }
            }
        }

        $this->writer->startElement('БазоваяЕдиница');
        $this->writer->writeAttribute('Код', $unit['okei_code']);
        $this->writer->writeAttribute('НаименованиеПолное', $unit['name']);
        if ($unit['okei_code'] === '796') {
            $this->writer->writeAttribute('МеждународноеСокращение', 'PCE');
        }
        if (!empty($unit['short_name'])) {
            $this->writer->text($unit['short_name']);
        }
        $this->writer->endElement(/*БазоваяЕдиница*/);
    }

    private function writePrices($prices, $product)
    {
        $stock_unit_id = ifset($product, 'stock_unit_id', '0');
        if ($this->data['fractional'] && !empty($stock_unit_id)) {
            foreach ($this->getAllUnits() as $unit) {
                if ($unit['id'] === $stock_unit_id) {
                    break;
                }
            }
        }

        $this->writer->startElement('Цены');
        foreach ($prices as $uuid => $price) {
            $this->writer->startElement('Цена');
            $this->writer->writeElement('ИдТипаЦены', $uuid);
            $this->writer->writeElement('ЦенаЗаЕдиницу', $this->price($price));
            $this->writer->writeElement('Валюта', $this->currency($product->currency));
            $this->writer->writeElement('Единица', ifset($unit, 'short_name', 'шт'));
            $this->writer->writeElement('Коэффициент', 1);
            $this->writer->endElement(/*Цена*/);
        }
        $this->writer->endElement(/*Цены*/);
    }

    /**
     * @param           $field
     * @param           $contact
     * @param waContact $c
     * @return null|string
     */
    private function getContactField($field, $contact, $c = null)
    {
        $value = null;
        if (($field !== null) && is_array($contact) && isset($contact[$field]) && (trim($contact[$field]) !== '')) {
            $value = trim($contact[$field]);
        } elseif ($c) {
            $value = $c->get($field, 'value');
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
        static $replace = null;

        if (empty($currency)) {
            $currency = $this->defaultPluginCurrency();
        }

        if ($currency == $this->defaultPluginCurrency()) {
            if ($replace === null) {
                $replace = $this->pluginSettings('currency_map');
            }
            if ($replace) {
                $currency = $replace;
            }
        }

        return $currency;
    }

    private function defaultCurrency()
    {
        static $default_currency;
        if (!$default_currency) {
            /**
             * @var shopConfig $config
             */
            $config = $this->getConfig();
            $default_currency = $config->getCurrency();
        }
        return $default_currency;
    }

    private function defaultPluginCurrency()
    {
        static $plugin_currency;

        if (empty($plugin_currency)) {
            $plugin_currency = $this->pluginSettings('currency');
            if (empty($plugin_currency)) {
                $plugin_currency = $this->defaultCurrency();
            }
        }
        return $plugin_currency;
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
     * @param array       $skus
     */
    private function fixSkuBasePriceSelectable(&$p, &$skus)
    {
        if (true || ($p->sku_type == shopProductModel::SKU_TYPE_SELECTABLE)) {
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

    private $guid2name_map;
    private $guid2name_changed = false;

    private function guid2name($guid, $name = null)
    {
        if (!is_array($this->guid2name_map)) {
            $file = wa()->getDataPath('plugins/cml1c/guid.php', false, 'shop');
            if (file_exists($file)) {
                $this->guid2name_map = include($file);
            }
            if (!is_array($this->guid2name_map)) {
                $this->guid2name_map = array();
            }
        }
        if ($name) {
            if (!isset($this->guid2name_map[$guid]) || ($this->guid2name_map[$guid] != $name)) {
                $this->guid2name_map[$guid] = $name;
                $this->guid2name_changed = true;
            }
        }
        return isset($this->guid2name_map[$guid]) ? $this->guid2name_map[$guid] : $guid;
    }

    private function formatGuid($id, $extra_id = null)
    {
        static $strategy = null;
        if ($strategy === null) {
            $strategy = $this->pluginSettings('guid_format');
            if (empty($strategy)) {
                $strategy = 'full';
            }
        }
        if (strpos($id, '#')) {
            list($id, $extra_id) = explode('#', $id, 2);
        }
        if (empty($extra_id)) {
            $extra_id = $id;
        }

        if (($strategy === 'full') || ($id != $extra_id)) {
            $uuid = $id.'#'.$extra_id;
        } else {
            $uuid = $id;
        }
        return $uuid;
    }

    private function formatOrderId($order_id)
    {
        if (!empty($this->data['export_orders_mask']) && (strpos($this->data['export_orders_mask'], '{$order.id}') !== false)) {
            $guid = str_replace('{$order.id}', $order_id, $this->data['export_orders_mask']);
        } else {
            $guid = $order_id;
        }

        return $guid;
    }

    private function formatContactId($contact_id)
    {
        if (!empty($this->data['export_contacts_mask']) && (strpos($this->data['export_contacts_mask'], '{$order.contact_id}') !== false)) {
            $guid = str_replace('{$order.contact_id}', $contact_id, $this->data['export_contacts_mask']);
        } else {
            $guid = $contact_id;
        }

        return $guid;
    }

    private static function getGuid($data, $default = null)
    {
        if (!is_array($data)) {
            return empty($data) ? $default : $data;
        } elseif (isset($data['id_1c'])) {
            return ifempty($data['id_1c'], $default);
        } elseif (isset($data['cml1c_id'])) {
            return ifempty($data['cml1c_id'], $default);
        } else {
            return $default;
        }
    }

    public function __destruct()
    {
        if ($this->reader) {
            $this->reader->close();
        }
        if ($this->guid2name_changed) {
            $file = wa()->getDataPath('plugins/cml1c/guid.php', false, 'shop');
            waUtils::varExportToFile($this->guid2name_map, $file);
        }
    }
}
