<?php

class shopCsvProductrunController extends waLongActionController
{
    const STAGE_CATEGORY = 'category';
    const STAGE_PRODUCT = 'product';
    const STAGE_SKU = 'sku';
    const STAGE_IMAGE = 'image';
    const STAGE_FILE = 'file';

    /**
     *
     * file reader
     * @var shopCsvReader
     */
    private $reader;

    /**
     *
     * file writer
     * @var shopCsvWriter
     */

    private $writer;
    /**
     *
     * @var shopProductsCollection
     */
    private $collection;

    protected function preExecute()
    {
        $this->getResponse()->addHeader('Content-type', 'application/json');
        $this->getResponse()->sendHeaders();
    }

    protected function init()
    {
        try {
            $this->data['timestamp'] = time();
            $this->data['direction'] = waRequest::post('direction', 'import');
            $type_model = new shopTypeModel();
            $this->data['types'] = array_map('intval', array_keys($type_model->getTypes()));
            switch ($this->data['direction']) {

                case 'export':
                    $this->initExport();
                    break;
                case 'import':
                default:
                    $this->data['direction'] = 'import';
                    $this->initImport();
                    break;
            }

            $stages = array_keys($this->data['count']);
            $this->data['current'] = array_fill_keys($stages, 0);
            $value = ($this->data['direction'] == 'import') ? ($this->emulate(null) ? array(
                'add'      => 0,
                'found'    => 0,
                'skip'     => 0,
                'rights'   => 0,
                'currency' => 0
            ) : array('new' => 0, 'update' => 0, 'skip' => 0, 'error' => 0, 'rights' => 0, 'currency' => 0)) : 0;
            $this->data['processed_count'] = array_fill_keys($stages, $value);

            $this->data['map'] = array();

            $this->data['memory'] = memory_get_peak_usage();
            $this->data['memory_avg'] = memory_get_usage();

            $this->data['timestamp'] = time();
        } catch (waException $ex) {
            $this->error($ex->getMessage());
            echo $this->json(
                array(
                    'error' => $ex->getMessage(),
                )
            );
            exit;
        }
    }

    private function initRouting()
    {
        $routing = wa()->getRouting();
        $app_id = $this->getAppId();
        $domain_routes = $routing->getByApp($app_id);
        foreach ($domain_routes as $domain => $routes) {
            foreach ($routes as $route) {
                if ($domain.'/'.$route['url'] == $this->data['config']['domain']) {
                    $routing->setRoute($route, $domain);

                    if ($types = ifempty($route['type_id'], array())) {
                        $types = array_map('intval', $types);
                        $this->data['types'] = array_intersect($this->data['types'], $types);
                    }
                    $this->data['base_url'] = $domain;
                    break;
                }
            }
        }
    }

    private function initImport()
    {
        $name = basename(waRequest::post('file'));
        if (empty($name)) {
            throw new waException('Empty import filename');
        }

        $config = wa('shop')->getConfig();
        /**
         * @var shopConfig $config ;
         */

        //TODO detect emulate & type of control
        $file = wa()->getTempPath('csv/upload/'.$name);
        $this->data['emulate'] = waRequest::post('emulate') ? array() : null;
        $this->data['rights'] = $this->getUser()->getRights('shop', 'settings');
        $this->data['new_features'] = array();

        $this->data['currencies'] = $config->getCurrencies();


        $this->data['type_id'] = waRequest::post('type_id', null, waRequest::TYPE_INT);
        if ($this->data['type_id'] && !in_array($this->data['type_id'], $this->data['types'])) {
            $this->data['type_id'] = reset($this->data['types']);
        }

        $map = waRequest::post('csv_map');

        if ($this->emulate()) {
            $this->reader = shopCsvReader::snapshot($file);
            if (!$this->reader) {
                throw new waException('CSV file not found');
            }
            $this->reader->rewind();
        } else {
            /*, waRequest::post('encoding', 'utf-8')*/
            //after upload encoding converted into utf-8
            $this->reader = new shopCsvReader($file, waRequest::post('delimiter', ';'));

            $header = $this->reader->header();

            foreach ($map as $id => &$target) {
                if (preg_match('@^f\+:(.+)$@', $target, $matches)) {
                    if ($this->data['rights']) {
                        $id = preg_replace('@\D.*$@', '', $id);
                        $feature = array(
                            'name'       => ifset($header[$id], 'csv feature'),
                            'type'       => shopFeatureModel::TYPE_VARCHAR,
                            'multiple'   => 0,
                            'selectable' => 0,
                        );
                        list($feature['type'], $feature['multiple'], $feature['selectable']) = explode(':', $matches[1]);
                        $feature['type'] = preg_replace('@([^\.]+\.)\1@', '$1', $feature['type']);
                        if (empty($feature_model)) {
                            $feature_model = new shopFeatureModel();
                        }
                        if (empty($type_features_model)) {
                            $type_features_model = new shopTypeFeaturesModel();
                        }
                        $feature['id'] = $feature_model->save($feature);
                        if ($this->data['type_id']) {
                            $type_features_model->updateByFeature($feature['id'], array($this->data['type_id']), false);
                        }
                        $target = 'features:'.$feature['code'];

                        $this->data['new_features'][$feature['code']] = array(
                            'id'    => $feature['id'],
                            'types' => (array)$this->data['type_id'],
                        );
                    } else {
                        unset($map[$id]);
                    }
                }
            }
            unset($target);
        }

        $map = array_flip($map);
        $this->reader->setMap($map);

        $this->data['file'] = serialize($this->reader);

        $this->data['primary'] = waRequest::post('primary', 'name');
        $this->data['secondary'] = waRequest::post('secondary', 'skus:-1:sku');
        $this->data['extra_secondary'] = false;
        switch ($this->data['secondary']) {
            case 'skus:-1:sku':
                if (isset($map['skus:-1:name']) && (intval($map['skus:-1:name']) >= 0)) {
                    $this->data['extra_secondary'] = 'skus:-1:name';
                }
                break;
            case 'skus:-1:name':
                if (isset($map['skus:-1:sku']) && (intval($map['skus:-1:sku']) >= 0)) {
                    $this->data['extra_secondary'] = 'skus:-1:sku';
                }
                break;
        }
        $upload_app = waRequest::post('upload_app', 'shop', waRequest::TYPE_STRING_TRIM);
        if ($upload_app != 'site') {
            $upload_app = 'shop';
        }

        $this->data['upload_path'] = preg_replace('@[\\\\/]+$@', '/', waRequest::post('upload_path', 'upload/images/').'/');
        $this->data['upload_path'] = preg_replace('@(^|/)(\.\.)/@', '$1/', $this->data['upload_path']);
        if (waSystem::getSetting('csv.upload_path') != $this->data['upload_path']) {
            $app_settings = new waAppSettingsModel();
            $app_settings->set('shop', 'csv.upload_path', $this->data['upload_path']);
        }

        $this->data['virtual_sku_stock'] = waRequest::post('virtual_sku_stock', '', waRequest::TYPE_STRING_TRIM);

        if ($upload_app == 'site') {
            $this->data['upload_path'] = wa()->getDataPath($this->data['upload_path'], true, 'site');
        } else {

            $this->data['upload_path'] = wa()->getDataPath($this->data['upload_path'], false, 'shop');
        }

        if (waSystem::getSetting('csv.upload_app') != $upload_app) {
            if (empty($app_settings)) {
                $app_settings = new waAppSettingsModel();
            }
            $app_settings->set('shop', 'csv.upload_app', $upload_app);
        }
        $this->data['ignore_category'] = !!waRequest::post('ignore_category', 0, waRequest::TYPE_INT);
        if (!in_array($this->data['primary'], array('name', 'url', 'null',))) {
            throw new waException(_w('Invalid primary field'));
        }
        if ($this->data['primary'] == 'null') {
            $this->data['primary'] = null;
        }
        if (!in_array($this->data['secondary'], array('skus:-1:sku', 'skus:-1:name',))) {
            throw new waException(_w('Invalid secondary field'));
        }
        $current = $this->reader->current();
        if (!empty($this->data['primary']) && (self::getData($current, $this->data['primary']) === null)) {
            throw new waException(_w('Empty primary CSV column'));
        }
        if (empty($this->data['primary']) && (self::getData($current, $this->data['secondary']) === null)) {
            throw new waException(_w('Empty secondary CSV column'));
        }
        $this->data['count'] = array(
            self::STAGE_FILE     => $this->reader ? $this->reader->size() : null,
            self::STAGE_CATEGORY => null,
            self::STAGE_PRODUCT  => null,
            self::STAGE_SKU      => null,
            self::STAGE_IMAGE    => null,
        );
    }

