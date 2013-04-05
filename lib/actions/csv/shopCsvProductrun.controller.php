<?php

class shopCsvProductrunController extends waLongActionController
{
    const STAGE_CATEGORY = 'category';
    const STAGE_PRODUCT = 'product';
    const STAGE_SKU = 'sku';
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
            $this->data['processed_count'] = array_fill_keys($stages, ($this->data['direction'] == 'import') ? array('new' => 0, 'update' => 0, ) : 0);

            $this->data['map'] = array();

            $this->data['memory'] = memory_get_peak_usage();
            $this->data['memory_avg'] = memory_get_usage();

            $this->data['timestamp'] = time();
        } catch (waException $ex) {
            $this->error($ex->getMessage());
            echo json_encode(array(
                'error' => $ex->getMessage(),
            ));
            exit;
        }
    }

    private function initImport()
    {
        $name = basename(waRequest::post('file'));
        $file = wa()->getDataPath('temp/csv/upload/'.$name);
        $this->reader = new shopCsvReader($file, waRequest::post('delimeter', ';'), waRequest::post('encoding', 'utf-8'));
        $this->reader->setMap(waRequest::post('csv_map'));
        $this->data['file'] = serialize($this->reader);
        $this->data['primary'] = waRequest::post('primary', 'name');
        $this->data['type_id'] = waRequest::post('type_id', null, waRequest::TYPE_INT);
        $this->data['ignore_category'] = !!waRequest::post('ignore_category', 0, waRequest::TYPE_INT);
        if (!in_array($this->data['primary'], array('name', 'url', 'skus:-1:sku'))) {
            throw new waException(_w('Invalid primary field'));
        }
        $current = $this->reader->current();
        if (self::getData($current, $this->data['primary']) === null) {
            throw new waException(_w('Empty primary CSV column'));
        }
        $this->data['count'] = array(
            self::STAGE_FILE => $this->reader ? $this->reader->size() : null,
            self::STAGE_CATEGORY => null,
            self::STAGE_PRODUCT => null,
            self::STAGE_SKU => null,
        );
    }

    private function initExport()
    {
        $hash = null;
        $this->data['export_category'] = true;
        switch ($hash = waRequest::post('hash')) {
            case 'id':
                $this->data['export_category'] = false;
                $hash = 'id/'.waRequest::post('product_ids');
                break;
            case 'set':
                $this->data['export_category'] = false;
                $hash = 'set/'.waRequest::post('set_id', waRequest::TYPE_STRING_TRIM);
                break;
            case 'type':
                $hash = 'type/'.waRequest::post('type_id', waRequest::TYPE_INT);
                break;
            default:
                $hash = '*';
                break;
        }
        $this->data['timestamp'] = time();
        $this->data['hash'] = $hash;
        $encoding = waRequest::post('encoding', 'utf-8');
        //$name = sprintf('export.csv', $encoding);
        $name = 'export.csv';
        $file = wa()->getDataPath('temp/csv/download/'.$name);

        $map = array(
            'name'                   => _w('Product name'),
            'skus:-1:name'           => _w('SKU name'),
            'skus:-1:sku'            => _w('SKU code'),
            'currency'               => _w('Currency'),
            'skus:-1:price'          => _w('Price'),
            'skus:-1:available'      => _w('Available for purchase'),
            'skus:-1:compare_price'  => _w('Compare at price'),
            'skus:-1:purchase_price' => _w('Purchase price'),
            'skus:-1:stock:0'        => _w('In stock'),

            'url'                    => _w('Storefront link'),
            'type_name'              => _w('Product type'),

            'meta_title'             => _w('Title'),
            'meta_keywords'          => _w('META Keyword'),
            'meta_description'       => _w('META Description'),
            'summary'                => _w('Summary'),
            'description'            => _w('Description'),
            'sort'                   => _w('Product sort order'),
            'tags'                   => _w('Tags'),
            'tax_name'               => _w('Taxable'),
        );
        $stock_model = new shopStockModel();
        if ($stocks = $stock_model->getAll('id')) {

            foreach ($stocks as $stock_id => $stock) {
                $map['skus:-1:stock:'.$stock_id] = _w('In stock').' @'.$stock['name'];
            }
        }

        $features_model = new shopFeatureModel();
        $features = $features_model->getAll();

        foreach ($features as $feature) {
            $map[sprintf('features:%s', $feature['code'])] = $feature['name'];
        }

        $this->writer = new shopCsvWriter($file, waRequest::post('delimeter', ';'), $encoding);
        $this->writer->setMap($map);

        $this->data['file'] = serialize($this->writer);
        $this->data['map'][self::STAGE_CATEGORY] = null;
        $this->data['map'][self::STAGE_PRODUCT] = 0;

        $model = new shopCategoryModel();
        $this->data['count'] = array(
            self::STAGE_PRODUCT => $this->getCollection()->count(),
            self::STAGE_CATEGORY => $this->data['export_category'] ? $model->countByField('type', shopCategoryModel::TYPE_STATIC) : 0,
            self::STAGE_SKU => null,
        );
    }

    private static function getData($data, $key)
    {
        $value = null;
        if (strpos($key, ':')) {
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
            $value = ifset($data[$key]);
        }
        return $value;
    }

    /**
     *
     * @param string $hash
     * @return shopProductsCollection
     */
    private function getCollection()
    {
        static $id = null;

        if ($this->data['export_category'] && ($id !== $this->data['map'][self::STAGE_CATEGORY])) {
            $this->collection = null;
        }

        if (!$this->collection) {
            $hash = null;
            if ($this->data['export_category']) {
                $id = $this->data['map'][self::STAGE_CATEGORY];
                $hash = 'search/category_id='.$id;
                if ($this->data['hash'] != '*') {
                    $hash .= '&'.str_replace('/', '_id=', $this->data['hash']);
                }
            } else {
                $hash = $this->data['hash'];
            }

            $this->collection = new shopProductsCollection($hash);
        }

        return $this->collection;
    }

    private function getStageReport($stage, $count)
    {
        static $strings;
        if (!$strings) {
            $strings = array(
                'new' => array(
                    self::STAGE_CATEGORY => array /*_w*/('imported %d category', 'imported %d categories'),
                    self::STAGE_PRODUCT => array /*_w*/('imported %d product', 'imported %d products'),
                    self::STAGE_SKU => array /*_w*/('imported %d product variant', 'imported %d product variants'),

                ),
                'update' => array(
                    self::STAGE_CATEGORY => array /*_w*/('updated %d category', 'updated %d categories'),
                    self::STAGE_PRODUCT => array /*_w*/('updated %d product', 'updated %d products'),
                    self::STAGE_SKU => array /*_w*/('updated %d product variant', 'updated %d product variants'),
                ),
                0     => array(
                    self::STAGE_CATEGORY => array /*_w*/('%d category', '%d categories'),
                    self::STAGE_PRODUCT => array /*_w*/('updated %d product', '%d products'),
                    self::STAGE_SKU => array /*_w*/('%d product variant', '%d product variants'),
                ),
            );
        }
        $info = array();
        if (ifempty($count[$stage])) {
            foreach ((array) $count[$stage] as $type => $count) {
                if ($count) {
                    $args = $strings[$type][$stage];
                    $args[] = $count;
                    $info[] = call_user_func_array('_w', $args);
                }
            }
        }
        return implode(', ', $info);

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
                break;
            }
        }
        return $done;
    }

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

    private function stepImport()
    {
        $this->reader->next();
        $result = false;
        if ($current = $this->reader->current()) {
            $this->data['current']['file'] = $this->reader->offset();
            if ($type = $this->getDataType($current)) {
                $method_name = 'stepImport'.ucfirst($type);
                if (method_exists($this, $method_name)) {
                    $result = $this->$method_name($current);
                } else {
                    $this->error(sprintf("Unsupported import data type %s", $type));
                }
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
        if (empty($model)) {
            $model = new shopProductModel();
        }
        if (empty($currencies)) {
            $currencies = array();
            $config = wa()->getConfig();
            $c = $config->getCurrency();
            $currencies[$c] = $c;
            foreach ($config->getCurrencies() as $row) {
                $currencies[$row['code']] = $row['code'];
            }
        }

        if (!empty($sku['skus'][-1]['stock'])) {
            $per_stock = false;
            $stock =& $sku['skus'][-1]['stock'];
            foreach ($stock as $id => & $count) {
                if ($count = intval($count)) {
                    if ($id && !$per_stock) {
                        $per_stock = true;
                    }
                } elseif ($id) {
                    unset($stock[$id]);
                }
            }
            if ($per_stock && isset($stock[0])) {
                unset($stock[0]);
            }
            unset($count);
            unset($stock);
        }

        $stack = ifset($this->data['map'][self::STAGE_CATEGORY], array());
        $category_id = end($stack);
        if (!$category_id) {
            $category_id = null;
        }
        $primary = $this->data['primary'];
        $fields = false;
        if (strpos($primary, ':')) {
            $keys = explode(':', $primary);
            $sku_model = new shopProductSkusModel();
            if ($sku = $sku_model->getByField(end($keys), self::getData($data, $keys))) {
                $fields = array(
                    'category_id' => $category_id,
                    'id'          => $sku['product_id'],
                );
            }

        } else {
            $fields = array(
                'category_id' => $category_id,
                $primary      => ifset($data[$primary]),
            );
        }
        if ($fields && $this->data['ignore_category']) {
            unset($fields['category_id']);
        }

        if ($fields && ($current_data = $model->getByField($fields))) {
            $product = new shopProduct($current_data['id']);
            $data['type_id'] = ifempty($current_data['type_id'], $this->data['type_id']);
            if (!empty($current_data['tax_id'])) {
                $data['tax_id'] = $current_data['tax_id'];
            }
            $target = 'update';
            if (isset($data['currency']) && !isset($currencies[$data['currency']])) {
                $data['currency'] = reset($currencies);
            }
            if (!empty($data['skus'])) {
                $data['sku_id'] = ifempty($current_data['sku_id'], -1);
            }
            foreach ($product->skus as $sku_id => $current_sku) {
                if (empty($data['skus'][$sku_id])) {
                    $data['skus'][$sku_id] = $current_sku;
                }
            }
        } else {
            $product = new shopProduct();
            $target = 'new';
            if ($category_id) {
                $data['categories'] = array($category_id);
            }
            $data['currency'] = ifempty($data['currency'], reset($currencies));
            if (!isset($currencies[$data['currency']])) {
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
        }

        if (!empty($data['features'])) {
            foreach ($data['features'] as $code => & $values) {
                if (preg_match('/^\{(.*)\}$/', $values, $matches)) {
                    $values = explode(',', $matches[1]);
                }
            }
            unset($values);
        }

        $this->findTax($data);
        $this->findType($data);
        return $product;
    }

    private function findType(&$data)
    {
        static $types;
        static $model;
        if (!empty($data['type_name'])) {
            $type = $data['type_name'];
            if (!isset($types[$type])) {
                if (!$model) {
                    $model = new shopTypeModel();
                }
                if ($type_row = $model->getByName($type)) {
                    $types[$type] = $type_row['id'];
                } else {
                    $types[$type] = $this->data['type_id'];
                }
            }
            $data['type_id'] = $types[$type];
        } else {
            $data['type_id'] = ifempty($data['type_id'], $this->data['type_id']);
        }
        if (isset($data['type_name'])) {
            unset($data['type_name']);
        }
    }

    private function findTax(&$data)
    {
        static $taxes;
        static $tax_model;
        if (ifset($data['tax_name'])) {
            if (empty($data['tax_name'])) {
                unset($data['tax_id']);
            } else {
                $tax = $data['tax_name'];
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

    private function stepImportProduct($data)
    {
        $product = $this->findProduct($data);

        $target = $product->getId() ? 'update' : 'new';
        $product->save($data);
        $this->data['map'][self::STAGE_PRODUCT] = $product->getId();
        $this->data['processed_count'][self::STAGE_PRODUCT][$target]++;
        return true;
    }

    private function stepImportSku($data)
    {
        $product = $this->findProduct($data);
        $current_id = ifset($this->data['map'][self::STAGE_PRODUCT]);
        $id = $product->getId();
        $target = $id ? 'update' : 'new';
        $target_sku = 'new';
        $sku_only = false;
        if ($id && isset($data['skus'][-1])) {
            if ($id == $current_id) {
                $sku_only = true;
            }
            $sku = $data['skus'][-1];
            unset($data['skus'][-1]);
            $item_sku_id = -1;
            $sku_primary = ($this->data['primary'] == 'skus:-1:sku') ? 'sku' : 'name';
            foreach ($product->skus as $sku_id => $current_sku) {
                if ($current_sku[$sku_primary] === ifset($sku[$sku_primary], '')) {
                    $item_sku_id = $sku_id;
                    $target_sku = 'update';
                    $sku = array_merge($current_sku, $sku);
                    break;
                }
            }
            if (($item_sku_id < 0) && !isset($sku['available'])) {
                $sku['available'] = true;
            }
            if (!$sku_only && !$product->skus) {
                $data['sku_id'] = $sku_id;
            }
            $data['skus'][$item_sku_id] = $sku;
        }

        if ($sku_only) {
            $product->save(array('skus' => $data['skus']));
        } else {
            $product->save($data);
            $this->data['map'][self::STAGE_PRODUCT] = $product->getId();

            $this->data['processed_count'][self::STAGE_PRODUCT][$target]++;
        }
        $this->data['processed_count'][self::STAGE_SKU][$target_sku]++;
        return true;
    }

    private function stepImportCategory($data)
    {
        $model = new shopCategoryModel();
        if (!isset($this->data['map'][self::STAGE_CATEGORY])) {
            $this->data['map'][self::STAGE_CATEGORY] = array();
        }
        $stack =& $this->data['map'][self::STAGE_CATEGORY];
        if (preg_match('/^(!{1,})(.+)$/', ifset($data['name']), $matches)) {
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
            $primary    => ifset($data[$primary]),
        );

        if ($current_data = $model->getByField($fields)) {
            $stack[] = $current_data['id'];
            $model->updateById($current_data['id'], $data);
            $target = 'update';
        } else {
            $target = 'new';
            $stack[] = $model->add($data, $parent_id);
        }
        $this->data['processed_count'][self::STAGE_CATEGORY][$target]++;
        return true;
    }

    private function getDataType($data)
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
        if (($type == self::STAGE_PRODUCT) && !empty($data['skus']) && ($sku = reset($data['skus'])) && (!empty($sku['sku']) || !empty($sku['name']))) {
            $type = self::STAGE_SKU;
        }
        return $type;
    }

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
        $this->info();
        $result = false;
        if ($this->getRequest()->post('cleanup')) {
            $result = true;
        }
        return $result;
    }

    protected function report()
    {
        $report = '<div class="successmsg">';
        $report .= sprintf('<i class="icon16 yes"></i>%s ', ($this->data['direction'] == 'import') ? '' : _w('Exported'));
        $chunks = array();
        foreach ($this->data['processed_count'] as $stage => $current) {
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
            'progress'   => 0.0,
            'ready'      => $this->isDone(),
            'count'      => empty($this->data['count']) ? false : $this->data['count'],
            'memory'     => sprintf('%0.2fMByte', $this->data['memory'] / 1048576),
            'memory_avg' => sprintf('%0.2fMByte', $this->data['memory_avg'] / 1048576),
        );

        $stage_count = 0; //count($this->data['current']);
        foreach ($this->data['current'] as $stage => $current) {
            if ($this->data['count'][$stage]) {
                ++$stage_count;
                $response['progress'] += 100.0 * (1.0 * $current / $this->data['count'][$stage]);
            }
        }
        $response['progress'] = sprintf('%0.3f%%', $response['progress'] / max(1, $stage_count));
        $response['stage_count'] = $stage_count;
        $response['current_count'] = $this->data['current'];
        $response['processed_count'] = $this->data['processed_count'];
        if ($this->getRequest()->post('cleanup')) {
            $response['report'] = $this->report();
        }
        echo json_encode($response);
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

    /**
     *
     * @return shopYandexmarketPlugin
     */
    private function plugin()
    {
        static $plugin;
        if (!$plugin) {
            $plugin = wa()->getPlugin('csvproducts');
        }
        return $plugin;
    }

    private function stepExportCategory(&$current_stage, &$count, &$processed)
    {
        static $categories;
        if (!$categories) {
            $model = new shopCategoryModel();
            $categories = $model->getFullTree(true);
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
        static $features_model;
        if (!$products) {
            $offset = $current_stage[self::STAGE_PRODUCT] - $this->data['map'][self::STAGE_PRODUCT];
            $products = $this->getCollection()->getProducts('*', $offset, 50);
        }
        $chunk = 1;
        $non_sku_fields = array(
            'summary',
            'meta_title',
            'meta_keywords',
            'meta_description',
            'description',
            'sort',
            'tags',
            'currency',
            'features',
            'type_name',
        );
        while (($chunk-- > 0) && ($product = reset($products))) {
            $exported = false;
            if (!$this->data['export_category'] || ($product['category_id'] == $this->data['map'][self::STAGE_CATEGORY])) {
                $shop_product = new shopProduct($product);
                if (!isset($product['features'])) {
                    if (!$features_model) {
                        $features_model = new shopProductFeaturesModel();
                    }
                    $product['features'] = $features_model->getValues($product['id']);
                }

                $product['type_name'] = $shop_product->type['name'];
                #WORK
                $skus = $shop_product->skus;

                if ($product['sku_id']) {
                    if (isset($skus[$product['sku_id']])) {
                        $sku = $skus[$product['sku_id']];
                        $sku['stock'][0] = $sku['count'];
                        $product['skus'] = array(-1 => $sku);
                        unset($skus[$product['sku_id']]);
                    }
                    $this->writer->write($product);
                    $exported = true;
                    unset($product['features']);
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
                    $product['skus'] = array(-1 => $sku);
                    $this->writer->write($product);
                    $exported = true;
                    ++$current_stage[self::STAGE_SKU];
                    ++$processed[self::STAGE_SKU];
                }
            } elseif (count($products) > 1) {
                $chunk = 1;
            }

            array_shift($products);
            ++$current_stage[self::STAGE_PRODUCT];
            if ($exported) {
                ++$processed[self::STAGE_PRODUCT];
            }
        }
        return ($current_stage[self::STAGE_PRODUCT] < $count[self::STAGE_PRODUCT]);
    }

    private function format($field, $value, $info = array(), $data = array())
    {
        static $currency_model;
        switch ($field) {
            case 'field':
                break;
        }

        return sprintf(ifempty($info['format'], '%s'), $value);
    }

    private function error($message)
    {
        $path = wa()->getConfig()->getPath('log');
        waFiles::create($path.'/shop/plugins/csvproducts.log');
        waLog::log($message, 'shop/plugins/csvproducts.log');
    }
}
