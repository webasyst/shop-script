<?php
class shopCml1cPluginBackendRunController extends waLongActionController
{
    const STAGE_ORDER = 'order';
    const STAGE_CATEGORY = 'category';
    const STAGE_PRODUCT = 'product';
    const STAGE_SKU = 'sku';
    const STAGE_OFFER = 'offer';
    const STAGE_IMAGE = 'image';
    const STAGE_FILE = 'file';

    const UUID_OWNER = 'bd72d900-55bc-11d9-848a-00112f43529a';
    const UUID_OFFER = 'bd72d8f9-55bc-11d9-848a-00112f43529a';

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
    private $xml;

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

    protected function init()
    {
        try {
            $this->data['encoding'] = 'windows-1251';
            $this->data['timestamp'] = time();
            $this->data['direction'] = waRequest::post('direction', 'import');
            $type_model = new shopTypeModel();
            $this->data['types'] = array_keys($type_model->getTypes());
            $this->data['map'] = array();
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
            $this->data['timestamp'] = time();
            $stages = array_keys($this->data['count']);
            $this->data['current'] = array_fill_keys($stages, 0);

            switch ($this->data['direction']) {
                case 'export':
                    $this->data['processed_count'] = array_fill_keys($stages, 0);
                    break;

                case 'import':
                default:
                    $this->data['processed_count'] = array_fill_keys($stages, array(
                        'new'    => 0,
                        'update' => 0,
                    ));
                    break;
            }
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

    protected function initExport()
    {
        $model = new shopOrderModel();
        $this->data['count'] = array();
        $this->data['orders'] = $count =& $this->data['count'];

        $this->data['price_type'] = $this->plugin()->getSettings('price_type');
        $this->data['price_type_uuid'] = $this->plugin()->getSettings('price_type_uuid');

        $this->data['purchase_price_type'] = $this->plugin()->getSettings('purchase_price_type');
        $this->data['purchase_price_type_uuid'] = $this->plugin()->getSettings('purchase_price_type_uuid');

        $export = waRequest::post('export');
        if (!is_array($export)) {
            $export = array();
        }
        if (!empty($export['order'])) {
            $this->data['orders_time'] = !empty($export['new_order']) ? $this->plugin()->exportTime() : 0;
            if ($this->data['orders_time']) {
                $this->data['orders_time'] = $this->data['orders_time'] - 3600;
                $sql = 'SELECT COUNT(*) FROM `'.$model->getTableName().'` WHERE  IFNULL(`update_datetime`,`create_datetime`) > s:0';
                $count[self::STAGE_ORDER] = $model->query($sql, date("Y-m-d H:i:s", $this->data['orders_time']))->fetchField();
            } else {
                $count[self::STAGE_ORDER] = $model->countAll();
            }
        }
        $model = new shopCategoryModel();
        if (!empty($export['product'])) {
            $count[self::STAGE_CATEGORY] = $model->countByField('type', shopCategoryModel::TYPE_STATIC);
            $count[self::STAGE_PRODUCT] = $this->getCollection()->count();
            $count[self::STAGE_OFFER] = $count[self::STAGE_PRODUCT];
        }

        $url = $this->plugin()->getPluginStaticUrl(true);

        $this->data['fsize'] = 0;
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

        $this->write("<?xml version=\"1.0\" encoding=\"{$this->data['encoding']}\"?>\n");
        $this->write('<?xml-stylesheet type="text/xsl" href="'.$url.'xml/sale.xsl"?>'."\n");
        $this->write('<КоммерческаяИнформация ВерсияСхемы="2.05" ДатаФормирования="'.date("Y-m-d H:i")."\">\n");
    }

    protected function initImport()
    {
        if (!extension_loaded('xmlreader')) {
            throw new waException('PHP extension xmlreader required');
        }
        if ($zip = waRequest::post('zipfile')) {
            $this->data['zipfile'] = $this->plugin()->path(basename($zip));
        }
        $this->data['filename'] = $this->plugin()->path($file = basename(waRequest::post('filename')));
        if (!file_exists($this->data['filename'])) {
            $this->extract($file);
        }
        $this->data['price_type'] = mb_strtolower($this->plugin()->getSettings('price_type'), 'utf-8');
        $this->data['price_type_uuid'] = $this->plugin()->getSettings('price_type_uuid');

        $this->data['purchase_price_type'] = mb_strtolower($this->plugin()->getSettings('purchase_price_type'), 'utf-8');
        $this->data['purchase_price_type_uuid'] = $this->plugin()->getSettings('purchase_price_type_uuid');

        $this->data['count'] = array(
            self::STAGE_CATEGORY => 0,
            self::STAGE_PRODUCT  => 0,
            self::STAGE_OFFER    => 0,
            self::STAGE_SKU      => 0,
            self::STAGE_IMAGE    => 0,
            self::STAGE_FILE     => filesize($this->data['filename']),

        );
        if (file_exists($this->data['filename']) && ($xml = @file_get_contents($this->data['filename']))) {
            $this->plugin()->validate($xml);
        }

        $this->xml = new XMLReader();
        $this->xml->open($this->data['filename']);
        return;

        $node_map = array(
            "Классификатор"    => self::STAGE_CATEGORY,
            "Товары"           => self::STAGE_PRODUCT,
            "ПакетПредложений" => self::STAGE_OFFER,
        );

        while ($this->xml->read()) {
            $node = (string)$this->xml->name;
            if (isset($node_map[$node])) {
                $method_name = 'initCount'.ucfirst($node_map[$node]);
                if (!method_exists($this, $method_name)) {
                    $method_name = null;
                }
                while ($this->xml->read() && !($this->xml->name == $node && $this->xml->nodeType == XMLReader::END_ELEMENT)) {

                    $this->data['count'][$node_map[$node]] += $method_name ? $this->$method_name() : 1;
                }
            }
        }

        $this->xml = new XMLReader();
        $this->xml->open($this->data['filename']);
    }

    protected function extract($filename)
    {
        $target = $this->plugin()->path($filename);
        $result = false;
        if (file_exists($target)) {
            $result = $target;
        } elseif (!empty($this->data['zipfile'])) {
            if (function_exists('zip_open') && ($zip = zip_open($this->data['zipfile'])) && is_resource($zip)) {
                while ($zip_entry = zip_read($zip)) {
                    if ($filename == zip_entry_name($zip_entry)) {
                        if ($z = fopen($target, "w")) {
                            $zip_fs = zip_entry_filesize($zip_entry);
                            $size = 0;
                            while ($zz = zip_entry_read($zip_entry, max(0, min(4096, $zip_fs - $size)))) {
                                fwrite($z, $zz);
                                $size += 1024;
                            }
                            fclose($z);
                            zip_entry_close($zip_entry);
                            $result = $target;
                            break;
                        } else {
                            zip_entry_close($zip_entry);
                            zip_close($zip);
                            throw new waException("Error while read zip file");
                        }
                        break;
                    }
                }
                zip_close($zip);
            } else {
                throw new waException("Error while read zip file");
            }
        }
        return $result;
    }

    private function write($xml)
    {
        if (!$this->fp) {

            $this->fp = fopen($this->data['filename'], 'ab');
            if (!empty($this->data['fsize'])) {
                ftruncate($this->fp, $this->data['fsize']);
                fseek($this->fp, $this->data['fsize']);

            }
            if (strtolower($this->data['encoding']) != 'utf-8') {
                if (!@stream_filter_prepend($this->fp, $filter = 'convert.iconv.UTF-8/'.$this->data['encoding'].'//IGNORE')) {
                    throw new waException(sprintf("error while register file filter %s", $filter));
                }
            }
        }
        $size = fwrite($this->fp, $xml);
        $this->data['fsize'] = ftell($this->fp);
        return $size;
    }

    private function _deleteHTML_Elements($str, $strip_tags = true)
    {

        if ($strip_tags) {
            $str = strip_tags($str);
        }
        $str = str_replace('&nbsp;', ' ', $str);
        $str = str_replace("\r", "", $str);
        $str = htmlspecialchars($str, ENT_NOQUOTES, 'utf-8');
        /*$str = str_replace("&", "&amp;", $str);
         $str = str_replace("<", "&lt;", $str);
         $str = str_replace(">", "&gt;", $str);*/
        return $str;
    }

    private function writeCategory($category, &$level)
    {
        if ($category['depth'] > $level) {
            $level = $category['depth'];
            $this->write(str_repeat("\t", $level - 1)."				<Группы>\n");

        } else
            if ($category['depth'] < $level) {
                $delta = $level - $category['depth'] + 1;
                $level = $category['depth'];
                while (--$delta > 0) {
                    $this->write(str_repeat("\t", $level + $delta)."			</Группы>\n");
                    $this->write(str_repeat("\t", $level + $delta)."		</Группа>\n");
                }
            }

        $this->write(str_repeat("\t", $level)."				<Группа>\n");
        $this->write(str_repeat("\t", $level)."					<Ид>".$category['id_1c']."</Ид>\n");
        $this->write(str_repeat("\t", $level)."					<Наименование>".$this->_deleteHTML_Elements($category['name'], false)."</Наименование>\n");
        if (!empty($category['parent_id_1c'])) {
            $this->write(str_repeat("\t", $level)."					<Родитель>".$category['parent_id_1c']."</Родитель>\n");
        }

        if (($category['right_key'] - $category['left_key']) == 1) {
            $this->write(str_repeat("\t", $level)."				</Группа>\n");
        }
    }

    private function writeProduct($product, $sku)
    {
        $uuid = ($product['id_1c'] != $sku['id_1c']) ? $product['id_1c'].'#'.$sku['id_1c'] : $sku['id_1c'];
        $group = false;
        if (!empty($product['category_id']) && isset($this->data['map'][self::STAGE_CATEGORY][$product['category_id']])) {
            $group = $this->data['map'][self::STAGE_CATEGORY][$product['category_id']];

        }
        $dom = new DOMDocument('1.0', 'utf-8');

        $p = $dom->createElement('Товар');
        $p->appendChild($dom->createElement('Ид', $uuid));
        $p->appendChild($dom->createElement('Артикул', $sku['sku']));
        if ($group) {
            $g = $dom->createElement('Группы');
            $g->appendChild($dom->createElement('Ид', $group));
            $p->appendChild($g);

        }

        $p->appendChild($dom->createElement('Наименование', str_replace("&", "&amp;", $product["name"])));
        $b = $dom->createElement('БазоваяЕдиница', 'шт');
        $b->setAttribute('Код', 796);
        $b->setAttribute('НаименованиеПолное', 'Штука');
        $b->setAttribute('МеждународноеСокращение', 'PCE');
        $p->appendChild($b);

        $p->appendChild($dom->createElement('Описание', str_replace("&", "&amp;", $product["description"])));

        $r = $dom->createElement('ЗначенияРеквизитов');
        $r_ = array(
            'ВидНоменклатуры' => 'Товар',
            'ТипНоменклатуры' => 'Товар',
        );
        foreach ($r_ as $name => $value) {
            $rr = $dom->createElement('ЗначениеРеквизита');
            $rr->appendChild($dom->createElement('Наименование', $name));
            $rr->appendChild($dom->createElement('Значение', $value));
            $r->appendChild($rr);
        }
        $p->appendChild($r);
        $dom->appendChild($p);
        $dom->formatOutput = true;
        $dom->encoding = 'utf-8';
        $this->write($dom->saveXML($p));

    }

    private function writeOffer($product, $sku)
    {
        $currency = $product['currency'];
        if (in_array($currency, array("RUB", "RUR"))) {
            $currency = "руб";
        }
        $uuid = ($product['id_1c'] != $sku['id_1c']) ? $product['id_1c'].'#'.$sku['id_1c'] : $sku['id_1c'];

        $dom = new DOMDocument('1.0', 'utf-8');

        $o = $dom->createElement('Предложение');
        $o->appendChild($dom->createElement('Ид', $uuid));
        $o->appendChild($dom->createElement('Наименование', str_replace("&", "&amp;", $product["name"])));

        $b = $dom->createElement('БазоваяЕдиница', 'шт');
        $b->setAttribute('Код', 796);
        $b->setAttribute('НаименованиеПолное', 'Штука');
        $b->setAttribute('МеждународноеСокращение', 'PCE');
        $o->appendChild($b);

        $ps = $dom->createElement('Цены');

        $p = $dom->createElement('Цена');

        $p->appendChild($dom->createElement('ИдТипаЦены', $this->data['price_type_uuid']));
        $p->appendChild($dom->createElement('ЦенаЗаЕдиницу', $sku['price']));
        $p->appendChild($dom->createElement('Валюта', $currency));
        $p->appendChild($dom->createElement('Единица', 'шт'));
        $p->appendChild($dom->createElement('Коэффициент', 1));

        $ps->appendChild($p);
        if ($sku['purchase_price'] && !empty($this->data['purchase_price_type_uuid'])) {
            $pp = $dom->createElement('Цена');

            $pp->appendChild($dom->createElement('ИдТипаЦены', $this->data['purchase_price_type_uuid']));
            $pp->appendChild($dom->createElement('ЦенаЗаЕдиницу', $sku['purchase_price']));
            $pp->appendChild($dom->createElement('Валюта', $currency));
            $pp->appendChild($dom->createElement('Единица', 'шт'));
            $pp->appendChild($dom->createElement('Коэффициент', 1));

            $ps->appendChild($pp);
        }
        $o->appendChild($ps);

        $o->appendChild($dom->createElement('Количество', $sku["count"]));

        $dom->appendChild($o);
        $dom->formatOutput = true;
        $dom->encoding = 'utf-8';
        $this->write($dom->saveXML($o));
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
                    $this->write("\n</КоммерческаяИнформация>\n");
                } catch (waException $ex) {

                }
            }
        }
        return $done;
    }

    protected function step()
    {
        $method = array(
            'step'.ucfirst($this->data['direction']).ucfirst($this->data['stage']),
            'step'.ucfirst($this->data['direction']),
        );

        $result = false;
        try {
            if (method_exists($this, $method_name = array_shift($method))) {
                $result = $this->$method_name($this->data['current'], $this->data['count'], $this->data['processed_count']);
            } else
                if (method_exists($this, $method_name = array_shift($method))) {
                    $result = $this->$method_name($this->data['current'], $this->data['count'], $this->data['processed_count']);
                } else {
                    $this->error(sprintf("Unsupported action %s", $method_name));
                }
        } catch (Exception $ex) {
            $this->error($this->data['direction'].'@ '.$this->data['stage'].': '.$ex->getMessage()."\n".$ex->getTraceAsString());
            sleep(5);
        }
        $this->data['memory'] = memory_get_peak_usage();
        $this->data['memory_avg'] = memory_get_usage();

        return !$this->isDone() && $result;
    }

    protected function finish($filename)
    {
        $result = false;
        switch (ifset($this->data['direction'])) {
            case 'export':
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
                    } else {
                        $this->info();
                    }
                    $result = true;
                } else {
                    if (file_exists($path) && false) {
                        $this->plugin()->validate(file_get_contents($path));
                    }
                    if (waRequest::param('module') == 'frontend') {
                        waFiles::readFile($path, null, false);
                        waFiles::delete($path);
                        $result = true;
                    } else {
                        $this->info();
                    }
                }
                break;
            case 'import':
                if (waRequest::param('module') == 'frontend') {
                    if ($this->data['filename']) {
                        if ($this->xml) {
                            $this->xml->close();
                        }
                        waFiles::delete($this->data['filename']);
                        $result = true;
                        $this->info();
                    }
                }
                break;
            default:
                $this->info();
                break;
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
        foreach ($this->data['current'] as $stage => $current) {
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
            $report .= '<br><a href="?plugin=cml1c&action=download&file='.basename($this->data['filename']).'"><i class="icon16 download"></i><strong>Скачать</strong></a>';
            $report .= ' или <a href="?plugin=cml1c&action=download&file='.basename($this->data['filename']).'&mode=view" target="_blank">просмотреть<i class="icon16 new-window"></i></a>';
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
        }

        switch (waRequest::param('module', 'backend')) {
            case 'backend':
                $this->getResponse()->addHeader('Content-type', 'application/json');
                $this->getResponse()->sendHeaders();
                echo json_encode($response);
                break;
            case 'frontend':
                $this->getResponse()->addHeader('Content-type', 'text/plain');
                $this->getResponse()->sendHeaders();

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
                            self::STAGE_ORDER    => array /*_w*/
                            ('imported %d order', 'imported %d orders'
                            ),
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
        switch ($stage) {

            case self::STAGE_ORDER:
                $name = 'Экспорт заказов...';
                break;

            case self::STAGE_PRODUCT:
                $name = 'Экспорт товаров...';
                break;

            case self::STAGE_CATEGORY:
                $name = 'Экспорт категорий...';
                break;

            case self::STAGE_OFFER:
                $name = 'Экспорт предложений...';
                break;
        }
        return $name;
    }

    protected function restore()
    {
        switch ($this->data['direction']) {
            case 'import':
                $this->xml = new XMLReader();
                $this->xml->open($this->data['filename']);
                break;
        }
    }

    private function stepExportProduct(&$current_stage, &$count, &$processed)
    {
        static $products;
        static $features_model;
        static $product_model;
        static $product_sku_model;
        static $config;
        if (!$products) {
            $offset = $current_stage[self::STAGE_PRODUCT];
            $products = $this->getCollection()->getProducts('*', $offset, 50, false);
        }
        if (!$current_stage[self::STAGE_PRODUCT]) {
            if (!$config) {
                /**
                 * @var shopConfig $config
                 */
                $config = $this->getConfig();
            }
            $this->data['map'][self::STAGE_PRODUCT] = shopCml1cPlugin::makeUuid();
            $this->write("		<Каталог>\n");
            $this->write("			<Ид>".$this->data['map'][self::STAGE_PRODUCT]."</Ид>\n");
            $this->write("			<ИдКлассификатора>".$this->data['map'][self::STAGE_OFFER]."</ИдКлассификатора>\n");
            $this->write("			<Наименование>Каталог товаров от ".date("Y-m-d H:i")."</Наименование>\n");
            $this->write("			<Владелец>\n");
            $this->write("				<Ид>".self::UUID_OWNER."</Ид>\n");
            $this->write("				<ПолноеНаименование>".$this->_deleteHTML_Elements($config->getGeneralSettings('name'), false)."</ПолноеНаименование>\n");
            $this->write("				<Наименование>".$this->_deleteHTML_Elements($config->getGeneralSettings('name'), false)."</Наименование>\n");
            $this->write("			</Владелец>\n");
            $this->write("			<Товары>\n");
        }
        $chunk = 1;
        while (($chunk-- > 0) && ($product = reset($products))) {
            $exported = false;
            if (true) { /* optional - check rights by type*/
                if (empty($product['id_1c'])) {
                    if (!$product_model) {
                        $product_model = new shopProductModel();
                    }
                    do {
                        $product['id_1c'] = shopCml1cPlugin::makeUuid();
                    } while ($product_model->getByField('id_1c', $product['id_1c']));
                    $product_model->updateById($product['id'], array('id_1c' => $product['id_1c']));
                }
                $shop_product = new shopProduct($product);

                if (!isset($product['features']) && false) {
                    if (!$features_model) {
                        $features_model = new shopProductFeaturesModel();
                    }
                    $product['features'] = $features_model->getValues($product['id']);
                }

                //$product['type_name'] = $shop_product->type['name'];
                #WORK
                $skus = $shop_product->skus;

                foreach ($skus as $sku) {

                    $sku['stock'][0] = $sku['count'];
                    $product['skus'] = array(-1 => $sku);
                    if (empty($sku['id_1c'])) {
                        if (!$product_sku_model) {
                            $product_sku_model = new shopProductSkusModel();
                        }
                        do {
                            $sku['id_1c'] = shopCml1cPlugin::makeUuid();
                        } while ($product_sku_model->getByField('id_1c', $sku['id_1c']));
                        $product_sku_model->updateById($sku['id'], array('id_1c' => $sku['id_1c']));
                    }

                    $this->writeProduct($product, $sku);

                    $exported = true;
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
        if ($current_stage[self::STAGE_PRODUCT] == $count[self::STAGE_PRODUCT]) {

            $this->write("				</Товары>\n");
            $this->write("			</Каталог>\n");
        }
        return ($current_stage[self::STAGE_PRODUCT] < $count[self::STAGE_PRODUCT]);
    }

    private function stepExportOffer(&$current_stage, &$count, &$processed)
    {
        static $products;
        static $config;
        if (!$products) {
            $offset = $current_stage[self::STAGE_OFFER];
            $products = $this->getCollection()->getProducts('*', $offset, 50);
        }

        if (!$current_stage[self::STAGE_OFFER]) {
            if (!$config) {
                /**
                 * @var shopConfig $config
                 */
                $config = $this->getConfig();
            }

            $this->write("			<ПакетПредложений СодержитТолькоИзменения=\"false\">\n");
            $this->write("				<Ид>".self::UUID_OFFER."#</Ид>\n");
            $this->write("				<Наименование>Пакет предложений</Наименование>\n");
            $this->write("				<ИдКаталога>".$this->data['map'][self::STAGE_PRODUCT]."</ИдКаталога>\n");
            $this->write("				<ИдКлассификатора>".$this->data['map'][self::STAGE_OFFER]."</ИдКлассификатора>\n");
            $this->write("			<Владелец>\n");
            $this->write("				<Ид>".self::UUID_OWNER."</Ид>\n");
            $this->write("				<ПолноеНаименование>".$this->_deleteHTML_Elements($config->getGeneralSettings('name'), false)."</ПолноеНаименование>\n");
            $this->write("				<Наименование>".$this->_deleteHTML_Elements($config->getGeneralSettings('name'), false)."</Наименование>\n");
            $this->write("			</Владелец>\n");
            $this->write("				<ТипыЦен>\n");
            $this->write("					<ТипЦены>\n");
            $this->write("					<Ид>".$this->_deleteHTML_Elements($this->data['price_type_uuid'])."</Ид>\n");
            $this->write("					<Наименование>".$this->_deleteHTML_Elements($this->data['price_type'])."</Наименование>\n");
            $currency = $config->getCurrency();
            if (in_array($currency, array("RUB", "RUR"))) {
                $currency = "руб";
            }
            $this->write("					<Валюта>".$currency."</Валюта>\n");
            $this->write("					</ТипЦены>\n");

            if (!empty($this->data['purchase_price_type_uuid'])) {
                $this->write("					<ТипЦены>\n");
                $this->write("					<Ид>".$this->_deleteHTML_Elements($this->data['purchase_price_type_uuid'])."</Ид>\n");
                $this->write("					<Наименование>".$this->_deleteHTML_Elements($this->data['purchase_price_type'])."</Наименование>\n");
                $this->write("					<Валюта>".$currency."</Валюта>\n");
                $this->write("					</ТипЦены>\n");
            }
            $this->write("				</ТипыЦен>\n");
            $this->write("				<Предложения>\n");
        }
        $chunk = 1;
        while (($chunk-- > 0) && ($product = reset($products))) {
            $exported = false;
            if (true) { /* optional - check rights by type*/
                $shop_product = new shopProduct($product);
                #WORK
                foreach ($shop_product->skus as $sku) {

                    $sku['stock'][0] = $sku['count'];
                    $product['skus'] = array(-1 => $sku);

                    $this->writeOffer($product, $sku);

                    $exported = true;
                }
            } elseif (count($products) > 1) {
                $chunk = 1;
            }

            array_shift($products);
            ++$current_stage[self::STAGE_OFFER];
            if ($exported) {
                ++$processed[self::STAGE_OFFER];
            }
        }
        if ($current_stage[self::STAGE_OFFER] == $count[self::STAGE_OFFER]) {
            $this->write("				</Предложения>\n");
            $this->write("			</ПакетПредложений>\n");
        }
        return ($current_stage[self::STAGE_OFFER] < $count[self::STAGE_OFFER]);
    }

    private function stepExportCategory(&$current_stage, &$count, &$processed)
    {
        static $categories;
        static $level = 0;
        static $model;
        if (!$model) {
            $model = new shopCategoryModel();
        }
        if (!$categories) {

            $categories = $model->getFullTree('*', true);
            if ($current_stage[self::STAGE_CATEGORY]) {
                $categories = array_slice($categories, $current_stage[self::STAGE_CATEGORY]);
            }
        }
        if (!$current_stage[self::STAGE_CATEGORY]) {
            /**
             * @var shopConfig $config
             */
            $config = $this->getConfig();
            $this->data['map'][self::STAGE_OFFER] = shopCml1cPlugin::makeUuid();
            $this->write("	<Классификатор>\n");
            $this->write("		<Ид>".$this->data['map'][self::STAGE_OFFER]."</Ид>\n");
            $this->write("		<Наименование>Классификатор (Каталог товаров)</Наименование>\n");
            $this->write("		<Владелец>\n");
            $this->write("			<Ид>".self::UUID_OWNER."</Ид>\n");
            $this->write("			<ПолноеНаименование>".$this->_deleteHTML_Elements($config->getGeneralSettings('name'), false)."</ПолноеНаименование>\n");
            $this->write("			<Наименование>".$this->_deleteHTML_Elements($config->getGeneralSettings('name'), false)."</Наименование>\n");
            $this->write("		</Владелец>\n");
            $this->write("		<Группы>\n");
        }
        if (!isset($this->data['map'][self::STAGE_CATEGORY])) {
            $this->data['map'][self::STAGE_CATEGORY] = $model->select('`id`, `id_1c`')->where('`id_1c` IS NOT NULL')->fetchAll('id', true);
        }
        $map =& $this->data['map'][self::STAGE_CATEGORY];
        if ($category = reset($categories)) {
            if (!$category['id_1c']) {
                do {
                    $category['id_1c'] = shopCml1cPlugin::makeUuid();
                } while ($model->getByField('id_1c', $category['id_1c']));
                $model->updateById($category['id'], array('id_1c' => $category['id_1c']));
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
            $i = $level + 1;
            while (--$i > 0) {
                $this->write(str_repeat("\t", $i)."					</Группы>\n");
                $this->write(str_repeat("\t", $i)."				</Группа>\n");
            }

            $this->write("			</Группы>\n");
            $this->write("		</Классификатор>\n");
        }
        return ($current_stage[self::STAGE_CATEGORY] < $count[self::STAGE_CATEGORY]);
    }

    private function stepExportOrder(&$current_stage, &$count, &$processed)
    {

        static $orders;
        static $model;
        static $sku_model;
        static $states;
        static $region_model;
        static $product_model;

        if (!$orders) {
            if (!$model) {
                $model = new shopOrderModel();
            }
            $limit = 50;
            $params = array(
                'offset' => $current_stage[self::STAGE_ORDER],
                'limit'  => $limit,
            );
            if ($this->data['orders_time']) {
                $params['change_datetime'] = $this->data['orders_time'];
            }
            $orders = $model->getList("*,items.name,items.type,items.sku_id,items.product_id,items.quantity,items.price,contact,params", $params);
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
            $map =& $this->data['map'][self::STAGE_ORDER];
            $order['id_str'] = shopHelper::encodeOrderId($order['id']);
            $order['status_comment'] = ''; //TODO

            list($date, $time) = explode(" ", date("Y-m-d H:i", strtotime($order["create_datetime"])));

            $order['params']['shipping'] = array_merge($empty_address, shopHelper::getOrderAddress($order['params'], 'shipping'));

            $order['params']['billing'] = array_merge($empty_address, shopHelper::getOrderAddress($order['params'], 'billing'));

            $currency_code = $order['currency'];
            if ($currency_code == 'RUR' || $currency_code == 'RUB') {
                $currency_code = "руб";
            }

            $shipping_address = ifset($order['params']['shipping_address.street']);
            if (ifempty($order['params']['shipping_address.city'])) {
                if ($shipping_address) {
                    $shipping_address .= ', ';
                }
                $shipping_address .= trim($order['params']['shipping_address.city']);
            }
            if (ifempty($order['params']['shipping_address.zip'])) {
                if ($shipping_address) {
                    $shipping_address .= ', ';
                }
                $shipping_address .= trim($order['params']['shipping_address.zip']);
            }
            if (ifempty($order['params']['shipping_address.country'])) {
                if ($shipping_address) {
                    $shipping_address .= ', ';
                }
                $shipping_address .= waCountryModel::getInstance()->name($order['params']['shipping_address.country']);
            }
            $billing_address = ifset($order['params']['billing_address.street']);
            if (ifempty($order['params']['billing_address.city'])) {
                if ($billing_address) {
                    $billing_address .= ', ';
                }
                $billing_address .= trim($order['params']['billing_address.city']);
            }
            if (ifempty($order['params']['billing_address.zip'])) {
                if ($billing_address) {
                    $billing_address .= ', ';
                }
                $billing_address .= trim($order['params']['billing_address.zip']);
            }
            if (ifempty($order['params']['billing_address.country'])) {
                if ($billing_address) {
                    $billing_address .= ', ';
                }
                $billing_address .= waCountryModel::getInstance()->name($order['params']['billing_address.country']);
            }
            list($order['contact']['lastname'], $order['contact']['firstname']) = explode(' ', ifempty($order['contact']['name'], '-').' %', 2);
            $order['contact']['firstname'] = preg_replace('/\s+%$/', '', $order['contact']['firstname']);
            $this->write('
	<Документ>
		<Ид>'.$order['id'].'</Ид>
		<Номер>'.$order['id_str'].'</Номер>
		<Дата>'.$date.'</Дата>
		<ХозОперация>Заказ товара</ХозОперация>
		<Роль>Продавец</Роль>
		<Валюта>'.$currency_code.'</Валюта>
		<Курс>'.$order['rate'].'</Курс>
		<Сумма>'.$order['total'].'</Сумма>
		<Контрагенты>
			<Контрагент>
				<Ид>'.$order['contact_id'].'</Ид>
				<Наименование>'.$this->_deleteHTML_Elements(ifempty($order['contact']['name'], '-')).'</Наименование>
				<ПолноеНаименование>'.$this->_deleteHTML_Elements(ifempty($order['contact']['name'], '-')).'</ПолноеНаименование>
				<Роль>Покупатель</Роль>
				<Фамилия>'.$order['contact']['lastname'].'</Фамилия>
				<Имя>'.$order['contact']['firstname'].'</Имя>
				<АдресРегистрации>
					<Вид>Адрес доставки</Вид>
					<Представление>'.$this->_deleteHTML_Elements($shipping_address).'</Представление>
');
            if (!empty($order['params']['shipping_address.zip'])) {
                $this->write('
					<АдресноеПоле>
						<Тип>Почтовый индекс</Тип>
						<Значение>'.$this->_deleteHTML_Elements($order['params']['shipping_address.zip']).'</Значение>
					</АдресноеПоле>');
            }
            if (!empty($order['params']['shipping_address.region'])) {
                if (!$region_model) {
                    $region_model = new waRegionModel();
                }
                if ($region = $region_model->get(ifset($order['params']['shipping_address.country']), $order['params']['shipping_address.region'])) {
                    $this->write('
					<АдресноеПоле>
						<Тип>Регион</Тип>
						<Значение>'.$this->_deleteHTML_Elements($region['name']).'</Значение>
					</АдресноеПоле>');
                }
            }
            if (!empty($order['params']['shipping_address.city'])) {
                $this->write('
					<АдресноеПоле>
						<Тип>Город</Тип>
						<Значение>'.$this->_deleteHTML_Elements($order['params']['shipping_address.city']).'</Значение>
					</АдресноеПоле>');
            }
            if (!empty($order['params']['shipping_address.street'])) {
                $this->write('
					<АдресноеПоле>
						<Тип>Улица</Тип>
						<Значение>'.$this->_deleteHTML_Elements($order['params']['shipping_address.street']).'</Значение>
					</АдресноеПоле>');
            }
            $this->write('
				</АдресРегистрации>
				<Контакты>
					<Контакт>
						<Тип>Почта</Тип>
						<Значение>'.$this->_deleteHTML_Elements(ifempty($order['params']['contact_email'], ifset($order['pcontact']['email']))).'</Значение>
					</Контакт>');

            if ($phone = false) {
                $phone = $this->_deleteHTML_Elements($phone);
                $this->write('
						<Контакт>
							<Тип>ТелефонРабочий</Тип>
							<Представление>'.$phone.'</Представление>
							<Значение>'.$phone.'</Значение>
					</Контакт>');
            }

            $this->write('
				</Контакты>
			</Контрагент>
		</Контрагенты>
		<Время>'.$time.'</Время>
		<Комментарий>'.$this->_deleteHTML_Elements(ifset($order['status_comment'])).'</Комментарий>
		');

            $items = ifempty($order['items'], array());
            $subtotal = 0;

            $params = ifset($order['params']);
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
            $taxes = shopTaxes::apply($items, $params);
            if ($taxes) {
                $this->write('
                <Налоги>
');
                foreach ($taxes as $tax) {
                    if (!empty($tax['name'])) {
                        $this->write('
            <Налог>
                <Наименование>'.$this->_deleteHTML_Elements($tax['name']).'</Наименование>
                <УчтеноВСумме>'.($tax['included'] ? 'true' : 'false').'</УчтеноВСумме>
                <Сумма>'.str_replace(',', '.', ($tax['included'] ? $tax['sum_included'] : $tax['sum'])).'</Сумма>
            </Налог>');
                    }
                }
                $this->write('
        </Налоги>');
            }

            if (!$items && ($order['discount'] > 0)) {
                $this->write('
			<Скидки>
				<Скидка>
					<Наименование>Скидка</Наименование>
					<Сумма>'.$order['discount'].'</Сумма>
					<УчтеноВСумме>true</УчтеноВСумме>
				</Скидка>
			</Скидки>');
            }
            $this->write('
		<Товары>');
            $skus = array();

            foreach ($items as $product) {
                if (!isset($map[$product['sku_id']])) {
                    $skus[] = $product['sku_id'];
                }
            }
            $skus = array_map('intval', $skus);
            if ($skus = array_unique($skus)) {
                if (!$sku_model) {
                    $sku_model = new shopProductSkusModel();
                }
                $sql = 'SELECT `s`.`id`, CONCAT(`p`.`id_1c`,"#",`s`.`id_1c`) `cml1c`
                FROM `shop_product_skus` `s`
                LEFT JOIN `shop_product` `p` ON (`p`.`id` = `s`.`product_id`)
                WHERE `s`.`id` IN (i:skus)';
                $map += (array)$sku_model->query($sql, array('skus' => $skus))->fetchAll('id', true);
            }
            foreach ($items as $product) {

                $uuid = explode('#', ifset($map[$product['sku_id']]));
                if ((count($uuid) > 1) && (reset($uuid) != end($uuid))) {
                    $product['id_1c'] = reset($uuid).'#'.end($uuid);
                } else {
                    $product['id_1c'] = reset($uuid);
                }
                $this->write('
			<Товар>
				<Ид>'.ifset($product['id_1c'], '-').'</Ид>
				<Наименование>'.$this->_deleteHTML_Elements($product['name']).'</Наименование>
				<БазоваяЕдиница Код="796" НаименованиеПолное="Штука" МеждународноеСокращение="PCE">шт</БазоваяЕдиница>
');
                $product['dicount'] = 0;
                if ($discount_rate > 0) {
                    $product['dicount'] = str_replace(',', '.', $discount_rate * $product['price']);
                    $this->write('
			<Скидки>
				<Скидка>
					<Наименование>Скидка на товар</Наименование>
					<Сумма>'.$product['dicount'].'</Сумма>
					<УчтеноВСумме>true</УчтеноВСумме>
				</Скидка>
			</Скидки>');
                }
                $this->write('
				<ЗначенияРеквизитов>
					<ЗначениеРеквизита>
						<Наименование>ВидНоменклатуры</Наименование>
						<Значение>Товар</Значение>
					</ЗначениеРеквизита>
					<ЗначениеРеквизита>
						<Наименование>ТипНоменклатуры</Наименование>
						<Значение>Товар</Значение>
					</ЗначениеРеквизита>
				</ЗначенияРеквизитов>
				<ЦенаЗаЕдиницу>'.$product['price'].'</ЦенаЗаЕдиницу>
				<Количество>'.$product['quantity'].'</Количество>
				<Сумма>'.str_replace(',', '.', ($product['quantity'] * $product['price'] + ($product['tax_included'] ? 0 : $product['tax'])) - $product['quantity'] * $product['dicount']).'</Сумма>');
                if ($product['tax_id'] && ($tax = ifempty($taxes[$product['tax_id']]))) {
                    $this->write('
        <Налоги>
            <Налог>
                <Наименование>'.$this->_deleteHTML_Elements($tax['name']).'</Наименование>
                <УчтеноВСумме>'.($product['tax_included'] ? 'true' : 'false').'</УчтеноВСумме>
                <Сумма>'.str_replace(',', '.', $product['tax']).'</Сумма>
                <Ставка>'.str_replace(',', '.', $tax['rate']).'</Ставка>
            </Налог>
        </Налоги>');

                }
                $this->write('
			</Товар>
');
            }
            if (!empty($order['shipping'])) {
                $this->write('
			<Товар>
				<Ид>ORDER_DELIVERY</Ид>
				<Наименование>Доставка заказа</Наименование>
				<БазоваяЕдиница Код="796" НаименованиеПолное="Штука" МеждународноеСокращение="PCE">шт</БазоваяЕдиница>
				<ЗначенияРеквизитов>
					<ЗначениеРеквизита>
						<Наименование>ВидНоменклатуры</Наименование>
						<Значение>Услуга</Значение>
					</ЗначениеРеквизита>
					<ЗначениеРеквизита>
						<Наименование>ТипНоменклатуры</Наименование>
						<Значение>Услуга</Значение>
					</ЗначениеРеквизита>
				</ЗначенияРеквизитов>
				<ЦенаЗаЕдиницу>'.$order['shipping'].'</ЦенаЗаЕдиницу>
				<Количество>1</Количество>
				<Сумма>'.$order['shipping'].'</Сумма>
			</Товар>');
            }

            $this->write('
		</Товары>
		<ЗначенияРеквизитов>
			<ЗначениеРеквизита>
				<Наименование>Способ оплаты</Наименование>
				<Значение>'.$this->_deleteHTML_Elements(ifset($order['params']['payment_name'])).'</Значение>
			</ЗначениеРеквизита>
			<ЗначениеРеквизита>
				<Наименование>Статус заказа</Наименование>
				<Значение>'.$this->_deleteHTML_Elements($states[$order['state_id']]->getName()).'</Значение>
			</ЗначениеРеквизита>
			<ЗначениеРеквизита>
				<Наименование>Дата изменения статуса</Наименование>
				<Значение>'.date("Y-m-d H:i:s", strtotime(ifempty($order['update_datetime'], $order['create_datetime']))).'</Значение>
			</ЗначениеРеквизита>
			<ЗначениеРеквизита>
				<Наименование>Способ доставки</Наименование>
				<Значение>'.$this->_deleteHTML_Elements(ifset($order['params']['shipping_name'])).'</Значение>
			</ЗначениеРеквизита>
			<ЗначениеРеквизита>
				<Наименование>Адрес доставки</Наименование>
				<Значение>'.$this->_deleteHTML_Elements($shipping_address).'</Значение>
			</ЗначениеРеквизита>
			<ЗначениеРеквизита>
				<Наименование>Адрес платильщика</Наименование>
				<Значение>'.$this->_deleteHTML_Elements($billing_address).'</Значение>
			</ЗначениеРеквизита>');
            if ($order['state_id'] == 'deleted') {
                $this->write('
			<ЗначениеРеквизита>
				<Наименование>ПометкаУдаления</Наименование>
				<Значение>true</Значение>
			</ЗначениеРеквизита>');
            }
            $this->write('
		</ЗначенияРеквизитов>
	</Документ>
');
            $current_stage[self::STAGE_ORDER]++;
            $processed[self::STAGE_ORDER]++;
            array_shift($orders);
        }
        return ($current_stage[self::STAGE_ORDER] < $count[self::STAGE_ORDER]);
    }

    /**
     *
     * @return SimpleXMLElement
     */
    private function element()
    {
        $element = $this->xml->readOuterXml();
        return simplexml_load_string(trim($element));
    }

    /**
     *
     *
     * @param SimpleXMLElement $element
     * @param string $field
     * @param string $type
     * @return mixed
     */
    private static function field(&$element, $field, $type = 'string')
    {
        $value = $element->$field;
        switch ($type) {
            case 'floatval':
                $value = floatval(str_replace(array(' ', ','), array('', '.'), (string)$value));
                break;
            case 'doubleval':
                $value = doubleval(str_replace(array(' ', ','), array('', '.'), (string)$value));
                break;
            case 'array':
                $value = (array)$value;
                break;
            case 'string':
            default:
                $value = (string)$value;
                break;
        }
        return $value;
    }

    private function stepImport(&$current_stage, &$count, &$processed)
    {
        $node_map = array(
            "Классификатор"    => self::STAGE_CATEGORY,
            "Товары"           => self::STAGE_PRODUCT,
            "ПакетПредложений" => self::STAGE_OFFER,
        );
        /**
         * @todo add resume code
         */
        while ($this->xml->read()) {
            $node = (string)$this->xml->name;
            if (isset($node_map[$node])) {
                $stage = $node_map[$node];
                $method_name = 'stepImport'.ucfirst($stage);
                if (method_exists($this, $method_name)) {
                    while ($this->xml->read() && !($this->xml->name == $node && $this->xml->nodeType == XMLReader::END_ELEMENT)) {
                        $this->$method_name($current_stage, $count, $processed);
                    }
                }
            }
        }

        $current_stage[self::STAGE_FILE] = $count[self::STAGE_FILE];
        return true;
    }

    private function initCountCategory()
    {
        return ($this->xml->name == 'Группа') ? 1 : 0;
    }

    private function stepImportCategory(&$current_stage, &$count, &$processed)
    {
        $result = false;
        static $id = 0;
        static $stack = array();
        if (!isset($this->data['map'][self::STAGE_CATEGORY])) {
            $category_model = new shopCategoryModel();
            $this->data['map'][self::STAGE_CATEGORY] = $category_model->select('`id`, `id_1c`')->where('`id_1c` IS NOT NULL')->fetchAll('id_1c', true);
        }
        $map =& $this->data['map'][self::STAGE_CATEGORY];
        switch ($this->xml->name) {
            case 'Группы':
                switch ($this->xml->nodeType) {
                    case XMLReader::ELEMENT:
                        $result = true;
                        if (str_replace(' ', '', $this->xml->readOuterXml()) != '<Группы/>') {
                            array_unshift($stack, $id);
                        }

                        break;
                    case XMLReader::END_ELEMENT:
                        array_shift($stack);
                        $result = true;
                        break;
                }
                break;
            case 'Группа':
                if ($this->xml->nodeType == XMLReader::ELEMENT) {
                    $element = $this->element();
                    $uuid = self::field($element, 'Ид');

                    $data = array(
                        'name' => self::field($element, 'Наименование'),
                    );
                    $model = new shopCategoryModel();

                    if ($id = ifempty($map[$uuid])) {
                        $model->update($id, $data);
                    } else {
                        $parent = ($p = self::field($element, 'Родитель')) ? ifset($map[$p], 0) : reset($stack);
                        $data['id_1c'] = $uuid;
                        $map[$uuid] = $id = $model->add($data, $parent);
                    }
                    $result = true;
                    ++$current_stage[self::STAGE_CATEGORY];
                    ++$processed[self::STAGE_CATEGORY];
                }
                break;
        }
        return $result;

    }

    /**
     *
     * @param array $uuid
     * @return shopProduct
     */
    private function findProduct($uuid)
    {
        static $model;
        if (!$model) {
            $model = new shopProductModel();
        }

        if ($data = $model->getByField('id_1c', reset($uuid))) {
            $product = new shopProduct($data['id']);
        } else {
            $product = new shopProduct();
            $product->currency = 'RUB';
        }
        return $product;
    }

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
                    $taxes[$key] = $row['id'];
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

    private function stepImportOffer(&$current_stage, &$count, &$processed)
    {
        $map =& $this->data['map']['currency'];

        static $product_skus_model;
        $price_type = $this->data['price_type'];
        $purchase_price_type = $this->data['purchase_price_type'];
        switch ($this->xml->nodeType) {
            case XMLReader::ELEMENT:
                switch ($this->xml->name) {
                    case "Предложение":
                        $element = $this->element();
                        $uuid = explode('#', self::field($element, 'Ид'));
                        $product = $this->findProduct($uuid);
                        $target = 'new';
                        if ($product->getId()) {
                            $target = 'update';
                            $skus = $product->skus;

                            $price = false;
                            $purchase_price = false;
                            $currency = false;
                            foreach ($element->xpath('Цены/Цена') as $p) {
                                $price = self::field($p, 'ЦенаЗаЕдиницу', 'doubleval');
                                if (self::field($p, 'ИдТипаЦены') == $map[$price_type]["id"]) {
                                    $currency = $map[$price_type];
                                    break;
                                }
                            }

                            if (strlen($price) && $currency) {
                                if (!$product->currency) {
                                    $product->currency = $currency["currency"];
                                } elseif ($currency["currency"] != $product->currency) {
                                    $price = $price / (float)$currency['rate'];
                                }
                            }

                            if (isset($map[$purchase_price_type])) {
                                foreach ($element->xpath('Цены/Цена') as $p) {
                                    if (self::field($p, 'ИдТипаЦены') == $map[$purchase_price_type]["id"]) {
                                        $purchase_price = self::field($p, 'ЦенаЗаЕдиницу', 'doubleval');
                                        $purchase_currency = $map[$purchase_price_type];
                                        if ($purchase_currency["currency"] != $currency["currency"]) {
                                            $purchase_price = $purchase_price / (float)$purchase_currency['rate'];
                                        }
                                        break;
                                    }
                                }
                            }

                            foreach ($element->xpath('ХарактеристикиТовара/ХарактеристикаТовара') as $property) {
                                switch (self::field($property, 'Наименование')) {
                                    case "Модель":
                                        $sku = self::field($property, 'Значение');
                                        break;
                                }
                            }

                            $features = array();
                            $feature_model = new shopFeatureModel();
                            foreach ($element->xpath('ХарактеристикиТовара/ХарактеристикаТовара') as $property) {
                                $feature_name = self::field($property, 'Наименование');
                                if (isset($feature_map[$feature_name])) {
                                    $features[$feature_map[$feature_name]] = self::field($property, 'Значение');
                                } elseif ($feature = $feature_model->getByField('name', $feature_name)) {
                                    $feature_map[$feature_name] = $feature['code'];
                                    $features[$feature['code']] = self::field($property, 'Значение');
                                } else {
                                    /* @todo make it adjustable */
                                    $feature = array(
                                        'name' => $feature_name,
                                        'code' => strtolower(waLocale::transliterate($feature_name, 'ru_RU')),
                                        'type' => shopFeatureModel::TYPE_VARCHAR,
                                    );

                                    if ($feature['id'] = $feature_model->save($feature)) {
                                        $feature_map[$feature_name] = $feature['code'];
                                        $features[$feature['code']] = self::field($property, 'Значение');
                                    }
                                }
                            }

                            $skus[-1] = array(
                                'id_1c'     => end($uuid),
                                'sku'       => self::field($element, 'Артикул'),
                                'name'      => self::field($element, 'Наименование'),
                                'available' => 1,
                                'price'     => $price,
                                'stock'     => array(
                                    0 => self::field($element, 'Количество', 'doubleval'),
                                ),
                                'features'  => $features,
                            );
                            if (!empty($purchase_price)) {
                                $skus[-1]['purchase_price'] = $purchase_price;
                            }

                            if (empty($skus[-1]['sku']) && $product->sku_id && isset($skus[$product->sku_id])) {
                                $skus[-1]['sku'] = $skus[$product->sku_id]['sku'];
                            }

                            foreach ($skus as $id => & $sku) {
                                if (($id > 0) && !count($sku['stock']) && ($sku['count'] !== null)) {
                                    $sku['stock'][0] = $sku['count'];
                                }
                                if (($id > 0) && isset($skus[-1]) && ($sku['id_1c'] == $skus[-1]['id_1c'])) {
                                    unset($skus[-1]['available']);
                                    unset($skus[-1]['name']);
                                    unset($skus[-1]['features']);
                                    $sku = array_merge($sku, $skus[-1]);
                                    unset($skus[-1]);
                                }
                            }
                            unset($sku);
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
                                    if (!$product_skus_model) {
                                        $product_skus_model = new shopProductSkusModel();
                                    }
                                    $delete_sku = $skus[$dummy_id]['id'];

                                    unset($skus[$dummy_id]);
                                }
                            }

                            $product->skus = $skus;
                            $product->save();
                            if ($delete_sku) {
                                $product_skus_model->delete($delete_sku);
                            }
                            ++$processed[self::STAGE_OFFER][$target];
                        } else {
                            $this->error(sprintf('Product with Ид %s not found', implode('#', $uuid)));
                        }
                        unset($element);
                        ++$current_stage[self::STAGE_OFFER];
                        break;
                    case "ТипЦены":
                        $element = $this->element();
                        $currency = array(
                            'id'       => self::field($element, 'Ид'),
                            'currency' => self::field($element, 'Валюта'),
                            'rate'     => 1.0,
                        );

                        if (in_array($currency['currency'], array('руб', 'RUB', 'RUR',))) {
                            $currency['currency'] = 'RUB';
                        }

                        $currency_model = new shopCurrencyModel();
                        $currency['rate'] = $currency_model->getRate($currency['currency']);
                        if (empty($currency['rate'])) {
                            $currency['rate'] = 1.0;
                        }

                        $map[mb_strtolower(self::field($element, 'Наименование'), 'utf-8')] = $currency;
                        break;
                }
                break;

            case XMLReader::END_ELEMENT:
                switch ($this->xml->name) {
                    case 'ТипыЦен':
                        if (isset($map[$purchase_price_type])) {
                            if ($map[$purchase_price_type]['id'] != $this->data['purchase_price_type_uuid']) {
                                $this->plugin()->saveSettings(array('purchase_price_type_uuid' => $map[$purchase_price_type]['id']));
                            }
                        }
                        if (isset($map[$price_type])) {
                            if ($map[$price_type]['id'] != $this->data['price_type_uuid']) {
                                $this->plugin()->saveSettings(array('price_type_uuid' => $map[$price_type]['id']));
                            }
                        } else {
                            $map[$price_type] = reset($map);
                        }
                        break;
                }
                break;
        }
    }

    private function initCountProduct()
    {
        return ($this->xml->name == "Товар" && $this->xml->nodeType == XMLReader::ELEMENT) ? 1 : 0;
    }

    private function stepImportProduct(&$current_stage, &$count, &$processed)
    {
        if ($this->xml->name == "Товар" && $this->xml->nodeType == XMLReader::ELEMENT) {
            $element = $this->element();
            $uuid = explode('#', self::field($element, 'Ид'));

            $subject = ((count($uuid) < 2) || (reset($uuid) == end($uuid))) ? self::STAGE_PRODUCT : self::STAGE_SKU;

            $product = $this->findProduct($uuid);
            if (!isset($this->data['map'][self::STAGE_CATEGORY])) {
                $this->data['map'][self::STAGE_CATEGORY] = array();
            }
            $map = $this->data['map'][self::STAGE_CATEGORY];
            if (!isset($this->data['map'][self::STAGE_PRODUCT])) {
                $this->data['map'][self::STAGE_PRODUCT] = array();
            }
            $feature_map =& $this->data['map'][self::STAGE_PRODUCT];

            $features = array();
            $feature_model = new shopFeatureModel();
            foreach ($element->xpath('ХарактеристикиТовара/ХарактеристикаТовара') as $property) {
                $feature_name = self::field($property, 'Наименование');
                if (isset($feature_map[$feature_name])) {
                    $features[$feature_map[$feature_name]] = self::field($property, 'Значение');
                } elseif ($feature = $feature_model->getByField('name', $feature_name)) {
                    $feature_map[$feature_name] = $feature['code'];
                    $features[$feature['code']] = self::field($property, 'Значение');
                } else {
                    /* @todo make it adjustable */
                    $feature = array(
                        'name' => $feature_name,
                        'code' => strtolower(waLocale::transliterate($feature_name, 'ru_RU')),
                        'type' => shopFeatureModel::TYPE_VARCHAR,
                    );

                    if ($feature['id'] = $feature_model->save($feature)) {
                        $feature_map[$feature_name] = $feature['code'];
                        $features[$feature['code']] = self::field($property, 'Значение');
                    }
                }
            }

            /**
             *
             * Extend product categories
             */
            $categories = array_keys($product->categories);
            foreach ($element->xpath('Группы/Ид') as $category) {
                if ($category = ifset($map[(string)$category])) {
                    $categories[] = $category;
                }
            }
            $product->categories = $categories;
            $product->id_1c = reset($uuid);

            $description = null;
            $summary = htmlspecialchars(self::field($element, 'Описание'));
            $description = nl2br(htmlspecialchars(self::field($element, 'Описание')));
            $name = self::field($element, 'Наименование');

            $skus = $product->skus;
            $skus[-1] = array(
                'sku'       => self::field($element, 'Артикул'),
                'name'      => $name.(((count($skus) > 1) && count($features)) ? ' ('.implode(', ', $features).')' : ''),
                'available' => 1,
                'id_1c'     => end($uuid),
                'price'     => 0,
            );

            foreach ($element->xpath('//ЗначениеРеквизита') as $property) {
                switch (self::field($property, 'Наименование')) {
                    case "Полное наименование":
                        $summary = self::field($property, 'Значение');
                        break;
                    case "Вес":
                        $features['weight'] = self::field($property, 'Значение', 'doubleval').' kg';
                        break;
                    case 'ОписаниеВФорматеHTML':
                        if ($value = self::field($property, 'Значение')) {
                            $description = $value;
                        }
                        break;
                }
            }
            $taxes = array();
            foreach ($element->xpath('//СтавкиНалогов/СтавкаНалога') as $tax) {
                $taxes[] = $this->findTax(array(
                    'name' => self::field($tax, 'Наименование'),
                    'rate' => self::field($tax, 'Ставка', 'doubleval'),

                ));
            }

            /* TODO add target feature code setup */
            if (isset($features['weight']) & !$feature_model->getByCode('weight')) {
                $feature = array(
                    'name' => _w('Weight'),
                    'code' => 'weight',
                    'type' => shopFeatureModel::TYPE_DIMENSION.'.'.'weight',
                );

                if ($feature_model->save($feature)) {
                    $feature_map[$feature['name']] = $feature['code'];
                }
            }

            $target = 'update';
            if (!$product->getId()) {
                $target = 'new';
                $product->name = $name;

                if (!empty($description)) {
                    $product->description = $description;
                }
                if (!empty($summary)) {
                    $product->summary = $summary;
                }
                if (($tax = reset($taxes)) && !empty($tax['id'])) {
                    $product->tax_id = $tax['id'];
                }
                if ($features) {
                    if (count($uuid) > 1) {
                        $skus[-1]['features'] = $features;
                    } else {
                        $product->features = $features;
                    }
                }
            } else {
                if (count($uuid) > 1) {
                    $skus[-1]['features'] = $features;
                } else {
                    /* ignore empty SKU for exists products */
                    unset($skus[-1]);
                }
            }

            foreach ($skus as $id => & $sku) {
                if (($id > 0) && !count($sku['stock']) && ($sku['count'] !== null)) {
                    $sku['stock'][0] = $sku['count'];
                }
                if (($id > 0) && isset($skus[-1]) && ($sku['id_1c'] == $skus[-1]['id_1c'])) {
                    unset($skus[-1]['price']);
                    if (isset($skus[-1]['features'])) {
                        unset($skus[-1]['features']);
                    }
                    $sku = array_merge($sku, $skus[-1]);
                    unset($skus[-1]);
                }
            }
            unset($sku);

            $product->skus = $skus;

            $product->save();
            foreach ($element->xpath('//Картинка') as $image) {
                $this->data['map'][self::STAGE_IMAGE][] = array((string)$image, $product->getId());
                if (!isset($count[self::STAGE_IMAGE])) {
                    $count[self::STAGE_IMAGE] = 0;
                }
                ++$count[self::STAGE_IMAGE];
            }
            ++$processed[$subject][$target];

            ++$current_stage[self::STAGE_PRODUCT];
            unset($product);
        }
    }

    private function stepImportImage(&$current_stage, &$count, &$processed)
    {
        static $model;
        /**
         * @var shopConfig $config
         */
        $result = false;
        if (!is_array($this->data['map'][self::STAGE_IMAGE]) && $this->data['map'][self::STAGE_IMAGE]) {
            $this->data['map'][self::STAGE_IMAGE] = array($this->data['map'][self::STAGE_IMAGE]);

        }
        if ($image = reset($this->data['map'][self::STAGE_IMAGE])) {
            $result = true;
            list($file, $product_id) = $image;
            if (($file = $this->extract($file)) && is_file($file)) {
                if (!$model) {
                    $model = new shopProductImagesModel();
                }

                try {
                    $target = 'new';
                    $name = basename($file);

                    if ($image = new waImage($file)) {

                        $data = array(
                            'product_id'        => $product_id,
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

    private function error($message)
    {
        $path = wa()->getConfig()->getPath('log');
        waFiles::create($path.'/shop/plugins/cml1c.log');
        waLog::log($message, 'shop/plugins/cml1c.log');
    }
}