    private function initExport()
    {
        $hash = shopImportexportHelper::getCollectionHash();
        $this->data['export_category'] = !in_array($hash['type'], array('id', 'set', 'type'));

        $this->data['timestamp'] = time();
        $this->data['hash'] = $hash['hash'];
        $encoding = waRequest::post('encoding', 'utf-8');

        $options = array();

        $config = array(
            'encoding'  => $encoding,
            'delimiter' => waRequest::post('delimiter', ';'),
            'features'  => !!waRequest::post('features'),
            'images'    => !!waRequest::post('images'),
            'extra'     => !!waRequest::post('extra'),
            'domain'    => waRequest::post('domain'),
            'hash'      => $hash['hash'],
        );


        $map = shopCsvProductuploadController::getMapFields(true, $config['extra']);

        $this->data['composite_features'] = array();
        $features_model = new shopFeatureModel();
        if (!empty($config['features'])) {

            if (preg_match('@^id/(.+)$@', $this->data['hash'], $matches)) {
                $product_ids = array_unique(array_map('intval', explode(',', $matches[1])));
                $features = $features_model->getByProduct($product_ids);
                $feature_selectable_model = new shopProductFeaturesSelectableModel();
                $feature_ids = $feature_selectable_model->getFeatures($product_ids);
                if ($feature_ids = array_diff($feature_ids, array_keys($features))) {
                    $features += $features_model->getById($feature_ids);
                }

                $parents = array();
                foreach ($features as $feature) {
                    if (!empty($feature['parent_id'])) {
                        if (!isset($parents[$feature['parent_id']])) {
                            $parents[$feature['parent_id']] = $feature['parent_id'];
                        }
                    }
                }
                if ($parents) {
                    $features += $features_model->getById($parents);
                }

            } else {
                $features = $features_model->getAll();
            }
            if ($features) {
                $options['features'] = true;
                foreach ($features as $feature) {
                    if (!preg_match('/\.\d$/', $feature['code']) && ($feature['type'] != shopFeatureModel::TYPE_DIVIDER)) {
                        $map[sprintf('features:%s', $feature['code'])] = $feature['name'];
                        if ($encoding != 'UTF-8') {
                            $this->data['composite_features'][$feature['code']] = true;
                        }
                    }
                }
            }
        }
        $tax_model = new shopTaxModel();
        if ($taxes = $tax_model->getAll()) {
            $this->data['taxes'] = array();
            foreach ($taxes as $tax) {
                $this->data['taxes'][$tax['id']] = $tax['name'];
            }
        }

        if (!empty($config['images'])) {
            $sql = 'SELECT COUNT(1) AS `cnt` FROM `shop_product_images` GROUP BY `product_id` ORDER BY `cnt` DESC LIMIT 1';
            if ($cnt = $features_model->query($sql)->fetchField('cnt')) {
                $options['images'] = true;
                for ($n = 0; $n < $cnt; $n++) {
                    $field = sprintf('images:%d', $n);
                    $map[$field] = _w('Product images');
                }
            }

            if (isset($map['images'])) {
                unset($map['images']);
            }
        } else {
            if (isset($map['images'])) {
                unset($map['images']);
            }

            foreach (array_keys($map) as $field) {
                if (preg_match('@^images:\d+$@', $field)) {
                    unset($map[$field]);
                }
            }
        }

        $profile_helper = new shopImportexportHelper('csv:product:export');
        $profile = $profile_helper->setConfig($config);
        $profile_raw = waRequest::request('profile', array(), waRequest::TYPE_ARRAY);
        $profile_name = substr(waLocale::transliterate(ifempty($profile_raw['name'], $profile)), 0, 32);

        $name = sprintf('products(%s)_%s_%s.csv', $profile_name, date('Y-m-d'), strtolower($encoding));
        $name = preg_replace('@[^A-Za-z0-9\-\(\),_\.]+@', '', $name);
        $file = wa()->getTempPath('csv/download/'.$profile.'/'.$name);
        $this->writer = new shopCsvWriter($file, $config['delimiter'], $encoding);
        $this->writer->setMap($map);

        $this->data['file'] = serialize($this->writer);
        $this->data['map'][self::STAGE_CATEGORY] = null;
        $this->data['map'][self::STAGE_PRODUCT] = 0;
        $this->data['config'] = $config;
        $this->data['options'] = $options;

        $this->initRouting();


        $this->data['count'] = array(
            self::STAGE_PRODUCT  => $this->getCollection()->count(),
            self::STAGE_CATEGORY => 0,
            self::STAGE_SKU      => null,
            self::STAGE_IMAGE    => null,
        );

        if ($this->data['export_category']) {
            $model = new shopCategoryModel();
            if (preg_match('@^category/(\d+)$@', $this->data['hash'], $matches)) {
                $this->data['count'][self::STAGE_CATEGORY] = count($model->getPath($matches[1])) + 1;
                //TODO add subcategories for nested
                //$model->getTree($matches[1]);
            } else {
                $this->data['count'][self::STAGE_CATEGORY] = $model->countByField('type', shopCategoryModel::TYPE_STATIC);
            }
        }
    }

    private static function getData($data, $key)
    {
        $value = null;
        if (is_string($key) && strpos($key, ':')) {
            $key = explode(':', $key);
        }
        if (is_array($key)) {
            $value = $data;
            while (($key_chunk = array_shift($key)) !== null) {
                $value = ifset($value[$key_chunk]);
                if ($value === null) {
                    break;
                }
            }
        } else {
            /**
             * @var string $key
             */
            $value = ifset($data[(string)$key]);
        }

        return $value;
    }

    /**
     *
     * @return shopProductsCollection
     */
    private function getCollection()
    {
        static $id = null;

        if ($this->data['export_category'] && ($id !== ifset($this->data['map'][self::STAGE_CATEGORY], null))) {
            $this->collection = null;
        }

        if (!$this->collection) {
            $hash = null;
            if ($this->data['export_category']) {
                $id = $this->data['map'][self::STAGE_CATEGORY];
                $hash = 'search/category_id='.($id ? $id : '=null');
                if ($this->data['hash'] != '*') {
                    $hash .= '&'.str_replace('/', '_id=', $this->data['hash']);
                }
            } else {
                $hash = $this->data['hash'];
            }
            $options = array( //
            );
            $this->collection = new shopProductsCollection($hash, $options);
        }

        return $this->collection;
    }

