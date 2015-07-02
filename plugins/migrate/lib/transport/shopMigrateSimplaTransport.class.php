<?php

/**
 * Class shopMigrateSimplaTransport
 * @title Simpla
 * @description migrate data via Simpla REST API
 */
class shopMigrateSimplaTransport extends shopMigrateTransport
{

    const API_PRODUCT_PER_PAGE = 100;

    protected function initOptions()
    {
        parent::initOptions();
        $options = array(
            'url'      => array(
                'class'        => 'long',
                'title'        => 'URL',
                'description'  => 'Адрес вашего интернет-магазина на основе Simpla',
                'placeholder'  => 'http://www.yoursimplastore.ru',
                'control_type' => waHtmlControl::INPUT,
                'cache'        => true,
            ),
            'login'    => array(
                'title'        => 'Имя пользователя',
                'description'  => 'Логин администратора интернет-магазина',
                'placeholder'  => 'admin',
                'control_type' => waHtmlControl::INPUT,
                'cache'        => true,
            ),
            'password' => array(
                'title'        => 'Пароль',
                'placeholder'  => '',
                'control_type' => waHtmlControl::PASSWORD,
            ),
        );
        foreach ($options as $name => $option) {
            $this->addOption($name, $option);
        }
    }

    public function validate($result, &$errors)
    {
        try {
            $hostname = rtrim($this->getOption('url'), '/');
            if (empty($hostname)) {
                $errors['url'] = 'Empty url';
                $result = false;
            } else {
                $this->setOption('url', $hostname);
            }


            if ($result) {
                $apikey = $this->getOption('login');
                if (empty($apikey)) {
                    $errors['login'] = 'Некорректный идентификатор';
                    $result = false;
                }

                $password = $this->getOption('password');
                if (empty($password)) {
                    $errors['password'] = 'Некорректный пароль';
                    $result = false;
                }
            }
            if ($result) {
                $options = array(
                    'url'        => array(
                        'readonly' => true,
                        'valid'    => true,
                    ),
                    'login'      => array(
                        'readonly' => true,
                        'valid'    => true,
                    ),
                    'password'   => array(
                        'readonly' => true,
                        'valid'    => true,
                    ),
                    'type'       => $this->getProductTypeOption(),
                    'image_size' => array(
                        'title'        => 'Размер изображений на витрине',
                        'description'  => 'Будут импортированы только уже созданные эскизы указанных размеров с водяным знаком',
                        'value'        => '800x600w',
                        'placeholder'  => '800x600w',
                        'control_type' => waHtmlControl::INPUT,
                    ),
                );
                $this->addOption($options);
            }
        } catch (Exception $ex) {
            $result = false;
            $errors['hostname'] = $errors['apikey'] = $errors['password'] = $ex->getMessage();
        }

        return parent::validate($result, $errors);
    }

    public function count()
    {
        $count = array();
        $count[self::STAGE_PRODUCT] = $this->loadProducts();
        $count[self::STAGE_PRODUCT_IMAGE] = null;
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

    }

