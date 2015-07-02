<?php

/**
 * Class shopMigrateWoocommerceTransport
 * @title Woocommerce Wordpress plugin
 * @description WooCommerce 2.1 and later WordPress ecommerce plugin
 * REST API must be enabled under WooCommerce > Settings
 * @link http://woothemes.github.io/woocommerce-rest-api-docs/
 * @link http://docs.woothemes.com/document/woocommerce-rest-api/
 */
class shopMigrateWoocommerceTransport extends shopMigrateTransport
{
    const API_HASH_ALGORITHM = 'SHA256';

    const API_PRODUCT_PER_PAGE = 50;
    const API_ORDERS_PER_PAGE = 25;
    const API_CUSTOMERS_PER_PAGE = 25;
    const API_CATEGORIES_PER_PAGE = 25;

    protected function initOptions()
    {
        waHtmlControl::registerControl('StatusControl', array(&$this, "settingStatusControl"));
        $this->addOption('url', array(
            'value'        => '',//optional default value
            'title'        => _wp('WordPress URL'),
            'description'  => _wp('Enter your main WordPress blog URL'),
            'placeholder'  => 'http://my.wordpress.com',
            'control_type' => waHtmlControl::INPUT,
            'cache'        => true,
        ));

        $this->addOption('consumer_key', array(
            'title'        => _wp('Consumer Key'),
            'value'        => '',
            'placeholder'  => '',
            'description'  => '',
            'control_type' => waHtmlControl::INPUT,
            'cache'        => true,
        ));
        $this->addOption('consumer_secret', array(
            'title'        => _wp('Consumer Secret'),
            'value'        => '',
            'description'  => sprintf(_wp('Enable WooCommerce REST API and obtain Consumer Key and Consumer Secret according to this guide: <a href="%1$s" target="_blank">%1$s</a>'),
                'http://docs.woothemes.com/document/woocommerce-rest-api/'),
            'placeholder'  => '',
            'control_type' => waHtmlControl::INPUT,
            'cache'        => true,
        ));

        $this->addOption('version', array(
            'value'        => 'v2',
            'control_type' => waHtmlControl::HIDDEN,
            'options'      => array(
                array(
                    'value' => 'v2',
                    'title' => 'WooCommerce 2.2 and later',
                ),
                array(
                    'value'       => 'v1',
                    'title'       => "WooCommerce 2.1/2.2/2.3",
                    'description' => "Doesn't support import categories and orders(?)",
                ),
            ),

        ));
        parent::initOptions();
    }