    private function getStageReport($stage, $count, $wrapper = '<i class="icon16 %s"></i>%s', $separator = ', ')
    {
        static $strings;
        if (!$strings) {
            $strings = array(
                'add'       => array(
                    self::STAGE_CATEGORY => array /*_w*/
                    (
                                                  '%d new category to be added',
                                                  '%d new categories to be added',
                    ),
                    self::STAGE_PRODUCT  => array /*_w*/
                    (
                                                  '%d new product to be added',
                                                  '%d new products to be added',
                    ),
                    self::STAGE_SKU      => array /*_w*/
                    (
                                                  '%d new SKU to be added',
                                                  '%d new SKUs to be added',
                    ),
                    'icon'               => 'yes',

                ),
                'found'     => array(
                    self::STAGE_CATEGORY => array /*_w*/
                    (
                                                  '%d category to be updated',
                                                  '%d categories to be updated',
                    ),
                    self::STAGE_PRODUCT  => array /*_w*/
                    (
                                                  '%d product to be updated',
                                                  '%d products to be updated',
                    ),
                    self::STAGE_SKU      => array /*_w*/
                    (
                                                  '%d SKU to be updated',
                                                  '%d SKUs to be updated',
                    ),
                    'icon'               => 'yes',
                ),
                'collision' => array(
                    self::STAGE_CATEGORY => array /*_w*/
                    (
                                                  'Excessive declaration for a category on %d line',
                                                  'Excessive declaration for a category on %d lines',
                    ),
                    self::STAGE_PRODUCT  => array /*_w*/
                    (
                                                  'Excessive declaration for a product on %d line',
                                                  'Excessive declaration for a product on %d lines',
                    ),
                    self::STAGE_SKU      => array /*_w*/
                    (
                                                  'Excessive declaration for a SKU on %d line',
                                                  'Excessive declaration for a SKU on %d lines',
                    ),
                    'icon'               => 'exclamation',
                ),
                'new'       => array(
                    self::STAGE_CATEGORY => array /*_w*/
                    (
                                                  'Added %d category',
                                                  'Added %d categories',
                    ),
                    self::STAGE_PRODUCT  => array /*_w*/
                    (
                                                  'Added %d product',
                                                  'Added %d products',
                    ),
                    self::STAGE_SKU      => array /*_w*/
                    (
                                                  'Added %d SKU',
                                                  'Added %d SKUs'
                    ),
                    self::STAGE_IMAGE    => array /*_w*/
                    (
                                                  'Linked %d product image',
                                                  'Linked %d product images',
                    ),
                    'icon'               => 'yes',

                ),
                'update'    => array(
                    self::STAGE_CATEGORY => array /*_w*/
                    (
                                                  'Updated %d category',
                                                  'Updated %d categories',
                    ),
                    self::STAGE_PRODUCT  => array /*_w*/
                    (
                                                  'Updated %d product',
                                                  'Updated %d products',
                    ),
                    self::STAGE_SKU      => array /*_w*/
                    (
                                                  'Updated %d SKU',
                                                  'Updated %d SKUs',
                    ),
                    self::STAGE_IMAGE    => array /*_w*/
                    (
                                                  'Updated %d product image',
                                                  'Updated %d product images',
                    ),
                    'icon'               => 'yes',
                ),
                'skip'      => array(
                    self::STAGE_CATEGORY => array /*_w*/
                    (
                                                  'Ambiguous identification conditions for %d category',
                                                  'Ambiguous identification conditions for %d categories',
                    ),
                    self::STAGE_PRODUCT  => array /*_w*/
                    (
                                                  'Ambiguous identification conditions for %d product',
                                                  'Ambiguous identification conditions for %d products',
                    ),
                    self::STAGE_SKU      => array /*_w*/
                    (
                                                  'Ambiguous identification conditions for %d SKU',
                                                  'Ambiguous identification conditions for %d SKUs',
                    ),
                    self::STAGE_IMAGE    => array /*_w*/
                    (
                                                  'Ambiguous identification conditions for %d product image',
                                                  'Ambiguous identification conditions for %d product images',
                    ),
                    'icon'               => 'no-bw',
                ),
                'rights'    => array(
                    self::STAGE_PRODUCT => array /*_w*/
                    (
                                                 '%d product record was not updated due to insufficient access rights for you as Webasyst user',
                                                 '%d product records were not updated due to insufficient access rights for you as Webasyst user',
                    ),
                    'icon'              => 'no-bw',
                ),
                'currency'  => array(
                    self::STAGE_PRODUCT => array /*_w*/
                    (
                                                 '%d product has unknown currency',
                                                 '%d products have unknown currency',
                    ),
                    'icon'              => 'no-bw',
                ),
                'error'     => array(
                    self::STAGE_CATEGORY => array /*_w*/
                    (
                                                  '%d category imported with errors',
                                                  '%d categories imported with errors',
                    ),
                    'icon'               => 'no',
                ),
                0           => array(
                    self::STAGE_CATEGORY => array /*_w*/
                    (
                                                  '%d category',
                                                  '%d categories',
                    ),
                    self::STAGE_PRODUCT  => array /*_w*/
                    (
                                                  '%d product',
                                                  '%d products',
                    ),
                    self::STAGE_SKU      => array /*_w*/
                    (
                                                  '%d SKU',
                                                  '%d SKUs',
                    ),
                    self::STAGE_IMAGE    => array /*_w*/
                    (
                                                  '%d product image',
                                                  '%d product images',
                    ),
                    'icon'               => 'yes'
                ),
            );
        }
        $info = array();
        if (ifempty($count[$stage])) {
            foreach ((array)$count[$stage] as $type => $count) {
                if ($count) {
                    $args = $strings[$type][$stage];
                    $args[] = $count;
                    $info[] = sprintf($wrapper, $strings[$type]['icon'], htmlentities(call_user_func_array('_w', $args), ENT_QUOTES, 'utf-8'));
                }
            }
        }

        return implode($separator, $info);

    }

    public function execute()
    {
        try {
            parent::execute();
        } catch (waException $ex) {
            if ($ex->getCode() == '302') {
                echo $this->json(array('warning' => $ex->getMessage()));
            } else {
                echo $this->json(array('error' => $ex->getMessage()));
            }
        }
    }

    protected function isDone()
    {
        $done = true;
        foreach ($this->data['current'] as $stage => $current) {
            if ($current < $this->data['count'][$stage]) {
                $done = false;
                break;
            }
        }

        return $done;
    }

    protected function restart()
    {
        //rewind procedure (change import options & etc)
    }

    /**
     * @uses stepExport
     * @uses stepImport
     */
    protected function step()
    {
        $method_name = 'step'.ucfirst($this->data['direction']);
        $result = false;
        try {
            if (method_exists($this, $method_name)) {

                $result = $this->$method_name();
                switch ($this->data['direction']) {
                    case 'import':
                        $this->data['file'] = serialize($this->reader);
                        break;
                    case 'export':
                        $this->data['file'] = serialize($this->writer);
                        break;
                }
            } else {
                $this->error(sprintf("Unsupported direction %s", $this->data['direction']));
            }
        } catch (Exception $ex) {
            sleep(5);
            $this->error($this->data['direction'].': '.$ex->getMessage()."\n".$ex->getTraceAsString());
        }

        return $result && !$this->isDone();
    }

    /**
     * @return bool
     * @uses stepImportCategory
     * @uses stepImportProduct
     * @uses stepImportSku
     * @uses stepImportImage
     * @usedby step
     */
    private function stepImport()
    {
        $result = false;
        if ($this->data['count'][self::STAGE_IMAGE] > $this->data['current'][self::STAGE_IMAGE]) {
            $result = $this->stepImportImage();
        } else {
            if ($this->reader->next() && ($current = $this->reader->current())) {
                $this->data['current'][self::STAGE_FILE] = $this->reader->offset();
                if ($type = self::getDataType($current)) {
                    $method_name = 'stepImport'.ucfirst($type);
                    if (method_exists($this, $method_name)) {
                        $result = $this->{$method_name}($current);
                    } else {
                        $this->error(sprintf("Unsupported import data type %s", $type));
                    }
                    if (false) {
                        //TODO write
                        $row = $this->reader->getTableRow();
                    }
                }
            } elseif (!$this->reader->valid()) {
                $this->data['current'][self::STAGE_FILE] = $this->reader->offset();
            }
        }

        return $result;
    }

