<?php

/**
 * Class shopMigrateYmlTransport
 * @description Import data from file in YandexMarketLanguage format
 * @group YML
 */
class shopMigrateYmlTransport extends shopMigrateTransport
{
    const STAGE_CURRENCY = 'currency';

    private static $node_map = array(
        "yml_catalog/shop/currencies" => self::STAGE_CURRENCY,
        "yml_catalog/shop/categories" => self::STAGE_CATEGORY,
        "yml_catalog/shop/offers"     => self::STAGE_PRODUCT,

    );


    private static $node_name_map = array(
        self::STAGE_CURRENCY => array(
            'currency',
            'next'
        ),
        self::STAGE_CATEGORY => array(
            'category',
            'next'
        ),
        self::STAGE_PRODUCT  => array(
            'offer',
            'next'
        ),
    );

    /**
     * @var XMLReader
     */
    private $reader;
    private $path = array();

    protected function initOptions()
    {
        $option = array(
            'title'        => _wp('Feed URL'),
            'description'  => _wp('Specify YML feed URL.'),
            'control_type' => waHtmlControl::INPUT,
            'placeholder'  => 'http://',
            'class'        => 'long',
        );
        $this->addOption('url', $option);
        parent::initOptions();
    }

    protected function getStepMethod($stage)
    {
        $methods = array(
            'step'.ucfirst($stage),
        );
        $method_name = null;
        foreach ($methods as $method) {
            if (method_exists($this, $method)) {
                $method_name = $method;
                break;
            }
        }
        if (!$method_name) {
            $this->log(sprintf("Unsupported actions %s", implode(', ', $methods)), self::LOG_ERROR);
        }

        return $method_name;
    }

    public function getStageName($stage)
    {
        switch ($stage) {
            case self::STAGE_CURRENCY:
                $name = _wp('Importing currencies...');
                break;
            default:
                $name = parent::getStageName($stage);
                break;
        }

        return $name;
    }

    public function getStageReport($stage, $data)
    {
        $report = '';
        if (!empty($data[$stage])) {
            $count = $data[$stage];
            switch ($stage) {
                case self::STAGE_CURRENCY:
                    $report = _wp('%d currency', '%d currencies', $count);
                    break;
                default:
                    $report = parent::getStageReport($stage, $data);
            }
        }

        return $report;
    }

    private function uploadYml()
    {
        $url = $this->getOption('url');
        if (empty($url)) {
            throw new waException(_wp('Empty URL for YML'));
        } else {
            $name = parse_url($url, PHP_URL_HOST).'.xml';
            $path = wa()->getTempPath('plugins/migrate/yml/'.$name);
            try {
                waFiles::upload($url, $path);
            } catch (waException $ex) {
                $this->log($ex->getMessage(), self::LOG_ERROR, compact('url', 'path'));
                throw new waException(sprintf(_wp('Error while upload YML file: %s'), $ex->getMessage()));
            }
        }

        return $name;
    }

    public function validate($result, &$errors)
    {
        try {
            $file = $this->uploadYml();
            $option = array(
                'readonly' => true,
                'valid'    => true,
            );
            $this->addOption('url', $option);
            $this->addOption('type', $this->getProductTypeOption());
            $this->addOption('path', array('value' => $file));
        } catch (waException $ex) {
            $result = false;
            $errors['url'] = $ex->getMessage();
            $this->addOption('url', array('readonly' => false));
        }

        return parent::validate($result, $errors);
    }

    private function getReadOptions()
    {

    }

