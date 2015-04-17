<?php

/**
 * Class shopMigrateOpencartTransport
 * @link https://github.com/zenwalker/opencart-webapi/
 * @title OpenCart 1.5.x with Web API 1.0 plugin
 * @description migrate data via Web API 1.0 OpenCart plugin
 */
class shopMigrateOpencartTransport extends shopMigrateTransport
{
    protected function initOptions()
    {
        parent::initOptions();
        waHtmlControl::registerControl('OptionsControl', array(&$this, "settingOptionsControl"));
        $options = array(
            'url' => array(
                'title'        => _wp('Data access URL'),
                'description'  => 'Enter your OpenCart installation URL',
                'placeholder'  => 'http://www.youronlinestore.com',
                'control_type' => waHtmlControl::INPUT,
                'cached'       => true,
                'class'        => 'long',
            ),
        );
        $this->addOption($options);
    }

    public function validate($result, &$errors)
    {
        try {
            $url = $this->getOption('url');
            if (empty($url)) {
                $errors['url'] = 'Empty URL';
                $result = false;
            } else {
                if (!($parsed = parse_url($url))) {
                    $errors['url'] = 'Invalid URL';
                    $result = false;
                }
                if (empty($parsed['scheme'])) {
                    $errors['url'] = 'Invalid URL: expect http:// or https://';
                    $result = false;
                } elseif (empty($parsed['host'])) {
                    $errors['url'] = 'Invalid URL: Empty URL host';
                    $result = false;
                }
            }
            if ($result) {
                $config = $this->query('store/get', 'store');
                $info = "<ul>";
                if (!empty($config['config_language'])) {
                    $info .= "<li>"._wp('Store name').': <b>'.$config['config_name'].'</b></li>';
                }
                if (!empty($config['config_language'])) {
                    $info .= "<li>"._wp('Source locale').': <b>'.$config['config_language'].'</b></li>';
                }
                if (!empty($config['config_currency'])) {
                    $info .= "<li>"._wp('Source currency code').': <b>'.$config['config_currency'].'</b></li>';
                }
                $info .= '</ul>';
                $option = array(
                    'readonly'    => true,
                    'valid'       => true,
                    'description' => $info,
                );
                $this->addOption('url', $option);
                try {
                    if (intval($this->query('information/count', 'result'))) {
                        $option = array(
                            'value'        => false,
                            'control_type' => waHtmlControl::SELECT,
                            'title'        => _wp('Storefront'),
                            'description'  => _wp('Shop-Script settlement for static info pages'),
                            'options'      => array(),
                        );

                        $this->getRouteOptions($option);
                        $this->addOption('domain', $option);
                    }
                } catch (waException $ex) {
                    $this->log('Information pages will be skipped');
                }

                $this->addOption('type', $this->getProductTypeOption());

                #options map
                $option = array(
                    'control_type' => 'OptionsControl',
                    'title'        => _wp('Custom parameters'),
                    'options'      => array(),
                );
                $this->addOption('options', $option);

                $option = array(
                    'value'        => 1,
                    'control_type' => waHtmlControl::CHECKBOX,
                    'title'        => _wp('Preserve IDs'),
                );
                $this->addOption('preserve', $option);
            }
        } catch (waException $ex) {
            $result = false;
            $errors['url'] = $ex->getMessage();
            $this->addOption('url', array('readonly' => false));
        }

        return parent::validate($result, $errors);
    }

    public function count()
    {
        $count = array();

        $count[self::STAGE_OPTIONS] = count(self::getOptions());
        $count[self::STAGE_CATEGORY] = intval($this->query('category/count', 'result'));
        $count[self::STAGE_PRODUCT] = intval($this->query('product/count', 'result'));
        try {
            $count[self::STAGE_PAGES] = intval($this->query('information/count', 'result'));
        } catch (Exception $ex) {
            //just ignore it
        }

        $count[self::STAGE_PRODUCT_IMAGE] = null;
        if ($count[self::STAGE_PRODUCT] && $count[self::STAGE_CATEGORY]) {
            $count[self::STAGE_PRODUCT_CATEGORY] = null;
        }
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
        static $categories;
        if ($categories === null) {
            $categories = $this->query('category/list&level=9999', 'categories');
        }

        if (!isset($this->map[self::STAGE_CATEGORY])) {
            $this->map[self::STAGE_CATEGORY] = array();
        }

        $iterator = new RecursiveArrayIterator($categories);
        iterator_apply($iterator, array($this, 'traverseStructure'), array($iterator, &$current_stage, &$processed));

        $count[self::STAGE_PRODUCT_CATEGORY] = count($this->map[self::STAGE_CATEGORY]);
        return true;
    }

