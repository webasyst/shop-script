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
        "КоммерческаяИнформация/ПакетПредложений/Предложения" => self::STAGE_OFFER,
        "КоммерческаяИнформация/ПакетПредложений/Свойства-"   => self::STAGE_FEATURE,

        //Импорт заказов - поддержка не планируется
        "КоммерческаяИнформация/Документ-"                    => self::STAGE_ORDER,
    );

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
    private $path = array();
    /**
     * @var XMLWriter
     */
    private $writer;

    /**
     *
     * @return shopCml1cPlugin
     */
    private function plugin()
    {
        static $plugin;
        if (!$plugin) {
            $plugin = wa()->getPlugin('cml1c');
        }
        return $plugin;
    }

    /**
     * @uses self::initExport()
     * @uses self::initImport()
     */
    protected function init()
    {
        try {
            $type_model = new shopTypeModel();

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
                    break;
            }

            $this->data['current'] = array_fill_keys(array_keys($this->data['count']), 0);

            $stages = array(
                self::STAGE_CATEGORY,
                self::STAGE_FEATURE,
                self::STAGE_PRODUCT,
                self::STAGE_PRICE,
                self::STAGE_SKU,
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
        static $model;
        static $feature_relation = array();
        if (empty($model)) {
            $model = new shopFeatureModel();
        }


        $code_ = array_diff($code, array_keys($feature_relation));
        if ($code_) {
            $multiple_features = $model->getByField(array(
                'code'     => $code_,
                'multiple' => 1,
            ), 'code');
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

        $this->data['price_type'] = $this->plugin()->getSettings('price_type');
        $this->data['price_type_uuid'] = $this->plugin()->getSettings('price_type_uuid');

        $this->data['purchase_price_type'] = $this->plugin()->getSettings('purchase_price_type');
        $this->data['purchase_price_type_uuid'] = $this->plugin()->getSettings('purchase_price_type_uuid');
        if ($order_state = $this->plugin()->getSettings('order_state')) {
            $this->data['order_state'] = array_keys(array_filter($order_state));
        }

        $this->data['stock_id'] = max(0, $this->plugin()->getSettings('stock'));

        switch (waRequest::param('module', 'backend')) {
            case 'frontend':
                $name = $this->processId.'.xml';
                break;
            case 'backend':
            default:
                $name = sprintf('products_(%s).xml', date('Y-m-d'));
                break;
        }

        $this->data['filename'] = $this->plugin()->path($name);
        $this->data['fsize'] = 0;

        $this->initExportCount();

        $this->writer = new XMLWriter();
        $w = & $this->writer;
        $w->openMemory();
        $w->setIndent(true);
        $w->setIndentString("\t");
        $w->startDocument('1.0', $this->data['encoding']);
        $url = $this->plugin()->getPluginStaticUrl(true);
        $w->writePi('xml-stylesheet', 'type="text/xsl" href="'.$url.'xml/sale.xsl"');
        $w->startElement('КоммерческаяИнформация');
        $w->writeAttribute('ВерсияСхемы', $this->data['version']);
        $w->writeAttribute('ДатаФормирования', date("Y-m-d\TH:i:s"));
        $w->writeComment('Shop-Script '.wa()->getVersion('shop'));
        $this->write();
    }

    private function initExportCount()
    {
        $model = new shopOrderModel();
        $export = waRequest::post('export');
        if (!is_array($export)) {
            $export = array();
        }
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
                $sql = 'SELECT COUNT(*) FROM `'.$model->getTableName().'` WHERE '.implode(' AND ', $where);
                $count[self::STAGE_ORDER] = $model->query($sql, $params)->fetchField();
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
        if (!extension_loaded('xmlreader')) {
            throw new waException('PHP extension xmlreader required');
        }
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
        if ($zip) {
            $this->extract($file, $this->data['filename']);
            if (($file == 'offers.xml') || (wa()->getEnv() == 'backend')) {
                $this->data['files'][] = $this->data['zipfile'];
            }
            if (preg_match('@[\\/]@', $file)) {
                $this->data['base_path'] = dirname($file).'/';
            }
        }


        #init import params
        $this->data['price_type'] = mb_strtolower($this->plugin()->getSettings('price_type'), 'utf-8');
        $this->data['price_type_uuid'] = $this->plugin()->getSettings('price_type_uuid');

        $this->data['purchase_price_type'] = mb_strtolower($this->plugin()->getSettings('purchase_price_type'), 'utf-8');
        $this->data['purchase_price_type_uuid'] = $this->plugin()->getSettings('purchase_price_type_uuid');

        $this->data['stock_id'] = max(0, waRequest::get('stock', $this->plugin()->getSettings('stock'), waRequest::TYPE_INT));
        if ($this->data['stock_id']) {
            $stock_model = new shopStockModel();
            if (!$stock_model->stockExists($this->data['stock_id'])) {
                throw new waException('Выбранный склад не существует');
            }
        }


        $this->initImportCount();
    }

    private function initImportCount()
    {
        $this->data['count'] = array();

        static $method = null;

        $this->openXml();
        while ($this->read($method)) {
            $method = 'unknown_count';
            if ($this->reader->depth >= 2) {
                if ($stage = $this->getStage()) {
                    list($node, $method) = self::$node_name_map[$stage];
                    if ($method == 'next') {
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
                            $method_ = $method;
                        }

                        if ($this->read($method_, $path)) {
                            if ($this->reader->nodeType == XMLReader::ELEMENT) {
                                if ($this->reader->name == $node) {
                                    ++$this->data['count'][$stage];
                                }
                            }
                        } else {
                            $method = 'end_count';
                            $this->read($method);
                            break 2;
                        }

                    }
                }
                $method = 'next';
            }
        }
        if (!empty($this->data['count'][self::STAGE_PRODUCT])) {
            $this->data['count'][self::STAGE_IMAGE] = null;
        }
        $this->reader->close();
    }

    private function initData()
    {
        $feature_model = new shopFeatureModel();
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

                    } while (
                        $result &&
                        ($path == $base) &&
                        (($this->reader->nodeType != XMLReader::ELEMENT) || ($this->reader->name != $name))
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

    protected function isDone()
    {
        static $done;
        if (!$done) {
            $done = true;
            foreach ($this->data['current'] as $stage => $current) {
                if ($current < $this->data['count'][$stage]) {
                    $done = false;
                    $this->data['stage'] = $stage;
                    break;
                }
            }
            $this->data['stage_name'] = $this->getStageName($this->data['stage']);
            if ($done && ($this->data['direction'] == 'export') && empty($this->data['ready'])) {
                try {
                    $this->data['ready'] = true;
                    $this->writer->endElement( /*КоммерческаяИнформация*/);
                    $this->write();
                    if (!empty($this->data['timestamp'])) {
                        $interval = time() - $this->data['timestamp'];
                        $interval = sprintf(_wp('%02d ч %02d мин %02d с'), floor($interval / 3600), floor($interval / 60) % 60, $interval % 60);
                        $this->writer->writeComment(sprintf(_wp('Время формирования: %s'), $interval));
                        $this->write();
                    }
                } catch (waException $ex) {

                }
            }
        }
        if ($done) {
            $this->data['is_done'] = true;
        }
        return $done;
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
                waFiles::delete($this->data['filename']);
                $result = true;
                // $this->info();
            }
        }
        if ($this->getRequest()->post('cleanup')) {
            if ($this->reader) {
                $this->reader->close();
            }
            foreach ($this->data['files'] as $file) {
                waFiles::delete($file, true);
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
                $this->plugin()->validate(file_get_contents($path), $path);
            }
            if (waRequest::param('module') == 'frontend') {
                waFiles::readFile($path, null, false);
                waFiles::delete($path);
            }
        }
        return $result;
    }

    protected function report()
    {
        $report = '<div class="successmsg">';
        switch ($this->data['direction']) {
            case 'import':
                $report .= sprintf('<i class="icon16 yes"></i>%s ', 'Импорт завершен');
                break;
            case 'export':
                $report .= sprintf('<i class="icon16 yes"></i>%s ', 'Экспорт завершен');
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
            $interval = sprintf(_wp('%02d ч %02d мин %02d с'), floor($interval / 3600), floor($interval / 60) % 60, $interval % 60);
            $report .= ' '.sprintf(_wp('(Общее время: %s)'), $interval);
        }

        $report .= '</div>';
        if (($this->data['direction'] == 'export') && (waRequest::param('module', 'backend') == 'backend')) {
            $name = htmlentities(basename($this->data['filename']), ENT_QUOTES, 'utf-8');
            $report .= <<<HTML
<br>
<a href="?plugin=cml1c&action=download&file={$name}"><i class="icon16 download"></i><strong>Скачать</strong></a>
 или
<a href="?plugin=cml1c&action=download&file={$name}&mode=view" target="_blank">просмотреть<i class="icon16 new-window"></i></a>
HTML;
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
                    if ($this->data['direction'] == 'import') {
                        echo "success\n";
                        echo strip_tags($this->report());
                    }
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
                            self::STAGE_ORDER    => array /*_w*/
                            ('%d заказ', '%d заказов'
                            ),
                            self::STAGE_PRODUCT  => array /*_w*/
                            ('%d товар', '%d товаров'
                            ),
                            self::STAGE_OFFER    => array /*_w*/
                            ('%d предложение', '%d предложений'
                            ),
                            self::STAGE_CATEGORY => array /*_w*/
                            ('%d категория', '%d категорий'
                            ),
                        ),
                    );

                    break;
                case 'import':
                default:
                    $strings = array(
                        'new'    => array(
                            self::STAGE_IMAGE    => array /*_w*/
                            ('imported %d product image', 'imported %d product images'
                            ),
                            self::STAGE_CATEGORY => array /*_w*/
                            ('imported %d category', 'imported %d categories'
                            ),
                            self::STAGE_PRODUCT  => array /*_w*/
                            ('imported %d product', 'imported %d products'
                            ),
                            self::STAGE_SKU      => array /*_w*/
                            ('imported %d sku', 'imported %d skus'
                            ),
                            self::STAGE_OFFER    => array /*_w*/
                            ('imported %d offer', 'imported %d offers'
                            ),

                        ),
                        'update' => array(
                            self::STAGE_ORDER    => array /*_w*/
                            ('updated %d order', 'updated %d orders'
                            ),
                            self::STAGE_IMAGE    => array /*_w*/
                            ('updated %d product image', 'updated %d product images'
                            ),
                            self::STAGE_CATEGORY => array /*_w*/
                            ('updated %d category', 'updated %d categories'
                            ),
                            self::STAGE_PRODUCT  => array /*_w*/
                            ('imported %d product', 'updated %d products'
                            ),
                            self::STAGE_SKU      => array /*_w*/
                            ('updated %d sku', 'updated %d skus'
                            ),
                            self::STAGE_OFFER    => array /*_w*/
                            ('updated %d offer', 'updated %d offers'
                            ),
                        ),
                        'skip'   => array(
                            self::STAGE_ORDER    => array /*_w*/
                            ('skipped %d order', 'skipped %d orders'
                            ),
                            self::STAGE_IMAGE    => array /*_w*/
                            ('skipped %d product image', 'skipped %d product images'
                            ),
                            self::STAGE_CATEGORY => array /*_w*/
                            ('skipped %d category', 'skipped %d categories'
                            ),
                            self::STAGE_PRODUCT  => array /*_w*/
                            ('skipped %d product', 'skipped %d products'
                            ),
                            self::STAGE_SKU      => array /*_w*/
                            ('skipped %d sku', 'skipped %d skus'
                            ),
                            self::STAGE_OFFER    => array /*_w*/
                            ('skipped %d offer', 'skipped %d offers'
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
                    $info[] = call_user_func_array('_w', $args);
                }
            }
        }
        return implode(', ', $info);

    }

    public function getStageName($stage)
    {
        $name = '';
        if (isset($this->data['direction']) && ($this->data['direction'] == 'import')) {
            $name = 'Импорт ';
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
                $this->writer = new XMLWriter();
                $this->writer->openMemory();
                $this->writer->setIndent(true);
                $this->writer->setIndentString("\t");
                $this->writer->startDocument('1.0', $this->data['encoding']);
                $this->writer->startElement('КоммерческаяИнформация');
                $this->writer->writeComment(__FUNCTION__);

                //TODO go to last xpath info
                $this->writer->flush();
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
        if (!$products) {
            $products = $this->getProducts($current_stage[self::STAGE_PRODUCT]);
        }

        $w = & $this->writer;
        if (!$current_stage[self::STAGE_PRODUCT]) {
            $this->data['map'][self::STAGE_PRODUCT] = shopCml1cPlugin::makeUuid();

            $w->startElement('Каталог');
            $w->writeElement('Ид', $this->data['map'][self::STAGE_PRODUCT]);
            $w->writeElement('ИдКлассификатора', $this->data['map'][self::STAGE_OFFER]);
            $w->writeElement('Наименование', "Каталог товаров от ".date("Y-m-d H:i"));
            $this->writeOwner();
            $w->startElement('Товары');
        }
        $chunk = 10;
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
            $w->endElement( /*Товары*/);
            $w->endElement( /*Каталог*/);
        }
        return ($current_stage[self::STAGE_PRODUCT] < $count[self::STAGE_PRODUCT]);
    }


    private function stepExportOffer(&$current_stage, &$count, &$processed)
    {
        static $products;
        if (!$products) {
            $products = $this->getProducts($current_stage[self::STAGE_OFFER]);
        }
        $w = & $this->writer;
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
            $w->endElement( /*ТипЦены*/);

            if (!empty($this->data['purchase_price_type_uuid'])) {
                $w->writeElement('Ид', $this->data['purchase_price_type_uuid']);
                $w->writeElement('Наименование', $this->data['purchase_price_type']);
                $w->writeElement('Валюта', $this->currency());
                $w->endElement( /*ТипЦены*/);
            }
            $w->endElement( /*ТипыЦен*/);
            $w->startElement('Предложения');
        }
        $chunk = 10;
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
            $w->endElement( /*Предложения*/);
            $w->endElement( /*ПакетПредложений*/);
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

            $w = & $this->writer;
            $w->startElement('Классификатор');
            $w->writeElement('Ид', $this->data['map'][self::STAGE_OFFER]);
            $w->writeElement('Наименование', 'Классификатор (Каталог товаров)');
            $this->writeOwner();
            $w->startElement('Группы');
        }


        if (!isset($this->data['map'][self::STAGE_CATEGORY])) {
            $this->data['map'][self::STAGE_CATEGORY] = $this->getCategoryModel()->select('`id`, `id_1c`')->where('`id_1c` IS NOT NULL')->fetchAll('id', true);
        }
        $map =& $this->data['map'][self::STAGE_CATEGORY];
        if ($category = reset($categories)) {
            if (!$category['id_1c']) {
                do {
                    $category['id_1c'] = shopCml1cPlugin::makeUuid();
                } while ($this->getCategoryModel()->getByField('id_1c', $category['id_1c']));
                $this->getCategoryModel()->updateById($category['id'], array('id_1c' => $category['id_1c']));
                $map[$category['id']] = $category['id_1c'];
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
            $this->writer->endElement( /*Группы*/);
            $this->writer->endElement( /*Классификатор*/);
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
        static $model;
        if (!$model) {
            $model = new shopOrderModel();
        }
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
        static $product_model;
        static $rate;

        if (!$orders) {
            $orders = $this->getOrders($current_stage[self::STAGE_ORDER], 50);
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

        if ($order = reset($orders)) {

            if (!isset($this->data['map'][self::STAGE_ORDER])) {
                $this->data['map'][self::STAGE_ORDER] = array();
            }

            $order['id_str'] = shopHelper::encodeOrderId($order['id']);
            $order['status_comment'] = ''; //TODO

            $params = & $order['params'];

            list($date, $time) = explode(" ", date("Y-m-d H:i:s", strtotime($order["create_datetime"])));

            $order['params']['shipping'] = shopHelper::getOrderAddress($params, 'shipping') + $empty_address;
            $shipping_address = $this->getAddress($params, 'shipping');

            if (!$region_model) {
                $region_model = new waRegionModel();
            }
            if (ifset($params['shipping_address.country']) && ifset($params['shipping_address.region'])) {
                if ($region = $region_model->get($params['shipping_address.country'], $params['shipping_address.region'])) {
                    $params['shipping_address.region_name'] = $region['name'];
                }
            }

            $order['params']['billing'] = shopHelper::getOrderAddress($params, 'billing') + $empty_address;
            $billing_address = $this->getAddress($params, 'billing');

            list($order['contact']['lastname'], $order['contact']['firstname']) = explode(' ', ifempty($order['contact']['name'], '-').' %', 2);
            $order['contact']['firstname'] = preg_replace('/\s+%$/', '', $order['contact']['firstname']);

            $w = & $this->writer;

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
            $currency = $this->plugin()->getSettings('currency');
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
            $w->writeElement('Наименование', ifempty($order['contact']['name'], '-'));
            $w->writeElement('ПолноеНаименование', ifempty($order['contact']['name'], '-'));
            $w->writeElement('Роль', 'Покупатель');
            $w->writeElement('Фамилия', $order['contact']['lastname']);
            $w->writeElement('Имя', $order['contact']['firstname']);

            $w->startElement('АдресРегистрации');
            $w->writeElement('Вид', 'Адрес доставки');
            $w->writeElement('Представление', $shipping_address);


            $address_fields = array(
                'Почтовый индекс' => 'shipping_address.zip',
                'Регион'          => 'shipping_address.region_name',
                'Город'           => 'shipping_address.city',
                'Улица'           => 'shipping_address.street',
            );

            foreach ($address_fields as $type => $field) {
                if (!empty($params[$field])) {
                    $w->startElement('АдресноеПоле');
                    $w->writeElement('Тип', $type);
                    $w->writeElement('Значение', $params[$field]);
                    $w->endElement( /*АдресноеПоле*/);
                }
            }
            $w->endElement( /*АдресРегистрации*/);


            if (($c_id = ifset($order['contact']['id'])) && ($c = new waContact($c_id))) {
                $w->startElement('Контакты');
                if ($field = $this->plugin()->getSettings('contact_email')) {
                    if ($value = $c->get($field, 'default')) {
                        $w->startElement('Контакт');
                        $w->writeElement('Тип', 'Почта');
                        $w->writeElement('Значение', $value);
                        $w->endElement( /*Контакт*/);
                    }
                }

                if ($field = $this->plugin()->getSettings('contact_phone')) {
                    if ($value = $c->get($field, 'default')) {
                        $w->startElement('Контакт');
                        $w->writeElement('Тип', 'ТелефонРабочий');
                        $w->writeElement('Представление', $value);
                        $w->writeElement('Значение', $value);
                        $w->endElement( /*Контакт*/);
                    }
                }

                $w->endElement( /*Контакты*/);
            }
            $w->endElement( /*Контрагент*/);

            $w->endElement( /*Контрагенты*/);

            //Время документа
            $w->writeElement('Время', $time);
            $w->writeElement('Комментарий', ifset($order['status_comment']));


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
                if (!$product_model) {
                    $product_model = new shopProductModel();
                }

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
                $this->writeDiscounts(array(
                    array(
                        'name'     => 'Скидка',
                        'discount' => $order['discount'],
                        'included' => true,
                    ),
                ));
            }

            $w->startElement('Товары');
            foreach ($this->findOrderItems($items) as $product) {
                $this->writeOrderItem($product, $discount_rate, $product['tax_id'] ? ifempty($taxes[$product['tax_id']]) : null);
            }

            //XXX Услуги

            if (!empty($order['shipping'])) {
                $this->writeOrderService(array(
                    'id_1c' => 'ORDER_DELIVERY',
                    'name'  => 'Доставка заказа',
                    'price' => $order['shipping'],
                ));
            }
            $w->endElement( /*Товары*/);

            $data = array(
                'Способ оплаты'          => ifset($params['payment_name']),
                'Статус заказа'          => $states[$order['state_id']]->getName(), //XXX
                'Дата изменения статуса' => date("Y-m-dTH:i:s", strtotime(ifempty($order['update_datetime'], $order['create_datetime']))),
                'Способ доставки'        => ifset($params['shipping_name']),
                'Адрес доставки'         => $shipping_address,
                'Адрес платильщика'      => $billing_address,
                //XXX
                'Заказ оплачен'          => null,
                'Доставка разрешена'     => null,
            );
            if ($order['state_id'] == 'deleted') {
                $data['ПометкаУдаления'] = 'true';
            }
            $this->writeProperties($data);

            $w->endElement( /*Документ*/);
            $current_stage[self::STAGE_ORDER]++;
            $processed[self::STAGE_ORDER]++;
            array_shift($orders);
        }
        return ($current_stage[self::STAGE_ORDER] < $count[self::STAGE_ORDER]);
    }

    private function findOrderItems($items)
    {
        static $sku_model;
        $skus = array();
        foreach ($items as $product) {
            if (!isset($map[$product['sku_id']])) {
                $skus[] = $product['sku_id'];
            }
        }
        $map =& $this->data['map'][self::STAGE_ORDER];
        if ($skus = array_unique(array_map('intval', $skus))) {
            if (!$sku_model) {
                $sku_model = new shopProductSkusModel();
            }
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
            }
            if ((count($uuid) > 1) && (reset($uuid) != end($uuid))) {
                $product['id_1c'] = reset($uuid).'#'.end($uuid);
            } else {
                $product['id_1c'] = reset($uuid);
            }
            unset($product);
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
    private function writeOrderItem($product, $discount_rate, $tax)
    {

        if ($discount_rate > 0) {
            $product['discount'] = $discount_rate * $product['price'];
        }

        $product['total'] = $product['quantity'] * ($product['price'] - ifset($product['discount'], 0));
        if (!empty($product['tax']) && empty($product['tax_included'])) {
            $product['total'] += $product['tax'];
        }

        $this->writer->startElement('Товар');
        $this->writer->writeElement('Ид', ifset($product['id_1c'], '-'));
        //XXX ИдКаталога ?
        $this->writer->writeElement('Наименование', $product['name']);
        $this->writeUnit();

        if ($discount_rate > 0) {
            $product['discount'] = $discount_rate * $product['price'];
            $this->writeDiscounts(array(
                array(
                    'name'     => 'Скидка на товар',
                    'discount' => $product['discount'],
                    'included' => true,
                ),
            ));
        }
        $this->writeProperties(array(
            'ВидНоменклатуры' => 'Товар',
            'ТипНоменклатуры' => 'Товар',
        ));
        $this->writer->writeElement('ЦенаЗаЕдиницу', $this->price($product['price']));
        $this->writer->writeElement('Количество', $product['quantity']);
        $this->writer->writeElement('Сумма', $this->price($product['total']));
        if ($tax) {
            $this->writeTaxes(array($tax));
        }

        $this->writer->endElement( /*Товар*/);
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
     */
    private function writeOrderService($service, $discount_rate = 0)
    {
        if ($discount_rate > 0) {
            $product['discount'] = $discount_rate * $service['price'];
        }

        $service['total'] = ifset($service['quantity'], 1) * ($service['price'] - ifset($service['discount'], 0));
        if (!empty($service['tax']) && !empty($service['tax_included'])) {
            $service['total'] += $service['tax'];
        }

        $this->writer->startElement('Товар');
        $this->writer->writeElement('Ид', ifset($service['id_1c'], '-'));
        $this->writer->writeElement('Наименование', $service['name']);
        if (!empty($service['sku'])) {
            $this->writer->writeElement('Артикул', $service['sku']);
        }
        $this->writeUnit();


        $this->writeProperties(array(
            'ВидНоменклатуры' => 'Услуга',
            'ТипНоменклатуры' => 'Услуга',
        ));
        $this->writer->writeElement('ЦенаЗаЕдиницу', $this->price($service['price']));
        $this->writer->writeElement('Количество', $service['quantity']);
        $this->writer->writeElement('Сумма', $this->price($service['total']));


        $this->writer->endElement( /*Товар*/);
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

                $this->writer->endElement( /*Скидка*/);
            }
            $this->writer->endElement( /*Скидки*/);
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
                $this->writer->endElement( /*Налог*/);
            }
            $this->writer->endElement( /*Налоги*/);
        }
    }

    private function writeProperties($data)
    {
        $this->writer->startElement('ЗначенияРеквизитов');
        foreach ($data as $name => $value) {
            $this->writeProperty($name, $value);
        }
        $this->writer->endElement( /*ЗначенияРеквизитов*/);
    }

    private function writeProperty($name, $value)
    {
        $this->writer->startElement('ЗначениеРеквизита');
        $this->writer->writeElement('Наименование', $name);
        $this->writer->writeElement('Значение', $value);
        $this->writer->endElement( /*ЗначениеРеквизита*/);
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
        $value = preg_replace_callback('/\\\\u([0-9a-f]{4})/i', array(__CLASS__, 'replace_unicode_escape_sequence'), $value);
        $value = preg_replace_callback('/\\\\u([0-9a-f]{4})/i', array(__CLASS__, 'html_dereference'), $value);
        return $value;
    }

    private static function html_dereference($match)
    {
        if (strtolower($match[1][0]) === 'x') {
            $code = intval(substr($match[1], 1), 16);
        } else {
            $code = intval($match[1], 10);
        }
        return mb_convert_encoding(pack('N', $code), 'UTF-8', 'UTF-32BE');
    }

    private static function replace_unicode_escape_sequence($match)
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
        static $method = null;
        static $offset = array();
        $result = true;

        while ($while = $this->read($method)) {
            $method = 'unknown_import';
            if ($this->reader->depth >= 2) {
                if ($stage = $this->getStage()) {
                    $method_name = 'stepImport'.ucfirst($stage);
                    if (method_exists($this, $method_name) && ($current_stage[$stage] < $count[$stage])) {
                        list($node, $method) = self::$node_name_map[$stage];
                        if ($method == 'next') {
                            $map = array_flip(self::$node_map);
                            $path = ifset($map[$stage], '/').'/'.$node;
                        } else {
                            $path = null;
                        }
                        while (($cur_stage = $this->getStage()) && ($cur_stage == $stage)) {

                            if (!isset($offset[$stage])) {
                                $offset[$stage] = 0;
                                $method_ = 'read';
                            } else {
                                $method_ = $method;
                            }

                            if ($this->read($method_, $path)) {
                                if ($this->reader->nodeType == XMLReader::ELEMENT) {
                                    if ($this->reader->name == $node) {
                                        ++$offset[$stage];
                                        if ($current_stage[$stage] < $offset[$stage]) {
                                            $result = $this->$method_name($current_stage, $count, $processed);
                                            if ($current_stage[$stage] && ($current_stage[$stage] === $count[$stage])) {
                                                $complete_method = 'completeImport'.ucfirst($stage);
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
                                                    $method = 'skip';
                                                }
                                                break 2;
                                            }
                                        }
                                    }
                                }
                            } else {
                                $method = 'end';
                                $this->read($method);
                                break 2;
                            }
                        }
                    }
                }
                $method = 'next';
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
                            $method = 'skip';
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
        static $model;
        if (empty($model)) {
            $model = new shopCategoryModel();
        }
        return $model;
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
     */
    private function stepImportCategory(&$current_stage, &$count, &$processed)
    {
        $category = & $this->category();
        if (!empty($category['id'])) {
            $target = 'update';
            $update_fields = $this->plugin()->getSettings('update_category_fields');

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

        ++$current_stage[self::STAGE_CATEGORY];
        ++$processed[self::STAGE_CATEGORY][$target];

        return true;

    }

    /**
     * @todo import features values
     *
     * Требуется настройка выгрузка справочников
     *
     * @param $current_stage
     * @param $count
     * @param $processed
     *
     * @return bool
     */
    private function stepImportFeature(&$current_stage, &$count, &$processed)
    {
        //Сопоставление и импорт справочника
        //+ заполнить карту соответствий характеристик
        //TODO black & white lists
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
            case 'Число':
                $data['type'] = ifempty($data['type'], shopFeatureModel::TYPE_DOUBLE);
            case 'Время':
                $data['type'] = ifempty($data['type'], shopFeatureModel::TYPE_DIMENSION.'.time');
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
     *
     * @param array $uuid
     *
     * @return shopProduct
     */
    private function findProduct($uuid)
    {
        static $model;
        static $currency;
        static $type;
        if (!$currency) {
            $currency = wa()->getSetting('currency', 'USD', 'shop');;
        }
        if (!$model) {
            $model = new shopProductModel();
        }

        if ($data = $model->getByField('id_1c', reset($uuid))) {
            $product = new shopProduct($data['id']);
        } else {
            $product = new shopProduct();

            $product->currency = $currency;
            if (!$type) {
                $type = $this->plugin()->getSettings('product_type');
            }
            $product->type_id = $type;
        }
        return $product;
    }

    /**
     * @param string $name
     * @param array $data
     * @return string feature code
     */
    private function findFeature($name, $data = null)
    {
        static $feature_model;

        $feature_map =& $this->data['map'][self::STAGE_PRODUCT];
        if (!isset($feature_map[$name])) {
            if (empty($feature_model)) {
                $feature_model = new shopFeatureModel();
            }

            //TODO enable features map
            if (false && !empty($uuid) && ($features = $feature_model->getFeatures('id_1c', $uuid))) {
                $feature = reset($features);
                $feature_map[$name] = $feature['code'];
            } elseif ($features = $feature_model->getFeatures('lname', $name)) {
                //XXX check fo collisions (multiple features with same name
                $feature = reset($features);
                $feature_map[$name] = $feature['code'];
            } else {
                /* @todo make it adjustable */
                $feature = array(
                    'name' => $name,
                    'code' => strtolower(waLocale::transliterate($name, 'ru_RU')),
                    'type' => ifempty($data['type'], shopFeatureModel::TYPE_VARCHAR),
                );
                $feature += ifempty($data, array());

                if ($feature['id'] = $feature_model->save($feature)) {
                    $feature_map[$name] = $feature['code'];
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

        return ifset($feature_map[$name]);
    }

    private function setFeatureType($codes, $product_type = null)
    {
        if ($product_type) {
            foreach ($codes as $code) {
                if (isset($this->data['new_features'][$code])) {
                    $types = & $this->data['new_features'][$code]['types'];
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
        static $tax_model;
        if (ifset($tax['name'])) {
            $key = mb_strtolower($tax['name'], 'utf-8');
            if (!isset($taxes[$key])) {
                if (!$tax_model) {
                    $tax_model = new shopTaxModel();
                }
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


    private function findType($name)
    {
        static $types = array();
        /**
         * @var shopTypeModel $model
         */
        static $model;
        $type_id = null;
        if (!empty($name)) {
            $name = mb_strtolower($name, 'utf-8');
            if (!isset($types[$name])) {
                if (!$model) {
                    $model = new shopTypeModel();
                }
                if ($type_row = $model->getByName($name)) {
                    $types[$name] = intval($type_row['id']);
                } else {
                    $types[$name] = intval($model->insert(array(
                        'name' => $name,
                        'icon' => 'ss pt box',
                    )));
                }
            }
            $type_id = $types[$name];
        } else {
            $type_id = $this->plugin()->getSettings('product_type');
        }
        return $type_id;
    }

    private function stepImportPrice(&$current_stage, &$count, &$processed)
    {
        $map =& $this->data['map'][self::STAGE_PRICE];

        $element = $this->element();

        $currency = array(
            'id'       => self::field($element, 'Ид'),
            'currency' => self::field($element, 'Валюта'),
        );

        if (in_array($currency['currency'], array('руб', 'RUB', 'RUR',))) {
            $currency['currency'] = 'RUB';
        }

        $map[mb_strtolower(self::field($element, 'Наименование'), 'utf-8')] = $currency;

        ++$current_stage[self::STAGE_PRICE];
        ++$processed[self::STAGE_PRICE];
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
            $settings += $this->plugin()->getSettings();
            $this->plugin()->saveSettings($settings);
        }
    }

    private function convertPrice($price, $from, $to)
    {

        static $currency_model;
        if (empty($currency_model)) {
            $currency_model = new shopCurrencyModel();
        }
        return $currency_model->convert($price, $from, $to);
    }

    private function stepImportOffer(&$current_stage, &$count, &$processed)
    {
        static $product_skus_model;
        static $price_map = null;

        if ($price_map === null) {
            $map = $this->data['map'][self::STAGE_PRICE];
            $price_map = array();
            foreach (array('price', 'purchase_price') as $type) {
                if (!empty($this->data[$type.'_type'])) {
                    $price_name = mb_strtolower($this->data[$type.'_type'], 'utf-8');
                    if (!empty($map[$price_name])) {
                        $map_ = $map[$price_name];
                        if (isset($price_map[$map_['id']])) {
                            $price_map[$map_['id']]['name'][] = $price_name;
                        } else {
                            $price_map[$map_['id']] = array(
                                'type'     => $type,
                                'currency' => $map_['currency'],
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
                'stock'     => array(
                    $this->data['stock_id'] => self::field($element, 'Количество', 'intval'),
                ),
            );

            $sku = & $skus[-1];

            #get offer prices
            $prices = array(
                'price' => null,
            );

            foreach ($this->xpath($element, '//Цены/Цена') as $p) {
                $value = self::field($p, 'ЦенаЗаЕдиницу', 'doubleval');
                if ($k = self::field($p, 'Коэффициент', 'doubleval')) {
                    $value *= $k;
                }
                if ($currency = self::field($p, 'Валюта')) {
                    if (in_array($currency, array('руб', 'RUB', 'RUR',))) {
                        $currency = 'RUB';
                    }
                }

                if ($price_info = ifset($price_map[self::field($p, 'ИдТипаЦены')])) {
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
            if (!empty($prices['price']['currency']) && ($prices['price']['currency'] != $product->currency)) {
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
            foreach ($this->xpath($element, '//ХарактеристикиТовара/ХарактеристикаТовара') as $property) {
                $name = self::field($property, 'Наименование');
                switch ($name) {
                    case "Модель":
                        if ($sku_code = self::field($property, 'Значение')) {
                            $sku['sku'] = $sku_code;
                        }
                        break;
                    default:
                        if ($code = $this->findFeature($name)) {
                            $features[$code] = self::field($property, 'Значение');
                        }
                        break;
                }
            }

            if (!empty($features)) {
                $sku['features'] = $features;
            }

            if (empty($sku['sku']) && $product->sku_id && isset($skus[$product->sku_id])) {
                $sku['sku'] = $skus[$product->sku_id]['sku'];
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

            $product->skus = $skus;
            shopProductStocksLogModel::setContext(shopProductStocksLogModel::TYPE_IMPORT, 'Обмен через CommerceML');
            $product->save();
            shopProductStocksLogModel::clearContext();
            if ($delete_sku) {
                #remove empty default SKU
                if (!$product_skus_model) {
                    $product_skus_model = new shopProductSkusModel();
                }
                $product_skus_model->delete($delete_sku);
            }
            ++$processed[self::STAGE_OFFER]['update'];
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
     */
    private function stepImportProduct(&$current_stage, &$count, &$processed)
    {
        static $update_fields_settings = null;
        if ($update_fields_settings === null) {
            $update_fields_settings = $this->plugin()->getSettings('update_product_fields');
        }
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

        #fill product features data

        $features = array();
        foreach ($this->xpath($element, '//ХарактеристикиТовара/ХарактеристикаТовара') as $property) {
            //Ид
            if ($code = $this->findFeature(self::field($property, 'Наименование'))) {
                $features[$code] = self::field($property, 'Значение');
            }
        }

        if (true) {
            foreach ($this->xpath($element, '//ЗначенияСвойств/ЗначенияСвойства') as $property) {
                //Ид по Ид получать код фичи из карты или наименование
                //Значение | ИдЗначения - undocumented feature?
                $id = self::field($property, 'Ид');
                $feature = ifset($this->data['map'][self::STAGE_FEATURE][$id]);
                $value = self::field($property, 'Значение');

                switch (mb_strtolower($feature['name'], 'utf-8')) {
                    case 'вид номенклатуры':
                    case 'вид товара':
                        //значение из справочника "номенклатурные группы"

                        if (!$product->getId()) {
                            $product->type_id = $this->findType($value);
                        } elseif (!empty($update_fields_settings['type_id'])) {
                            $product->type_id = $this->findType($value);
                        }
                        break;
                    default:
                        if ($feature['name']) {
                            if ($code = $this->findFeature($feature['name'], $feature)) {
                                if (shopCml1cPlugin::isGuid($value) && isset($feature['values'][$value])) {
                                    $features[$code] = $feature['values'][$value];
                                } else {
                                    $features[$code] = $value;
                                }
                            }
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

        switch ($this->plugin()->getSettings('update_product_categories')) {
            case 'sync':
                $categories = array();
                break;
            case 'none':
                $categories = false;
                break;
            case 'update':
            default:
                $categories = array_keys($product->categories);
                break;

        }
        if ($product->id || ($categories !== false)) {
            foreach ($this->xpath($element, '//Группы/Ид') as $category) {
                if ($category = ifset($map[(string)$category])) {
                    $categories[] = intval($category['id']);
                }
            }
            $product->categories = $categories;
        }

        $description = self::field($element, 'Описание');
        if ($this->plugin()->getSettings('description_is_html')) {
            $description = htmlspecialchars($description, ENT_NOQUOTES, 'utf-8');
        }

        $update_fields = array(
            'summary'     => $description,
            'description' => nl2br($description),
            'name'        => self::field($element, 'Наименование'),
        );

        $image_descriptions = array();

        /**
         * xpath = ЗначенияРеквизитов/ЗначениеРеквизита Наименование/  = Значение/
         */
        foreach ($this->xpath($element, '//ЗначениеРеквизита') as $property) {
            switch (self::field($property, 'Наименование')) {
                case "Полное наименование":
                case "ПолноеНаименование":
                case "НаименованиеПолное":
                    if ($value = self::field($property, 'Значение')) {
                        $update_fields['summary'] = $value;
                    }
                    break;
                case "Вес":
                    if ($value = self::field($property, 'Значение', 'doubleval')) {
                        $features['weight'] = $value.' kg';
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
                        if (!$product->getId()) {
                            $product->type_id = $this->findType($value);
                        } elseif (!empty($update_fields_settings['type_id'])) {
                            $product->type_id = $this->findType($value);
                        }
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
            }
        }

        $taxes = array();
        foreach ($this->xpath($element, '//СтавкиНалогов/СтавкаНалога') as $tax) {
            $taxes[] = $this->findTax(array(
                'name' => self::field($tax, 'Наименование'),
                'rate' => self::field($tax, 'Ставка', 'doubleval'),

            ));
        }

        if ($code = $this->plugin()->getSettings('base_unit')) {
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

        //TODO features: ignore|extend|override
        $skus = $product->skus;
        $skus[-1] = array(
            'sku'       => self::field($element, 'Артикул'),
            'name'      => $update_fields['name'].(((count($skus) > 1) && count($sku_features)) ? ' ('.implode(', ', $sku_features).')' : ''),
            'available' => 1,
            'id_1c'     => end($uuid),
        );

        $target = 'update';
        if (!$product->getId()) {
            # update name/summary/description only for new items

            $target = 'new';
            $product->name = $update_fields['name'];
            $product->url = shopHelper::transliterate($product->name);

            foreach ($update_fields as $field => $value) {
                if (!empty($value)) {
                    $product->{$field} = $value;
                }

            }
            if (($tax = reset($taxes)) && !empty($tax['id'])) {
                $product->tax_id = $tax['id'];
            }

            if ($sku_features) {
                $skus[-1]['features'] = $sku_features;
            }
            if ($features) {
                $product->features = $features;
            }
        } else {
            foreach ($update_fields as $field => $value) {
                if (!empty($value) && !empty($update_fields_settings[$field])) {
                    $product->{$field} = $value;
                }
            }

            if (!empty($update_fields_settings['features'])) {
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
        }


        $this->mergeSkus($skus);
        $product->skus = $skus;

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
        ++$processed[$subject][$target];

        ++$current_stage[self::STAGE_PRODUCT];
        unset($product);
        return true;
    }

    private function completeImportProduct()
    {
        //add new features into related product types
        if (!empty($this->data['new_features'])) {
            $feature_types_model = new shopTypeFeaturesModel();
            foreach ($this->data['new_features'] as $feature) {
                if (!empty($feature['types'])) {
                    $feature_types_model->updateByFeature($feature['id'], $feature['types'], false);
                }
            }
        }
    }

    private function stepImportImage(&$current_stage, &$count, &$processed)
    {
        /**
         * @var shopProductImagesModel $model
         */
        static $model;
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

            if ($file && ($file = $this->extract($file)) && is_file($file)) {
                if (!$model) {
                    $model = new shopProductImagesModel();
                }

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
                            throw new waException(sprintf("The insufficient file write permissions for the %s folder.", substr($image_path, strlen($this->getConfig()->getRootPath()))));
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
            $update_fields = $this->plugin()->getSettings('update_product_fields', array());
        }
        foreach ($skus as $id => & $sku) {
            if (($id > 0) && !count($sku['stock']) && ($sku['count'] !== null)) {
                $sku['stock'][0] = $sku['count'];
            }
            if (($id > 0) && isset($skus[-1]) && ($sku['id_1c'] == $skus[-1]['id_1c'])) {
                unset($skus[-1]['available']);
                if (empty($update_fields['sku_name'])) {
                    unset($skus[-1]['sku_name']);
                }
                if (empty($update_fields['sku'])) {
                    unset($skus[-1]['sku']);
                }
                if (isset($skus[-1]['features']) && empty($update_fields['features'])) {
                    unset($skus[-1]['features']);
                }
                $sku = array_merge($sku, $skus[-1]);
                unset($skus[-1]);
            }
            unset($sku);
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
            if (function_exists('zip_open') && ($zip = zip_open($this->data['zipfile'])) && is_resource($zip)) {
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
                throw new waException("Ошибка чтения архива.".(function_exists('zip_open') ? '' : ' Требуется наличие PHP расширения zlib'));
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
        $this->writer->endElement( /*Владелец*/);
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
            $this->writer->endElement( /*Группа*/);
            while ($level < $current--) {
                $this->writer->endElement( /*Группы*/);
                $this->writer->endElement( /*Группа*/);
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

        $w = & $this->writer;
        $w->startElement('Товар');
        $w->writeElement('Ид', $uuid);
        $w->writeElement('Артикул', $sku['sku']);

        if ($group) {
            $w->startElement('Группы');
            foreach ((array)$group as $id) {
                $w->writeElement('Ид', $id);
            }
            $w->endElement( /*Группы*/);
        }

        $w->writeElement('Наименование', $product["name"]);
        $this->writeUnit();
        $w->writeElement('Описание', $product["description"]);

        $this->writeProperties(array(
            'ВидНоменклатуры' => 'Товар',
            'ТипНоменклатуры' => 'Товар',
        ));
        $w->endElement( /*Товар*/);
    }

    /**
     * @param shopProduct $product
     * @param $sku
     */
    private function writeOffer($product, $sku)
    {
        $w = & $this->writer;
        $w->startElement('Предложение');
        if ($product['id_1c'] != $sku['id_1c']) {
            $uuid = $product['id_1c'].'#'.$sku['id_1c'];
        } else {
            $uuid = $sku['id_1c'];
        }
        $w->writeElement('Ид', $uuid);
        $w->writeElement('Наименование', $product->name);
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

        $w->endElement( /*Предложение*/);
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
        $this->writer->endElement( /*БазоваяЕдиница*/);
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
            $this->writer->endElement( /*Цена*/);
        }
        $this->writer->endElement( /*Цены*/);
    }

    private function error($message)
    {
        $path = wa()->getConfig()->getPath('log');
        waFiles::create($path.'/shop/plugins/cml1c.log');
        waLog::log($message, 'shop/plugins/cml1c.log');
    }

    private function trace()
    {
        $args = func_get_args();
        foreach ($args as &$arg) {
            if (is_array($arg)) {
                $arg = var_export($arg, true);
            }
            unset($arg);
        }
        $path = wa()->getConfig()->getPath('log');
        waFiles::create($path.'/shop/plugins/cml1c.debug.log');
        waLog::log(implode("\t", $args), 'shop/plugins/cml1c.debug.log');
    }

    private function price($price, $precision = 2)
    {
        return number_format(floatval($price), $precision, '.', '');
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
            $plugin_currency = $this->plugin()->getSettings('currency');
            $replace = $this->plugin()->getSettings('currency_map');
        }
        if ($replace && ($plugin_currency == $currency)) {
            $currency = $replace;
        }

        return $currency;
    }

    public function __destruct()
    {
        if ($this->reader) {
            $this->reader->close();
        }
    }
}
