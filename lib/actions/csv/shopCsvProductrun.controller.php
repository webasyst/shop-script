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
    protected function preExecute()
    {
        $this->getResponse()->addHeader('Content-type', 'application/json');
        $this->getResponse()->sendHeaders();
    }

    protected function init()
    {
        try {
            $this->data['action'] = 'import';
            $name = basename(waRequest::post('file'));
            $file = wa()->getDataPath('temp/csv/upload/'.$name);
            $this->reader = new shopCsvReader($file);
            $this->reader->setMap(waRequest::post('csv_map'));
            $this->data['file'] = $this->reader;
            $this->data['primary'] = waRequest::post('primary', 'name');
            if (!in_array($this->data['primary'], array('name', 'url', 'skus:-1:sku'))) {
                throw new waException(_w('Invalid primary field'));
            }
            $current = $this->reader->current();
            if (self::getData($current, $this->data['primary']) === null) {
                throw new waException(_w('Empty primary CSV column').var_export(array($current, $this->data['primary']), true));
            }

            $model = new shopCategoryModel();
            $this->data['count'] = array(
                self::STAGE_FILE => $this->reader->size(),
                self::STAGE_CATEGORY => null,
                self::STAGE_PRODUCT => null,
                self::STAGE_SKU => null,
            );

            $stages = array_keys($this->data['count']);
            $this->data['current'] = array_fill_keys($stages, 0);
            $this->data['processed_count'] = array_fill_keys($stages, ($this->data['action'] == 'import') ? array('new' => 0, 'update' => 0, ) : 0);

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

    private function getStageReport($stage, $count)
    {
        static $strings;
        if (!$strings) {
            $strings = array(
                'new' => array(
                    self::STAGE_CATEGORY => array /*_w*/('imported %d category', 'imported %d categories'),
                    self::STAGE_PRODUCT => array /*_w*/('imported %d product', 'imported %d products'),
                    self::STAGE_SKU => array /*_p*/('imported %d product variant', 'imported %d product variants'),

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

        $method_name = 'step'.ucfirst($this->data['action']);
        $result = false;
        try {
            if (method_exists($this, $method_name)) {
                $result = $this->$method_name();
            } else {
                $this->error(sprintf("Unsupported direction %s", $this->data['action']));
            }
        } catch (Exception $ex) {
            sleep(5);
            $this->error($this->data['action'].': '.$ex->getMessage()."\n".$ex->getTraceAsString());
        }
        return !$this->isDone();
    }

    private function stepImport()
    {
        $this->reader->next();
        $result = false;
        $this->data['current']['file'] = $this->reader->offset();
        if ($current = $this->reader->current()) {
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

        if ($fields && ($current_data = $model->getByField($fields))) {
            $product = new shopProduct($current_data['id']);
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
        return $product;
    }

    private function findTax(&$data)
    {
        static $taxes;
        static $tax_model;
        if (ifset($data['tax_id'])) {
            if (empty($data['tax_id'])) {
                unset($data['tax_id']);
            } else {
                $tax = $data['tax_id'];
                if (!isset($taxes[$tax])) {
                    if (!$tax_model) {
                        $tax_model = new shopTaxModel();
                    }
                    if ($tax_row = $tax_model->getByName($tax)) {
                        $taxes[$tax] = $tax_row['id'];
                    } else {
                        //TODO use default tax
                        $taxes[$tax] = 0;
                    }
                }
                $data['tax_id'] = $taxes[$tax];
            }
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
                if ($current_sku[$sku_primary] === $sku[$sku_primary]) {
                    $item_sku_id = $sku_id;
                    $target_sku = 'update';
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
        if (strpos($primary,'skus:')===0) {
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
    }

    private function getDataType($data)
    {
        static $noncategory_fields = array("skus", "features", "total_sales", "summary");
        $type = self::STAGE_CATEGORY;
        foreach ($noncategory_fields as $field) {
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
        $report .= sprintf('<i class="icon16 yes"></i>%s ', ($this->data['action'] == 'import') ? '' : _w('Exported'));
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
        $this->reader = $this->data['file'];
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

    private function stepCategoryExport(&$current_stage, &$count, &$processed)
    {
        static $categories;
        if (!$categories) {
            $model = new shopCategoryModel();
            $categories = $model->getFullTree();
            if ($current_stage) {
                $categories = array_slice($categories, $current_stage);
            }
        }
        $chunk = 50;
        while ((--$chunk > 0) && ($category = reset($categories))) {
            ;
            array_shift($categories);
            ++$current_stage;
            ++$processed;
        }
        return ($current_stage < $count['category']);
    }

    protected function save()
    {
        ;
    }

    private function getProductFields()
    {
        return '*, frontend_url';
        $fields = array();
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

    private function stepExportProduct(&$current_stage, &$count, &$processed)
    {
        static $products;
        static $features_model;
        if (!$products) {
            $products = $this->getCollection()->getProducts($this->getProductFields(), $current_stage, 200);
        }
        $chunk = 50;
        while ((--$chunk > 0) && ($product = reset($products))) {
            if (!isset($product['features'])) {
                if (!$features_model) {
                    $features_model = new shopProductFeaturesModel();
                }
                $product['features'] = $features_model->getValues($product['id']);
            }

            #WORK
            array_shift($products);
            ++$current_stage;
            ++$processed;
        }
        return ($current_stage < $count['product']);
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