    public function validate($result, &$errors)
    {
        try {
            $info = $this->query('');
        } catch (waException $ex) {
            $result = false;
            $errors['url'] = $ex->getMessage();
        }
        if (!empty($info)) {
            $params = array(
                'name'        => $info['store']['name'],
                'description' => $info['store']['description'],
                'version'     => ifset($info['store']['wc_version']),
                'ssl'         => ifset($info['store']['meta']['ssl_enabled']),
                'currency'    => ifset($info['store']['meta']['currency']),
            );
            $info = "<ul>";
            if (!empty($params['name'])) {
                $info .= "<li>"._wp('Store name').': <b>'.$params['name'].'</b> '.$params['description'].'</li>';
            }
            if (!empty($params['config_language'])) {
                $info .= "<li>"._wp('Source locale').': <b>'.$params['config_language'].'</b></li>';
            }
            if (!empty($params['currency'])) {
                $info .= "<li>"._wp('Source currency code').': <b>'.$params['currency'].'</b></li>';
            }
            if (!empty($params['version'])) {
                $info .= "<li>"._wp('Woocommerce plugin version').': <b>'.$params['version'].'</b></li>';
            }
            $info .= '</ul>';
            if (version_compare($params['version'], '2.2', '>=')) {
                $this->addOption('url', array(
                    'valid'       => true,
                    'readonly'    => true,
                    'description' => $info,
                ));
            } else {
                $errors['url'] = _wp('Woocommerce plugin version should be 2.2 or later');
            }

            try {
                //check read rights
                $option = array(
                    'control_type' => 'StatusControl',
                    'title'        => _wp('Status map'),
                    'options'      => $this->get('orders/statuses', 'order_statuses'),
                );
                $this->addOption('status', $option);
                $this->addOption('type', $this->getProductTypeOption());
                #default currency
                $option = array(
                    'control_type' => waHtmlControl::SELECT,
                    'title'        => _wp('Currency'),
                    'options'      => array(),
                );

                $option['description_wrapper'] = '%s &nbsp;→&nbsp;';

                $option['control_wrapper'] = <<<HTML
<div class="field">
%s
<div class="value no-shift">%3\$s%2\$s</div>
</div>
HTML;

                $currency_model = new shopCurrencyModel();
                if ($currencies = $currency_model->getAll()) {
                    foreach ($currencies as $currency) {
                        $option['options'][$currency['code']] = $currency['code'];
                    }
                }

                $currency_value = $params['currency'];
                if (!$this->getOption('currency')) {
                    $option['description_wrapper'] = '%s &nbsp;→&nbsp;';

                    $option['control_wrapper'] = <<<HTML
<div class="field">
%s
<div class="value no-shift">%3\$s%2\$s</div>
</div>
HTML;
                    if (in_array($currency_value, array('RUB', 'RUR'))) {
                        if ($currency = $currency_model->getById(array('RUB', 'RUR'))) {
                            reset($currency);
                            $currency_value = key($currency);
                        } else {
                            $option['class'] = 'error';
                            $option['description'] = sprintf(_wp("Unknown default currency %s"), $currency_value);
                            $currency_value = false;

                        }
                    } elseif ($currency_value && !$currency_model->getById($currency_value)) {
                        $option['class'] = 'error';
                        $option['description'] = sprintf(_wp("Unknown default currency %s"), $currency_value);
                        $currency_value = false;

                    } else {
                        $option['description'] = $currency_value;
                    }
                    $option['value'] = $currency_value;
                }

                $this->addOption('currency', $option);
                //post_status
            } catch (waException $ex) {
                $result = false;
                $message = $ex->getMessage();
                if (preg_match('@consumer key@i', $message)) {
                    $errors['consumer_key'] = $message;
                } else {
                    $this->addOption('consumer_key', array(
                        'valid' => true,
                    ));
                    $errors['consumer_secret'] = $message;
                }
            }

        } else {
            $result = false;
            $errors['url'] = 'Could not find WordPress installation by the URL provided';
        }


        return parent::validate($result, $errors);
    }

    public function count()
    {
        $count = array(
            self::STAGE_CATEGORY         => count($this->get('products/categories', 'product_categories')),//XXX check it
            self::STAGE_CATEGORY_REBUILD => null,
            self::STAGE_PRODUCT          => $this->get('products/count', 'count'),
            //        self::STAGE_COUPON   => $this->get('coupons/count', 'count'),
            self::STAGE_CUSTOMER         => $this->get('customers/count', 'count'),
            self::STAGE_ORDER            => $this->get('orders/count', 'count'),
            self::STAGE_PRODUCT_IMAGE    => null,
        );
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
        static $raw = null;
        static $category_model;
        if (empty($category_model)) {
            $category_model = new shopCategoryModel();
        }
        if (!$raw) {
            $params = array(
                'limit'  => self::API_CATEGORIES_PER_PAGE,
                'offset' => $current_stage,
            );
            $raw = $this->get('products/categories', 'product_categories', $params);
        }
        if (!isset($this->map[self::STAGE_CATEGORY])) {
            $this->map[self::STAGE_CATEGORY] = array();
            $this->map[self::STAGE_CATEGORY_REBUILD] = array();
        }
        $rebuild = &$this->map[self::STAGE_CATEGORY_REBUILD];

        if ($data = reset($raw)) {
            $category = array(
                'name'        => $data['name'],
                'url'         => $data['slug'],
                'description' => $data['description'],
            );
            if ($category['id'] = $category_model->add($category)) {
                $this->map[self::STAGE_CATEGORY][$data['id']] = array(
                    'name' => $data['name'],
                    'id'   => $category['id'],
                );
                if (!empty($data['parent'])) {
                    if (!isset($rebuild[$data['parent']])) {
                        $rebuild[$data['parent']] = array();
                    }
                    $rebuild[$data['parent']][] = $category['id'];
                    $count[self::STAGE_CATEGORY_REBUILD] = count($rebuild);
                }
                ++$processed;
            }
            ++$current_stage;
            array_shift($raw);
        }
        return true;
    }

