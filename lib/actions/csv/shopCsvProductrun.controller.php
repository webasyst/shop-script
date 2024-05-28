<?php

class shopCsvProductrunController extends waLongActionController
{
    const STAGE_CATEGORY = 'category';
    const STAGE_DYNAMIC_CATEGORY = 'dynamic_category';
    const STAGE_PRODUCT = 'product';
    const STAGE_VARIANT = 'variant';
    const STAGE_PRODUCT_VARIANT = 'product_variant';
    const STAGE_IMAGE = 'image';
    const STAGE_IMAGE_DESCRIPTION = 'image_description';
    const STAGE_FILE = 'file';

    const EXPORT_SKU_TYPE_FLAT = 1;
    const EXPORT_SKU_TYPE_SELECTABLE = 2;

    /** @var shopCsvReader file reader */
    private $reader;

    /** @var shopCsvWriter file writer */
    private $writer;

    /** @var shopProductsCollection */
    private $collection;

    public static $save_product_ids = [];

    private $params = array(
        //true - all subcategories will be selected
        //false
        'include_sub_categories' => true,
    );

    private static $non_sku_fields = array(
        'summary',
        'meta_title',
        'meta_keywords',
        'meta_description',
        'description',
        'sort',
        'tags',
        'images',
        'images_descriptions',
        'params',
        'stock_unit_id',
        'base_unit_id',
        'order_multiplicity_factor'
    );

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
            /** @var shopTypeModel $type_model */
            $type_model = $this->model('type');
            /**
             * get available product types for current user
             */
            $this->data['types'] = array_map('intval', array_keys($type_model->getTypes()));
            switch ($this->data['direction']) {
                case 'export':
                    $this->initExport();
                    break;
                case 'import':
                default:
                    $this->data['direction']   = 'import';
                    $this->data['emulate']     = waRequest::post('emulate') ? array() : null;
                    $this->data['image_match'] = waRequest::post('image_match');
                    $this->data['type_id']     = waRequest::post('type_id', null, waRequest::TYPE_INT);
                    $this->data['primary']     = waRequest::post('primary', 'name');
                    $this->data['secondary']   = waRequest::post('secondary', 'skus:-1:sku');
                    $this->data['virtual_sku_stock'] = waRequest::post('virtual_sku_stock', '', waRequest::TYPE_STRING_TRIM);
                    $this->data['ignore_category']   = !!waRequest::post('ignore_category', 0, waRequest::TYPE_INT);
                    $this->data['nl2br_description'] = !!waRequest::post('nl2br_description');
                    $params = [
                        'name'        => basename(waRequest::post('file')),
                        'csv_map'     => waRequest::post('csv_map'),
                        'delimiter'   => waRequest::post('delimiter', ';'),
                        'upload_app'  => waRequest::post('upload_app', 'shop', waRequest::TYPE_STRING_TRIM),
                        'upload_path' => waRequest::post('upload_path', 'upload/images/')
                    ];
                    $this->initImport($params);
                    break;
            }

            $stages = array_keys($this->data['count']);
            $this->data['current'] = array_fill_keys($stages, 0);
            if ($this->data['direction'] == 'import') {
                if ($this->emulate(null)) {
                    $value = array(
                        'add'      => 0,
                        'found'    => 0,
                        'skip'     => 0,
                        'rights'   => 0,
                        'currency' => 0,
                    );
                } else {
                    $value = array(
                        'new'      => 0,
                        'update'   => 0,
                        'skip'     => 0,
                        'error'    => 0,
                        'rights'   => 0,
                        'currency' => 0,
                        'validate' => 0,
                    );
                }
            } else {
                $value = 0;
            }

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

    /**
     * @param string $type
     * @return waModel
     */
    private function model($type)
    {
        static $models = array();
        if (!isset($models[$type])) {
            $class = shopHelper::camelName($type, 'shop%sModel');
            if (class_exists($class)) {
                $models[$type] = new $class();
            }
        }
        return $models[$type];
    }

    private function initRouting()
    {
        $routing = wa()->getRouting();
        $app_id = $this->getAppId();
        $url = $this->getConfig()->getRootUrl(true);
        $this->data['base_url'] = preg_replace('@/.*$@', '', preg_replace('@^https?://@', '', $url));
        $this->data['scheme'] = waRequest::isHttps() ? 'https://' : 'http://';
        $domain_routes = $routing->getByApp($app_id);
        foreach ($domain_routes as $domain => $routes) {
            foreach ($routes as $route) {
                if ($domain.'/'.$route['url'] == $this->data['config']['domain']) {
                    $routing->setRoute($route, $domain);
                    $types = array_map('intval', ifempty($route['type_id'], array()));
                    if ($types) {
                        $this->data['types'] = array_intersect($this->data['types'], $types);
                    }
                    $this->data['base_url'] = preg_replace('@/.*$@', '', $domain);
                    break;
                }
            }
        }
    }