    /**
     *
     * @param array $data
     * @return shopProduct
     */
    private function findProduct(&$data)
    {
        static $currencies;
        static $model;
        static $sku_model;
        /**
         * @var shopTypeFeaturesModel $type_features_model
         */
        static $type_features_model;
        if (empty($model)) {
            $model = new shopProductModel();
        }
        if (empty($currencies)) {
            $currencies = array();
            $config = wa()->getConfig();
            /**
             * @var shopConfig $config
             */
            $c = $config->getCurrency();
            $currencies[$c] = $c;
            foreach ($config->getCurrencies() as $row) {
                $currencies[$row['code']] = $row['code'];
            }
        }

        if (!empty($data['skus'][-1]['stock'])) {
            $per_stock = false;
            $stock =& $data['skus'][-1]['stock'];
            foreach ($stock as $id => & $count) {
                if ($count === '') {
                    $count = null;
                } else {
                    $count = intval($count);
                    if ($id) {
                        $per_stock = true;
                    }
                }
            }
            unset($count);
            if ($per_stock) {
                if (isset($stock[0])) {
                    unset($stock[0]);
                }
            } else {
                $count = ifset($stock[0]);
                $stock = array(
                    0 => $count,
                );
            }

            unset($stock);
        }

        $stack = ifset($this->data['map'][self::STAGE_CATEGORY], array());
        $category_id = end($stack);
        if (!$category_id) {
            $category_id = null;
        }
        $primary = $this->data['primary'];
        $fields = false;
        if (empty($primary)) {
            $keys = explode(':', $this->data['secondary']);
            if (empty($sku_model)) {
                $sku_model = new shopProductSkusModel();
            }
            $sku_fields = array(
                end($keys) => self::getData($data, $keys),
            );
            //hack for empty SKU code ???
            if (false && (reset($sku_fields) === '') && $this->data['extra_secondary']) {
                $extra_keys = explode(':', $this->data['extra_secondary']);
                $sku_fields[end($extra_keys)] = self::getData($data, $this->data['extra_secondary']);
            }
            if ($sku = $sku_model->getByField($sku_fields)) {
                $fields = array(
                    'category_id' => $category_id,
                    'id'          => $sku['product_id'],
                );
            }

        } elseif (!empty($primary)) {
            $fields = array(
                'category_id' => $category_id,
                $primary      => ifset($data[$primary], ''),
            );
        }

        if ($fields && $this->data['ignore_category']) {
            unset($fields['category_id']);
        }

        $key = 'p';

        if ($fields && ($current_data = $model->getByField($fields))) {
            $product = new shopProduct($current_data['id']);
            $data['type_id'] = ifempty($current_data['type_id'], $this->data['type_id']);
            if (!empty($current_data['tax_id'])) {
                $data['tax_id'] = $current_data['tax_id'];
            }
            if (isset($data['currency']) && !isset($currencies[$data['currency']])) {
                $this->data['processed_count'][self::STAGE_PRODUCT]['currency']++;
                $data['currency'] = reset($currencies);
            }
            if (!empty($data['skus'])) {
                $data['sku_id'] = ifempty($current_data['sku_id'], -1);
            }
            foreach ($product->skus as $sku_id => $current_sku) {
                if (empty($data['skus'][$sku_id])) {
                    if (!count($current_sku['stock']) && ($current_sku['count'] !== null)) {
                        $current_sku['stock'][0] = $current_sku['count'];
                    }
                    $data['skus'][$sku_id] = $current_sku;
                }
            }
            $key .= ':u:'.$product->getId();
        } else {
            $product = new shopProduct();
            if ($category_id) {
                $data['categories'] = array($category_id);
            }
            $data['currency'] = ifempty($data['currency'], reset($currencies));
            if (!isset($currencies[$data['currency']])) {
                $this->data['processed_count'][self::STAGE_PRODUCT]['currency']++;
                $data['currency'] = reset($currencies);
            }

            if (!empty($data['skus'])) {
                $sku = reset($data['skus']);
                $data['sku_id'] = key($data['skus']);
                if (!isset($sku['available'])) {
                    $sku['available'] = true;
                    $data['skus'][$data['sku_id']] = $sku;
                }
            }
            $key .= ':i:'.$this->getKey($fields);
        }
        if (!empty($data['features'])) {
            foreach ($data['features'] as $feature => & $values) {
                if (is_array($values)) {

                } elseif (preg_match('/^<\{(.*)\}>$/', $values, $matches)) {
                    if (!isset($data['features_selectable'])) {
                        $data['features_selectable'] = array();
                    }
                    if ($values = explode(',', $matches[1])) {
                        foreach ($values as &$value) {
                            if (preg_match('@^(.+)=([\+\-]?(\d+|\.\d+|\d\.\d))$@', $value, $matches)) {
                                $value = array(
                                    'value' => trim($matches[1]),
                                    'price' => $matches[2],
                                );
                            } else {
                                $value = array(
                                    'value' => trim($value),
                                );
                            }
                            unset($value);
                        }

                        $data['features_selectable'][$feature] = array(
                            'values' => $values,
                        );

                        if (!empty($this->data['virtual_sku_stock']) && isset($data['skus'][-1]['stock'])) {
                            $stock = $data['skus'][-1]['stock'];
                            switch ($this->data['virtual_sku_stock']) {
                                case 'distribute':
                                    if (is_array($stock)) {
                                        foreach ($stock as &$stock_item) {
                                            $stock_item = $stock_item / count($values);
                                            unset($stock_item);
                                        }
                                    } else {
                                        $stock = $stock / count($values);
                                    }
                                    $data['features_selectable'][$feature]['stock'] = $stock;
                                    break;
                                case 'set':
                                    $data['features_selectable'][$feature]['stock'] = $stock;
                                    break;
                            }
                        }
                        $product->sku_type = shopProductModel::SKU_TYPE_SELECTABLE;
                        if (isset($data['skus'][-1])) {
                            if (!isset($data['base_price_selectable'])) {
                                $data['base_price_selectable'] = ifset($data['skus'][-1]['price']);
                            }

                            if (!isset($data['purchase_price_selectable'])) {
                                $data['purchase_price_selectable'] = ifset($data['skus'][-1]['purchase_price']);
                            }

                            if (!isset($data['compare_price_selectable'])) {
                                $data['compare_price_selectable'] = ifset($data['skus'][-1]['compare_price']);
                            }
                        }
                        unset($data['skus']);
                    }
                    unset($data['features'][$feature]);
                } elseif (preg_match('/^\{(.*)\}$/', $values, $matches)) {
                    $values = explode(',', $matches[1]);
                }
            }
            unset($values);
        }

        $this->findTax($data);
        $access = $this->findType($data);
        if ($access) {
            $access = !$product->type_id || in_array($product->type_id, $this->data['types']);
        }
        if ($access) {
            $product->__hash = $key;
            foreach ($this->data['new_features'] as $code => &$feature) {
                if (isset($data['features'][$code]) || isset($data['features_selectable'][$code])) {
                    if ($data['type_id'] && !in_array($data['type_id'], $feature['types'])) {
                        if (empty($type_features_model)) {
                            $type_features_model = new shopTypeFeaturesModel();
                        }
                        $type_features_model->updateByFeature($feature['id'], array($data['type_id']), false);

                        $feature['types'][] = $data['type_id'];
                    }
                }
                unset($feature);
            }
        }

        return $access ? $product : null;
    }

    private function castSku(&$sku)
    {
        $prices = array(
            'price',
            'compare_price',
            'purchase_price',
        );
        foreach ($prices as $field) {
            if (isset($sku[$field]) && (trim($sku[$field]) === '')) {
                unset($sku[$field]);
            }
        }

    }

    private function findType(&$data)
    {
        static $types = array();
        /**
         * @var shopTypeModel $model
         */
        static $model;
        if (!empty($data['type_name'])) {
            $type = mb_strtolower(self::flatData($data['type_name']));
            if (!isset($types[$type])) {
                if (!$model) {
                    $model = new shopTypeModel();
                }
                if ($type_row = $model->getByName($type)) {
                    $types[$type] = $type_row['id'];
                } else {
                    if (!$this->data['rights']) {
                        $types[$type] = $data['type_id'] = false;
                    } else {
                        $types[$type] = $model->insert(array('name' => $data['type_name']));
                        if (empty($this->data['types'])) {
                            $this->data['types'] = array();
                        }
                        $this->data['types'][] = intval($types[$type]);
                    }

                }
            }
            $data['type_id'] = $types[$type];
        } else {
            $data['type_id'] = ifempty($data['type_id'], $this->data['type_id']);
        }
        if (isset($data['type_name'])) {
            unset($data['type_name']);
        }

        /* check rights per product type */

        return in_array($data['type_id'], $this->data['types']);
    }