    private function stepProduct(&$current_stage, &$count, &$processed)
    {
        static $products;
        if (!$products) {
            $products = include($this->getProductsPath($current_stage));
            if ($offset = $current_stage % self::API_PRODUCT_PER_PAGE) {
                $products = array_slice($products, $offset);
            }
        }
        $counter = 0;
        if ((++$counter < 10) && ($p = reset($products))) {
            $product = new shopProduct();

            $product->name = $p['name'];
            $product->description = $p['body'];
            $product->summary = strip_tags($p['annotation']);
            $product->meta_keywords = $p['meta_keywords'];
            $product->meta_description = $p['meta_description'];
            $product->meta_title = $p['meta_title'];
            $product->url = $p['url'];
            $product->create_datetime = $p['created'];

            $product->type_id = $this->getOption('type');
            $product->currency = $this->getConfig()->getCurrency(true);
            $features = array();
            foreach ($p['features'] as $feature) {
                $features[$feature['id']] = $feature['value'];
                //todo features map
                //input:text
            }

            $skus = array();
            $id = 0;
            foreach ($p['variants'] as $variant) {
                $skus[--$id] = array(
                    'name'          => (string)$variant['name'],
                    'sku'           => (string)$variant['sku'],
                    'stock'         => array(
                        0 => ifempty($variant['infinity']) ? '' : (int)$variant['stock'],
                    ),
                    'price'         => (double)$variant['price'],
                    'available'     => $p['visible'] ? 1 : 0,
                    'compare_price' => (double)$variant['compare_price'],
                );
            }

            $product->skus = $skus;
            if ($product->save()) {
                ++$processed;

                foreach ($p['images'] as $image) {
                    if ($name = ifset($image['filename'])) {
                        if (!isset($this->map[self::STAGE_PRODUCT_IMAGE])) {
                            $this->map[self::STAGE_PRODUCT_IMAGE] = array();
                        }
                        $this->map[self::STAGE_PRODUCT_IMAGE][] = array($product->getId(), $name, ifset($image['name']));
                        $count[self::STAGE_PRODUCT_IMAGE] = count($this->map[self::STAGE_PRODUCT_IMAGE]);
                    }
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
            list($product_id, $name, $description) = $item;
            $file = $this->getTempPath('pi');
            try {
                $url = preg_replace('@(\.[a-z]{3,4})$@', '.800x600w$1', $name);
                //$token = md5($name.'md5(salt)');
                $url = $this->getOption('url').'/files/products/'.$url;

                if (waFiles::delete($file) && waFiles::upload($url, $file)) {
                    $processed += $this->addProductImage($product_id, $file, $name, $description);
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

    private function loadProducts()
    {
        $total_count = 0;
        $features = array();
        $params = array(
            'limit' => self::API_PRODUCT_PER_PAGE,
            'join'  => 'images,variants,categories,comments,features',
        );
        do {
            $path = $this->getProductsPath((isset($params['page']) ? $params['page'] - 1 : 0) * $params['limit']);
            $data = $this->query('products', $params, $path);
            if (false) {
                foreach ($data as $product) {
                    foreach ($product['features'] as $feature) {
                        if (!isset($features[$feature['id']])) {
                            $features[$feature['id']] = $feature['name'];
                        }
                    }
                }
            }
            $count = $data ? count($data) : 0;
            $total_count += $count;
            $params['page'] = ifempty($params['page'], 1) + 1;
        } while ($count);
        return $total_count;
    }

    private function getProductsPath($offset)
    {
        return $this->getTempPath().sprintf('/products.%05d.php', floor($offset / self::API_PRODUCT_PER_PAGE));
    }

    private function query($query, $params = array(), $file = null)
    {
        waSessionStorage::close();
        $login = $this->getOption('login');
        $hostname = preg_replace('@^https?://@', '', $this->getOption('url'));
        $password = $this->getOption('password');

        $url = "http://{$login}:{$password}@{$hostname}/simpla/rest/{$query}";
        $params = array_filter($params);
        if ($params) {
            $url .= '?'.http_build_query($params);
        }
        if (function_exists('curl_init')) {
            $ch = @curl_init($url);
            @curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $response = curl_exec($ch);
            if (!$response) {
                if ($error = curl_error($ch)) {
                    $this->log($error, self::LOG_ERROR, compact('query', 'params', 'url'));
                }
            }
            @curl_close($ch);
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

        $this->log(var_export(compact('query', 'params', 'response'), true), self::LOG_DEBUG);

        $json = null;
        if ($response) {
            if ($response == 'false') {
                $json = false;
            } else {
                if (($json = @json_decode($response, true)) && is_array($json)) {
                    if ($file) {
                        waUtils::varExportToFile($json, $file);
                    }
                } else {
                    $this->log(var_export(compact('url', 'response'), true), self::LOG_ERROR);
                    throw new waException('Invalid JSON response');
                }
            }
        } else {
            throw new waException('Empty server response');
        }
        return $json;
    }


    protected function getContextDescription()
    {
        $url = $this->getOption('url');
        return empty($url) ? '' : sprintf(_wp('Import data from %s'), $url);
    }
}