    private function stepCategoryRebuild(&$current_stage, &$count, &$processed)
    {
        $result = false;
        $map = $this->map[self::STAGE_CATEGORY];
        if ($rebuild = reset($this->map[self::STAGE_CATEGORY_REBUILD])) {
            $parent = key($this->map[self::STAGE_CATEGORY_REBUILD]);
            if (!empty($map[$parent])) {
                $category_id = $map[$parent]['id'];
                $category = new shopCategoryModel();
                foreach ($rebuild as $id) {
                    $category->move($id, null, $category_id);
                }
                $item = $category->getById($category_id);
                // update full_url of all descendant
                $category->correctFullUrlOfDescendants($item['id'], trim($item['full_url'], '/'));
            }

            unset($this->map[self::STAGE_CATEGORY_REBUILD][$parent]);
            ++$current_stage;
            $result = true;
        }
        return $result;
    }

    private function stepProduct(&$current_stage, &$count, &$processed)
    {
        static $raw = null;
        if (!$raw) {
            $params = array(
                'limit'  => self::API_PRODUCT_PER_PAGE,
                'offset' => $current_stage,
            );
            $response = $this->query('products', $params);
            $raw = ifset($response['products'], array());

        }
        if (!isset($this->map[self::STAGE_PRODUCT])) {
            $this->map[self::STAGE_PRODUCT] = array();
            $map = $this->map[self::STAGE_CATEGORY];
            $this->map[self::STAGE_CATEGORY] = array();
            foreach ($map as $category) {
                $this->map[self::STAGE_CATEGORY][$category['name']] = $category['id'];
            }
        }


        if (!isset($this->map['skus'])) {
            $this->map['skus'] = array();
        }

        $status_map = array(
            'draft'     => shopProductModel::STATUS_DRAFT,
            'published' => shopProductModel::STATUS_ACTIVE,
        );

        if ($p = reset($raw)) {
            $this->log(__METHOD__, self::LOG_DEBUG, $p);

            $product = new shopProduct();

            //integer Product ID (post ID)
            if ($this->getOption('persistent')) {
                $product->id = (int)$p['id'];
            }

            //string    Product name
            $product->name = trim($p['title']);
            $product->meta_title = htmlentities(trim($p['title'], ENT_QUOTES));

            //string UTC DateTime when the product was created
            $product->create_datetime = $this->formatDatetime($p['created_at']);

            //string    UTC DateTime when the product was last updated
            $product->edit_datetime = $this->formatDatetime($p['updated_at']);

            //string    Product status (post status). Default is publish
            $product->status = ifset($status_map[$p['status']], shopProductModel::STATUS_DRAFT);

            //    string    Product URL (post permalink)
            $product->url = trim(str_replace(rtrim($this->getOption('url'), '/').'/product/', '', $p['permalink']), '/');

//   string    Product type. By default in WooCommerce the following types are available: simple, grouped, external, variable. Default is simple
#            $product->f = $p['type'];
//   boolean    If the product is downloadable or not. Downloadable products give access to a file upon purchase
#            $product->f = $p['downloadable'];

//   boolean    If the product is virtual or not. Virtual products are intangible and aren’t shipped
#            $product->f = $p['virtual'];

//   string    SKU refers to a Stock-keeping unit, a unique identifier for each distinct product and service that can be purchased
#            $product->f = $p['sku'];

//   float    Current product price. This is setted from regular_price and sale_price
            $product->currency = $this->getOption('currency');

            if (empty($p['visible'])) {
                $product->status = shopProductModel::STATUS_DISABLED;
            } else {
                $product->status = shopProductModel::STATUS_ACTIVE;
            }

            $product->description = $p['description'];
            $product->summary = $p['short_description'];
            $product->rating = $p['average_rating'];
            $product->rating_count = $p['rating_count'];

            if (!empty($p['categories'])) {
                foreach ($p['categories'] as &$category) {
                    if (!empty($this->map[self::STAGE_CATEGORY][$category])) {
                        $category = $this->map[self::STAGE_CATEGORY][$category];
                    } else {
                        $category = false;
                    }
                }
                if ($p['categories'] = array_filter($p['categories'])) {
                    $product->categories = $p['categories'];
                }

            }

            $product->tags = $p['tags'];
            $product->type_id = $this->getOption('type');

            $product->currency = $this->getConfig()->getCurrency(true);


            $features = array(
                'weight' => $p['weight'],
            );

            if ($features) {
                $product->features = $features;
            }
            $skus = array();
            $sku_id = 0;
            foreach ($p['variations'] as $variant) {

                $sku = array(
                    'stock'     => array(
                        0 => $this->formatStock($variant),
                    ),
                    'sku'       => $variant['sku'],
                    'available' => !empty($variant['visible']),
                );
                $sku += $this->formatPrice($variant);
                $skus[--$sku_id] = $sku;
            }
            if (empty($skus)) {

                $skus[--$sku_id] = $this->formatPrice($p);
                $skus[$sku_id] += array(
                    //'name'          => $sku_options ? $data['name_'.$locale] : '',
                    'sku'       => ifempty($p['sku'], ''),
                    'stock'     => array(
                        0 => $this->formatStock($p),
                    ),
                    //TODO convert price and currency
                    'available' => 1,
                );
            }
            $product->currency = $this->getOption('currency');
            $product->sku_id = $sku_id;
            $product->skus = $skus;

            if ($product->save()) {
                $this->map[self::STAGE_PRODUCT][(int)$p['id']] = array(
                    'id'     => $product->getId(),
                    'sku_id' => $product->sku_id,
                    //'skus'   => array_combine($variants, array_keys($product->skus)),
                );
                ++$processed;
            }
            ++$current_stage;
            array_shift($raw);

            foreach ($p['images'] as $image) {
                if (!empty($image['id']) && ($url = $image['src'])) {
                    if (!isset($this->map[self::STAGE_PRODUCT_IMAGE])) {
                        $this->map[self::STAGE_PRODUCT_IMAGE] = array();
                    }
                    $this->map[self::STAGE_PRODUCT_IMAGE][] = array($product->getId(), $url, $image['title']);
                    $count[self::STAGE_PRODUCT_IMAGE] = count($this->map[self::STAGE_PRODUCT_IMAGE]);
                }
            }
        }
        return true;
    }