    private function findTax(&$data)
    {
        static $taxes;
        static $tax_model;
        if (ifset($data['tax_name'])) {
            if (empty($data['tax_name'])) {
                unset($data['tax_id']);
            } else {
                $tax = mb_strtolower(self::flatData($data['tax_name']));
                if (!isset($taxes[$tax])) {
                    if (!$tax_model) {
                        $tax_model = new shopTaxModel();
                    }
                    if ($tax_row = $tax_model->getByName($tax)) {
                        $taxes[$tax] = $tax_row['id'];
                    } else {
                        $taxes[$tax] = null;
                    }
                }
                $data['tax_id'] = $taxes[$tax];
            }
            unset($data['tax_name']);
        }
    }

    /**
     * @usedby stepImport
     * @param $data
     * @return bool
     */
    private function stepImportProduct($data)
    {
        $empty = $this->reader->getEmpty();
        $data += $empty;
        if ($product = $this->findProduct($data)) {
            $target = $product->getId() ? 'update' : 'new';
            if (!$this->emulate($product->__hash)) {
                shopProductStocksLogModel::setContext(shopProductStocksLogModel::TYPE_IMPORT);
                $product->save($data);
                shopProductStocksLogModel::clearContext();

                $this->data['map'][self::STAGE_PRODUCT] = $product->getId();
                if (!empty($data['images'])) {
                    foreach ($data['images'] as & $image) {
                        if (strpos($image, ',')) {
                            $images = explode(',', $image);
                            $image = end($images);
                        }
                    }
                    unset($image);
                    $this->data['map'][self::STAGE_IMAGE] = $data['images'];
                    $this->data['count'][self::STAGE_IMAGE] += count($data['images']);
                }
            }
            $this->data['processed_count'][self::STAGE_PRODUCT][$target]++;
        }

        return true;
    }

    /**
     * @usedby stepImport
     * @param $data
     * @return bool
     */
    private function stepImportSku($data)
    {
        static $sku_primary;
        static $sku_secondary;
        static $empty_sku;
        static $empty;
        if (!isset($sku_primary)) {
            $secondary = explode(':', $this->data['secondary']);
            $sku_primary = end($secondary);
        }
        if (!isset($sku_secondary)) {
            $extra_secondary = explode(':', $this->data['extra_secondary']);
            $sku_secondary = end($extra_secondary);
        }


        if (!isset($empty)) {
            $empty = $this->reader->getEmpty();
        }
        if (!isset($empty_sku)) {
            $empty_sku = ifset($empty['skus'][-1], array());
        }
        $data += $empty;
        if ($product = $this->findProduct($data)) {
            $item_sku_id = false;
            $current_id = ifset($this->data['map'][self::STAGE_PRODUCT]);
            $id = $product->getId();
            if ($this->emulate()) {
                $target = $id ? 'found' : 'add';
                $target_sku = 'add';
            } else {
                $target = $id ? 'update' : 'new';
                $target_sku = 'new';
            }

            $key = null;
            $sku_only = false;

            $product_exists = $this->emulate() ? ($product->__hash == $current_id) : $id;

            if ($id && isset($data['skus'][-1])) {
                if ($this->emulate() ? ($product->__hash == $current_id) : ($id == $current_id)) {
                    $sku_only = true;
                }

                $sku = $data['skus'][-1] + $empty_sku;
                $this->castSku($sku);

                unset($data['skus'][-1]);
                $item_sku_id = -1;
                $matches = 0;
                foreach ($product->skus as $sku_id => $current_sku) {

                    if ($current_sku[$sku_primary] === ifset($sku[$sku_primary], '')) {
                        //extra workaround for empty primary attribute
                        if (false && ($current_sku[$sku_primary] === '') && $sku_secondary) {
                            if (ifset($sku[$sku_secondary], '') !== $current_sku[$sku_secondary]) {
                                continue;
                            }
                        }

                        if (++$matches == 1) {
                            $item_sku_id = $sku_id;
                            $target_sku = $this->emulate() ? 'found' : 'update';
                            $sku = array_merge($current_sku, $sku);

                        } else {
                            $target_sku = 'skip';
                            $item_sku_id = false;
                            break;
                        }
                    }
                }

                if ($item_sku_id !== false) {
                    if (($item_sku_id < 0) && !isset($sku['available'])) {
                        $sku['available'] = true;
                    }
                    if (!$sku_only && !$product->skus) {
                        $data['sku_id'] = $item_sku_id;
                    }
                    $data['skus'][$item_sku_id] = $sku;
                    $key = 's:';
                    if ($item_sku_id > 0) {
                        $key .= 'u:'.$item_sku_id;
                    } else {
                        $key .= 'i:';
                        $key .= $this->getKey(
                            array(
                                $sku_primary => ifset($sku[$sku_primary], ''),
                                //   $sku_secondary => $sku[$sku_secondary],
                            )
                        );

                    }
                } else {
                    unset($data['skus']);
                }
            } elseif (isset($data['skus'][-1])) {
                if ($this->emulate() && ($product->__hash == $current_id)) {
                    $sku_only = true;
                    $item_sku_id = true;
                } else {

                }
                $sku = $data['skus'][-1] + $empty_sku;
                $key = 's:';
                $key .= 'i:';
                $key .= $this->getKey(
                    array(
                        $sku_primary => ifset($sku[$sku_primary], ''),
                        //$sku_secondary => $sku[$sku_secondary],
                    )
                );
            } elseif (!empty($data['features_selectable'])) {
                if ($product_exists) {
                    $target = $this->emulate() ? 'found' : 'update';
                }
                //TODO recount virtual SKUs count
                $key = 's:v:';
                $key .= $this->getKey($data['features_selectable']);
                if ($id) {
                    $target_sku = $this->emulate() ? 'found' : 'update';
                } else {
                    //add
                }
            }

            shopProductStocksLogModel::setContext(shopProductStocksLogModel::TYPE_IMPORT);
            if ($sku_only || empty($this->data['primary'])) {
                if ($product_exists && ($item_sku_id !== false)) {
                    if (!$this->emulate($product->__hash, $key)) {
                        $truncated_data = array(
                            'skus' => $data['skus'],
                        );
                        $virtual_fields = array();
                        foreach ($virtual_fields as $field) {
                            if (isset($data[$field])) {
                                $truncated_data['skus'][$item_sku_id][$field] = $data[$field];
                            }
                        }

                        if (isset($data['features'])) {
                            $model = new shopFeatureModel();
                            $features = $model->getMultipleSelectableFeaturesByType($data['type_id'], 'code');
                            if (!$features) {
                                $features = array();
                            }
                            $features['weight'] = true;
                            foreach (array_keys($features) as $code) {
                                if (isset($data['features'][$code])) {
                                    if (!isset($truncated_data['skus'][$item_sku_id]['features'])) {
                                        $truncated_data['skus'][$item_sku_id]['features'] = array();
                                    }
                                    $truncated_data['skus'][$item_sku_id]['features'] [$code] = $data['features'][$code];
                                }
                            }
                        }

                        $product->save($truncated_data);
                        $this->data['map'][self::STAGE_PRODUCT] = $product->getId();
                    } else {
                        $this->data['map'][self::STAGE_PRODUCT] = $product->__hash;
                    }
                }
            } else {
                if (!$this->emulate($product->__hash, $key)) {
                    $product->save($data);
                    $this->data['map'][self::STAGE_PRODUCT] = $product->getId();
                    if (!empty($data['images'])) {
                        $this->data['map'][self::STAGE_IMAGE] = $data['images'];
                        $this->data['count'][self::STAGE_IMAGE] += count($data['images']);
                    }
                } else {
                    $this->data['map'][self::STAGE_PRODUCT] = $product->__hash;
                }

                $this->data['processed_count'][self::STAGE_PRODUCT][$target]++;
            }

            shopProductStocksLogModel::clearContext();

            if ($product->getId() || $this->emulate()) {
                $this->data['processed_count'][self::STAGE_SKU][$target_sku]++;
            }
        } else {
            $this->data['processed_count'][self::STAGE_PRODUCT]['rights']++;
        }

        return true;
    }