    /**
     * @throws waDbException
     * @throws waException
     */
    protected function initImport($params = [])
    {
        $name = ifempty($params['name']);
        if (empty($name)) {
            throw new waException('Empty import filename');
        }

        /** @var shopConfig $config */
        $config = wa('shop')->getConfig();

        //TODO detect emulate & type of control
        $file = wa()->getTempPath('csv/upload/'.$name);
        $this->data['rights'] = $this->getUser()->getRights('shop', 'settings');
        $this->data['new_features'] = array();
        $this->data['product_selectable_features'] = [];
        $this->data['currencies'] = $config->getCurrencies();
        if ($this->data['type_id'] && !in_array($this->data['type_id'], $this->data['types'])) {
            $this->data['type_id'] = reset($this->data['types']);
        }

        $map = ifempty($params['csv_map'], []);

        if ($this->emulate()) {
            $this->reader = shopCsvReader::snapshot($file);
            if (!$this->reader) {
                throw new waException('CSV file not found');
            }
            $this->reader->rewind();
        } else {
            /*, waRequest::post('encoding', 'utf-8')*/
            //after upload encoding converted into utf-8
            $this->reader = new shopCsvReader($file, ifempty($params['delimiter'], ';'));

            $header = $this->reader->header();

            $exploded = false;
            foreach ($map as $id => &$target) {
                if (preg_match('@^f\+:(.+)$@', $target, $matches)) {
                    if ($this->data['rights']) {
                        $id = preg_replace('@\D.*$@', '', $id);
                        if (!isset($header[$id]) && !$exploded) {
                            $exploded = true;
                            foreach ($header as $i => $name) {
                                if (is_string($i) && mb_strpos($i, ':') > 0) {
                                    $ids = array_map('intval', explode(':', $i));
                                    foreach ($ids as $_id) {
                                        if (!isset($header[$_id])) {
                                            $header[$_id] = $name;
                                        }
                                    }
                                }
                            }
                        }
                        $feature = array(
                            'name'       => ifset($header[$id], 'csv feature'),
                            'type'       => shopFeatureModel::TYPE_VARCHAR,
                            'multiple'   => 0,
                            'selectable' => 0,
                        );
                        list($feature['type'], $feature['multiple'], $feature['selectable']) = explode(':', $matches[1]);
                        $feature['type'] = preg_replace('@([^\.]+\.)\1@', '$1', $feature['type']);
                        if (empty($feature_model)) {
                            /** @var shopFeatureModel $feature_model */
                            $feature_model = $this->model('feature');
                        }
                        if (empty($type_features_model)) {
                            /** @var shopTypeFeaturesModel $type_features_model */
                            $type_features_model = $this->model('type_features');
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

        $csv_map = array();
        foreach ($map as $column => $field) {
            if (!isset($csv_map[$field])) {
                $csv_map[$field] = $column;
            } else {
                $csv_map[$field] .= ':'.$column;
            }
        }
        $this->reader->setMap($csv_map);

        $this->data['file'] = serialize($this->reader);
        $this->data['is_sku_feature'] = false;
        $this->getUser()->setSettings('shop', 'csv_primary_column', $this->data['primary']);
        $this->getUser()->setSettings('shop', 'csv_secondary_column', $this->data['secondary']);
        if ($this->data['secondary'] == 'skus:-1:sku_feature') {
            $this->data['is_sku_feature'] = true;
            $this->data['secondary'] = 'skus:-1:sku';
        }
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
        $upload_app = ifempty($params['upload_app'], 'shop');
        if ($upload_app != 'site') {
            $upload_app = 'shop';
        }

        $this->data['upload_path'] = preg_replace('@[\\\\/]+$@', '/', ifempty($params['upload_path'],'upload/images/').'/');
        $this->data['upload_path'] = preg_replace('@(^|/)(\.\.)/@', '$1/', $this->data['upload_path']);
        if (waSystem::getSetting('csv.upload_path') != $this->data['upload_path']) {
            $app_settings = new waAppSettingsModel();
            $app_settings->set('shop', 'csv.upload_path', $this->data['upload_path']);
        }

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

        $primary_fields = array('id', 'name', 'url', 'null', 'false', 'id_1c',);
        $secondary_fields = array('skus:-1:id', 'skus:-1:sku', 'skus:-1:name', 'skus:-1:id_1c',);

        if (!in_array($this->data['primary'], $primary_fields)) {
            throw new waException(_w('Invalid primary field'));
        }
        if ($this->data['primary'] === 'null') {
            $this->data['primary'] = null;
        } elseif ($this->data['primary'] === 'false') {
            $this->data['primary'] = false;
        }
        if (!in_array($this->data['secondary'], $secondary_fields)) {
            throw new waException(_w('Invalid secondary field'));
        }
        $current = $this->reader->current();
        if (!empty($this->data['primary']) && (self::getData($current, $this->data['primary']) === null)) {
            throw new waException(_w('Empty primary CSV column'));
        }
        if (empty($this->data['primary']) && (self::getData($current, $this->data['secondary']) === null)) {
            throw new waException(_w('Empty secondary CSV column'));
        }

        if ($this->emulate()) {
            //check for collision:
            //array: features
        }

        $this->data['previous_type'] = null;

        $this->data['count'] = array(
            self::STAGE_FILE              => $this->reader ? $this->reader->size() : null,
            self::STAGE_CATEGORY          => null,
            self::STAGE_PRODUCT           => null,
            self::STAGE_VARIANT           => null,
            self::STAGE_IMAGE             => null,
            self::STAGE_IMAGE_DESCRIPTION => null,
        );
    }

    private function initExport()
    {
        $hash = shopImportexportHelper::getCollectionHash();
        //$this->data['export_category'] = !in_array($hash['type'], array('id', 'set', 'type'));
        #category exported only for all products or for category
        if (in_array($hash['type'], array('category', ''), true)) {
            if (preg_match('@^category/(\d+)$@', $hash['hash'], $matches)) {
                $this->data['export_category'] = intval($matches[1]);
            } else {
                $this->data['export_category'] = true;
            }
        } else {
            $this->data['export_category'] = false;
        }
        $this->data['exported_products'] = [];


        $this->data['timestamp'] = time();
        $this->data['hash'] = $hash['hash'];

        $encoding = waRequest::post('encoding', 'utf-8');

        $options = array();

        $config = array(
            'export_mode'            => !!waRequest::post('export_mode'),
            # csv file encoding
            'encoding'               => $encoding,
            'delimiter'              => waRequest::post('delimiter', ';'),
            # export product description
            'description'            => !!waRequest::post('description'),
            # export product features
            'features'               => !!waRequest::post('features'),
            # export links to product images
            'images'                 => waRequest::post('images'),
            'extra_categories'       => !!waRequest::post('extra_categories'),
            'primary_sku'            => !!waRequest::post('primary_sku'),
            'include_sub_categories' => $this->params['include_sub_categories'] || !!waRequest::post('include_sub_categories'),
            # export extra fields (added by plugins and etc)
            'extra'                  => !!waRequest::post('extra'),
            # export product and categories params
            'params'                 => !!waRequest::post('params'),
            # domain for properly generated image's links
            'domain'                 => waRequest::post('domain'),
            # collection hash
            'hash'                   => $hash['hash'],
        );

        $map = shopCsvProductuploadController::getMapFields(true, $config['extra']);

        if (empty($config['primary_sku'])) {
            unset($map['skus:-1:_primary']);
        }
        if (empty($config['params'])) {
            unset($map['params']);
        }

        $this->data['composite_features'] = array();
        /** @var shopFeatureModel $features_model */
        $features_model = $this->model('feature');
        if (!empty($config['features'])) {
            $features = [];
            $type_ids = [];
            if (preg_match('@^id/(.+)$@', $this->data['hash'], $matches)) {
                $product_ids = array_unique(array_map('intval', explode(',', $matches[1])));
                if ($product_ids) {
                    /** @var shopProductModel $product_model */
                    $product_model = $this->model('product');
                    $type_ids = array_keys($product_model->select('type_id')->where('id IN (?)', [$product_ids])->fetchAll('type_id'));
                }
            } elseif (($this->data['export_category'] && preg_match('@^category/(\d+)$@', $this->data['hash']))
                || preg_match('@^set/[a-z0-9\._-]+$@i', $this->data['hash'])
            ) {
                $types = (new shopProductsCollection($this->data['hash']))->getProducts('type_id', 0, 999999999);
                $type_ids = waUtils::getFieldValues($types, 'type_id');
            } elseif (preg_match('@^type/(\d+)$@', $this->data['hash'], $matches)) {
                $type_ids = (int)$matches[1];
            } else {
                $features = $features_model->getFeatures(true);
            }
            if ($type_ids) {
                /** @var shopTypeFeaturesModel $type_features_model */
                $type_features_model = $this->model('type_features');
                $type_features = $type_features_model->select('feature_id')->where('type_id IN (?)', [$type_ids])->fetchAll('feature_id');
                if ($type_features) {
                    $features = $features_model->getById(array_keys($type_features));
                    $features += $features_model->getByField('parent_id', array_keys($features), 'id');
                }
            }
            if ($features) {
                $options['features'] = true;
                foreach ($features as $feature) {
                    if (!preg_match('/\.\d$/', $feature['code'])
                        &&
                        ($feature['type'] != shopFeatureModel::TYPE_DIVIDER)
                    ) {
                        $map[sprintf('features:%s', $feature['code'])] = $feature['name'];
                        if ($encoding != 'UTF-8') {
                            $this->data['composite_features'][$feature['code']] = true;
                        }
                    }
                    if (!empty($feature['available_for_sku'])) {
                        $this->data['sku_features'][$feature['code']] = true;
                    }
                }
            }
        }

        /** @var shopTaxModel $tax_model */
        $tax_model = $this->model('tax');
        $taxes = $tax_model->getAll();
        if ($taxes) {
            $this->data['taxes'] = array();
            foreach ($taxes as $tax) {
                $this->data['taxes'][$tax['id']] = $tax['name'];
            }
        }

        if (isset($config['description']) && empty($config['description'])) {
            unset($map['description']);
        }

        if (!empty($config['images'])) {
            $sql = <<<SQL
SELECT COUNT(1) AS `cnt`
FROM `shop_product_images`
GROUP BY `product_id`
ORDER BY `cnt` DESC
LIMIT 1
SQL;
            $cnt = $features_model->query($sql)->fetchField('cnt');
            if ($cnt) {
                $options['images'] = true;
                for ($n = 0; $n < $cnt; $n++) {
                    $field = sprintf('images:%d', $n);
                    $map[$field] = _w('Product images');
                    $field = sprintf('images_descriptions:%d', $n);
                    $map[$field] = _w('Product image descriptions');
                }
            }

            if (isset($map['images'])) {
                unset($map['images']);
                unset($map['images_descriptions']);
            }
        } else {
            if (isset($map['images'])) {
                unset($map['images']);
                unset($map['images_descriptions']);
            }

            foreach (array_keys($map) as $field) {
                if (preg_match('@^images(_descriptions)?:\d+$@', $field)) {
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
            self::STAGE_PRODUCT  => 0,
            self::STAGE_CATEGORY => 0,
            self::STAGE_VARIANT  => null,
            self::STAGE_IMAGE    => null,
        );

        if ($this->data['export_category']) {
            /** @var shopCategoryModel $model */
            $model = $this->model('category');
            if (preg_match('@^category/(\d+)$@', $this->data['hash'], $matches)) {
                $category_id = intval($matches[1]);
                $category = $model->getById($category_id);
                if ($category) {
                    $this->data['count'][self::STAGE_CATEGORY] = count($model->getPath($category_id)) + 1;
                    if (!empty($category['include_sub_categories']) || $this->data['config']['include_sub_categories']) {
                        $route = null;
                        $categories = $model->getTree($category_id, null, false, $route);
                        $this->data['include_sub_categories'] = array_keys($categories);
                        array_shift($categories);
                        $this->data['count'][self::STAGE_CATEGORY] += count($categories);
                    }
                } else {
                    throw new waException(sprintf('Category with id %d', $category_id), 404);
                }
            } else {
                $this->data['count'][self::STAGE_CATEGORY] = $model->countAll();
            }
        }

        $this->data['count'][self::STAGE_PRODUCT] = $this->getCollection()->count();
        $this->collection = null;
    }

    private static function getData($data, $key)
    {
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
            /** @var string $key */
            $value = ifset($data[(string)$key]);
        }

        return $value;
    }

    /**
     *
     * @return shopProductsCollection
     * @throws waException
     */
    private function getCollection($category_id = null)
    {
        static $id = null, $dynamic_categories = null;
        if ($dynamic_categories === null) {
            $category_model = new shopCategoryModel();
            $dynamic_categories = $category_model->getByField('type', $category_model::TYPE_DYNAMIC, 'id');
        }

        if ($this->data['export_category'] && ($id !== ifset($this->data['map'][self::STAGE_CATEGORY], null))) {
            $this->collection = null;
        }

        if (!$this->collection) {
            $hash = null;
            if ($this->data['export_category']) {
                //hash is * or category/id
                #rebuild hash with current category id
                if ($category_id) {
                    $id = $category_id;
                } else {
                    $id = $this->data['map'][self::STAGE_CATEGORY];
                }
                $dynamic_category = isset($dynamic_categories[$id]) ? $dynamic_categories[$id] : null;
                if ($this->data['hash'] == '*') {
                    if ($dynamic_category) {
                        $hash = 'category/0';
                    } else {
                        $hash = 'search/category_id=' . ($id ?: '=null');
                    }
                } else {
                    #hash = category/%id%
                    if (($this->data['export_category'] !== true) && ($this->data['export_category'] == $id)) {
                        $this->data['export_category'] = true;
                    }
                    if ($this->data['export_category'] === true) {
                        if ($dynamic_category) {
                            $hash = 'category/'. ($dynamic_category['depth'] > 0 && $this->data['hash'] != "category/$id" ? 0 : $id);
                        } else {
                            $hash = 'search/category_id=' . ($id ?: '=null');
                        }
                    } else {
                        //this hash always return empty collection
                        //used to skip parent categories entries
                        $hash = 'category/0';
                    }
                }
            } else {
                $hash = $this->data['hash'];
            }

            $options = array();

            if (!empty($this->data['config']['params'])) {
                #get product's params
                $options['params'] = true;
            }

            $this->collection = new shopProductsCollection($hash, $options);
            $this->collection->orderBy('name');
        }

        if ($category_id) {
            $collection = $this->collection;
            $id = null;
            $this->collection = null;
            $this->data['export_category'] = false;
            return $collection;
        } else {
            return $this->collection;
        }
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
                    self::STAGE_VARIANT  => array /*_w*/
                    (
                                                  '%d new SKU to be added',
                                                  '%d new SKUs to be added',
                    ),
                    'icon'               => 'yes fas fa-check-circle text-green',

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
                    self::STAGE_VARIANT  => array /*_w*/
                    (
                                                  '%d SKU to be updated',
                                                  '%d SKUs to be updated',
                    ),
                    'icon'               => 'yes fas fa-check-circle text-green',
                ),
                'collision' => array(
                    self::STAGE_CATEGORY => array /*_w*/
                    (
                                                  'Duplicate category entries on %d line',
                                                  'Duplicate category entries on %d lines',
                    ),
                    self::STAGE_PRODUCT  => array /*_w*/
                    (
                                                  'Duplicate product entries on %d line',
                                                  'Duplicate product entries on %d lines',
                    ),
                    self::STAGE_VARIANT  => array /*_w*/
                    (
                                                  'Duplicate SKU entries on %d line',
                                                  'Duplicate SKU entries on %d lines',
                    ),
                    'icon'               => 'exclamation fas fa-exclamation-triangle text-yellow',
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
                    self::STAGE_VARIANT  => array /*_w*/
                    (
                                                  'Added %d SKU',
                                                  'Added %d SKUs',
                    ),
                    self::STAGE_IMAGE    => array /*_w*/
                    (
                                                  'Linked %d product image',
                                                  'Linked %d product images',
                    ),
                    'icon'               => 'yes fas fa-check-circle text-green',

                ),
                'update'    => array(
                    self::STAGE_CATEGORY          => array /*_w*/
                    (
                                                           'Updated %d category',
                                                           'Updated %d categories',
                    ),
                    self::STAGE_PRODUCT           => array /*_w*/
                    (
                                                           'Updated %d product',
                                                           'Updated %d products',
                    ),
                    self::STAGE_VARIANT           => array /*_w*/
                    (
                                                           'Updated %d SKU',
                                                           'Updated %d SKUs',
                    ),
                    self::STAGE_IMAGE             => array /*_w*/
                    (
                                                           'Updated %d product image',
                                                           'Updated %d product images',
                    ),
                    self::STAGE_IMAGE_DESCRIPTION => array /*_w*/
                    (
                                                           'Updated %d product image description',
                                                           'Updated %d product image descriptions',
                    ),
                    'icon'                        => 'yes fas fa-check-circle text-green',
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
                    self::STAGE_VARIANT  => array /*_w*/
                    (
                                                  'Ambiguous identification conditions for %d SKU',
                                                  'Ambiguous identification conditions for %d SKUs',
                    ),
                    self::STAGE_IMAGE    => array /*_w*/
                    (
                                                  'Ambiguous identification conditions for %d product image',
                                                  'Ambiguous identification conditions for %d product images',
                    ),
                    'icon'               => 'no-bw fas fa-times-circle',
                ),
                'rights'    => array(
                    self::STAGE_PRODUCT => array /*_w*/
                    (
                                                 '%d product record was not updated due to insufficient access rights for you as Webasyst user',
                                                 '%d product records were not updated due to insufficient access rights for you as Webasyst user',
                    ),
                    'icon'              => 'no-bw fas fa-times-circle',
                ),
                'currency'  => array(
                    self::STAGE_PRODUCT => array /*_w*/
                    (
                                                 '%d product has unknown currency',
                                                 '%d products have unknown currency',
                    ),
                    'icon'              => 'no-bw fas fa-times-circle',
                ),
                'error'     => array(
                    self::STAGE_CATEGORY => array /*_w*/
                    (
                                                  '%d category imported with errors',
                                                  '%d categories imported with errors',
                    ),
                    self::STAGE_IMAGE    => array /*_w*/
                    (
                                                  '%d image imported with errors',
                                                  '%d images imported with errors',
                    ),
                    self::STAGE_VARIANT  => array /*_w*/
                    (
                                                  '%d product imported with errors',
                                                  '%d products imported with errors',
                    ),
                    self::STAGE_PRODUCT  => array /*_w*/
                    (
                                                  '%d SKU imported with errors',
                                                  '%d SKUs imported with errors',
                    ),
                    'icon'               => 'no fas fa-times-circle text-red',
                ),
                'validate'  => array(
                    self::STAGE_CATEGORY => array /*_w*/
                    (
                                                  '%d category imported with errors',
                                                  '%d categories imported with errors',
                    ),
                    self::STAGE_IMAGE    => array /*_w*/
                    (
                                                  '%d image imported with errors',
                                                  '%d images imported with errors',
                    ),
                    self::STAGE_VARIANT  => array /*_w*/
                    (
                                                  '%d product imported with errors',
                                                  '%d products imported with errors',
                    ),
                    self::STAGE_PRODUCT  => array /*_w*/
                    (
                                                  '%d SKU not imported due to pricing plan restriction',
                                                  '%d SKUs not imported due to pricing plan restriction',
                    ),
                    'icon'               => 'no-bw fas fa-times-circle',
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
                    self::STAGE_VARIANT  => array /*_w*/
                    (
                                                  '%d SKU',
                                                  '%d SKUs',
                    ),
                    self::STAGE_IMAGE    => array /*_w*/
                    (
                                                  '%d product image',
                                                  '%d product images',
                    ),
                    'icon'               => 'yes fas fa-check-circle text-green',
                ),
            );
        }
        $info = array();
        if (ifempty($count[$stage])) {
            foreach ((array)$count[$stage] as $type => $count) {
                if ($count || ($stage == self::STAGE_PRODUCT && ($type == 'new' || $type == 'update'))) {
                    $args = ifset($strings, $type, $stage, null);
                    $args[] = $count;
                    $string = htmlentities(call_user_func_array('_w', $args), ENT_QUOTES, 'utf-8');
                    $info[] = sprintf($wrapper, $strings[$type]['icon'], $string);
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
     * @uses shopCsvProductrunController::stepExport()
     * @uses shopCsvProductrunController::stepImport()
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
        } catch (waDbException $ex) {
            //TODO get duplicate error's
            sleep(5);
            $this->error($this->data['direction'].': '.$ex->getMessage()."\n".$ex->getTraceAsString());
        } catch (Exception $ex) {
            //TODO get duplicate error's
            sleep(5);
            $this->error($this->data['direction'].': '.$ex->getMessage()."\n".$ex->getTraceAsString());
        }

        if ($this->isDone() && $this->data['direction'] == 'import') {
            $this->deleteProductWithoutSkus();
        }

        return $result && !$this->isDone();
    }

    /**
     * @return bool
     * @uses   shopCsvProductrunController::stepImportCategory
     * @uses   shopCsvProductrunController::stepImportVariant
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
                try {
                    if (!isset($current['row_type']) && isset($this->reader->data_mapping['row_type'])) {
                        $current['row_type'] = '';
                    }
                    $type = self::getDataType($current);
                } catch (waException $e) {
                    $type = $e->getMessage();
                    $this->writeImportError(_w('The specified row type is not supported.'));
                }
                if ($type) {
                    $this->workupData($current);
                    $method_name = 'stepImport'.ucfirst($type);
                    if (method_exists($this, $method_name)) {
                        $result = $this->{$method_name}($current);
                        $this->data['previous_type'] = $type;
                    } else {
                        $this->error(sprintf("Unsupported import data type %s", $type));
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
     * @throws waException
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
        static $is_extended_sku_behavior = false;

        $this->data['sku_feature_codes'] = array();
        $this->data['last_product_sku_code'] = null;

        if (empty($model)) {
            /** @var shopProductModel $model */
            $model = $this->model('product');
        }
        if (empty($currencies)) {
            $currencies = array();
            /** @var shopConfig $config */
            $config = wa('shop')->getConfig();
            $c = $config->getCurrency();
            $currencies[$c] = $c;
            foreach ($config->getCurrencies() as $row) {
                $currencies[$row['code']] = $row['code'];
            }
        }

        if (!empty($data['skus'][-1]['stock'])) {
            $per_stock = false;
            $stock = &$data['skus'][-1]['stock'];
            foreach ($stock as $id => &$count) {
                if ($count === '') {
                    $count = null;
                } else {
                    if (shopFrac::isEnabled()) {
                        $count = str_replace(',', '.', $count);
                        $count = floatval($count);
                    } else {
                        $count = intval($count);
                    }
                    if ($id) {
                        $per_stock = true;
                    }
                }
                unset($count);
            }

            if ($per_stock) {
                if (isset($stock[0])) {
                    unset($stock[0]);
                }
            } else {
                $count = ifset($stock[0]);
                $stock = array(
                    0 => $count,
                );
                unset($count);
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
                /** @var shopProductSkusModel $sku_model */
                $sku_model = $this->model('product_skus');
            }
            $sku_fields = array(
                end($keys) => self::getData($data, $keys),
            );
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

        // nl2br for description
        if ($this->data['nl2br_description']) {
            if (!empty($data['description'])) {
                $data['description'] = nl2br($data['description']);
            }
            if (!empty($data['summary'])) {
                $data['summary'] = nl2br($data['summary']);
            }
        }

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
                    if (!empty($current_sku['virtual']) && isset($data['row_type'])) {
                        $current_sku['virtual'] = 0;
                    }
                    $data['skus'][$sku_id] = $current_sku;
                }
            }
            if ($category_id) {
                //add extra category if category detected
                $data['categories'] = array_merge(array_keys($product->categories), array($category_id));
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
                foreach (array('available', 'status') as $skus_key) {
                    if (!isset($sku[$skus_key])) {
                        $sku[$skus_key] = true;
                        $data['skus'][$data['sku_id']] = $sku;
                    }
                }
            }
            $key .= ':i:'.$this->getKey($fields);
        }

        //Tags workaround
        if (!empty($data['tags']) && is_string($data['tags']) && preg_match('/^\{(.+,.+)\}$/', $data['tags'], $matches)) {
            $data['tags'] = array_filter(array_map('trim', $this->parseRow($matches[1])));
        }

        if (isset($data['sku_type'])) {
            $data['sku_type'] = $data['sku_type'] == self::EXPORT_SKU_TYPE_SELECTABLE ? shopProductModel::SKU_TYPE_SELECTABLE : shopProductModel::SKU_TYPE_FLAT;
        }

        if (isset($data['row_type']) && ($data['row_type'] == self::STAGE_PRODUCT || $data['row_type'] == self::STAGE_PRODUCT_VARIANT)) {
            $this->data['is_deleted_empty_sku'] = false;
            $this->data['product_selectable_features'] = [];
            if ($data['row_type'] == self::STAGE_PRODUCT) {
                $is_extended_sku_behavior = false;
            }
            if ($data['row_type'] == self::STAGE_PRODUCT_VARIANT) {
                $this->data['last_product_sku_code'] = ifset($data, 'skus', -1, 'sku', null);
            }
        }

        //Features workaround
        if (!empty($data['features'])) {
            $virtual_sku_stock = null;
            foreach ($data['features'] as $feature => &$values) {
                if (is_array($values)) {
                } elseif (preg_match('/^\{(.+,.+)\}$/', $values, $matches)) {
                    $values = array_map('trim', $this->parseRow($matches[1]));
                } elseif (preg_match('/^<\{(.*)\}>$/', $values, $matches)) {
                    if (!isset($this->data['sku_feature_codes'][$feature])) {
                        $this->data['sku_feature_codes'][$feature] = $feature;
                    }
                    if (!isset($data['features_selectable'])) {
                        $data['features_selectable'] = array();
                    }
                    if (isset($data['row_type']) && $data['row_type'] == self::STAGE_PRODUCT && $values == '<{}>') {
                        $is_extended_sku_behavior = true;
                        $data['features_selectable'] = array();
                        continue;
                    }

                    $matches[1] = preg_replace('/^<\{(.*)\}>$/', '$1', $matches[1], 1);
                    $values = $this->parseRow($matches[1]);
                    if ($values) {
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

                        if ($is_extended_sku_behavior == false) {
                            $data['features_selectable'][$feature] = array(
                                'values' => $values,
                            );

                            if (!empty($this->data['virtual_sku_stock'])) {
                                if (isset($data['skus'][-1]['stock'])) {
                                    $virtual_sku_stock = $data['skus'][-1]['stock'];
                                }
                                if ($virtual_sku_stock !== null) {
                                    $stock = &$virtual_sku_stock;

                                    switch ($this->data['virtual_sku_stock']) {
                                        case 'distribute':
                                            //it's a bug!
                                            $features_count = count($values);
                                            if (is_array($stock)) {
                                                foreach ($stock as &$stock_item) {
                                                    $stock_item = $stock_item / $features_count;
                                                    unset($stock_item);
                                                }
                                            } else {
                                                $stock = $stock / $features_count;
                                            }
                                            $data['features_selectable'][$feature]['stock'] = &$stock;
                                            break;
                                        case 'set':
                                            $data['features_selectable'][$feature]['stock'] = $stock;
                                            break;
                                    }
                                    unset($stock);
                                }
                            }
                        }
                        if (!isset($data['sku_type'])) {
                            $product->sku_type = shopProductModel::SKU_TYPE_SELECTABLE;
                        }
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
                            if (isset($data['row_type']) && $data['row_type'] != self::STAGE_PRODUCT_VARIANT) {
                                $data['skus'][-1]['features'][$feature] = $values[0]['value'];
                            }
                        }
                        if (!isset($data['row_type']) || $data['row_type'] == self::STAGE_PRODUCT_VARIANT) {
                            unset($data['skus']);
                        }
                    }
                    unset($data['features'][$feature]);
                }
            }
            unset($values);
            //TODO if cleanup is disabled filter empty values for features
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
                            /** @var shopTypeFeaturesModel $type_features_model */
                            $type_features_model = $this->model('type_features');
                        }
                        $type_features_model->updateByFeature($feature['id'], array($data['type_id']), false);

                        $feature['types'][] = $data['type_id'];
                    }
                }
                unset($feature);
            }

            //Use id & sku_id only for search
            unset($data['id']);
            //Use id_1c only for search
            //TODO update parsing 1c guid
            // unset($data['id_1c']);
            // unset($data['skus'][-1]['id_1c']);
        }

        return $access ? $product : null;
    }

    protected function isEmptyPrimaryProductColumn($data)
    {
        $empty = false;
        $primary_field = $this->data['primary'];
        if (empty($primary_field)) {
            $primary_field = $this->data['secondary'];
        }
        if (is_string($primary_field) && strlen($primary_field) > 0) {
            $primary_keys = explode(':', $primary_field);
            $primary_value = self::getData($data, $primary_keys);
            if ($primary_value == null && isset($this->reader->data_mapping[$primary_field])) {
                $empty_column_error = _w('The products identification column is empty.');
                $this->writeImportError($empty_column_error);
                $this->error($empty_column_error);
                $empty = true;
            }
        }

        return $empty;
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

    /**
     * @param $data
     * @return bool
     */
    private function findType(&$data)
    {
        static $types = array();
        if (!empty($data['type_name'])) {
            $type = mb_strtolower(self::flatData($data['type_name']));
            if (!isset($types[$type])) {
                /** @var shopTypeModel $model */
                $model = $this->model('type');
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
        if (ifset($data['tax_name'])) {
            if (empty($data['tax_name'])) {
                unset($data['tax_id']);
            } else {
                $tax = mb_strtolower(self::flatData($data['tax_name']));
                if (!isset($taxes[$tax])) {
                    /** @var shopTaxModel $tax_model */
                    $tax_model = $this->model('tax');
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
     * @param $file
     * @param $name
     * @param $product_id
     * @return array|bool|null
     * @throws waException
     */
    private function findImage($file, &$name, $product_id)
    {
        /** @var shopProductImagesModel $model */
        $model = $this->model('product_images');

        $search = array(
            'product_id' => $product_id,
            'ext'        => pathinfo($name, PATHINFO_EXTENSION),
        );

        $_is_url = preg_match('@^(https?|fpts?)://@', $file);

        if ($_is_url) {
            $pattern = sprintf('@/(%d)/images/(\\d+)/\\2\\.(\\d+(x\\d+)?)\\.([^\\.]+)$@', $search['product_id']);
            if (preg_match($pattern, $file, $matches)) {
                $exists = array(
                    'product_id' => $matches[1],
                    'id'         => $matches[2],
                    'ext'        => $matches[5],
                );

                if ((strpos($file, shopImage::getUrl($exists, $matches[3])) !== false)) {
                    $exists = $model->getByField($exists);
                    if ($exists) {
                        $exists['::same'] = true;
                    }
                } else {
                    $exists = false;
                }
            }
        }

        if (empty($exists)) {
            switch ($this->data['image_match']) {
                case 'host_path_md5':
                    $path = $_is_url ? dirname(parse_url($file, PHP_URL_PATH)) : dirname($file);
                    $domain = $_is_url ? parse_url($file, PHP_URL_HOST) : 'localhost';
                    $name = sprintf('%s.%s', md5($domain.'/'.$path.'/'.$name), $search['ext']);
                    break;
                case 'path_md5':
                    $path = $_is_url ? dirname(parse_url($file, PHP_URL_PATH)) : dirname($file);
                    $name = sprintf('%s.%s', md5($path.'/'.$path.'/'.$name), $search['ext']);
                    break;
            }

            $search['original_filename'] = $name;

            $exists = $model->getByField($search);
        }

        return $exists;
    }

    /**
     * @usedby stepImport
     * @param $data
     * @return bool
     * @throws waException
     */
    private function stepImportVariant($data)
    {
        static $sku_primary;
        static $sku_secondary;
        static $empty_sku;
        static $empty;
        static $saved_features = array();
        static $available_for_sku_features;

        if ($this->isEmptyPrimaryProductColumn($data)) {
            return false;
        }

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
        $data_features = ifset($data, 'features', []);
        $data += $empty;

        if (
            !(empty($empty['features']) && empty($data['features']))
            && (is_array($empty['features']) && is_array($data['features']))
        ) {
            $data['features'] = array_merge($empty['features'], $data['features']);
        }

        if ($product = $this->findProduct($data)) {
            $item_sku_id = false;
            $current_id = ifset($this->data['map'][self::STAGE_PRODUCT]);
            $id = $product->getId();
            if ($this->emulate()) {
                // checking import options (step 1)
                $target = $id ? 'found' : 'add';
                $target_sku = 'add';
            } else {
                // import (step 2)
                $target = $id ? 'update' : 'new';
                $target_sku = 'new';
            }

            if (!empty($this->data['last_wrong_product']) && (($this->data['last_wrong_product']['field'] == 'id' && $id == $this->data['last_wrong_product']['value'])
                    || ($this->data['last_wrong_product']['field'] == $this->data['primary']
                        && isset($data[$this->data['primary']]) && $data[$this->data['primary']] == $this->data['last_wrong_product']['value']))
            ) {
                // ignore the product line and its modifications
                return false;
            }

            $key = null;
            $sku_only = false;

            $product_exists = $this->emulate() ? ($product->__hash == $current_id) : $id;

            /** @var shopFeatureModel $feature_model */
            $feature_model = $this->model('feature');
            $data['features'] = ifset($data, 'features', []);
            $missing_keys = array_diff_key($data_features, $saved_features);
            if ($missing_keys) {
                $saved_features += $feature_model->getByField(array('code' => array_keys($missing_keys)), 'code');
            }

            if ($id && isset($data['skus'][-1])) {
                if (in_array($this->data['previous_type'], array(self::STAGE_VARIANT, null), true)
                    && ($this->emulate() ? ($product->__hash == $current_id) : ($id == $current_id))
                ) {
                    $sku_only = true;
                }

                $sku = $data['skus'][-1] + $empty_sku;
                $this->castSku($sku);

                unset($data['skus'][-1]);
                $item_sku_id = -1;
                $matches = 0;

                /** @var shopProductFeaturesModel $product_feature_model */
                $product_feature_model = $this->model('product_features');
                $selectable_data_features = [];
                foreach ($data_features as $feature_code => $feature_value) {
                    if (preg_match('/^<\{(.*)\}>$/', $feature_value) === 1) {
                        $selectable_data_features[$feature_code] = $feature_value;
                    }
                }
                /** @var shopProductFeaturesSelectableModel $feature_selectable_model */
                $feature_selectable_model = $this->model('product_features_selectable');
                $selectable_feature_ids = $feature_selectable_model->getFeatures($id);
                if ($selectable_feature_ids) {
                    foreach ($saved_features as $feature_code => $feature) {
                        foreach ($selectable_feature_ids as $feature_id) {
                            if ($feature['id'] == $feature_id && isset($data_features[$feature_code])) {
                                $selectable_data_features[$feature_code] = $data_features[$feature_code];
                            }
                        }
                    }
                }
                $current_features = array_intersect_key($saved_features, $selectable_data_features);
                $product_feature_values = $product_feature_model->getValuesMultiple($current_features, $id, array_keys($product->skus));

                foreach ($product->skus as $sku_id => $current_sku) {
                    if ($current_sku[$sku_primary] === ifset($sku[$sku_primary], '')) {
                        $all_values_exist = true;
                        if (!empty($product_feature_values) && $this->data['is_sku_feature']) {
                            foreach ($selectable_data_features as $feature_code => $feature_value) {
                                if (!is_array($feature_value)) {
                                    if (isset($product_feature_values[$sku_id][$feature_code])) {
                                        $product_feature_value = sprintf('%s', str_replace("\r\n", "\r", $product_feature_values[$sku_id][$feature_code]));
                                        if (strpos($feature_value, '<{') === 0) {
                                            $product_feature_value = '<{' . $product_feature_value . '}>';
                                        }
                                        if ($product_feature_value != $feature_value) {
                                            $all_values_exist = false;
                                            break;
                                        }
                                    } elseif (strpos($feature_value, '<{') === 0) {
                                        $all_values_exist = false;
                                        break;
                                    }
                                }
                            }
                        }

                        if ($all_values_exist) {
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
                }

                unset($sku['id']);

                if ($item_sku_id !== false) {
                    foreach (array('available', 'status') as $skus_key) {
                        if (($item_sku_id < 0) && !isset($sku[$skus_key])) {
                            $sku[$skus_key] = true;
                        }
                    }
                    if (!$sku_only && !$product->skus) {
                        $data['sku_id'] = $item_sku_id;
                    }
                    $data['skus'][$item_sku_id] = $sku;
                    $key = 's:';
                    if ($item_sku_id > 0) {
                        $key .= 'u:';
                        $key_by_params = [$sku_primary => $item_sku_id];
                        if ($this->data['is_sku_feature']) {
                            $key_by_params['features'] = ifset($data['skus'][$item_sku_id], 'features', '');
                        }
                    } else {
                        $key .= 'i:';
                        $key_by_params = array($sku_primary => ifset($sku, $sku_primary, ''));
                        if ($this->data['is_sku_feature']) {
                            $key_by_params['features'] = ifset($data['skus'][-1], 'features', '');
                        }
                    }
                    $key .= $this->getKey($key_by_params);
                } else {
                    unset($data['skus']);
                }
            } elseif (isset($data['skus'][-1])) {
                if (in_array($this->data['previous_type'], array(self::STAGE_VARIANT, null), true)
                    && $this->emulate() && ($product->__hash == $current_id)
                ) {
                    $sku_only = true;
                    $item_sku_id = true;
                }
                $sku = $data['skus'][-1] + $empty_sku;
                $key = 's:i:';
                $key_by_params = array($sku_primary => ifset($sku, $sku_primary, ''));
                if ($this->data['is_sku_feature']) {
                    $key_by_params['features'] = ifset($data['skus'][-1], 'features', '');
                }
                $key .= $this->getKey($key_by_params);
            } elseif (!empty($data['features_selectable'])) {
                if ($product_exists) {
                    $target = $this->emulate() ? 'found' : 'update';
                }
                //TODO recount virtual SKUs count
                $key = 's:v:';
                $key .= $this->getKey($data['features_selectable']);
                if ($id) {
                    $target_sku = $this->emulate() ? 'found' : 'update';
                }
            }

            if (isset($data['row_type'])) {
                if ($data['row_type'] == self::STAGE_PRODUCT) {
                    $sku_only = false;
                    foreach ($data['features'] as $code => $feature_value) {
                        if ($feature_value === '<{}>') {
                            unset($data['features'][$code]);
                        }
                    }
                } elseif ($data['row_type'] == self::STAGE_VARIANT) {
                    $sku_only = true;
                }
            }

            if (!in_array($item_sku_id, array(true, false, 0), true) && !empty($data['skus'][$item_sku_id]['_primary'])) {
                $product->sku_id = $item_sku_id;
                if (isset($data['sku_id'])) {
                    unset($data['sku_id']);
                }
            }

            $has_negative_prices = false;
            if (!empty($data['skus'])) {
                foreach ($data['skus'] as $sku_key => $sku) {
                    if (isset($sku['status']) && !($sku['status'] == '0' || $sku['status'] == '1')) {
                        if (strlen($sku['status'])) {
                            $this->writeImportError(sprintf_wp('The value of the “%s” field can be only 0 or 1.', _w('Visibility in the storefront')));
                        }
                        unset($data['skus'][$sku_key]['status']);
                    }
                    $has_negative_prices = $this->hasNegativePrices($sku);
                    // sku id is only used for identification, never to insert or update actual value of an id
                    unset($data['skus'][$sku_key]['id']);
                }
            }
            if (!$has_negative_prices) {
                $has_negative_prices = $this->hasNegativePrices($data, true);
            }
            if ($has_negative_prices) {
                if ($sku_only || $this->data['primary'] === null) {
                    $target_sku = 'skip';
                    $item_sku_id = false;
                } else {
                    $target = 'skip';
                }
            }
            $this->setDefaultUnitFeature($data);

            if (shopFrac::isEnabled()) {
                $correct_units = $this->validateUnits($data, $product, $item_sku_id);
                if ($correct_units === false) {
                    if ($data['row_type'] == self::STAGE_PRODUCT) {
                        if ($id) {
                            $this->data['last_wrong_product'] = [
                                'field' => 'id',
                                'value' => $id
                            ];
                            return false;
                        } elseif (isset($data[$this->data['primary']])) {
                            $this->data['last_wrong_product'] = [
                                'field' => $this->data['primary'],
                                'value' => $data[$this->data['primary']]
                            ];
                            return false;
                        } elseif (isset($data['url'])) {
                            $this->data['last_wrong_product'] = [
                                'field' => 'url',
                                'value' => $data['url']
                            ];
                            return false;
                        }
                    } else {
                        return false;
                    }
                }
            }

            $this->updateProductRemovalBehavior($data, $id);

            shopProductStocksLogModel::setContext(shopProductStocksLogModel::TYPE_IMPORT);
            if ($sku_only || ($this->data['primary'] === null)) {
                if ($product_exists && ($item_sku_id !== false)) {
                    if (!$this->emulate($product->__hash, $key)) {
                        if (isset($data['skus']) && isset($data['skus'][-1]) && is_array($data['skus'])
                            && !empty($this->data['is_new_product']) && empty($this->data['is_deleted_empty_sku'])
                        ) {
                            $sku_id = array_keys($data['skus'])[0];
                            $this->data['is_deleted_empty_sku'] = true;
                            $data['skus'][$sku_id] = '';
                        }

                        if (shopFrac::isEnabled()) {
                            foreach (['stock_base_ratio', 'order_count_min', 'order_count_step'] as $product_field) {
                                if (isset($data[$product_field])) {
                                    $data['skus'][$item_sku_id][$product_field] = $data[$product_field];
                                }
                            }
                        }

                        $truncated_data = array(
                            'skus' => $data['skus'],
                        );

                        if (!isset($available_for_sku_features)) {
                            $available_for_sku_features = $feature_model->select('code')->where('available_for_sku = 1')->fetchAll('code');
                            if (!$available_for_sku_features) {
                                $available_for_sku_features = array();
                            }
                            $available_for_sku_features['weight'] = true;
                        }

                        foreach (array_keys($available_for_sku_features) as $code) {
                            if (isset($data['features'][$code])) {
                                if (!isset($truncated_data['skus'][$item_sku_id]['features'])) {
                                    $truncated_data['skus'][$item_sku_id]['features'] = array();
                                }
                                if (isset($data['features'][$code])) {
                                    $truncated_data['skus'][$item_sku_id]['features'][$code] = $data['features'][$code];
                                }
                            }
                        }

                        try {
                            if (!empty($data['images'])) {
                                $images = array_filter((array)$data['images']);
                                if (count($images) === 1) {
                                    $file = reset($images);
                                    $name = $this->getImageName($file);
                                    $image = $this->findImage($file, $name, $product['id']);
                                    if ($image) {
                                        $truncated_data['skus'][$item_sku_id]['image_id'] = $image['id'];
                                    }
                                }
                            }
                        } catch (waException $e) {
                            $this->error($e->getMessage());
                            $this->writeImportError(_w('The SKU image could not be imported.') . '; ' . $e->getMessage());
                        }

                        try {
                            if ($product->save($truncated_data)) {
                                if (isset($data['row_type']) && $data['row_type'] == self::STAGE_VARIANT) {
                                    $this->setSelectableFeatureIds($product, $saved_features);
                                }
                            } else {
                                $target_sku = 'validate';
                            }
                            $this->data['map'][self::STAGE_PRODUCT] = $product->getId();
                            $this->data['map'][self::STAGE_VARIANT] = null;
                            if ($item_sku_id == -1 && !empty($product['skus']) && is_array($product['skus'])) {
                                $last_sku_id = max(array_keys($product['skus']));
                                if ($last_sku_id > 0) {
                                    $this->data['map'][self::STAGE_VARIANT] = $last_sku_id;
                                }
                            } elseif (isset($product['skus'][$item_sku_id])) {
                                $this->data['map'][self::STAGE_VARIANT] = $item_sku_id;
                            }
                            if (!empty($data['images'])) {
                                $this->data['map'][self::STAGE_IMAGE] = $data['images'];
                                $this->data['map'][self::STAGE_IMAGE_DESCRIPTION] = ifempty($data['images_descriptions'], array());
                                $this->data['count'][self::STAGE_IMAGE] += count((array)$data['images']);
                            }

                            $this->checkMainSku($product);
                        } catch (waDbException $ex) {
                            $this->writeImportError($ex->getMessage());
                            $target_sku = 'error';
                        }

                    } else {
                        $this->data['map'][self::STAGE_PRODUCT] = $product->__hash;
                    }
                }
            } else {
                $is_updated_new_product = false;
                if ($target != 'skip') {
                    if (!$this->emulate($product->__hash, $key)) {
                        try {
                            if ($product_exists && isset($data['row_type']) && $data['row_type'] == self::STAGE_PRODUCT) {
                                unset($data['skus'][-1]);
                            }
                            $not_added = !$product->getId();
                            if ($product->save($data)) {
                                if ('cli' === php_sapi_name() && $not_added) {
                                    self::$save_product_ids[] = $product->getId();
                                }
                                $is_updated_new_product = !empty($this->data['last_saved_product_id_without_skus']) && $this->data['last_saved_product_id_without_skus'] == $product->getId();
                                $this->afterSaveProduct($data, $product, $saved_features, $product_exists);
                            } else {
                                $target = 'validate';
                            }

                            $this->checkMainSku($product);

                            $this->data['map'][self::STAGE_PRODUCT] = $product->getId();
                            $this->data['map'][self::STAGE_VARIANT] = null;
                            if (!empty($data['images'])) {
                                $this->data['map'][self::STAGE_IMAGE] = $data['images'];
                                $this->data['map'][self::STAGE_IMAGE_DESCRIPTION] = ifempty($data['images_descriptions'], array());
                                $this->data['count'][self::STAGE_IMAGE] += is_array($data['images']) ? count($data['images']) : 1;
                            }
                        } catch (waDbException $ex) {
                            $this->writeImportError($ex->getMessage());
                            $target = 'error';
                            $target_sku = 'error';
                        }

                    } else {
                        $this->data['map'][self::STAGE_PRODUCT] = $product->__hash;
                    }
                }
                if (!($is_updated_new_product && $target == 'update')) {
                    $this->data['processed_count'][self::STAGE_PRODUCT][$target]++;
                }
            }

            if (!isset($this->data['emulate_keys'][$product->__hash][$key])) {
                $this->data['emulate_keys'][$product->__hash][$key] = $this->emulate();
            } else {
                $this->writeImportError(_w('Repeating row.'));
            }


            shopProductStocksLogModel::clearContext();

            $is_sku = true;
            if (isset($data['row_type'])) {
                $is_sku = $data['row_type'] == self::STAGE_VARIANT;
            }
            if (($product->getId() || $this->emulate()) && $is_sku) {
                $this->data['processed_count'][self::STAGE_VARIANT][$target_sku]++;
            }
        } else {
            $this->data['processed_count'][self::STAGE_PRODUCT]['rights']++;
        }

        return true;
    }

    /**
     * @usedby stepImport
     * @return bool
     * @throws Exception
     */
    private function stepImportImage()
    {
        if (!is_array($this->data['map'][self::STAGE_IMAGE]) && $this->data['map'][self::STAGE_IMAGE]) {
            $this->data['map'][self::STAGE_IMAGE] = array($this->data['map'][self::STAGE_IMAGE]);
        }

        if ($file = reset($this->data['map'][self::STAGE_IMAGE])) {
            $description = null;
            if (!empty($this->data['map'][self::STAGE_IMAGE_DESCRIPTION])) {
                $description = reset($this->data['map'][self::STAGE_IMAGE_DESCRIPTION]);
            }
            /** @var shopProductImagesModel $model */
            $model = $this->model('product_images');

            try {
                $product = array(
                    'product_id' => $this->data['map'][self::STAGE_PRODUCT],
                );

                $name = $this->getImageName($file);
                $exists = $this->findImage($file, $name, $product['product_id']);

                if ($exists) {
                    $target = !empty($exists['::same']) ? 'skip' : 'update';
                    $product['image_id'] = $exists['id'];
                    if ($description === null) {
                        $description = $exists['description'];
                    }
                } else {
                    $target = 'new';
                }

                switch ($target) {
                    case 'skip': //just update description
                        if (($description !== null)
                            && ($description != $exists['description'])
                        ) {
                            $model->updateById($exists['id'], compact('description'));
                            $this->data['processed_count'][self::STAGE_IMAGE_DESCRIPTION]['update']++;
                        }
                        $this->writeImportError(_w('The image already exists.'));
                        break;
                    default:
                        $image = $this->getImage($file);
                        $added_image = $model->addImage($image, $product, $name, $description);

                        if (!empty($added_image['id']) && !empty($this->data['map'][self::STAGE_VARIANT])) {
                            /** @var shopProductSkusModel $product_skus */
                            $product_skus = $this->model('product_skus');
                            $product_skus->updateById($this->data['map'][self::STAGE_VARIANT], ['image_id' => $added_image['id']]);
                        }

                        if (!empty($exists['id'])) {
                            $thumb_dir = shopImage::getThumbsPath($exists);
                            waFiles::delete($thumb_dir, true);
                            $back_thumb_dir = preg_replace('@(/$|$)@', '.back$1', $thumb_dir, 1);
                            waFiles::delete($back_thumb_dir, true);
                        }

                        if ((empty($exists) && !empty($description))
                            || $description != ifset($exists, 'description', null)
                        ) {
                            $this->data['processed_count'][self::STAGE_IMAGE_DESCRIPTION][$target]++;
                        }
                        break;
                }


            } catch (Exception $e) {
                $target = 'error';
                $this->error($e->getMessage());
                $this->writeImportError(_w('The image could not be imported.') . '; ' . $e->getMessage());
            }

            $this->data['processed_count'][self::STAGE_IMAGE][$target]++;

            array_shift($this->data['map'][self::STAGE_IMAGE]);
            array_shift($this->data['map'][self::STAGE_IMAGE_DESCRIPTION]);
            ++$this->data['current'][self::STAGE_IMAGE];
        }

        return true;
    }

    /**
     * @usedby stepImport
     * @param $data
     * @return bool
     * @throws waException
     */
    private function stepImportCategory($data)
    {
        /** @var shopCategoryModel $model */
        $model = $this->model('category');

        $empty = $this->reader->getEmpty();
        $data += $empty;
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
        if (isset($data['row_type']) && $data['row_type'] == self::STAGE_DYNAMIC_CATEGORY) {
            $data['type'] = shopCategoryModel::TYPE_DYNAMIC;
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

        //use ID only for search
        unset($data['id']);

        try {
            self::filterEmptyRows($data, array('url'));
            $key = 'c:';
            $id = null;
            if ($current_data = $model->getByField($fields)) {
                $key .= 'u:'.$current_data['id'];
                $id = $current_data['id'];
                $stack[] = $id;

                if (!$this->emulate($key)) {
                    $target = 'update';
                    if (!$model->update($current_data['id'], $data)) {
                        $target = 'error';
                        $id = null;
                    }
                } else {
                    $id = null;
                    $target = 'found';
                }
            } else {
                $key .= 'a:';
                if (!$this->emulate()) {
                    if (!isset($data['url'])) {
                        $data['url'] = shopHelper::transliterate($data['name']);
                    }
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
            if (!empty($data['params']) && !$this->emulate() && !empty($id)) {
                $params = array();
                foreach (explode("\n", $data['params']) as $param_str) {
                    $param = explode('=', $param_str, 2);
                    if (count($param) > 1) {
                        $params[$param[0]] = trim($param[1]);
                    }
                }

                /** @var shopCategoryParamsModel $params_model */
                $params_model = $this->model('category_params');
                $params_model->set($id, $params);
            }

            if (!$this->emulate() && !empty($id)) {
                $data['id'] = $id;
                /**
                 * @event category_save
                 * @param array $category
                 * @return void
                 */
                wa()->event('category_save', $data);
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
                fwrite($this->fd, $this->reader->key().';;'.$key.'|'.$second_key."\n");
            }
            return true;
        }
    }

    protected function workupData(&$data)
    {
        if (isset($data['url'])) {
            $data['url'] = trim($data['url'], '/ ');
            if (strpos($data['url'], '/') !== false) {
                unset($data['url']);
            }
        }
    }

    /**
     * @param $data
     * @return string
     * @throws waException
     */
    public static function getDataType(&$data)
    {
        if (isset($data['row_type'])) {
            $row_type = mb_strtolower(trim($data['row_type']));
            $types = [self::STAGE_CATEGORY, self::STAGE_DYNAMIC_CATEGORY, self::STAGE_PRODUCT, self::STAGE_PRODUCT_VARIANT, self::STAGE_VARIANT];
            $found_type = array_search($row_type, $types);
            if ($found_type === false) {
                $data['row_type'] = self::STAGE_VARIANT;
                throw new waException($row_type);
            } else {
                $data['row_type'] = $row_type;
                $type = $row_type == self::STAGE_CATEGORY || $row_type == self::STAGE_DYNAMIC_CATEGORY ? self::STAGE_CATEGORY : self::STAGE_VARIANT;
            }
        } else {
            static $non_category_fields = [
                "skus",
                "features",
                "total_sales",
                "type_name",
                "images",
                "images_descriptions",
                "tax_name",
            ];
            $type = self::STAGE_CATEGORY;
            foreach ($non_category_fields as $field) {
                if (!empty($data[$field])) {
                    $type = self::STAGE_VARIANT;
                    break;
                }
            }
        }

        return $type;
    }

    /**
     * @return bool
     * @usedby shopCsvProductrunController::step()
     * @throws waException
     */
    private function stepExport()
    {
        $res = $this->stepExportProduct($this->data['current'], $this->data['count'], $this->data['processed_count']);

        if (!$res                             #export products chunk complete
            && $this->data['export_category'] #export categories is enabled
        ) {
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

            if (($this->data['direction'] == 'import') && ($this->data['file'])) {
                if (!$this->reader) {
                    $this->reader = unserialize($this->data['file']);
                }
                $path = $this->getImageTmpPath();
                $this->reader->delete(true);
                waFiles::delete($path, true);
            }
        } else {
            $this->info($filename);
        }

        return $cleanup;
    }

    protected function report($collision)
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

        if ($this->emulate() && $collision) {
            $collisions = array();
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
        $class = 's-csv-importexport-stats alert';
        if (!$this->emulate()) {
            $class .= ' done success';
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

        $type = $this->getErrorType();
        if (isset($this->data['error_log_name'][$type])) {
            $report_file = wa()->getTempPath('csv/download/0/' . $this->data['error_log_name'][$type]);
            $report .= '<div class="block"><p>' . _w('Errors have been found in your CSV file. Download the log file to view details.') . '</p>';
            $report .= '<div><a href="?module=csv&action=productdownload&profile=0&file='.$report_file.'" class="bold nowrap">';
            $report .= '<i class="icon16 download fas fa-file-download"></i>' . _w('Download log file') . '</a></div></div>';
        }

        return iconv('UTF-8', 'UTF-8//IGNORE', $report);
    }

    protected function info($filename = null)
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
        $stage_count = 0;
        $category_progress = 0;
        $category_progress_step = 0;
        if (!empty($this->data['current'][self::STAGE_CATEGORY]) && !empty($this->data['count'][self::STAGE_CATEGORY])) {
            $categories_count = $this->data['count'][self::STAGE_CATEGORY] + 1;
            $category_progress = 100.0 * (1.0 * $this->data['current'][self::STAGE_CATEGORY] / $categories_count);
            $category_progress_step = 100.0 * (1.0 / $categories_count);
        }
        foreach ($this->data['current'] as $stage => $current) {
            if ($this->data['count'][$stage]) {
                if ($stage == self::STAGE_VARIANT) {
                    if ($this->data['direction'] == 'import') {
                        $response['progress'] += 100.0 * (1.0 * $current / $this->data['count'][$stage] - 1.0) / $this->data['count'][self::STAGE_PRODUCT];
                    }
                } else {
                    if ((!empty($category_progress) && $stage != self::STAGE_CATEGORY) || empty($category_progress)) {
                        ++$stage_count;
                        $response['progress'] += 100.0 * (1.0 * $current / $this->data['count'][$stage]);
                    }
                }
            }
        }
        if ($category_progress) {
            $total_progress = min(100, $response['progress'] / max(1, $stage_count) / 100.0 * $category_progress_step + $category_progress);
            $response['progress'] = sprintf('%0.3f%%', $total_progress);
        } else {
            $response['progress'] = sprintf('%0.3f%%', $response['progress'] / max(1, $stage_count));
        }
        $response['stage_count'] = $stage_count;
        $response['current_count'] = $this->data['current'];
        $response['processed_count'] = $this->data['processed_count'];
        $this->getResponse()->addHeader('Content-type', 'application/json');
        $this->getResponse()->sendHeaders();
        if ($this->writer) {
            $response['file'] = urlencode(basename($this->writer->file()));
        }
        if ($response['ready']) {
            if ($filename && $this->emulate()) {
                $collision = $this->getCollision($filename);
            } else {
                $collision = array();
            }
            $response['report'] = $this->report($collision);

            if ($this->emulate()) {
                $params = array();
                if ($collision) {
                    $response['collision'] = array();
                    foreach ($collision as $key => $rows) {
                        $rows = array_map('intval', $rows);
                        asort($rows);
                        $response['collision'][] = array(
                            'key'  => $key,
                            'rows' => array_values($rows),
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

    // Helper for info() to generate part of a final report
    protected function getCollision($filename)
    {
        $emulate = array();
        foreach (explode("\n", file_get_contents($filename)) as $line) {
            if ($line) {
                list($row, $key) = explode(';;', $line);
                if (!isset($emulate[$key])) {
                    $emulate[$key] = array();
                }
                $emulate[$key][] = $row;
            }
        }
        return array_filter($emulate, wa_lambda('$a', 'return count($a)>1;'));
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
            /** @var shopCategoryModel $model */
            $model = $this->model('category');
            if (preg_match('@^category/(\d+)$@', $this->data['hash'], $matches)) {
                $category_id = $matches[1];
                $category = $model->getById($category_id);
                if ($category) {
                    if (!empty($category['include_sub_categories']) || $this->params['include_sub_categories']) {
                        $categories = array_reverse($model->getTree($category_id), true);
                    } else {
                        $categories[$category_id] = $category;
                    }
                    $categories += $model->getPath($category_id);
                    $categories = array_reverse($categories, true);
                }

            } else {
                $categories = $model->getFullTree('*');
            }

            if (count($categories) != $count[self::STAGE_CATEGORY]) {
                throw new waException(sprintf('Invalid category count. Expected %d but get %d', $this->data['count'][self::STAGE_CATEGORY], count($categories)));
            }
            if ($current_stage) {
                $categories = array_slice($categories, $current_stage[self::STAGE_CATEGORY], null, true);
            }

            if (!empty($this->data['config']['params']) && $categories) {
                /** @var shopCategoryParamsModel $params_model */
                $params_model = $this->model('category_params');
                $category_map = array();
                foreach ($categories as &$category) {
                    $category['params'] = array();
                    $category_map[$category['id']] = &$category['params'];
                }
                unset($category);

                foreach ($params_model->getByField('category_id', array_keys($category_map), true) as $params) {
                    $category_map[$params['category_id']][$params['name']] = $params['value'];
                }
                unset($category_map);
            }

        }
        $category = reset($categories);
        if ($category) {
            //XXX category hidden at current settlement are not skipped
            $category['name'] = str_repeat('!', $category['depth']).$category['name'];

            if (!empty($category['params']) && is_array($category['params'])) {
                $category['params'] = $this->paramsToString($category['params']);
            }

            $category['row_type'] = $category['type'] == shopCategoryModel::TYPE_STATIC ? self::STAGE_CATEGORY : self::STAGE_DYNAMIC_CATEGORY;
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

        if (!$products) {
            $offset = $current_stage[self::STAGE_PRODUCT] - ifset($this->data['map'][self::STAGE_PRODUCT], 0);
            $fields = '*';
            if (!empty($this->data['options']['images'])) {
                $fields .= ', images';
            }
            $products = $this->getCollection()->getProducts($fields, $offset, 50, false);
        }
        $chunk = 5;
        while (($chunk-- > 0) && ($product = reset($products))) {
            $exported = false;
            /* check rights per product type && settlement options */
            $rights = empty($product['type_id']) || in_array($product['type_id'], $this->data['types']);

            $category_id = isset($product['category_id']) ? intval($product['category_id']) : null;
            /* check category match*/
            $category_match = !$this->data['export_category'] || ($category_id === $this->data['map'][self::STAGE_CATEGORY]);

            $is_duplicate_product = false;
            if (isset($this->data['exported_products'][$product['id']]) && $this->data['config']['extra_categories']) {
                $is_duplicate_product = true;
            }

            if (!$category_match) {

                /* check subcategory match */
                if (isset($this->data['include_sub_categories'])) {
                    if (!in_array($category_id, $this->data['include_sub_categories'])) {
                        if (!isset($this->data['external_products'])) {
                            $this->data['external_products'] = array();
                        }

                        if (!isset($this->data['external_products'] [$product['id']])) {
                            $category_match = true;
                            $this->data['external_products'] [$product['id']] = $category_id;
                        }
                    }
                }

                /* check extra categories match */
                if (!$category_match && $this->data['config']['extra_categories']) {
                    $category_match = true;
                }
            }

            if ($rights && $category_match) {
                /** @var shopProductFeaturesModel $product_features_model */
                $product_features_model = $this->model('product_features');
                $shop_product = new shopProduct($product, ['format_fractional_values' => true]);
                if (!empty($this->data['options']['features']) && !$is_duplicate_product) {
                    if (!isset($product['features'])) {
                        $product['features'] = $product_features_model->getValues($product['id']);
                    }
                    foreach ($product['features'] as $code => &$feature) {
                        if (!empty($this->data['composite_features'][$code])) {
                            $feature = str_replace('×', 'x', $feature);
                        }
                        unset($feature);
                    }
                }

                foreach (['order_multiplicity_factor', 'stock_base_ratio', 'order_count_min', 'order_count_step'] as $product_field) {
                    $product[$product_field] = shopFrac::discardZeros($product[$product_field]);
                }

                # tags
                if (!isset($product['tags']) && !$is_duplicate_product) {
                    /** @var shopProductTagsModel $tags_model */
                    $tags_model = $this->model('product_tags');
                    $product['tags'] = $this->writeRow($tags_model->getTags($product['id']));
                }

                # images
                if (!empty($this->data['options']['images'])) {
                    if (isset($product['images'])) {
                        $size = $this->getImageSize($this->data['config']['images']);
                        $domain = ifempty($this->data['base_url'], 'localhost');
                        $scheme = ifempty($this->data['scheme'], 'http://');
                        $images = array();
                        $images_descriptions = array();
                        foreach ($product['images'] as $image_id => $image) {
                            $images[$image_id] = $scheme.$domain.shopImage::getUrl($image, $size);
                            $images_descriptions[$image_id] = $image['description'];
                        }
                        $product['images'] = $images;
                        $product['images_descriptions'] = $images_descriptions;
                        unset($images);
                        unset($images_descriptions);
                    }
                }

                # product params
                if (!empty($product['params']) && is_array($product['params'])) {
                    $product['params'] = $this->paramsToString($product['params']);
                }

                $product['type_name'] = ifset($shop_product, 'type', 'name', '');

                # taxes
                if (!empty($product['tax_id'])) {
                    $product['tax_name'] = ifset($this->data['taxes'][$product['tax_id']]);
                }

                # features
                if (!isset($product['features'])) {
                    $product['features'] = array();
                }

                $units = $this->getUnits();
                $not_specified = false;
                if ($product['stock_unit_id'] == $product['base_unit_id']) {
                    $product['base_unit_id'] = _w('Not specified');
                    $not_specified = true;
                }
                foreach ($units as $unit) {
                    if ($unit['id'] == $product['stock_unit_id']) {
                        $product['stock_unit_id'] = $unit['okei_code'];
                    }
                    if (!$not_specified && $unit['id'] == $product['base_unit_id']) {
                        $product['base_unit_id'] = $unit['okei_code'];
                    }
                }

                #skus
                $skus = $shop_product->skus;
                $skus_exist = !empty($skus);

                /** @var shopProductFeaturesSelectableModel $features_selectable_model */
                $features_selectable_model = $this->model('product_features_selectable');
                $selected_selectable_feature_ids = $features_selectable_model->getProductFeatureIds($product['id']);
                $has_features_values = $product_features_model->checkProductFeaturesValues($product['id'], $product['type_id']);
                $simple_product = !(count($skus) > 1 || $has_features_values || !empty($shop_product->params['multiple_sku']) || $selected_selectable_feature_ids);

                $primary_sku_id = $product['sku_id'];
                if (!isset($skus[$primary_sku_id])) {
                    //set default sku as first
                    if ($skus_exist) {
                        $primary_sku_id = key($skus);
                        $product['sku_id'] = $primary_sku_id;
                    } else {
                        $product['sku_id'] = null;
                    }
                }

                if ($skus_exist) {
                    $sku = $skus[$primary_sku_id];
                    if (!empty($this->data['config']['primary_sku'])) {
                        $sku['_primary'] = '1';
                    }
                    unset($skus[$primary_sku_id]);
                    $skus = array($product['sku_id'] => $sku,) + $skus;
                } else {
                    /** @var shopProductSkusModel $sku_model */
                    $sku_model = $this->model('product_skus');
                    $sku = $sku_model->getEmptyRow();
                    $skus = [];
                }

                if (!$this->data['config']['export_mode']) {
                    $this->exportProductRow($product, $sku, false, !$is_duplicate_product, $simple_product);
                }

                if ((!$is_duplicate_product && !$simple_product) || $this->data['config']['export_mode']) {
                    foreach ($skus as $sku_id => $sku) {
                        if (!empty($this->data['config']['primary_sku'])) {
                            $sku['_primary'] = ($primary_sku_id == $sku_id) ? '1' : '';
                        }

                        $exported_product = $this->exportProductRow($product, $sku, true, !$is_duplicate_product, $simple_product);

                        ++$current_stage[self::STAGE_VARIANT];
                        if (!$is_duplicate_product) {
                            ++$processed[self::STAGE_VARIANT];
                            if (isset($exported_product['images'])) {
                                $processed[self::STAGE_IMAGE] += count($exported_product['images']);
                            }
                        }
                    }
                }

                $exported = true;
            } elseif (count($products) > 1) {
                ++$chunk;
            }

            array_shift($products);
            ++$current_stage[self::STAGE_PRODUCT];
            if ($exported && !$is_duplicate_product) {
                ++$processed[self::STAGE_PRODUCT];
            }
        }

        return ($current_stage[self::STAGE_PRODUCT] < $count[self::STAGE_PRODUCT]);
    }

    private function prepareProductFeatures(&$product, &$sku, $simple_product)
    {
        foreach ($product['features'] as $code => &$values) {
            if (isset($sku['features'][$code])) {
                switch ($code) {
                    case 'weight':
                        if (!empty($sku['features'][$code])) {
                            $values = $sku['features'][$code];
                        }
                        break;

                    default:
                        $sku_values = is_array($sku['features'][$code]) ? $sku['features'][$code] : array($sku['features'][$code]);
                        $product_values = is_array($values) ? $values : array($values);
                        $values = array_merge(
                            $product_values,
                            $sku_values
                        );

                        $values = array_unique($values);
                        break;
                }

            }
            unset($values);
        }

        if ($product['sku_type'] == shopProductModel::SKU_TYPE_SELECTABLE) {
            /** @var shopProductFeaturesSelectableModel $features_selectable_model */
            $features_selectable_model = $this->model('product_features_selectable');
            if ($selected = $features_selectable_model->getByProduct($product['id'])) {

                /** @var shopFeatureModel $feature_model */
                $feature_model = $this->model('feature');

                $features = $feature_model->getById(array_keys($selected));

                foreach ($features as $feature_id => $feature) {

                    $search_feature_values = array(
                        'feature_id' => $feature_id,
                        'id'         => $selected[$feature_id],
                    );

                    if ($feature_model = shopFeatureModel::getValuesModel($feature['type'])) {

                        $values = $feature_model->getValues($search_feature_values);

                        if (!empty($values[$feature['id']])) {
                            $f_values = $values[$feature['id']];
                            if (!isset($product['features'])) {
                                $product['features'] = array();
                            }
                            if (isset($sku['features'][$feature['code']])) {
                                array_unshift($f_values, (string)$sku['features'][$feature['code']]);
                            }

                            $product['features'][$feature['code']] = $this->writeRow($f_values, '<{%s}>', true);
                        }
                    }
                }
            }
        }

        if (!$simple_product) {
            $this->updateSelectableFeatureCodes($product['id']);
            foreach ($product['features'] as $code => $values) {
                if (isset(self::$selectable_feature_codes[$product['id']][$code])) {
                    $product['features'][$code] = '<{}>';
                }
            }
        }
    }

    private function prepareProductSkuFeatures(&$product, $sku)
    {
        $features = $sku['features'];
        if (!isset($features['weight']) && isset($product['features']['weight'])) {
            $features['weight'] = $product['features']['weight'];
        }
        $product['features'] = $features;
    }

    private function prepareProductAllSkuFeatures(&$product, $sku)
    {
        foreach ($sku['features'] as $code => $values) {
            if (!empty($values)) {
                $product['features'][$code] = $values;
            }
        }
    }

    protected function getUnits()
    {
        static $units = null;

        if ($units === null) {
            $units_model = new shopUnitModel();
            $piece = $units_model::getPc();
            $units = $units_model->select('id, okei_code, status')->fetchAll('id');
            $units += [
                $piece['id'] => $piece,
            ];
        }

        return $units;
    }

    /**
     * @param $data
     * @param $product shopProduct
     * @param $item_sku_id
     * @return bool
     * @throws waException
     */
    protected function validateUnits(&$data, $product, $item_sku_id)
    {
        static $types = null;
        if ($types === null) {
            $type_model = new shopTypeModel();
            $types = $type_model->getAll('id');
        }

        if (isset($data['row_type']) && in_array($data['row_type'], [self::STAGE_PRODUCT, self::STAGE_PRODUCT_VARIANT, self::STAGE_VARIANT])) {
            if ($data['row_type'] == self::STAGE_VARIANT) {
                $is_update_product = is_numeric($item_sku_id) && $item_sku_id > 0;
            } else {
                $is_update_product = $product->getId() > 0;
            }
            $units = $this->getUnits();
            if (isset($data['type_id']) && isset($types[$data['type_id']])) {
                $current_type = $types[$data['type_id']];
            } elseif (isset($current_type[$product->type_id])) {
                $current_type = $current_type[$product->type_id];
            } else {
                $current_type = reset($types);
            }
            $units_fields = ['stock_unit_id' => false, 'base_unit_id' => false];
            if ($current_type) {
                if ($current_type['stock_unit_fixed'] == shopTypeModel::PARAM_ALL_PRODUCTS) {
                    $units_fields['stock_unit_id'] = true;
                }
                if ($current_type['base_unit_fixed'] == shopTypeModel::PARAM_ALL_PRODUCTS) {
                    $units_fields['base_unit_id'] = true;
                }
            }
            if ($data['row_type'] != self::STAGE_VARIANT) {
                if (shopUnits::stockUnitsEnabled()) {
                    $correct_stock_unit = $this->validateStockUnit($units_fields, $data, $product, $is_update_product, $units, $current_type);
                    if ($correct_stock_unit === false) {
                        return false;
                    }
                }
                if (shopUnits::baseUnitsEnabled()) {
                    $correct_base_unit = $this->validateBaseUnit($units_fields, $data, $is_update_product, $units, $current_type);
                    if ($correct_base_unit === false) {
                        return false;
                    }
                }
                $this->data['equal_units'] = false;
                if (isset($data['stock_unit_id']) && isset($data['base_unit_id']) && $data['stock_unit_id'] == $data['base_unit_id']) {
                    $this->data['equal_units'] = true;
                }
            }

            if (shopUnits::baseUnitsEnabled()) {
                if (!isset($data['stock_base_ratio']) || $data['stock_base_ratio'] === '') {
                    if ($data['row_type'] == self::STAGE_VARIANT) {
                        unset($data['stock_base_ratio']);
                    } else {
                        if ($is_update_product) {
                            unset($data['stock_base_ratio']);
                        } else {
                            $data['stock_base_ratio'] = $current_type['stock_base_ratio'];
                        }
                    }
                } else {
                    if ($data['row_type'] != self::STAGE_VARIANT && $data['stock_unit_id'] == $data['base_unit_id']) {
                        $data['stock_base_ratio'] = 1;
                    } elseif ($data['row_type'] == self::STAGE_VARIANT && !empty($this->data['equal_units'])) {
                        $data['stock_base_ratio'] = null;
                    } elseif ($data['stock_base_ratio'] < 0.00000001 || $data['stock_base_ratio'] > '99999999.99999999'
                        || !$this->isCorrectFractionalPart($data['stock_base_ratio'], 8)
                    ) {
                        if ($is_update_product) {
                            $this->writeImportError(_w('The stock to base quantity units ratio was not updated because its value must be in the range from 0.00000001 and 99,999,999.99999999 and may contain a maximum of 8 decimal digits.'));
                            unset($data['stock_base_ratio']);
                        } else {
                            $this->writeImportError(_w('The stock to base quantity units ratio must be in the range from 0.00000001 and 99,999,999.99999999 and may contain a maximum of 8 decimal digits.'));
                            return false;
                        }
                    }
                }
            }

            foreach (['order_multiplicity_factor', 'order_count_min', 'order_count_step'] as $field) {
                if ($field == 'order_multiplicity_factor') {
                    if ($data['row_type'] == self::STAGE_VARIANT) {
                        continue;
                    } elseif ($is_update_product) {
                        $this->data['product_order_multiplicity_factor'] = ifset($product, 'order_multiplicity_factor', null);
                    } else {
                        $this->data['product_order_multiplicity_factor'] = ifset($data, 'order_multiplicity_factor', null);
                    }
                }
                if (!isset($data[$field]) || $data[$field] === '' || $current_type[$field.'_fixed'] == shopTypeModel::PARAM_DISABLED) {
                    if ($is_update_product || $data['row_type'] == self::STAGE_VARIANT) {
                        unset($data[$field]);
                    } else {
                        $data[$field] = $current_type[$field];
                    }
                } elseif (!is_numeric($data[$field]) || $data[$field] < 0.001 || $data[$field] > 999999.999
                    || ($field != 'order_multiplicity_factor'
                        && !empty($this->data['product_order_multiplicity_factor'])
                        && !empty(explode('.', $data[$field] / $this->data['product_order_multiplicity_factor'])[1]))
                    || !$this->isCorrectFractionalPart($data[$field], 3)
                ) {
                    if ($field == 'order_multiplicity_factor') {
                        $warning_message = _w('The add-to-cart step value was not updated because it must be in the range from 0.001 to 999,999.999 and may contain a maximum of 3 decimal digits.');
                        $error_message = _w('The add-to-cart step value must be in the range from 0.001 to 999,999.999 and may contain a maximum of 3 decimal digits.');
                    } elseif ($field == 'order_count_min') {
                        $warning_message = _w('The minimum orderable quantity must be divisible without remainder by the add-to-cart step value, must be in the range from 0.001 to 999,999.999 and may contain a maximum of 3 decimal digits.');
                        $error_message = _w('The minimum orderable quantity must be divisible without remainder by the add-to-cart step value, must be in the range from 0.001 to 999,999.999 and may contain a maximum of 3 decimal digits.');
                    } else {
                        $warning_message = _w('The quantity adjustment value via “+/-” buttons must be divisible without remainder by the add-to-cart step value, must be in the range from 0.001 to 999,999.999 and may contain a maximum of 3 decimal digits.');
                        $error_message = _w('The quantity adjustment value via “+/-” buttons must be divisible without remainder by the add-to-cart step value, must be in the range from 0.001 to 999,999.999 and may contain a maximum of 3 decimal digits.');
                    }
                    if ($is_update_product) {
                        $this->writeImportError($warning_message);
                        unset($data[$field]);
                    } else {
                        $this->writeImportError($error_message);
                        return false;
                    }
                }
            }
        }
        return true;
    }

    protected function validateStockUnit($units_fields, &$data, $product, $is_update_product, $units, $current_type)
    {
        if ($is_update_product && (!isset($data['stock_unit_id']) || $data['stock_unit_id'] === '')) {
            unset($data['stock_unit_id']);
            return true;
        } elseif ($current_type && (!$units_fields['stock_unit_id'] || !isset($data['stock_unit_id']) || $data['stock_unit_id'] === '')) {
            if ($is_update_product && !$units_fields['stock_unit_id'] && $data['stock_unit_id'] != $current_type['stock_unit_id']) {
                $this->writeImportError(_w('The stock quantity unit was not updated because it may not be changed according to product type settings.'));
            }
            $data['stock_unit_id'] = $current_type['stock_unit_id'];
        } else {
            $is_first_code = true;
            $found_unit = false;
            $data_unit = $data['stock_unit_id'];
            foreach ($units as $unit) {
                if ($unit['okei_code'] == $data_unit && $unit['status']) {
                    if ($is_first_code === true) {
                        if ($is_update_product) {
                            if ((!$units_fields['base_unit_id'] && isset($product['base_unit_id']) && $product['base_unit_id'] == $data['stock_unit_id'])
                                || (isset($data['base_unit_id']) && $data['base_unit_id'] == $data['stock_unit_id'])
                            ) {
                                unset($data['stock_unit_id']);
                                $this->writeImportError(_w('The stock quantity unit was not updated because it may not be the same as the base unit.'));
                                return true;
                            }
                        } elseif (isset($data['base_unit_id']) && $data['base_unit_id'] == $data['stock_unit_id']) {
                            $this->writeImportError(_w('The stock and the base quantity units must be different.'));
                            return false;
                        }
                        $data['stock_unit_id'] = $unit['id'];
                        $found_unit = true;
                    } else {
                        $this->writeImportError(_w('The quantity units configuration table contains more than one unit having the specified stock quantity unit code. One of those units, randomly selected, was imported.'));
                    }
                    $is_first_code = false;
                }
            }
            if (!$found_unit) {
                if ($is_update_product) {
                    unset($data['stock_unit_id']);
                    $this->writeImportError(_w('The stock quantity unit was not updated because the value specified for a stock quantity unit is missing in the list of enabled quantity units.'));
                } else {
                    $this->writeImportError(_w('The value specified for a stock quantity unit is missing in the list of enabled quantity units.'));
                    return false;
                }
            }
        }
        return true;
    }

    protected function validateBaseUnit($units_fields, &$data, $is_update_product, $units, $current_type)
    {
        if ($is_update_product && (!isset($data['base_unit_id']) || $data['base_unit_id'] === '')) {
            unset($data['base_unit_id']);
            return true;
        } elseif ($current_type && (!$units_fields['base_unit_id'] || !isset($data['base_unit_id']) || $data['base_unit_id'] === '')) {
            if (isset($current_type['base_unit_id'])) {
                if ($is_update_product && !$units_fields['base_unit_id'] && $data['base_unit_id'] != $current_type['base_unit_id']) {
                    $this->writeImportError(_w('The base quantity unit was not updated because it may not be changed according to product type settings.'));
                }
                $data['base_unit_id'] = $current_type['base_unit_id'];
            } else {
                $data['base_unit_id'] = $data['stock_unit_id'];
            }
        } else {
            $is_first_code = true;
            $found_unit = false;
            $data_unit = $data['base_unit_id'];
            $not_specified = mb_strtolower(_w(trim($data_unit))) == mb_strtolower(_w('Not specified'));
            if ($not_specified) {
                $data['base_unit_id'] = $data['stock_unit_id'];
                $found_unit = true;
            } else {
                foreach ($units as $unit) {
                    if ($unit['okei_code'] == $data_unit && $unit['status']) {
                        if ($is_first_code === true) {
                            if ($data['stock_unit_id'] == $data_unit) {
                                if ($is_update_product) {
                                    $this->writeImportError(_w('The base quantity unit was not updated because it may not be the same as the stock unit.'));
                                    unset($data['base_unit_id']);
                                    return true;
                                } else {
                                    $this->writeImportError(_w('The stock and the base quantity units must be different.'));
                                    return false;
                                }
                            }
                            $data['base_unit_id'] = $unit['id'];
                            $found_unit = true;
                        } else {
                            $this->writeImportError(_w('The quantity units configuration table contains more than one unit having the specified base quantity unit code. One of those units, randomly selected, was imported.'));
                        }
                        $is_first_code = false;
                    }
                }
            }
            if (!$found_unit) {
                if ($is_update_product) {
                    $this->writeImportError(_w('The base quantity unit was not updated because the value specified for a base quantity unit is missing in the list of enabled quantity units.'));
                    unset($data['base_unit_id']);
                } else {
                    $data['base_unit_id'] = $data['stock_unit_id'];
                    $this->writeImportError(_w('The base quantity unit was not set because the value specified for a base quantity unit is missing in the list of enabled quantity units.'));
                }
            }
        }
        return true;
    }

    function isCorrectFractionalPart($value, $sign_count)
    {
        $number_parts = explode('.', $value);
        if (isset($number_parts[1]) && mb_strlen($number_parts[1]) > $sign_count) {
            return false;
        }
        return true;
    }

    private function exportProductRow($original_product, $sku, $sku_mode, $full, $simple_product = false)
    {
        $product = $original_product;
        if ($sku_mode && !$simple_product && !$this->data['config']['export_mode']) {
            foreach (self::$non_sku_fields as $field) {
                if (isset($product[$field])) {
                    unset($product[$field]);
                }
            }
            foreach (['stock_base_ratio', 'order_count_min', 'order_count_step'] as $field) {
                if (!isset($sku[$field])) {
                    unset($product[$field]);
                }
            }
        }

        $sku['stock'][0] = $sku['count'];

        if ($full) {
            if (!empty($this->data['options']['features'])) {

                /** @var shopProductFeaturesModel $product_feature_model */
                $product_feature_model = $this->model('product_features');
                $sku['features'] = $product_feature_model->getValues($product['id'], -intval($sku['id']));

                if ($this->data['config']['export_mode'] || ($sku_mode == false && $simple_product)) {
                    $this->prepareProductAllSkuFeatures($product, $sku);
                } elseif ($sku_mode) {
                    $this->prepareProductSkuFeatures($product, $sku);
                } else {
                    $this->prepareProductFeatures($product, $sku, $simple_product);
                }

                if (!empty($product['features'])) {
                    if ($sku_mode) {
                        $this->updateSelectableFeatureCodes($product['id']);
                    }
                    foreach ($product['features'] as $code => &$feature) {
                        if (is_array($feature) || $simple_product) {
                            $feature = $this->writeRow($feature);
                        } elseif ($sku_mode) {
                            if (isset(self::$selectable_feature_codes[$product['id']][$code])) {
                                $feature = sprintf('<{%s}>', str_replace("\r\n", "\r", $feature));
                            }
                        }
                        unset($feature);
                    }
                }
            }

            if ($sku_mode) {
                if (!empty($sku['image_id'])) {
                    if (isset($original_product['images'][$sku['image_id']])) {
                        $product['images'] = array(
                            $sku['image_id'] => $original_product['images'][$sku['image_id']],
                        );
                    }
                }
            }
        }

        if (isset($product['images'])) {
            $product['images'] = array_values($product['images']);
        }

        if (isset($product['images_descriptions'])) {
            $product['images_descriptions'] = array_values($product['images_descriptions']);
        }

        if ($sku_mode || $simple_product) {
            $product['skus'] = array(-1 => $sku);
            foreach (['stock_base_ratio', 'order_count_min', 'order_count_step'] as $product_field) {
                if (isset($sku[$product_field])) {
                    $product[$product_field] = shopFrac::discardZeros($sku[$product_field]);
                }
            }
        }

        $product['sku_type'] = $product['sku_type'] == shopProductModel::SKU_TYPE_SELECTABLE ? self::EXPORT_SKU_TYPE_SELECTABLE : self::EXPORT_SKU_TYPE_FLAT;
        if (($sku_mode == false && $simple_product) || $this->data['config']['export_mode']) {
            $product['row_type'] = self::STAGE_PRODUCT_VARIANT;
        } else {
            $product['row_type'] = $sku_mode ? self::STAGE_VARIANT : self::STAGE_PRODUCT;
        }
        $this->writer->write($product);

        if (!isset($this->data['exported_products'][$product['id']])) {
            $this->data['exported_products'][$product['id']] = true;
        }

        return $product;
    }

    private function getImageSize($config)
    {
        if (!isset($this->data['config']['__image_size'])) {
            /** @var shopConfig $shop_config */
            $shop_config = $this->getConfig();
            if (in_array($config, $shop_config->getImageSizes(), true)) {
                $this->data['config']['__image_size'] = $config;
            } else {
                $this->data['config']['__image_size'] = $shop_config->getImageSize('big');
            }
        }
        return $this->data['config']['__image_size'];
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
     * @param array    $data
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

    private function writeImportError($message)
    {
        try {
            // information for tests
            $this->data['error_messages'][$this->reader->key()] = $message;
            $current = [
                'import_line_number' => $this->reader->key(),
                'error_message' => $message,
            ];
            $current += $this->reader->current(false);

            $type = $this->getErrorType();
            if (!isset($this->data['error_file'][$type])) {
                $this->data['error_log_name'][$type] = sprintf($type . '_csv_import_log_%s.csv', date('Y-m-d_H_i'));
                $file = wa()->getTempPath('csv/download/0/' . $this->data['error_log_name'][$type]);
                $error_writer = new shopCsvWriter($file, $this->reader->delimiter, $this->reader->encoding);
                $map = [
                    'import_line_number' => _w('ID of the invalid row in the import file'),
                    'error_message' => _w('Error description'),
                ];
                if (!isset($this->data['header'])) {
                    $header = $this->reader->header();
                    foreach ($header as $i => $name) {
                        if (is_string($i) && mb_strpos($i, ':') > 0) {
                            $ids = array_map('intval', explode(':', $i));
                            $mapping = $this->reader->data_mapping;
                            foreach ($ids as $_id) {
                                if (!isset($header[$_id])) {
                                    foreach ($mapping as $code => $num) {
                                        if ($num == $_id && is_string($code) && mb_strpos($code, 'features:') === 0) {
                                            unset($header[$i]);
                                            $header[$_id] = $name;
                                        }
                                    }
                                }
                            }
                        }
                    }
                    uksort($header, function ($a, $b) {
                        $a = intval($a);
                        $b = intval($b);
                        if ($a == $b) {
                            return 0;
                        }
                        return ($a < $b) ? -1 : 1;
                    });
                    $this->data['header'] = $header;
                }
                $map += $this->data['header'];
                $error_writer->setMap($map);
            } else {
                $error_writer = unserialize($this->data['error_file'][$type]);
            }
            $error_writer->write($current);
            $this->data['error_file'][$type] = serialize($error_writer);
        } catch (Exception $e) {
            $message .= "\n\nAdditionally, unable to write to CSV file with errors: ".strval($e);
        } catch (Error $e) {
            $message .= "\n\nAdditionally, unable to write to CSV file with errors: ".strval($e);
        }

        $this->error($message);
    }

    private function getErrorType()
    {
        return $this->emulate() ? 'inexact' : 'exact';
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

    private function parseRow($line, $delimiter = ',')
    {
        $enclosure = '"';
        $escape = '\\';
        if (!function_exists('str_getcsv')) {
            $fh = fopen('php://memory', 'rw');
            fwrite($fh, $line);
            rewind($fh);
            $data = fgetcsv($fh, 0, $delimiter, $enclosure);
            fclose($fh);
        } else {
            $data = str_getcsv($line, $delimiter, $enclosure, $escape);
        }
        return $data;
    }

    private function writeRow($data, $template = '{%s}', $force = false)
    {

        if (is_array($data)) {
            if (!$force && (count($data) == 1)) {
                $data = reset($data);
            } else {
                $enclosure = $this->writer->enclosure;
                $pattern = sprintf("/(?:%s|%s|%s)/", preg_quote(',', '/'), preg_quote($enclosure, '/'), preg_quote($enclosure, '/'));

                foreach ($data as &$value) {
                    if (preg_match($pattern, $value)) {
                        $value = $enclosure.str_replace($enclosure, $enclosure.$enclosure, $value).$enclosure;
                    }
                    unset($value);
                }
                $data = $data ? sprintf($template, implode(',', array_unique($data))) : '';
            }
        }
        return $data;
    }

    private function paramsToString($params)
    {
        $string = '';

        foreach ($params as $k => $v) {
            if ($k != 'order') {
                $string .= sprintf("%s=%s\n", $k, $v);
            }
        }
        return rtrim($string);
    }

    private function getImageName($file)
    {
        $name = preg_replace('@[^a-zA-Zа-яёА-ЯЁ0-9\._\-]+@u', '', basename(preg_replace('@([^?]+)((\?.*)?)@', '$1', urldecode($file))));
        $ext = pathinfo(urldecode($file), PATHINFO_EXTENSION);
        if (empty($ext) || !in_array($ext, array('jpeg', 'jpg', 'png', 'gif'))) {
            $image = $this->getImage($file);
            $ext = $image->getExt();
            if (empty($ext)) {
                $ext = 'jpeg';
            }
            $name .= '.'.$ext;
        }
        return $name;
    }

    /**
     * @param $file
     * @return waImage
     * @throws waException
     */
    private function getImage($file)
    {
        $_is_url = preg_match('@^(https?|fpts?)://@', $file);
        if ($_is_url) {

            $upload_file = tempnam($this->getImageTmpPath(true), '');
            $options = array(
                'verify' => false,
            );
            waFiles::upload($file, $upload_file, $options);
            $file = $upload_file;
        } elseif ($file) {
            $file = $this->data['upload_path'].$file;
        }

        if (!$file || !file_exists($file)) {
            throw new waException(sprintf('File [%s] not found', $file ? $file : '_EMPTY_'));
        }
        return waImage::factory($file);
    }

    private function getImageTmpPath($create = false)
    {
        $name = pathinfo($this->reader->file(), PATHINFO_FILENAME);
        $path = wa()->getTempPath(sprintf('csv/upload/%s.images', $name));
        if ($create && !file_exists($path)) {
            waFiles::create($path, true);
        }
        return $path;
    }

    private function updateProductRemovalBehavior($data, $id)
    {
        if (isset($data['row_type'])) {
            if ($data['row_type'] == self::STAGE_PRODUCT) {
                $this->data['product_may_be_deleted'] = true;
                $this->data['is_first_sku_after_product'] = true;
            } elseif ($data['row_type'] == self::STAGE_PRODUCT_VARIANT) {
                $this->data['product_may_be_deleted'] = false;
            } elseif ($data['row_type'] == self::STAGE_VARIANT && !empty($this->data['is_first_sku_after_product'])) {
                $this->data['is_first_sku_after_product'] = false;
                if (isset($this->data['last_saved_product_id_without_skus'])
                    && $this->data['last_saved_product_id_without_skus'] == $id
                ) {
                    $this->data['product_may_be_deleted'] = false;
                    $this->data['last_saved_product_id_without_skus'] = null;
                }
            }
        }
    }

    private function setDefaultUnitFeature(&$data)
    {
        static $dimension_features;
        if (!isset($dimension_features) && !empty($data['features'])) {
            $feature_codes = array_keys($data['features']);
            /** @var shopFeatureModel $feature_model */
            $feature_model = $this->model('feature');
            $dimension_features = $feature_model->getByCode($feature_codes);
        }
        if (!empty($data['features'])) {
            foreach ($data['features'] as $code => &$value) {
                if (!is_array($value)) {
                    $space_position = strrpos($value, ' ');
                    $last_piece = substr($value, (int)$space_position + 1);
                    if (($space_position === false || empty($last_piece) || is_numeric($last_piece))
                        && !empty($dimension_features[$code]['default_unit'])
                    ) {
                        $value = ('' === trim($value) ? '' : $value.' '.$dimension_features[$code]['default_unit']);
                    }
                } else {
                    foreach ($value as &$val) {
                        $space_position = strrpos($val, ' ');
                        $last_piece = substr($val, (int)$space_position + 1);
                        if (($space_position === false || empty($last_piece) || is_numeric($last_piece))
                            && !empty($dimension_features[$code]['default_unit'])
                        ) {
                            $val = $val . ' ' . $dimension_features[$code]['default_unit'];
                        }
                    }
                    unset($val);
                }
            }
            unset($value);
        }
    }

    /**
     * @param $data
     * @param shopProduct $product
     * @param $saved_features
     * @param $product_exists
     */
    private function afterSaveProduct($data, $product, $saved_features, $product_exists)
    {
        if (isset($data['row_type'])) {
            $this->setSelectableFeatureIds($product, $saved_features);
            $product_id = $product->getId();
            if ($data['row_type'] == self::STAGE_PRODUCT) {
                $this->data['is_new_product'] = empty($product_exists);
                if (!empty($this->data['last_saved_product_id_without_skus'])
                    && $this->data['last_saved_product_id_without_skus'] != $product_id
                ) {
                    $this->deleteProductWithoutSkus();
                }
                if ($product_id > 0 && $this->data['is_new_product'] && $this->data['product_may_be_deleted']) {
                    $this->data['last_saved_product_id_without_skus'] = $product_id;
                }
            } elseif ($data['row_type'] == self::STAGE_PRODUCT_VARIANT && $product_id > 0
                && !empty($data['features_selectable']) && $this->data['last_product_sku_code']) {
                /** @var shopProductSkusModel $sku_model */
                $product_skus_model = $this->model('product_skus');
                $product_skus_model->updateByField(array(
                    'product_id' => $product_id,
                    'sku' => ''
                ), array('sku' => $this->data['last_product_sku_code'])
                );
            }
        }
    }

    /**
     * Used only with new type of import
     */
    private function deleteProductWithoutSkus()
    {
        if (!empty($this->data['last_saved_product_id_without_skus'])) {
            /** @var shopProductModel $product_model */
            $product_model = $this->model('product');
            $delete_result = $product_model->delete([$this->data['last_saved_product_id_without_skus']]);
            if ($delete_result) {
                $this->data['processed_count'][self::STAGE_PRODUCT]['new']--;
            }
            $this->data['last_saved_product_id_without_skus'] = null;
        }
    }

    /**
     * @param shopProduct $product
     * @param $saved_features
     */
    private function setSelectableFeatureIds($product, $saved_features)
    {
        /** @var shopProductFeaturesSelectableModel $features_selectable_model */
        $features_selectable_model = $this->model('product_features_selectable');
        $features_selectable_ids = $features_selectable_model->getByProduct($product->getId());
        if (is_array($this->data['sku_feature_codes']) && is_array($features_selectable_ids)) {
            if (!isset($this->data['all_products_selectable_features'])) {
                $this->data['all_products_selectable_features'] = [];
            }
            $missing_feature_ids = array_diff(array_keys($features_selectable_ids), array_column($this->data['all_products_selectable_features'], 'id'));
            if (!empty($missing_feature_ids)) {
                /** @var shopProductFeaturesModel $feature_model */
                $feature_model = $this->model('feature');
                $this->data['all_products_selectable_features'] += $feature_model->select('id, code')->where('id IN (?)', [$missing_feature_ids])->fetchAll('code');
            }
            $product_selectable_features = [];
            foreach ($features_selectable_ids as $id => $empty_item) {
                foreach ($this->data['all_products_selectable_features'] as $code => $selectable_feature) {
                    if ($id == $selectable_feature['id']) {
                        $product_selectable_features[$code] = $selectable_feature;
                    }
                }
            }
            $this->data['product_selectable_features'] = array_merge($this->data['product_selectable_features'], $product_selectable_features);
            $sku_feature_codes = array_merge($this->data['sku_feature_codes'], $this->data['product_selectable_features']);
            if (!empty($saved_features) && $sku_feature_codes) {
                $selectable_features_ids = array_column(array_intersect_key($saved_features, $sku_feature_codes), 'id');
                if (!empty($selectable_features_ids)) {
                    $features_selectable_model->setFeatureIds($product, $selectable_features_ids);
                }
            }
        }
    }

    static $selectable_feature_codes = array();

    private function updateSelectableFeatureCodes($product_id)
    {
        if (!isset(self::$selectable_feature_codes[$product_id])) {
            /** @var shopFeatureModel $feature_model */
            $feature_model = $this->model('feature');
            $select_query = "SELECT DISTINCT f.code FROM shop_feature f JOIN shop_product_features_selectable spfs on f.id = spfs.feature_id WHERE spfs.product_id = i:id";
            if (count(self::$selectable_feature_codes) > 10) {
                self::$selectable_feature_codes = array();
            }
            self::$selectable_feature_codes[$product_id] = $feature_model->query($select_query, ['id' => $product_id])->fetchAll('code');
        }
    }

    /**
     * @param shopProduct $product
     */
    private function checkMainSku($product)
    {
        if (isset($product['skus'][$product['sku_id']]['status']) && $product['skus'][$product['sku_id']]['status'] == '0') {
            $product->save([
                'skus' => [
                    $product['sku_id'] => [
                        'status' => 1
                    ]
                ]
            ]);
            $main_sku_message = _w('The main SKU cannot be hidden; therefore, the visibility in the storefront is always enabled for it.');
            $this->writeImportError($main_sku_message);
        }
    }

    /**
     * @param array $data
     * @param bool $is_product
     * @return bool
     * @throws waException
     */
    protected function hasNegativePrices($data, $is_product = false)
    {
        if ($is_product) {
            $price_fields = [
                'base_price_selectable' => _w('Price'),
                'purchase_price_selectable' => _w('Compare at price'),
                'compare_price_selectable' => _w('Purchase price'),
            ];
        } else {
            $price_fields = [
                'price' => _w('Price'),
                'compare_price' => _w('Compare at price'),
                'purchase_price' => _w('Purchase price'),
            ];
        }
        $has_error = false;
        foreach ($price_fields as $field => $name) {
            if (isset($data[$field]) && $data[$field] < 0) {
                $this->writeImportError(sprintf_wp('The value of the “%s” field can be only 0 or greater than 0.', $name));
                $has_error = true;
            }
        }

        return $has_error;
    }
}