    private function stepProductImage(&$current_stage, &$count, &$processed)
    {
        if ($item = reset($this->map[self::STAGE_PRODUCT_IMAGE])) {
            list($product_id, $url, $description) = $item;
            $file = $this->getTempPath('pi');
            try {
                $name = preg_replace('@[^a-zA-Zа-яА-Я0-9\._\-]+@', '', basename(urldecode($url)));
                $name = preg_replace('@(-\d+x\d+)(\.[a-z]{3,4})@', '$2', $name);
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

    private function stepCustomer(&$current_stage, &$count, &$processed)
    {
        static $raw = null;
        $result = false;
        if (!$raw) {
            $params = array(
                'limit'  => self::API_CUSTOMERS_PER_PAGE,
                'offset' => $current_stage,
            );
            $raw = $this->get('customers', 'customers', $params);

        }
        if (!isset($this->map[self::STAGE_CUSTOMER])) {
            $this->map[self::STAGE_CUSTOMER] = array();
        }
        if ($data = reset($raw)) {
            if ($this->addCustomer($data)) {
                ++$processed;
            }
            $result = true;
            array_shift($raw);
            ++$current_stage;

        }

        return $result;
    }

    /**
     * @param $data
     * @return int|null
     * @throws waException
     */
    private function addCustomer($data)
    {
        $result = null;
        $this->log('Import customer', self::LOG_DEBUG, $data);

        $customer = new waContact();
        $customer['firstname'] = ifempty($data['first_name'], ifset($data['username'], 'Guest'));
        $customer['lastname'] = $data['last_name'];
        $customer['email'] = $data['email'];
        if (!empty($data['id']) && !empty($data['username'])) {
            $customer['password'] = md5(microtime(true).rand(0, 10000).$customer['email']);
        }
        $customer['create_datetime'] = $this->formatDatetime($data['created_at']);
        $customer['create_app_id'] = 'shop';
        if ($errors = $customer->save()) {
            $this->log("Error while import customer", self::LOG_ERROR, $errors);
        } else {
            if (($data['role'] == 'customer') || !empty($data['last_order_id'])) {
                $customer->addToCategory('shop');
            }
            $result = $customer->getId();
            $this->map[self::STAGE_CUSTOMER][$data['id']] = $result;

        }
        return $result;
    }

    private function stepOrder(&$current_stage, &$count, &$processed)
    {
        static $raw = null;

        $result = false;
        if (!$raw) {
            $params = array(
                'limit'  => self::API_ORDERS_PER_PAGE,
                'offset' => $current_stage,
            );
            $raw = $this->get('orders', 'orders', $params);

        }

        if ($data = reset($raw)) {

            $order = array(
                'id'              => $data['id'],
                'params'          => array(),
                'state_id'        => $this->statusMap($data['status']),
                'source'          => $this->getOption('url'),
                'create_datetime' => $this->formatDatetime($data['created_at']),
                'update_datetime' => $this->formatDatetime($data['updated_at']),
                'currency'        => $this->getOption('currency'),
                'rate'            => 1.0,
            );

            if (!empty($data['completed_at'])) {
                $order += $this->formatPaidDate($data['completed_at']);
            }

            $customer_id = intval($data['customer_id']);
            if (!empty($this->map[self::STAGE_CUSTOMER][$customer_id])) {
                $order['contact_id'] = $customer_id = $this->map[self::STAGE_CUSTOMER][$customer_id];
            } else {
                $order['contact_id'] = $this->addCustomer($data['customer']);
            }

            /**
             *
             */
            $map = array(
                //       'currency'                      => 'currency',
                'total_shipping'                => 'shipping',
                'total_discount'                => 'discount',
                'total'                         => 'total',
                'coupon_lines'                  => 'params:discount_description',
                // 'currency_value'       => 'rate',
                #
                'note'                          => 'comment',
                #customer snapshot(?)
                'customer_ip'                   => 'params:ip',
                'customer_user_agent'           => 'params:user_agent',
                'customer:first_name'           => 'params:contact_name',
                'customer:last_name'            => 'params:contact_name',
                'customer:email'                => 'params:contact_email',
                #shipping address
                'shipping_address:first_name'   => 'params:shipping_contact_name',
                'shipping_address:last_name'    => 'params:shipping_contact_name',
                'shipping_address:company'      => 'params:shipping_address.company',//TODO check(?)
                'shipping_address:country'      => 'params:shipping_address.country',//ISO code
                'shipping_address:region'       => 'params:shipping_address.region',
                'shipping_address:postcode'     => 'params:shipping_address.zip',
                'shipping_address:city'         => 'params:shipping_address.city',
                'shipping_address:address_1'    => 'params:shipping_address.street',
                'shipping_address:address_2'    => 'params:shipping_address.street',
                #shipping data
                'shipping_lines:0:method_title' => 'params:shipping_name',
                #billing address
                'billing_address:first_name'    => 'params:billing_contact_name',
                'billing_address:last_name'     => 'params:billing_contact_name',
                'billing_address:company'       => 'params:billing_address.company',//TODO check(?)
                'billing_address:country'       => 'params:billing_address.country',//ISO code
                'billing_address:region'        => 'params:billing_address.region',
                'billing_address:postcode'      => 'params:billing_address.zip',
                'billing_address:city'          => 'params:billing_address.city',
                'billing_address:address_1'     => 'params:billing_address.street',
                'billing_address:address_2'     => 'params:billing_address.street',
                #extra billing address
                'billing_address:email'         => 'params:billing_address.email',
                'billing_address:phone'         => 'params:billing_address.phone',
                #
                'payment_details:method_title'  => 'params:payment_name',
                //             'payment_details:paid'=>'params:payment_name',
            );
            self::dataMap($order, $data, $map);
            $this->deleteOrder($order['id']);

            $this->orderModel()->insert($order);

            foreach ($data['line_items'] as $item) {
                $product = ifset($this->map[self::STAGE_PRODUCT][$item['product_id']], array());

                $insert = array(
                    'order_id'   => $order['id'],
                    'type'       => 'product',
                    'name'       => $item['name'],
                    'quantity'   => $item['quantity'],
                    'price'      => doubleval($item['price']),
                    'currency'   => $order['currency'],
                    'product_id' => ifset($product['id'], null),
                    'sku_id'     => ifset($product['sku_id'], null),
                );
                $this->orderItemsModel()->insert($insert);

            }

            //$data['view_order_url']
            $order['params']['auth_code'] = shopWorkflowCreateAction::generateAuthCode($order['id']);
            $order['params']['auth_pin'] = shopWorkflowCreateAction::generateAuthPin();


            if (!empty($order['params'])) {

                $params = array_map('trim', $order['params']);
                $params_model = new shopOrderParamsModel();
                $params_model->set($order['id'], $params);
            }

            if ($customer_id) {
                $this->customerModel()->updateFromNewOrder($customer_id, $order['id'], ifset($order['source'], ''));
                shopCustomer::recalculateTotalSpent($customer_id);
            }

            $result = true;
            array_shift($raw);
            ++$current_stage;
            ++$processed;
            if ($current_stage == $count[self::STAGE_ORDER]) {
                $this->orderModel()->recalculateProductsTotalSales();
                $sql = <<<SQL
UPDATE shop_order o
JOIN (SELECT contact_id, MIN(id) id
FROM `shop_order`
WHERE paid_date IS NOT NULL
GROUP BY contact_id) AS f
ON o.id = f.id
SET o.is_first = 1
SQL;
                $this->orderModel()->query($sql);
            }
        } else {
            $sql = <<<SQL
UPDATE shop_order o
JOIN (SELECT contact_id, MIN(id) id
FROM `shop_order`
WHERE paid_date IS NOT NULL
GROUP BY contact_id) AS f
ON o.id = f.id
SET o.is_first = 1
SQL;
            $this->orderModel()->query($sql);
        }

        return $result;
    }


    private function query($query, $params = array(), $file = null)
    {
        waSessionStorage::close();
        $time = microtime(true);
        $hostname = rtrim(preg_replace('@^https?://@', '', $this->getOption('url')), '/');
        $params = array_filter($params);

        if ($query) {
            if (false) {
                $url = "https://{$this->getOption('consumer_key')}:{$this->getOption('consumer_secret')}@{$hostname}/wc-api/{$this->getOption('version','v1')}/{$query}";
            } else {
                $url = "http://{$hostname}/wc-api/{$this->getOption('version')}/{$query}";
                $params = $this->getOauthParams($url, $params);
            }

        } else {
            $url = "http://{$hostname}/wc-api/{$this->getOption('version')}/";

        }
        $url = rtrim($url, '/');
        if ($params) {
            $url .= '?'.http_build_query($params);
        }

        if (function_exists('curl_init')) {
            $ch = @curl_init($url);
            @curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            @curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            $response = curl_exec($ch);
            if (!$response) {
                if ($error = curl_error($ch)) {
                    $this->log($error, self::LOG_ERROR, compact('query', 'params', 'url'));
                }
            }
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            @curl_close($ch);
        } elseif (ini_get('allow_url_fopen')) {
            $context = stream_context_create(array(
                'http' => array(
                    'ignore_errors' => true,
                    'timeout'       => 10.0,
                ),
            ));
            $response = @file_get_contents($url, null, $context);
            if (!empty($http_response_header)) {
                /**
                 * @link http://php.net/manual/en/reserved.variables.httpresponseheader.php
                 * @var string[] $http_response_header
                 */
                foreach ($http_response_header as $header) {
                    if (preg_match('@^status:\s+(\d+)\s+(.+)$@i', $header, $matches)) {
                        $http_code = $matches[1];
                        break;
                    }
                }
            }
            if (!$response) {
                $error = error_get_last();
                if ($error && ($error['file'] == __FILE__)) {
                    $this->log($error['message'], self::LOG_ERROR, compact('query', 'params', 'url'));
                }

            }
        } else {
            throw new waException('PHP cUrl extension or PHP ini option allow_url_fopen required');
        }

        $time = microtime(true) - $time;

        $this->log(compact('time', 'query', 'params', 'http_code', 'response'), self::LOG_DEBUG);

        $json = null;
        if ($response) {
            if ($response == 'false') {
                $json = false;
            } else {
                if (($json = @json_decode($response, true)) && is_array($json)) {
                    if (!empty($json['errors'])) {
                        $message = array();
                        foreach ($json['errors'] as $error) {
                            $message[] = sprintf('%s (%s)', $error['message'], $error['code']);
                        }
                        throw new waException(implode('; ', $message));

                    }
                    if ($file) {
                        waUtils::varExportToFile($json, $file);
                    }
                } else {
                    $this->log(var_export(compact('url', 'response', 'http_code'), true), self::LOG_ERROR);
                    throw new waException('Invalid JSON response'.(!empty($http_code) ? sprintf(' with code %d', $http_code) : ''));
                }
            }
        } elseif (!empty($http_code)) {
            //x
        } else {
            throw new waException('Empty server response '.$url);
        }
        return $json;
    }

    /**
     * @param $method
     * @param $field
     * @param array $params
     * @return array|bool|null
     * @throws waException
     */
    protected function get($method, $field, $params = array())
    {
        $result = $this->query($method, $params);
        return $result ? ifset($result[$field]) : false;
    }


    /**
     * Generate the parameters required for OAuth 1.0a authentication
     *
     * @since 2.0
     * @param $params
     * @param $method
     * @return array
     */
    private function getOauthParams($url, $params, $method = 'GET')
    {
        $params = array_merge($params, array(
            'oauth_consumer_key'     => $this->getOption('consumer_key'),
            'oauth_timestamp'        => time(),
            'oauth_nonce'            => sha1(microtime()),
            'oauth_signature_method' => 'HMAC-'.self::API_HASH_ALGORITHM,
        ));
        // the params above must be included in the signature generation
        $params['oauth_signature'] = $this->generateOauthSignature($url, $params, $method);
        return $params;
    }

    /**
     * Generate OAuth signature, see server-side method here:
     *
     * @link https://github.com/woothemes/woocommerce/blob/master/includes/api/class-wc-api-authentication.php#L196-L252
     *
     * @since 2.0
     *
     * @param array $params query parameters (including oauth_*)
     * @param string $http_method , e.g. GET
     * @return string signature
     */
    private function generateOauthSignature($url, $params, $http_method)
    {
        $base_request_uri = rawurlencode($url);
        // normalize parameter key/values and sort them
        $params = $this->normalize_parameters($params);
        uksort($params, 'strcmp');
        // form query string
        $query_params = array();
        foreach ($params as $param_key => $param_value) {
            $query_params[] = $param_key.'%3D'.$param_value; // join with equals sign
        }
        $query_string = implode('%26', $query_params); // join with ampersand
        // form string to sign (first key)
        $string_to_sign = $http_method.'&'.$base_request_uri.'&'.$query_string;
        return base64_encode(hash_hmac(self::API_HASH_ALGORITHM, $string_to_sign, $this->getOption('consumer_secret'), true));
    }

    /**
     * Normalize each parameter by assuming each parameter may have already been
     * encoded, so attempt to decode, and then re-encode according to RFC 3986
     *
     * Note both the key and value is normalized so a filter param like:
     *
     * 'filter[period]' => 'week'
     *
     * is encoded to:
     *
     * 'filter%5Bperiod%5D' => 'week'
     *
     * This conforms to the OAuth 1.0a spec which indicates the entire query string
     * should be URL encoded
     *
     * Modeled after the core method here:
     *
     * @link https://github.com/woothemes/woocommerce/blob/master/includes/api/class-wc-api-authentication.php#L254-L288
     *
     * @since 2.0
     * @see rawurlencode()
     * @param array $parameters un-normalized pararmeters
     * @return array normalized parameters
     */
    private function normalize_parameters($parameters)
    {
        $normalized_parameters = array();
        foreach ($parameters as $key => $value) {
            // percent symbols (%) must be double-encoded
            $key = str_replace('%', '%25', rawurlencode(rawurldecode($key)));
            $value = str_replace('%', '%25', rawurlencode(rawurldecode($value)));
            $normalized_parameters[$key] = $value;
        }
        return $normalized_parameters;
    }

    /**
     * @param $data
     * @return array
     * @return float ['price']
     * @return float ['compare_price']
     */
    private function formatPrice($data)
    {
        return array(
            'price'         => $data['price'],
            'compare_price' => ($data['regular_price'] != $data['price']) ? $data['regular_price'] : null,
        );
    }

    private function formatStock($data)
    {
        return empty($data['managing_stock']) ? null : intval($data['stock_quantity']);
    }

    private static function dataMap(&$result, $data, $map)
    {
        foreach ($map as $field => $target) {
            $_data = $data;
            while (strpos($field, ':')) {
                list($_field, $field) = explode(':', $field, 2);
                $_data = ifset($_data[$_field]);
            }
            if ($target && isset($_data[$field])) {
                if (strpos($target, ':')) {
                    if (!empty($_data[$field])) {
                        list($target, $sub_target) = explode(':', $target, 2);
                        if (empty($result[$target][$sub_target])) {
                            $result[$target][$sub_target] = '';
                        } else {
                            $result[$target][$sub_target] .= ' ';
                        }
                        $result[$target][$sub_target] .= $_data[$field];
                    }
                } else {
                    $result[$target] = $_data[$field];
                }
            }
        }
    }


    public function settingStatusControl($name, $params = array())
    {
        foreach ($params as $field => $param) {
            if (strpos($field, 'wrapper')) {
                unset($params[$field]);
            }
        }
        $control = '';
        if (!isset($params['value']) || !is_array($params['value'])) {
            $params['value'] = array();
        }

        waHtmlControl::addNamespace($params, $name);
        $control .= '<table class="zebra">';
        $params['description_wrapper'] = '%s';
        $params['title_wrapper'] = '%s';
        $params['control_wrapper'] = '<tr title="%3$s"><td>%1$s</td><td>&rarr;</td><td>%2$s</td></tr>';
        $params['size'] = 6;
        $workflow = new shopWorkflow();
        $states = $workflow->getAvailableStates();
        $source_states = $params['options'];
        $params['options'] = array();
        $params['options'][] = _wp('Select target order state');

        $params['options']['::new'] = _wp('Add as new order state');

        foreach ($states as $id => $state) {
            $params['options'][$id] = $state['name'];
        }

        $predefined = array(
            "pending"    => "new",
            "processing" => "processing",
            "on-hold"    => "new",
            "completed"  => "completed",
            "cancelled"  => "deleted",
            "refunded"   => "refunded",
            //"failed"     => "Failed",
        );
        foreach ($source_states as $id => $state) {
            $control_params = $params;
            $control_params['value'] = (isset($predefined[$id]) && isset($states[$predefined[$id]])) ? $predefined[$id] : null;
            $control_params['title'] = $state;
            $control_params['title_wrapper'] = '%s';
            $control .= waHtmlControl::getControl(waHtmlControl::SELECT, $id, $control_params);
        }
        $control .= "</table>";

        return $control;

    }

    private function statusMap($status)
    {

        if (!isset($this->map[self::STAGE_ORDER])) {

            $this->map[self::STAGE_ORDER] = array();

            $workflow_config = shopWorkflow::getConfig();

            $states = $this->getOption('status');
            if ($status_names = $this->get('orders/statuses', 'order_statuses')) {
                foreach ($status_names as $status_id => $name) {
                    if (!empty($states[$status_id])) {
                        ;
                        if ($states[$status_id] === '::new') {
                            $id = &$states[$status_id];
                            $workflow_status = array(
                                'name'              => $name,
                                'options'           => array(
                                    'icon'  => 'icon16 ss flag-white',
                                    'style' => array(),
                                ),
                                'available_actions' => array(),

                            );
                            $status_id = waLocale::transliterate(mb_strtolower($status['name']), 'ru_RU');
                            $status_id = preg_replace('([^a-z_])', '_', $status_id);
                            $status_id = substr(preg_replace('([_]{2,})', '_', $status_id), 0, 16);
                            while (isset($workflow_config['states'][$status_id])) {
                                $status_id = substr(uniqid(substr($status_id, 0, 10)), 0, 16);
                            }


                            $workflow_config['states'][$status_id] = $workflow_status;
                            $id = $status_id;
                            unset($id);
                        }
                    }
                    $this->map[self::STAGE_ORDER] = $states;
                    shopWorkflow::setConfig($workflow_config);
                }

            }
        }
        if (!empty($this->map[self::STAGE_ORDER][$status])) {
            $state_id = $this->map[self::STAGE_ORDER][$status];
        } else {
            $state_id = 'new';
        }
        return $state_id;
    }
}