    private function stepImportImage()
    {
        /**
         * @var shopProductImagesModel $model
         */
        static $model;
        if (!is_array($this->data['map'][self::STAGE_IMAGE]) && $this->data['map'][self::STAGE_IMAGE]) {
            $this->data['map'][self::STAGE_IMAGE] = array($this->data['map'][self::STAGE_IMAGE]);
        }
        if ($file = reset($this->data['map'][self::STAGE_IMAGE])) {
            if (!$model) {
                $model = new shopProductImagesModel();
            }
            //TODO store image id & if repeated - skip it
            $target = 'new';
            $u = @parse_url($file);
            $_is_url = false;
            if (!$u || !(isset($u['scheme']) && isset($u['host']) && isset($u['path']))) {

            } elseif (in_array($u['scheme'], array('http', 'https', 'ftp', 'ftps'))) {
                $_is_url = true;
            } else {
                $target = 'skip';
                $file = null;
                $this->error(sprintf('Unsupported file source protocol', $u['scheme']));
            }

            $search = array(
                'product_id' => $this->data['map'][self::STAGE_PRODUCT],
                'ext'        => pathinfo($file, PATHINFO_EXTENSION),
            );

            try {
                $name = preg_replace('@[^a-zA-Zа-яА-Я0-9\._\-]+@', '', basename(urldecode($file)));
                if ($_is_url) {
                    $pattern = sprintf('@/(%d)/images/(\\d+)/\\2\\.(\\d+(x\\d+)?)\\.([^\\.]+)$@', $search['product_id']);
                    if (preg_match($pattern, $file, $matches)) {
                        $image = array(
                            'product_id' => $matches[1],
                            'id'         => $matches[2],
                            'ext'        => $matches[5],
                        );
                        if ((strpos($file, shopImage::getUrl($image, $matches[3])) !== false) && $model->getByField($image)) {
                            #skip local file
                            $target = 'skip';
                            $file = null;
                        }
                    }
                    if ($file) {
                        waFiles::upload($file, $file = wa()->getTempPath('csv/upload/images/'.waLocale::transliterate($name, 'en_US')));
                    }
                } elseif ($file) {
                    $file = $this->data['upload_path'].$file;
                }

                if ($file && file_exists($file)) {
                    if ($image = waImage::factory($file)) {
                        $search['original_filename'] = $name;
                        $data = array(
                            'product_id'        => $this->data['map'][self::STAGE_PRODUCT],
                            'upload_datetime'   => date('Y-m-d H:i:s'),
                            'width'             => $image->width,
                            'height'            => $image->height,
                            'size'              => filesize($file),
                            'original_filename' => $name,
                            'ext'               => pathinfo($file, PATHINFO_EXTENSION),
                        );
                        if ($exists = $model->getByField($search)) {
                            $data = array_merge($exists, $data);
                            $thumb_dir = shopImage::getThumbsPath($data);
                            $back_thumb_dir = preg_replace('@(/$|$)@', '.back$1', $thumb_dir, 1);
                            $paths[] = $back_thumb_dir;
                            waFiles::delete($back_thumb_dir); // old backups
                            if (file_exists($thumb_dir)) {
                                if (!(waFiles::move($thumb_dir, $back_thumb_dir) || waFiles::delete($back_thumb_dir)) && !waFiles::delete($thumb_dir)) {
                                    throw new waException(_w("Error while rebuild thumbnails"));
                                }
                            }

                        }

                        $image_changed = false;

                        /**
                         * TODO move it code into product core method
                         */

                        /**
                         * Extend add/update product images
                         * Make extra workup
                         * @event image_upload
                         */
                        $event = wa()->event('image_upload', $image);
                        if ($event) {
                            foreach ($event as $result) {
                                if ($result) {
                                    $image_changed = true;
                                    break;
                                }
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

                        if ($image_changed) {
                            $image->save($image_path);
                            /**
                             * @var shopConfig $config
                             */
                            $config = $this->getConfig();
                            if ($config->getOption('image_save_original') && ($original_file = shopImage::getOriginalPath($data))) {
                                waFiles::copy($file, $original_file);
                            }
                        } else {
                            waFiles::copy($file, $image_path);
                        }


                        $this->data['processed_count'][self::STAGE_IMAGE][$target]++;
                    } else {
                        $this->error(sprintf('Invalid image file', $file));
                    }
                } elseif ($file) {
                    $this->error(sprintf('File %s not found', $file));
                    $target = 'skip';
                    $this->data['processed_count'][self::STAGE_IMAGE][$target]++;
                } else {
                    $this->data['processed_count'][self::STAGE_IMAGE][$target]++;
                }
            } catch (Exception $e) {
                $this->error($e->getMessage());
                //TODO skip on repeated error
            }

            array_shift($this->data['map'][self::STAGE_IMAGE]);
            ++$this->data['current'][self::STAGE_IMAGE];
            if ($_is_url) {
                waFiles::delete($file);
            }
        }

        return true;

    }

    /**
     * @usedby stepImport
     * @param $data
     * @return bool
     */
    private function stepImportCategory($data)
    {
        /**
         * @var shopCategoryModel $model
         */
        static $model;
        $empty = $this->reader->getEmpty();
        $data += $empty;
        if (!$model) {
            $model = new shopCategoryModel();
        }
        if (!isset($this->data['map'][self::STAGE_CATEGORY])) {
            $this->data['map'][self::STAGE_CATEGORY] = array();
        }
        $stack =& $this->data['map'][self::STAGE_CATEGORY];
        if (preg_match('/^(!{1,})(.+)$/', trim(ifset($data['name'])), $matches)) {
            $data['name'] = $matches[2];
            $depth = strlen($matches[1]);
            $stack = array_slice($stack, 0, $depth);
            $parent_id = end($stack);
            if (!$parent_id) {
                $parent_id = 0;
            }
        } else {
            $stack = array();
            $parent_id = 0;
        }
        $primary = $this->data['primary'];
        if (strpos($primary, 'skus:') === 0) {
            $primary = 'name';
        }

        $fields = array(
            'parent_id' => $parent_id,
        );

        if ($primary) {
            $fields[$primary] = ifset($data[$primary]);
        }

        try {
            self::filterEmptyRows($data, array('url'));
            $key = 'c:';
            if ($current_data = $model->getByField($fields)) {
                $key .= 'u:'.$current_data['id'];
                $stack[] = $current_data['id'];
                if (!$this->emulate($key)) {
                    $target = 'update';
                    if (!$model->update($current_data['id'], $data)) {
                        $target = 'error';
                    }
                } else {
                    $target = 'found';
                }
            } else {
                $key .= 'a:';
                if (!$this->emulate()) {
                    $id = $model->add($data, $parent_id);
                    $model->move($id, null, $parent_id);
                    $stack[] = $id;
                    $target = 'new';
                } else {
                    $stack[] = $this->getKey($fields);
                    $target = 'add';
                    $key .= implode($stack);
                    $this->emulate($key);
                }

            }
        } catch (waDbException $ex) {
            $target = 'error';
            $this->error($ex->getMessage());
        }
        $this->data['processed_count'][self::STAGE_CATEGORY][$target]++;

        return true;
    }

    private function getKey($fields)
    {
        return md5(strtolower(var_export($fields, true)));
    }

    private function emulate($key = null, $second_key = '')
    {
        if (ifset($this->data['emulate']) === null) {
            return false;
        } else {
            if (!empty($key)) {

                $this->data['emulate'][$this->reader->key()] = $key.'|'.$second_key;
            }

            return true;
        }
    }

    public static function getDataType($data)
    {
        static $non_category_fields = array(
            "skus",
            "features",
            "total_sales",
        );
        $type = self::STAGE_CATEGORY;
        foreach ($non_category_fields as $field) {
            if (!empty($data[$field])) {
                $type = self::STAGE_PRODUCT;
                break;
            }
        }
        if (($type == self::STAGE_PRODUCT) /*&& !empty($data['skus']) && ($sku = reset($data['skus'])) && (!empty($sku['sku']) || !empty($sku['name']))*/) {
            $type = self::STAGE_SKU;
        }

        return $type;
    }

    /**
     * @return bool
     * @usedby self::step()
     */
    private function stepExport()
    {
        $res = $this->stepExportProduct($this->data['current'], $this->data['count'], $this->data['processed_count']);
        if (!$res && $this->data['export_category']) {
            $res = $this->stepExportCategory($this->data['current'], $this->data['count'], $this->data['processed_count']);
        }

        return $res;
    }

    protected function finish($filename)
    {
        $cleanup = !!$this->getRequest()->post('cleanup');
        if ($cleanup && !$this->emulate()) {
            if (!empty($this->data['processed_count'][self::STAGE_PRODUCT])) {
                $params = array(
                    'type' => 'CSV',
                );
                switch ($this->data['direction']) {
                    case 'import':
                        $action = 'catalog_import';
                        break;
                    case 'export':
                        $action = 'catalog_export';
                        break;
                }
                if (!empty($action)) {
                    $this->logAction($action, $params);
                }

            }
            if ($this->reader) {
                $this->reader->delete(true);
            }
        }

        return $cleanup;
    }

    protected function report()
    {
        $wrapper = '<li><i class="icon16 %s"></i>%s<br/></li>';
        $chunks = array();
        foreach ($this->data['processed_count'] as $stage => $current) {
            if ($current) {
                if ($data = $this->getStageReport($stage, $this->data['processed_count'], $wrapper, '')) {
                    $chunks[] = $data;
                }
            }
        }
        $collisions = array();
        if (!empty($this->data['emulate'])) {
            $callback = create_function('$a', 'return count($a)>1;');
            $emulate = array();
            foreach ($this->data['emulate'] as $row => $key) {
                if (!isset($emulate[$key])) {
                    $emulate[$key] = array();
                }
                $emulate[$key][] = $row;
            }
            if ($collision = array_filter($emulate, $callback)) {
                foreach ($collision as $key => $rows) {
                    switch (substr($key, 0, 1)) {
                        case 'c':
                            $stage = self::STAGE_CATEGORY;
                            break;
                        case 'p':
                            $stage = self::STAGE_PRODUCT;
                            break;
                        default:
                            $stage = false;
                            break;
                    }
                    if ($stage) {
                        if (!isset($collisions[$stage]['collision'])) {
                            $collisions[$stage]['collision'] = 0;
                        }
                        ++$collisions[$stage]['collision'];
                    }
                };
                foreach ($collisions as $stage => $count) {
                    if ($count && ($data = $this->getStageReport($stage, $collisions, $wrapper, ''))) {
                        $chunks[] = $data;
                    }
                }
            }
        }
        $class = 's-csv-importexport-stats';
        if (!$this->emulate()) {
            $class .= ' done';
        }
        $report = '<div class="'.$class.'">';
        if ($this->data['direction'] == 'import') {
            if ($this->emulate()) {
                $report .= '<p>'._w('The following information were gathered from the CSV file and is ready to be imported:').'</p>';
            } else {
                $report .= '<h2>'._w('Import completed!').'</h2>';
            }
        } else {
            $report .= '<h2>'._w('Export completed!').'</h2>';
        }

        $report .= '<ul>'.implode($chunks).'</ul>';

        if (!empty($this->data['timestamp']) && !$this->emulate()) {
            $interval = time() - $this->data['timestamp'];
            $interval = sprintf(_w('%02d hr %02d min %02d sec'), floor($interval / 3600), floor($interval / 60) % 60, $interval % 60);
            if ($this->data['direction'] == 'import') {
                $format = _w('Total import time: <strong>%s</strong>');
            } else {
                $format = _w('Total export time: <strong>%s</strong>');
            }
            $report .= '<p>'.sprintf($format, $interval).'</p>';
        }

        if (($this->data['direction'] == 'import') && $this->emulate()) {
            $report .= '<p class="hint">';
            $report .= _w('If estimation appears to be not valid, alter column assignment table and primary columns and review settings again.');
            $report .= '</p>';
        }
        $report .= '</div>';

        return iconv('UTF-8', 'UTF-8//IGNORE', $report);
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
            'progress'   => 0.0,
            'ready'      => $this->isDone(),
            'count'      => empty($this->data['count']) ? false : $this->data['count'],
            'memory'     => sprintf('%0.2fMByte', $this->data['memory'] / 1048576),
            'memory_avg' => sprintf('%0.2fMByte', $this->data['memory_avg'] / 1048576),
        );

        $stage_count = 0; //count($this->data['current']);
        foreach ($this->data['current'] as $stage => $current) {
            if ($this->data['count'][$stage]) {
                if ($stage == self::STAGE_SKU) {
                    if ($this->data['drection'] == 'import') {
                        $response['progress'] += 100.0 * (1.0 * $current / $this->data['count'][$stage] - 1.0) / $this->data['count'][self::STAGE_PRODUCT];
                    }
                } else {
                    ++$stage_count;
                    $response['progress'] += 100.0 * (1.0 * $current / $this->data['count'][$stage]);
                }
            }
        }
        $response['progress'] = sprintf('%0.3f%%', $response['progress'] / max(1, $stage_count));
        $response['stage_count'] = $stage_count;
        $response['current_count'] = $this->data['current'];
        $response['processed_count'] = $this->data['processed_count'];
        $this->getResponse()->addHeader('Content-type', 'application/json');
        $this->getResponse()->sendHeaders();
        if ($this->writer) {
            $response['file'] = urlencode(basename($this->writer->file()));
        }
        if ($response['ready']) {
            $response['report'] = $this->report();

            if (!empty($this->data['emulate'])) {
                $callback = create_function('$a', 'return count($a)>1;');
                $emulate = array();
                foreach ($this->data['emulate'] as $row => $key) {
                    if (!isset($emulate[$key])) {
                        $emulate[$key] = array();
                    }
                    $emulate[$key][] = $row;
                }
                $params = array();
                if ($collision = array_filter($emulate, $callback)) {
                    $response['collision'] = array();
                    foreach ($collision as $key => $rows) {
                        $rows = array_map('intval', $rows);
                        asort($rows);
                        $response['collision'][] = array(
                            'key'  => $key,
                            'rows' => $rows,
                        );
                        $params[$key] = $rows;
                    }
                } else {
                    $response['collision'] = true;
                }
                if (!empty($this->reader)) {
                    $response['rows_count'] = $this->reader->count();
                    shopCsvReader::snapshot($this->reader, $params);
                    unset($params);
                }
            }
        }

        echo $this->json($response);
    }

    protected function restore()
    {
        switch ($this->data['direction']) {
            case 'import':
                $this->reader = unserialize($this->data['file']);
                break;
            case 'export':
                $this->writer = unserialize($this->data['file']);
                break;
        }
    }

    private function stepExportCategory(&$current_stage, &$count, &$processed)
    {
        static $categories;
        if (!$categories) {
            $model = new shopCategoryModel();
            if (preg_match('@^category/(\d+)$@', $this->data['hash'], $matches)) {
                $categories = array_reverse($model->getPath($matches[1]));
                if ($category = $model->getById($matches[1])) {
                    $categories[$matches[1]] = $category;
                }
            } else {
                $categories = $model->getFullTree('*', true);
            }
            if (count($categories) != $this->data['count'][self::STAGE_CATEGORY]) {
                throw new waException(sprintf('Invalid category count. Expected %d but get %d', $this->data['count'][self::STAGE_CATEGORY], count($categories)));
            }
            if ($current_stage) {
                $categories = array_slice($categories, $current_stage[self::STAGE_CATEGORY]);
            }
        }
        if ($category = reset($categories)) {
            $category['name'] = str_repeat('!', $category['depth']).$category['name'];

            $this->writer->write($category);
            array_shift($categories);

            ++$current_stage[self::STAGE_CATEGORY];
            ++$processed[self::STAGE_CATEGORY];

            $this->data['map'][self::STAGE_CATEGORY] = intval($category['id']);
            $this->data['map'][self::STAGE_PRODUCT] = $current_stage[self::STAGE_PRODUCT];

            $count[self::STAGE_PRODUCT] += $this->getCollection()->count();
        }

        return ($current_stage[self::STAGE_CATEGORY] < $count[self::STAGE_CATEGORY]);
    }

    protected function save()
    {
        ;
    }

    private function stepExportProduct(&$current_stage, &$count, &$processed)
    {
        static $products;
        static $product_feature_model;
        static $feature_model;
        static $tags_model;
        static $size;

        if (!$products) {
            $offset = $current_stage[self::STAGE_PRODUCT] - ifset($this->data['map'][self::STAGE_PRODUCT], 0);
            $fields = '*';
            if (!empty($this->data['options']['images'])) {
                $fields .= ', images';
            }
            $products = $this->getCollection()->getProducts($fields, $offset, 50, false);
        }
        $chunk = 5;
        $non_sku_fields = array(
            'summary',
            'meta_title',
            'meta_keywords',
            'meta_description',
            'description',
            'sort',
            'tags',
            'images',
        );
        while (($chunk-- > 0) && ($product = reset($products))) {
            $exported = false;
            /* check rights per product type && settlement options */
            $rights = empty($product['type_id']) || in_array($product['type_id'], $this->data['types']);
            $category_id = isset($product['category_id']) ? intval($product['category_id']) : null;
            /* check category match*/
            $category_match = !$this->data['export_category'] || ($category_id === $this->data['map'][self::STAGE_CATEGORY]);

            if ($rights && $category_match) {
                $shop_product = new shopProduct($product);
                if (!empty($this->data['options']['features'])) {
                    if (!isset($product['features'])) {
                        if (!$product_feature_model) {
                            $product_feature_model = new shopProductFeaturesModel();
                        }
                        $product['features'] = $product_feature_model->getValues($product['id']);
                    }
                    foreach ($product['features'] as $code => &$feature) {
                        if (!empty($this->data['composite_features'][$code])) {
                            $feature = str_replace('×', 'x', $feature);
                        }
                        unset($feature);
                    }
                }

                if (!isset($product['tags'])) {
                    if (!$tags_model) {
                        $tags_model = new shopProductTagsModel();
                    }
                    $product['tags'] = implode(',', $tags_model->getTags($product['id']));
                }
                if (!empty($this->data['options']['images'])) {
                    if (isset($product['images'])) {
                        if (!$size) {
                            /**
                             * @var shopConfig $config
                             */
                            $config = $this->getConfig();
                            $size = $config->getImageSize('big');
                        }
                        foreach ($product['images'] as & $image) {
                            $image = 'http://'.ifempty($this->data['base_url'], 'localhost').shopImage::getUrl($image, $size);
                        }
                        $product['images'] = array_values($product['images']);
                    }
                }

                $product['type_name'] = $shop_product->type['name'];

                $skus = $shop_product->skus;

                if (false && $product['sku_id']) {
                    #default SKU reorder
                    if (isset($skus[$product['sku_id']])) {
                        $sku = $skus[$product['sku_id']];
                        $sku['stock'][0] = $sku['count'];
                        $product['skus'] = array(-1 => $sku);
                        unset($skus[$product['sku_id']]);
                    }
                    $this->writer->write($product);
                    if (!empty($this->data['options']['images'])) {
                        if (isset($product['images'])) {
                            $processed[self::STAGE_IMAGE] += count($product['images']);
                        }
                    }
                    $exported = true;
                    if (!empty($this->data['options']['features'])) {
                        unset($product['features']);
                    }
                }

                if (!empty($product['tax_id'])) {
                    $product['tax_name'] = ifset($this->data['taxes'][$product['tax_id']]);
                }
                if (!isset($product['features'])) {
                    $product['features'] = array();
                }

                foreach ($skus as $sku_id => $sku) {
                    if ($exported) {
                        foreach ($non_sku_fields as $field) {
                            if (isset($product[$field])) {
                                unset($product[$field]);
                            }
                        }
                    }

                    $sku['stock'][0] = $sku['count'];
                    if (!empty($this->data['options']['features'])) {
                        $sku['features'] = $product_feature_model->getValues($product['id'], -$sku_id);
                        if ($product['sku_type'] == shopProductModel::SKU_TYPE_SELECTABLE) {
                            if (!$exported) {
                                $features_selectable_model = new shopProductFeaturesSelectableModel();
                                if ($selected = $features_selectable_model->getByProduct($product['id'])) {
                                    if (!$feature_model) {
                                        $feature_model = new shopFeatureModel();
                                    }
                                    $features = $feature_model->getById(array_keys($selected));
                                    $enclosure = $this->writer->enclosure;
                                    $pattern = sprintf("/(?:%s|%s|%s)/", preg_quote(',', '/'), preg_quote($enclosure, '/'), preg_quote($enclosure, '/'));
                                    foreach ($features as $feature_id => $feature) {
                                        $values = shopFeatureModel::getValuesModel($feature['type'])->getValues(
                                            array(
                                                'feature_id' => $feature_id,
                                                'id'         => $selected[$feature_id],
                                            )
                                        );
                                        if (!empty($values[$feature['id']])) {
                                            $f_values = $values[$feature['id']];
                                            if (!isset($product['features'])) {
                                                $product['features'] = array();
                                            }
                                            if (isset($sku['features'][$feature['code']])) {
                                                array_unshift($f_values, (string)$sku['features'][$feature['code']]);
                                            }
                                            foreach ($f_values as &$value) {
                                                if (preg_match($pattern, $value)) {
                                                    $value = $enclosure.str_replace($enclosure, $enclosure.$enclosure, $value).$enclosure;
                                                }
                                                unset($value);
                                            }
                                            $f_values = array_unique($f_values);
                                            $product['features'][$feature['code']] = '<{'.implode(',', $f_values).'}>';
                                        }
                                    }
                                }

                                $virtual_product = $product;
                                if (isset($skus[$product['sku_id']])) {
                                    $virtual_product['skus'] = array(-1 => $skus[$product['sku_id']]);
                                } else {
                                    $virtual_product['skus'] = array(-1 => $sku);
                                }

                                $virtual_product['skus'][-1]['stock'] = array(0 => $product['count']);
                                $this->writer->write($virtual_product);
                            }

                            $product['features'] = $sku['features'];
                        } else {
                            if (!$exported) {
                                foreach ($product['features'] as $code => &$values) {
                                    if (isset($sku['features'][$code])) {
                                        $values = array_unique(array_merge($values, $sku['features'][$code]));
                                    }
                                    unset($values);
                                }
                            } else {
                                $product['features'] = $sku['features'];
                            }
                        }
                    }

                    $product['skus'] = array(-1 => $sku);
                    $this->writer->write($product);
                    if (isset($product['images'])) {
                        $processed[self::STAGE_IMAGE] += count($product['images']);
                    }
                    $exported = true;
                    ++$current_stage[self::STAGE_SKU];
                    ++$processed[self::STAGE_SKU];
                }
            } elseif (count($products) > 1) {
                ++$chunk;
            }

            array_shift($products);
            ++$current_stage[self::STAGE_PRODUCT];
            if ($exported) {
                ++$processed[self::STAGE_PRODUCT];
            }
        }

        return ($current_stage[self::STAGE_PRODUCT] < $count[self::STAGE_PRODUCT]);
    }

    private static function flatData(&$data)
    {
        if (is_array($data)) {
            $data = array_filter(array_map('trim', $data));
            $data = reset($data);
        }

        return $data;
    }

    /**
     * Filter not empty rows
     * @param array $data
     * @param string[] $fields
     */
    private static function filterEmptyRows(&$data, $fields = array())
    {
        foreach ($data as $field => $row) {
            if ($row === '') {
                if (!$fields || in_array($field, $fields)) {
                    unset($data[$field]);
                }
            }
        }
    }


    private function error($message)
    {
        $path = wa()->getConfig()->getPath('log');
        waFiles::create($path.'/shop/csvproducts.log');
        waLog::log($message, 'shop/csvproducts.log');
    }

    private function json($data)
    {
        // $options = JSON_UNESCAPED_UNICODE;
        return json_encode($data);
    }
}