    public function count()
    {
        $counts = array();
        $method = null;
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
                    while (($current_stage = $this->getStage())) {
                        if ($current_stage != $stage) {
                            $stage = $current_stage;
                            list($node, $method) = self::$node_name_map[$stage];
                            if ($method == 'next') {
                                $map = array_flip(self::$node_map);
                                $path = ifset($map[$stage], '/').'/'.$node;
                            } else {
                                $path = null;
                            }
                        }
                        if (!isset($counts[$stage])) {
                            $counts[$stage] = 0;
                            $method_ = 'read';
                        } else {
                            $method_ = $method;
                        }

                        if ($this->read($method_, $path)) {
                            if ($this->reader->nodeType == XMLReader::ELEMENT) {
                                if ($this->reader->name == $node) {
                                    ++$counts[$stage];
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
        $this->reader->close();
        $counts[self::STAGE_PRODUCT_IMAGE] = null;
        $this->log($counts, self::LOG_DEBUG);

        return $counts;
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

    public function step(&$current, &$count, &$processed, $current_stage, &$error)
    {
        static $read_method = null;
        static $offset = array();

        $result = true;
        $stage = null;
        try {
            $chunk = 30;

            while ($while = $this->read($read_method)) {
                $read_method = 'unknown_import';
                if ($this->reader->depth >= 2) {
                    if ($stage = $this->getStage()) {

                        $method_name = $this->getStepMethod($stage);
                        if (
                            $method_name && //method name determined for current node
                            ($current[$stage] < $count[$stage]) //node still not processed
                        ) {

                            list($node, $read_method) = self::$node_name_map[$stage];
                            if ($read_method == 'next') {
                                $map = array_flip(self::$node_map);
                                $path = ifset($map[$stage], '/').'/'.$node;
                            } else {
                                $path = null;
                            }

                            while (($cur_stage = $this->getStage()) && ($cur_stage == $stage)) {

                                if (!isset($offset[$stage])) {
                                    $offset[$stage] = 0;
                                    $internal_read_method = 'read';
                                } else {
                                    $internal_read_method = $read_method;
                                }

                                if ($this->read($internal_read_method, $path)) {
                                    if ($this->reader->nodeType == XMLReader::ELEMENT) {
                                        if ($this->reader->name == $node) {
                                            ++$offset[$stage];
                                            if ($current[$stage] < $offset[$stage]) {
                                                $result = $this->$method_name($current[$stage], $count, $processed[$stage]);
                                                if ($current[$stage] && ($current[$stage] === $count[$stage])) {
                                                    $complete_method = 'complete'.ucfirst($stage);
                                                    if (method_exists($this, $complete_method)) {
                                                        $this->$complete_method();
                                                    }
                                                    $result = false;
                                                }
                                                if (!$result) {
                                                    break 2;
                                                }
                                                if (--$chunk <= 0) {
                                                    $read_method = 'skip';
                                                    break 2;
                                                }
                                            }
                                        }
                                    }
                                } else {
                                    $read_method = 'end';
                                    $this->read($read_method);
                                    break 2;
                                }
                            }
                        }
                    }
                    $read_method = 'next';
                }
            }

            if (!$while) {
                $stage = self::STAGE_PRODUCT_IMAGE;
                $method_name = $this->getStepMethod($stage);
                if ($method_name && !empty($count[$stage]) && ($current_stage[$stage] < $count[$stage])) {
                    $result = true;
                    do {
                        if (--$chunk <= 0) {
                            $read_method = 'skip';
                            break;
                        }
                    } while ($this->$method_name($current[$stage], $count, $processed[$stage]));
                }

                return $result;
            }


            if ($r = $this->getXmlError()) {
                $this->log('XML errors while read: '.$r, self::LOG_ERROR);
            }

        } catch (Exception $ex) {
            $this->stepException($current, $stage, $error, $ex);
        }

        return ifempty($result);
    }

    private function stepCurrency(&$current_stage, &$count, &$processed)
    {
        if (!isset($this->map[self::STAGE_CURRENCY])) {
            $this->map[self::STAGE_CURRENCY] = array();
        }
        $element = $this->element();
        $code = self::attribute($element, 'id');
        if ($code == 'RUR') {
            $code = 'RUB';
        }
        $this->map[self::STAGE_CURRENCY][$code] = self::attribute($element, 'rate', 'double');
        ++$current_stage;
        ++$processed;

        return true || $current_stage < $count[self::STAGE_CURRENCY];
    }

    private function completeCurrency()
    {

        $model = new shopCurrencyModel();
        $config = wa('shop')->getConfig();
        /**
         * @var shopConfig $config
         */

        $map = $this->map[self::STAGE_CURRENCY];
        foreach ($map as $code => $rate) {
            if (!$model->getById($code)) {
                $model->add($code);
            }
        }
        $default_currency = $config->getCurrency();
        if (empty($map[$default_currency]) || ($map[$default_currency] != 1.0)) {
            foreach ($map as $code => $rate) {
                if ($rate == 1.0) {
                    $model->setPrimaryCurrency($code);
                    $default_currency = $code;
                    break;
                }
            }
        }

        foreach ($map as $code => $rate) {
            if ($code != $default_currency) {
                $model->changeRate($code, $rate);
            }
        }
        unset($this->map[self::STAGE_CURRENCY]);
    }

    private function stepCategory(&$current_stage, &$count, &$processed)
    {
        static $model;

        if (empty($model)) {
            $model = new shopCategoryModel();
        }
        if (!isset($this->map[self::STAGE_CATEGORY])) {
            $this->map[self::STAGE_CATEGORY] = array();
        }
        $map = $this->map[self::STAGE_CATEGORY];
        $element = $this->element();
        $id = self::attribute($element, 'id');
        $category = array(
            'name' => (string)$element,
        );
        $category['url'] = shopHelper::transliterate($category['name']);
        $parent_id = self::attribute($element, 'parentId');
        if (($parent_id !== '') && isset($map[$parent_id])) {
            $parent_id = $map[$parent_id];
        } else {
            $parent_id = null;
        }
        $this->map[self::STAGE_CATEGORY][$id] = $model->add($category, $parent_id);
        ++$current_stage;
        ++$processed;

        return true || $current_stage < $count[self::STAGE_CATEGORY];
    }

    private function stepProduct(&$current_stage, &$count, &$processed)
    {
        $map = $this->map[self::STAGE_CATEGORY];
        $element = $this->element();
        $product = new shopProduct();
        $url = self::field($element, 'url');
        $product->name = self::field($element, 'name');
        $product->description = self::field($element, 'description');

        $product->type_id = $this->getOption('type');
        if (preg_match('@/([^/])/(\?(utm_[^=]+=[^&]+)*)?@', $url, $matches)) {
            $product->url = $matches[1];
        } else {
            $product->url = shopHelper::transliterate($product->name);
        }

        if (($category = self::field($element, 'categoryId')) && (!empty($map[$category]))) {
            $product->categories = array($map[$category]);
        }
        $currency = self::field($element, 'currencyId');
        if ($currency == 'RUR') {
            $currency = 'RUB';
        }
        $product->currency = $currency;
        $product->skus = array(
            -1 => array(
                'price' => self::field($element, 'price', 'double'),
                'stock' => (self::attribute($element, 'available') === 'true') ? null : 0,
            ),
        );
        $product->save();

        foreach (self::xpath($element, 'picture') as $picture) {
            if ($url = (string)$picture) {
                if (!isset($this->map[self::STAGE_PRODUCT_IMAGE])) {
                    $this->map[self::STAGE_PRODUCT_IMAGE] = array();
                }
                if (empty($count[self::STAGE_PRODUCT_IMAGE])) {
                    $count[self::STAGE_PRODUCT_IMAGE] = 0;
                }
                $this->map[self::STAGE_PRODUCT_IMAGE][] = array(
                    $product->getId(),
                    $url
                );
                ++$count[self::STAGE_PRODUCT_IMAGE];
            }
        }

        ++$current_stage;
        ++$processed;

        return true || $current_stage < $count[self::STAGE_PRODUCT];
    }

    private function completeProduct()
    {
        if (isset($this->map[self::STAGE_CATEGORY])) {
            unset($this->map[self::STAGE_CATEGORY]);
        }
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
                $value = intval(
                    str_replace(
                        array(
                            ' ',
                            ','
                        ),
                        array(
                            '',
                            '.'
                        ),
                        (string)$value
                    )
                );
                break;
            case 'floatval':
            case 'float':
                $value = floatval(
                    str_replace(
                        array(
                            ' ',
                            ','
                        ),
                        array(
                            '',
                            '.'
                        ),
                        (string)$value
                    )
                );
                break;
            case 'doubleval':
            case 'double':
                $value = doubleval(
                    str_replace(
                        array(
                            ' ',
                            ','
                        ),
                        array(
                            '',
                            '.'
                        ),
                        (string)$value
                    )
                );
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
        $value = preg_replace_callback(
            '/\\\\u([0-9a-f]{4})/i',
            array(
                __CLASS__,
                'replaceUnicodeEscapeSequence'
            ),
            $value
        );
        $value = preg_replace_callback(
            '/\\\\u([0-9a-f]{4})/i',
            array(
                __CLASS__,
                'htmlDereference'
            ),
            $value
        );

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

    private function openXml()
    {
        if ($this->reader) {
            $this->reader->close();
        } else {
            $this->reader = new XMLReader();
        }
        $name = $this->getOption('path');
        $path = wa()->getTempPath('plugins/migrate/yml/'.$name);
        if (!file_exists($path) && is_file($path)) {
            throw new waException('XML file missed');
        }

        libxml_use_internal_errors(true);
        libxml_clear_errors();
        if (!@$this->reader->open($path, null, LIBXML_NONET)) {
            $this->log('Error while open XML '.$path, self::LOG_ERROR);
            throw new waException('Ошибка открытия XML файла');
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

    private function read($method = 'read', $node = null)
    {
        if (!$this->reader) {
            $this->openXml();
        }
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

    protected function getContextDescription()
    {
        $url = $this->getOption('url');
        $url = parse_url($url, PHP_URL_HOST);
        return empty($url) ? '' : sprintf(_wp('Import data from %s'), $url);
    }
}