    private function stepOptions(&$current_stage, &$count, &$processed)
    {
        $cache = self::getOptions();

        $result = false;
        if (!$current_stage) {
            $this->offset[self::STAGE_OPTIONS] = 0;
            $this->map[self::STAGE_OPTIONS] = array();
        }
        $offset =& $this->offset[self::STAGE_OPTIONS];

        static $feature_model;
        static $type_features_model;

        $options = $this->getOption('options');

        foreach ($cache as $id => $option_name) {

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
                            'name'       => $option_name,
                            'type'       => shopFeatureModel::TYPE_VARCHAR,
                            'multiple'   => 0,
                            'selectable' => 0,
                        );
                        list($feature['type'], $feature['multiple'], $feature['selectable']) = explode(':', $target[1]);
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
                if ($map) {
                    $this->map[self::STAGE_OPTIONS][$id] = $map;
                } else {
                    unset($this->map[self::STAGE_OPTIONS][$id]);
                }
            }
            $offset = $id;
            $result = true;
            array_shift($cache);
            ++$current_stage;
        }

        return $result;
    }

    private function stepProduct(&$current_stage, &$count, &$processed)
    {
        static $products;
        if (!$products) {
            $products = $this->query('product/list', 'products');
            if ($current_stage) {
                $products = array_slice($products, $current_stage);
            }
        }
        if ($product = reset($products)) {
            if ($product = $this->query('product/get&id='.intval($product['id']), 'product')) {
                if (!empty($product['id']) && ($product_id = $this->addProduct($product))) {
                    if (!isset($this->map[self::STAGE_PRODUCT])) {
                        $this->map[self::STAGE_PRODUCT] = array();
                    }
                    $this->map[self::STAGE_PRODUCT][$product['id']] = $product_id;
                    $pictures = array();
                    if (!empty($product['image'])) {
                        $pictures[] = $product['image'];
                    }
                    foreach ($product['images'] as $picture) {
                        $pictures[] = $picture;
                    }
                    $pictures = array_unique($pictures);
                    foreach ($pictures as $url) {
                        if (parse_url($url)) {
                            if (!isset($this->map[self::STAGE_PRODUCT_IMAGE])) {
                                $this->map[self::STAGE_PRODUCT_IMAGE] = array();
                            }
                            if (empty($count[self::STAGE_PRODUCT_IMAGE])) {
                                $count[self::STAGE_PRODUCT_IMAGE] = 0;
                            }
                            $this->map[self::STAGE_PRODUCT_IMAGE][] = array(
                                $product_id,
                                $url
                            );
                            ++$count[self::STAGE_PRODUCT_IMAGE];
                        }
                    }
                    ++$processed;
                }
            }
            ++$current_stage;
            array_shift($products);
        }
        return true;
    }

    private function stepProductImage(&$current_stage, &$count, &$processed)
    {
        if ($item = reset($this->map[self::STAGE_PRODUCT_IMAGE])) {
            list($product_id, $url) = $item;
            $file = $this->getTempPath('pi');
            try {
                $name = preg_replace('@[^a-zA-Zа-яА-Я0-9\._\-]+@', '', basename(urldecode($url)));
                $name = preg_replace('@(-\d+x\d+)(\.[a-z]{3,4})@', '$2', $name);
                if (waFiles::delete($file) && waFiles::upload($url, $file)) {
                    $processed += $this->addProductImage($product_id, $file, $name);
                } elseif ($file) {
                    $this->log(sprintf('Product image file %s not found', $file), self::LOG_ERROR);
                }
            } catch (Exception $e) {
                $this->log(__FUNCTION__.': '.$e->getMessage(), self::LOG_ERROR, compact('url', 'file', 'name'));
            }
            waFiles::delete($file);
            array_shift($this->map[self::STAGE_PRODUCT_IMAGE]);
            ++$current_stage;
        }
        return true;
    }

    private function stepProductCategory(&$current_stage, &$count, &$processed)
    {
        static $model;
        if ($category_id = reset($this->map[self::STAGE_CATEGORY])) {
            if ($category = key($this->map[self::STAGE_CATEGORY])) {
                if ($products = $this->query('product/list&category='.$category, 'products')) {
                    $product_ids = array();
                    foreach ($products as $product) {
                        $product_id = intval($product['id']);
                        if (!empty($this->map[self::STAGE_PRODUCT][$product_id])) {
                            $product_ids[] = $this->map[self::STAGE_PRODUCT][$product_id];
                        }
                    }
                    if ($product_ids) {
                        if (!$model) {
                            $model = new shopCategoryProductsModel();
                        }
                        $model->add($product_ids, array($category_id));
                        ++$processed;
                    }
                }
            } else {
                $this->log($this->map[self::STAGE_CATEGORY]);
            }
            unset($this->map[self::STAGE_CATEGORY][$category]);
            ++$current_stage;
        }
        return true;
    }

    private function stepPages(&$current_stage, &$count, &$processed)
    {
        static $pages;
        if (!$pages) {
            $pages = $this->query('information/list', 'result');
            if ($current_stage) {
                $pages = array_slice($pages, $current_stage);
            }
        }
        if ($page = reset($pages)) {
            if ($page = $this->query('information/get&id='.intval($page['information_id']), 'result')) {
                $this->log(var_export($page, true), self::LOG_DEBUG);
                if (!empty($page['information_id']) && $this->addPage($page)) {
                    ++$processed;
                }
            }
            ++$current_stage;
            array_shift($pages);
        }
        return true;
    }

    /**
     * @param RecursiveArrayIterator $iterator
     * @param int $current_stage
     * @param int $processed
     */
    private function traverseStructure($iterator, &$current_stage, &$processed)
    {
        static $count = 0;
        while ($iterator->valid()) {
            if ($iterator->hasChildren()) {
                if (is_int($iterator->key())) {
                    ++$count;
                    if ($count > $current_stage) {
                        if ($this->addCategory($iterator->current())) {

                            ++$processed;
                        }
                        ++$current_stage;
                    }
                }
                $this->traverseStructure($iterator->getChildren(), $current_stage, $processed);
            }
            $iterator->next();
        }
    }

    private function addCategory(&$raw)
    {
        static $category;
        if (!$category) {
            $category = new shopCategoryModel();
        }
        $parent_id = ifset($raw['parent_id']);

        $data = array(
            'name'        => $raw['name'],
            'description' => $raw['description'],
            'type'        => shopCategoryModel::TYPE_STATIC,
            'id'          => intval($raw['category_id']),
            'url'         => intval($raw['category_id']),
        );

        if ($category->countByField('id', $data['id'])) {
            if ($this->getOption('preserve')) {
                $category->delete($data['id']);
            } else {
                unset($data['id']);
            }
        }

        if (isset($this->map[self::STAGE_CATEGORY][$parent_id])) {
            $parent_id = $this->map[self::STAGE_CATEGORY][$parent_id];
        } else {
            $parent_id = 0;
        }

        $data['url'] = $category->suggestUniqueUrl($data['url'], ifset($data['id']), $parent_id);
        if ($id = $category->add($data, $parent_id ? $parent_id : null)) {
            $this->map[self::STAGE_CATEGORY][$raw['category_id']] = $id;
        }
        return $id;
    }

    private function addProduct($raw)
    {
        static $product_model;
        if (!$product_model) {
            $product_model = new shopProductModel();
        }
        $product = new shopProduct();

        if ($this->getOption('preserve') && !$product_model->countByField('id', $raw['id'])) {
            $product->id = $raw['id'];
        }

        $product->type_id = $this->getOption('type');

        $product->name = $raw['name'];
        $product->summary = $raw['description'];
        $product->description = $raw['description'];
        $product->meta_description = $raw['meta_description'];
        $product->meta_keywords = $raw['meta_keyword'];
        $product->tags = $raw['tag'];
        $sku = array(
            'available' => 1, //$raw['status']
            'stock'     => array(
                0 => intval($raw['quantity']),
            ),
            'sku'       => $raw['sku'],
        );
        $product->currency = $this->getCurrency($raw['price']);
        if (!empty($raw['special'])) {
            $sku['price'] = $this->getPrice($raw['special']);
            $sku['compare_price'] = $this->getPrice($raw['price']);
        } else {
            $sku['price'] = $this->getPrice($raw['price']);
        }
        $product->skus = array(
            -1 => $sku,
        );

        $options = self::getOptions();
        $features = array();
        foreach ($options as $option_id => $name) {
            $value = $raw[$option_id];
            if (isset($this->map[self::STAGE_OPTIONS][$option_id])) {
                $target = explode(':', $this->map[self::STAGE_OPTIONS][$option_id], 2);
                switch ($target[0]) {
                    case 'f':
                        if ($value !== '') {
                            $code = $target[1];
                            if (strpos($code, ':')) {
                                @list($code, $dimension) = explode(':', $code, 2);
                                if ($dimension && !preg_match('@\d\s+\w+$@', $value)) {
                                    $value = doubleval($value).' '.$dimension;
                                }
                            }
                            if (!isset($features[$code])) {
                                $features[$code] = array();
                            }
                            $features[$code][] = $value;
                        }
                        break;
                }
            }
        }

        if (false) {


            foreach ($raw['attribute_groups'] as $group) {
                foreach ($group['attribute'] as $attribute) {
                    $features[strtolower($attribute['name'])] = $attribute['text'];
                }
            }
        }
        if ($features) {
            $product->features = $features;
        }

        $product->save();
        return $product->getId();
    }

    private function addPage($raw)
    {
        static $pages_model;
        $params = array(
            'keywords'    => ifset($raw['meta_keywords']),
            'description' => ifset($raw['meta_description']),
        );


        $data = array(
            'domain'   => 'localhost',
            'route'    => '*',
            'name'     => ifset($raw['title']),
            'url'      => shopHelper::transliterate(ifset($raw['title'], $raw['information_id'])).'/',
            'full_url' => shopHelper::transliterate(ifset($raw['title'], $raw['information_id'])).'/',
            'content'  => ifset($raw['description']),
            'status'   => 1,

        );
        @list($data['domain'], $data['route']) = explode(':', $this->getOption('domain', 'localhost:*'));
        if (empty($pages_model)) {
            $pages_model = new shopPageModel();
        }
        $data['id'] = $pages_model->add($data);
        if ($params = array_filter($params)) {
            $pages_model->setParams($data['id'], $params);
        }
        return $data['id'];
    }

    private function getPrice($price)
    {
        return doubleval(str_replace(',', '.', preg_replace('@[^\d\.,]+@', '', $price)));
    }

    private function getCurrency($sign)
    {
        $sign = preg_replace('@[\d+\.,\s]+@', '', $sign);
        static $currencies;
        static $default;
        if (!$currencies) {
            $config = $this->getConfig();
            $currencies = $config->getCurrencies();
            $default = $config->getCurrency(true);
        }
        $code = $default;
        foreach ($currencies as $currency) {
            if ($currency['sign'] == $sign) {
                $code = $currency['code'];
                break;
            }
        }
        return $code;

    }

    private static function getOptions()
    {
        return array(
            'upc'          => 'UPC',
            'ean'          => 'EAN',
            'jan'          => 'JAN',
            'isbn'         => 'ISBN',
            'mpn'          => 'MPN',
            'weight'       => _w('Weight'),
            'length'       => _w('Length'),
            'width'        => _w('Width'),
            'height'       => _w('Height'),
            'manufacturer' => _w('Brand'),
        );
    }

    public function settingOptionsControl($name, $params = array())
    {
        $control = '';

        $options = self::getOptions();
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
        foreach ($options as $code => $title) {
            $option_params = $params;
            $option_params['target'] = 'feature';
            $option_params['title'] = $title;
            $option_params['description'] = $code;
            $option_params['value'] = isset($params['value'][$code]) ? $params['value'][$code] : array('feature' => $code,);
            $control .= waHtmlControl::getControl('OptionMapControl', $code, $option_params);
        }
        $control .= "</tbody>";
        $control .= "</table>";
        return $control;
    }

    /**
     * @param $query
     * @param null $field
     * @return array
     * @throws waException
     */
    private function query($query, $field = null)
    {
        $url = $this->getOption('url').'/index.php?route=api/'.$query;
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $response = curl_exec($ch);
            curl_close($ch);
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

        $this->log(var_export(compact('query', 'field', 'response'), true), self::LOG_DEBUG);

        $json = null;
        if ($response) {
            if ($json = json_decode($response, true)) {
                if (!is_array($json)) {

                    $hint = "\n".htmlentities(strip_tags($response), ENT_NOQUOTES, 'utf-8');

                    throw new waException('Unexpected server response:'.nl2br($hint));
                } elseif (empty($json['success'])) {
                    $hint = "\n".htmlentities(strip_tags($response), ENT_NOQUOTES, 'utf-8');
                    throw new waException('API error: '.nl2br($hint));
                }
            } else {
                $hint = "\n".htmlentities(strip_tags($response), ENT_NOQUOTES, 'utf-8');
                throw new waException('Unexpected server response: '.nl2br($hint));
            }
        } else {
            throw new waException('Empty server response '.$url);
        }
        $result = ($field === null) ? $json : ifset($json[$field], null);
        if (is_array($result)) {
            array_walk_recursive($result, array($this, 'workupQuery'));
        } else {
            $this->workupQuery($result);
        }
        return $result;
    }

    private function workupQuery(&$item)
    {
        if (is_string($item)) {
            $item = html_entity_decode($item, ENT_QUOTES, 'utf-8');
        }
    }

    protected function getContextDescription()
    {
        $url = $this->getOption('url');
        $url = parse_url($url, PHP_URL_HOST);
        return empty($url) ? '' : sprintf(_wp('Import data from %s'), $url);
    }
}
