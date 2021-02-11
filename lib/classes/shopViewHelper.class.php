<?php

/**
 * Class shopViewHelper
 * @property-read shopCart $cart
 * @property-read shopCustomer $customer
 */
class shopViewHelper extends waAppViewHelper
{
    const CROSS_SELLING_IN_STOCK = '%in_stock_settings';

    private $shop_cart;
    private $shop_customer;
    /**
     * @var string
     */
    private $shop_currency;

    /**
     * @var shopConfig
     */
    private $shop_config;


    protected static $url = '';

    /**
     * @param $count
     * @param $page
     * @param $url_params
     * @param $url_path
     * @param int $limit
     * @return string
     */
    public function pager($count, $page, $url_params = '', $url_path = '', $limit = null)
    {
        $limit = ifset($limit, wa('shop')->getConfig()->getOption('promos_per_page'));
        $old_url_path = self::$url;
        self::$url = $url_path;
        $width = 5;
        $html = '';
        $page = max($page, 1);
        self::$url .= '?page=';
        $url_params = trim(trim($url_params), '&?');
        $total = 0;
        if (isset($count['folders']) && isset($count['files']) &&
            is_numeric($count['folders']) &&
            is_numeric($count['files'])
        ) {
            $total = intval($count['folders']) + intval($count['files']);
        } elseif (is_numeric($count)) {
            $total = $count;
        }
        if ($total) {
            $pages = ceil($total / $limit);
            if ($pages > 1) {
                $page = intval($page);
                $html = '<ul class="pager">';
                if (is_numeric($count)) {
                    $html .= '<li>'._w('Total:').' <em>'.number_format((float)$count, 0, '.', ' ').'</em></li>';
                }
                if (!empty($count['folders'])) {
                    $html .= '<li>'._w('Folders:').' <em>'.number_format((float)$count['folders'], 0, '.', ' ').'</em></li>';
                }
                if (!empty($count['files'])) {
                    $html .= '<li>'._w('Files:').' <em>'.number_format((float)$count['files'], 0, '.', ' ').'</em></li>';
                }

                $html .= ' <span>'._w('Page:').'</span></li>';

                if ($page > 1) {
                    $title = _w('prev');
                    $url = self::$url.($page - 1).(strlen($url_params) > 0 ? '&'.$url_params : '');
                    $html .= "<li><a href='{$url}' title='{$title}'><i class='icon10 larr'></i>{$title}</a></li>";
                }

                $html .= self::item(1, $page, $url_params);
                for ($i = 2; $i < $pages; $i++) {
                    if (abs($page - $i) < $width ||
                        ($page - $i == $width && $i == 2) ||
                        ($i - $page == $width && $i == $pages - 1)
                    ) {
                        $html .= self::item($i, $page, $url_params);
                    } elseif (strpos(strrev($html), '...') != 5) { // 5 = strlen('</li>')
                        $html .= '<li>...</li>';
                    }
                }

                $html .= self::item($pages, $page, $url_params);

                if ($page < $pages) {
                    $title = _w('next');
                    $url = self::$url.($page + 1).(strlen($url_params) > 0 ? '&'.$url_params : '');
                    $html .= "<li><a href='{$url}' title='{$title}'>{$title}<i class='icon10 rarr'></i></a></li>";
                }
            }
        }

        self::$url = $old_url_path;

        return $html;
    }

    /**
     * @param $i
     * @param $page
     * @param $url_params
     * @return string
     */
    protected static function item($i, $page, $url_params = '')
    {
        if ($page != $i) {
            $url = self::$url.$i.(strlen($url_params) > 0 ? '&'.$url_params : '');
            return "<li><a href='{$url}'>".number_format((float)$i, 0, '.', ' ')."</a></li>";
        } else {
            return "<li class='selected'>".number_format((float)$i, 0, '.', ' ')."</li>";
        }
    }

    /**
     *
     * Get data array from product collection
     * @param string $hash selector hash
     * @param int $offset optional parameter
     * @param int $limit optional parameter
     * @param array $options optional parameter product collection. Here you can add sorting. $options['order_by'][field=> order]
     * or many variants $options['order_by'][0][field=> order] etc
     *
     * If $limit is omitted but $offset is not than $offset is interpreted as 'limit' and method returns first 'limit' items
     * If $limit and $offset are omitted that method returns first 500 items
     *
     * @return array
     */
    public function products($hash = '', $offset = null, $limit = null, $options = array())
    {
        if (is_array($offset)) {
            $options = $offset;
            $offset = null;
        }
        if (is_array($limit)) {
            $options = $limit;
            $limit = null;
        }

        $order_by = null;

        if (!empty($options['order_by'])) {
            $order_by = $options['order_by'];
            unset($options['order_by']);
        }

        $collection = new shopProductsCollection($hash, $options);

        /**
         * Output products in the smarty template. After the collection is initialized.
         *
         * @param object $collection shopProductsCollection
         *
         * @event view_products.before
         */
        $is_from_template = waConfig::get('is_template');
        waConfig::set('is_template', null);
        wa('shop')->event('view_products.before', $collection);
        waConfig::set('is_template', $is_from_template);

        if ($order_by && is_array($order_by)) {
            if (isset($order_by[0])) {
                foreach ($order_by as $order) {
                    reset($order);
                    $collection->orderBy(key($order), current($order));
                }
            } else {
                reset($order_by);
                $collection->orderBy(key($order_by), current($order_by));
            }
        }

        if (!$limit && $offset) {
            $limit = $offset;
            $offset = 0;
        }
        if (!$offset && !$limit) {
            $offset = 0;
            $limit = 500;
        }

        $products = $collection->getProducts(ifset($options, 'fields', '*'), $offset, $limit, true);

        /**
         * Output products in the smarty template. Result from the product collection
         *
         * @param array $products
         *
         * @event view_products.after
         */
        $is_from_template = waConfig::get('is_template');
        waConfig::set('is_template', null);
        wa('shop')->event('view_products.after', $products);
        waConfig::set('is_template', $is_from_template);

        return $products;

    }

    public function productsCount($hash = '')
    {
        $collection = new shopProductsCollection($hash);
        return $collection->count();
    }

    /**
     * Alias for products('set/<set_id>')
     * @param int $set_id
     * @param int $offset
     * @param int $limit
     * @param array $options
     * @return array
     */
    public function productSet($set_id, $offset = null, $limit = null, $options = array())
    {
        $cache_key = null;
        if (!$offset && !$limit && !$options && ($cache = $this->wa()->getCache())) {
            $route = $this->getRoute();
            $cache_key = 'set_'.$set_id.'_'.str_replace('/', '_', waRouting::clearUrl($route['domain'].'/'.$route['url'])).'_'.$this->currency();
            $products = $cache->get($cache_key, 'sets');
            if ($products !== null) {
                return $products;
            }
        }
        $products = $this->products('set/'.$set_id, $offset, $limit, $options);
        if (!empty($cache)) {
            $cache->set($cache_key, $products, 1200, 'sets');
        }
        return $products;
    }

    /**
     * Includes `list-thumbs.html` sub-template of given theme, rendering given list of products
     * as returned by $wa->shop->products() or $wa->shop->productSet() mehtods.
     *
     * @param array $products
     * @param string $theme_id
     * @since 8.11
     */
    public function getListThumbsTemplate($products, $theme_id = null)
    {
        try {
            if (!$theme_id) {
                $theme_id = waRequest::getTheme();
            }
            $theme = new waTheme($theme_id, $this->app_id);
            $view = wa($this->app_id)->getView();
            if(!$view->setThemeTemplate($theme, 'list-thumbs.html')) {
                return '';
            }
            $view->assign([
                'products' => $products,
            ]);

            return $view->fetch('list-thumbs.html');
        } catch (Exception $e) {
            if (waSystemConfig::isDebug() && wa()->getUser()->get('is_user') > 0) {
                return $e->getMessage()."\n<br><br>\n<pre>".$e->getFullTraceAsString()."</pre>";
            }
        }
    }

    /**
     * @param array $product_ids
     * @param bool $apply_rounding
     * @return array
     */
    public function skus($product_ids, $apply_rounding = true)
    {
        if (!$product_ids) {
            return array();
        }
        $skus_model = new shopProductSkusModel();
        $rows = $skus_model->select('*')->where('product_id IN (i:ids)', array('ids' => $product_ids))->order('sort')->fetchAll();
        $apply_rounding && shopRounding::roundSkus($rows);

        $skus = array();
        foreach ($rows as $row) {
            $skus[$row['product_id']][] = $row;
        }
        return $skus;
    }

    public function images($product_ids, $size = array(), $absolute = false)
    {
        if (!$product_ids) {
            return array();
        }

        $absolute = $absolute && !$this->cdn;
        $product_ids = array_map('intval', (array)$product_ids);
        $product_images_model = new shopProductImagesModel();
        $result = $product_images_model->getImages($product_ids, $size, 'product_id', $absolute);

        if ($this->cdn) {
            foreach ($result as &$images) {
                foreach ($images as &$image) {
                    foreach ($image as $k => &$v) {
                        if (substr($k, 0, 4) == 'url_') {
                            $v = $this->cdn.$v;
                        }
                    }
                }
            }

            unset($image, $images, $v);
        }
        return $result;
    }

    public function settings($name = null, $escape = true)
    {
        if (is_object($name) || is_array($name)) {
            return null;
        }
        $result = $this->shopConfig()->getGeneralSettings((string)$name);
        return $escape && !is_array($result) ? htmlspecialchars($result) : $result;
    }

    public function sortUrl($sort, $name, $active_sort = null)
    {
        if ($active_sort === null) {
            $active_sort = waRequest::get('sort');
        }
        $inverted = in_array($sort, array('rating', 'create_datetime', 'total_sales', 'count', 'stock'));
        $data = waRequest::get();
        $data['sort'] = $sort;
        if ($sort == $active_sort) {
            $data['order'] = waRequest::get('order', 'asc', 'string') == 'asc' ? 'desc' : 'asc';
        } else {
            $data['order'] = $inverted ? 'desc' : 'asc';
        }
        $html = '<a href="?'.http_build_query($data).'">'.$name.($sort == $active_sort ? ' <i class="sort-'.($data['order'] == 'asc' ? 'desc' : 'asc').'"></i>' : '').'</a>';
        return $html;
    }

    /**
     * @param bool $frontend
     * @return array
     */
    public function stocks($frontend = true)
    {
        return shopHelper::getStocks($frontend);
    }

    /**
     * @param array $products
     * @param bool $public_only
     * @return array
     * @throws waDbException
     * @throws waException
     */
    public function features(&$products, $public_only = true)
    {
        if (!$products) {
            return array();
        }
        $product_features_model = new shopProductFeaturesModel();
        $rows = $product_features_model->getByField([
            'product_id' => array_keys($products),
            'sku_id'     => null
        ], true);

        $selectable_product_ids = array();
        foreach ($products as $p) {
            if ($p['sku_type']) {
                $selectable_product_ids[] = $p['id'];
            }
        }
        if ($selectable_product_ids) {
            $sql = <<<SQL
SELECT DISTINCT pf.*
FROM shop_product_features pf
JOIN shop_product_features_selectable pfs
ON
  (pf.product_id = pfs.product_id)
  AND
  (pf.feature_id = pfs.feature_id)
WHERE
  pf.sku_id IS NOT NULL
  AND
  pf.product_id IN (i:ids)
SQL;
            $rows = array_merge($rows, $product_features_model->query($sql, array('ids' => $selectable_product_ids))->fetchAll());
        }
        if (!$rows) {
            return array();
        }
        $tmp = array();
        foreach ($rows as $row) {
            $tmp[$row['feature_id']] = true;
        }
        $feature_model = new shopFeatureModel();
        $sql = "SELECT * FROM `shop_feature` WHERE id IN (i:ids) OR type = 'divider'";
        $features = $feature_model->query($sql, array('ids' => array_keys($tmp)))->fetchAll('id');

        foreach ($features as $fid => $feature) {
            /** отбираем ID фич у которых есть родитель */
            if (null !== $feature['parent_id']) {
                $parents[$feature['parent_id']] = true;
            }
        }
        if (isset($parents)) {
            $sql = "SELECT * FROM `shop_feature` WHERE id IN (i:ids)";
            $parent_features = $feature_model->query($sql, ['ids' => array_keys($parents)])->fetchAll('id');
            $features = $features + $parent_features;
            unset($parent_features);
        }

        $type_values = [];
        $product_features = [];
        foreach ($rows as $row) {
            if (empty($features[$row['feature_id']])) {
                continue;
            }
            $f = $features[$row['feature_id']];
            if ($public_only && $f['status'] != 'public') {
                unset($features[$row['feature_id']]);
                continue;
            }
            $type = preg_replace('/\..*$/', '', $f['type']);
            if ($type != shopFeatureModel::TYPE_BOOLEAN && $type != shopFeatureModel::TYPE_DIVIDER) {
                $type_values[$type][$row['feature_value_id']] = $row['feature_value_id'];
            }
            if ($f['multiple']) {
                $product_features[$row['product_id']][$f['id']][$row['feature_value_id']] = $row['feature_value_id'];
            } else {
                $product_features[$row['product_id']][$f['id']] = $row['feature_value_id'];
            }
        }
        foreach ($type_values as $type => $value_ids) {
            $model = shopFeatureModel::getValuesModel($type);
            if ($model) {
                $type_values[$type] = $model->getValues('id', $value_ids);
            }
        }

        $tmp = array();
        foreach ($products as $p) {
            $tmp[(int)$p['type_id']] = true;
        }

        // get type features for correct sort
        $type_features_model = new shopTypeFeaturesModel();
        $rows = $type_features_model
            ->select('type_id, feature_id')
            ->where('type_id IN (i:type_id)', array('type_id' => array_keys($tmp)))
            ->order('sort')
            ->fetchAll();

        $type_features = array();
        foreach ($rows as $row) {
            $type_features[$row['type_id']][] = $row['feature_id'];
        }

        foreach ($products as &$p) {
            if (!empty($type_features[$p['type_id']])) {
                foreach ($type_features[$p['type_id']] as $feature_id) {
                    if (empty($features[$feature_id])) {
                        continue;
                    }
                    $f = $features[$feature_id];
                    $type = preg_replace('/\..*$/', '', $f['type']);

                    if ($type == shopFeatureModel::TYPE_BOOLEAN) {
                        if (!isset($product_features[$p['id']][$feature_id])) {
                            continue 1;
                        }
                        $value_ids = $product_features[$p['id']][$feature_id];
                        /** @var shopFeatureValuesBooleanModel $model */
                        $model = shopFeatureModel::getValuesModel($type);
                        $values = $model->getValues('id', $value_ids);
                        $p['features'][$f['code']] = reset($values);
                    } elseif ($type == shopFeatureModel::TYPE_DIVIDER) {
                        /** @var shopFeatureValuesDividerModel $model */
                        $values = shopFeatureModel::getValuesModel($type)->getValues('id', $feature_id);
                        $p['features'][$f['code']] = reset($values);
                    } elseif ($type == shopFeatureModel::TYPE_2D || $type == shopFeatureModel::TYPE_3D) {
                        $sub_type = preg_replace('#^(?:\S*\.)(double|dimension)(?:\.\S*)?#', '$1', $f['type']);
                        switch ($sub_type) {
                            case shopFeatureModel::TYPE_DIMENSION:
                            case shopFeatureModel::TYPE_DOUBLE:
                                $val_obj = [];
                                foreach ($features as $id => $param) {
                                    if ($param['parent_id'] === $feature_id) {
                                        if (isset($product_features[$p['id']][$param['id']])) {
                                            /** если у продукта есть такая фича */
                                            $feature_value_id = $product_features[$p['id']][$param['id']];
                                            if (isset($type_values[$sub_type][$param['id']][$feature_value_id])) {
                                                $val_obj[] = $type_values[$sub_type][$param['id']][$feature_value_id];
                                            }
                                        }
                                    }
                                }
                                if ($val_obj) {
                                    $p['features'][$f['code']] = implode('&times;', $val_obj);
                                }
                                break;
                            default:
                                /** no operation */
                        }
                    } else {
                        if (!isset($product_features[$p['id']][$feature_id])) {
                            continue 1;
                        }
                        $value_ids = $product_features[$p['id']][$feature_id];
                        if (is_array($value_ids)) {
                            $p['features'][$f['code']] = array();
                            //keep feature values order
                            foreach ($type_values[$type][$feature_id] as $v_id => $v_value) {
                                if (in_array($v_id, $value_ids)) {
                                    $p['features'][$f['code']][$v_id] = $v_value;
                                }
                            }
                        } elseif (isset($type_values[$type][$feature_id][$value_ids])) {
                            $p['features'][$f['code']] = $type_values[$type][$feature_id][$value_ids];
                        }
                    }
                }
            }
        }
        unset($p);

        // return features (key code)
        $result = array();
        foreach ($features as $f) {
            if (!$public_only || $f['status'] == 'public') {
                $result[$f['code']] = $f;
            }
        }

        /**
         * Output features in the smarty template.
         *
         * @param array $result features array
         *
         * @event view_features
         */
        $is_from_template = waConfig::get('is_template');
        waConfig::set('is_template', null);
        wa('shop')->event('view_features', $result);
        waConfig::set('is_template', $is_from_template);

        return $result;
    }

    /**
     * @param int $limit
     * @return array
     * @throws waDbException
     * @throws waException
     */
    public function reviews($limit = 10)
    {
        $allowed_types_id = array();
        $type_id = waRequest::param('type_id');
        if (is_array($type_id)) {
            foreach ($type_id as $key => $id) {
                if (filter_var($id, FILTER_VALIDATE_INT) === false) {
                    unset($type_id[$key]);
                }
            }
            $allowed_types_id = $type_id;
        }
        $product_reviews_model = new shopProductReviewsModel();
        $reviews = $product_reviews_model->getList('*,product,contact', array(
            'where'  => array(
                'review_id' => 0,
                'status'    => shopProductReviewsModel::STATUS_PUBLISHED
            ),
            'limit'  => $limit,
            'escape' => true,
            'allowed_types_id' => $allowed_types_id
        ));


        /**
         * Output reviews in the smarty template.
         *
         * @param array $reviews
         *
         * @event view_reviews
         */
        $is_from_template = waConfig::get('is_template');
        waConfig::set('is_template', null);
        wa('shop')->event('view_reviews', $reviews);
        waConfig::set('is_template', $is_from_template);

        return $reviews;
    }

    public function customer()
    {
        if (!$this->shop_customer) {
            $this->shop_customer = new shopCustomer(wa()->getUser()->getId());
        }
        return $this->shop_customer;
    }

    public function cart()
    {
        if (!$this->shop_cart) {
            $this->shop_cart = new shopCart();
        }
        return $this->shop_cart;
    }

    public function primaryCurrency()
    {
        if (!$this->shop_currency) {
            $this->shop_currency = $this->shopConfig()->getCurrency(true);
        }
        return $this->shop_currency;
    }

    public function currency($full_info = false)
    {
        $currency = $this->shopConfig()->getCurrency(false);
        if ($full_info) {
            return waCurrency::getInfo($currency);
        } else {
            return $currency;
        }
    }

    public function currencies()
    {
        return $this->shopConfig()->getCurrencies();
    }

    public function productUrl($product, $key = '', $route_params = array())
    {
        $route_params['product_url'] = $product['url'];
        if (isset($product['category_url'])) {
            $route_params['category_url'] = $product['category_url'];
        } else {
            $route_params['category_url'] = '';
        }
        return wa()->getRouteUrl('shop/frontend/product'.ucfirst($key), $route_params);
    }

    public function badgeHtml($code)
    {
        return shopHelper::getBadgeHtml($code);
    }

    public function getImageBadgeHtml($image)
    {
        return shopHelper::getImageBadgeHtml($image);
    }

    /**
     * @param $product
     * @param $size
     * @param array $attributes
     * @return string
     * @deprecated
     */
    public function getProductImgHtml($product, $size, $attributes = array())
    {
        return $this->productImgHtml($product, $size, $attributes);
    }


    public function productImgHtml($product, $size, $attributes = array())
    {
        if (empty($product['image_id'])) {
            if (!empty($attributes['default'])) {
                return '<img src="'.$attributes['default'].'">';
            }
            return '';
        }

        if (!empty($product['product_id'])) {
            $product['id'] = $product['product_id'];
        }

        if (!isset($product['image_filename'])) {
            $p = $this->wa()->getView()->getVars('product');
            /**
             * @var array $p
             */
            if ($p && ($p['id'] == $product['id'])) {
                $product['image_filename'] = $p['images'][$product['image_id']]['filename'];
            }
        }

        if (!isset($product['image_description'])) {
            if (empty($p)) {
                $p = $this->wa()->getView()->getVars('product');
            }
            if ($p && ($p['id'] == $product['id'])) {
                $product['image_description'] = $p['images'][$product['image_id']]['description'];
            }
        }

        return $this->imgHtml(array(
            'id'          => $product['image_id'],
            'product_id'  => $product['id'],
            'filename'    => isset($product['image_filename']) ? $product['image_filename'] : null,
            'ext'         => $product['ext'],
            'description' => !empty($product['image_description']) ? $product['image_description'] : (isset($product['name']) ? $product['name'] : null),
        ), $size, $attributes);
    }

    public function imgHtml($image, $size, $attributes = array())
    {
        if (!$image || empty($image['id'])) {
            if (!empty($attributes['default'])) {
                return '<img src="'.$attributes['default'].'">';
            }
            return '';
        }
        if (!empty($image['description']) && !isset($attributes['alt'])) {
            $attributes['alt'] = htmlspecialchars($image['description']);
        }
        if (!empty($image['description']) && !isset($attributes['title'])) {
            $attributes['title'] = htmlspecialchars($image['description']);
        }
        $html = '<img';
        foreach ($attributes as $k => $v) {
            if ($k != 'default') {
                $html .= ' '.$k.'="'.$v.'"';
            }
        }
        $html .= ' src="'.$this->imgUrl($image, $size).'">';
        return $html;
    }

    public function productImgUrl($product, $size)
    {
        if (empty($product['image_id'])) {
            return '';
        }
        if (!empty($product['product_id'])) {
            $product['id'] = $product['product_id'];
        }
        if (!isset($product['image_filename'])) {
            $p = $this->wa()->getView()->getVars('product');
            /**
             * @var array $p
             */
            if ($p && ($p['id'] == $product['id'])) {
                $product['image_filename'] = $p['images'][$product['image_id']]['filename'];
                $product['image_description'] = $p['images'][$product['image_id']]['description'];
            }
        }
        return $this->imgUrl(array(
            'id'         => $product['image_id'],
            'product_id' => $product['id'],
            'filename'   => $product['image_filename'],
            'ext'        => $product['ext']
        ), $size);
    }

    /**
     * @param array|string $image
     * @param string $size
     * @param bool $absolute
     * @return string
     * @throws waException
     */
    public function imgUrl($image, $size, $absolute = false)
    {
        $url = '';

        if (is_string($image)) {
            if (parse_url($image, PHP_URL_SCHEME)) {
                //If is url set back
                $url = $image;
            } else {

                $data_path = realpath(waConfig::get('wa_path_data').'/public');
                $root_path = wa()->getConfig()->getRootPath();

                $root_url = wa()->getConfig()->getRootUrl();
                $root_url_len = strlen($root_url);

                // 2 cases:
                //  - start with root_url (framework could be settled in folder)
                //  - not started with root_url

                if (substr($image, 0, $root_url_len) === $root_url) {
                    $path = $root_path . '/' . substr($image, $root_url_len);
                } else {
                    $path = $root_path . '/' . ltrim($image, '/');
                }

                $path = realpath($path);

                //Check if the file is in the public folder
                if (strpos($path, $data_path) === 0 && file_exists($path)) {
                    $file_info = pathinfo($path);
                    $thumb_path = $file_info['dirname'].'/'.$file_info['filename'].'.'.$size.'.'.$file_info['extension'];

                    if (!file_exists($thumb_path)) {
                        try {
                            $thumb = shopImage::generateThumb($path, $size);
                            $thumb->save($thumb_path);
                        } catch (waException $e) {
                            return '';
                        }
                    }

                    $path = $thumb_path;
                }

                if (!$absolute) {
                    // after realpath we has OS specific dir separators
                    $path = str_replace($root_path.DIRECTORY_SEPARATOR, '', $path);
                    $path = wa()->getRootUrl().$path;
                    if ($this->cdn) {
                        $path = $this->cdn.$path;
                    }
                    // after realpath we has OS specific dir separators
                    $url = str_replace(DIRECTORY_SEPARATOR, '/', $path);
                }
            }
        } elseif ($image && !empty($image['id'])) {
            $url = $this->cdn.shopImage::getUrl($image, $size, $absolute && !$this->cdn);
        }

        return $url;
    }


    /**
     * @param $id
     * @return shopProduct
     * @throws waException
     */
    public function product($id)
    {
        /**
         * Output product in the smarty template.
         * @param $id
         * @event view_product
         */
        $product = new shopProduct($id, true);

        $is_from_template = waConfig::get('is_template');
        waConfig::set('is_template', null);
        wa('shop')->event('view_product', $product);
        waConfig::set('is_template', $is_from_template);

        return $product;
    }

    /**
     * If $ available_only is equal to self::CROSS_SELLING_IN_STOCK then you need to use stocks settings
     *
     * @param $product_id
     * @param int $limit
     * @param bool|string $available_only If the string, then the value is $ key.
     * @param bool $key
     * @return array|mixed
     */
    public function crossSelling($product_id, $limit = 5, $available_only = false, $key = false)
    {
        if (!is_numeric($limit)) {
            $key = $available_only;
            $available_only = $limit;
            $limit = 5;
        }
        if (is_string($available_only) && $available_only !== self::CROSS_SELLING_IN_STOCK) {
            $key = $available_only;
            $available_only = false;
        }
        if (!$product_id) {
            return array();
        }
        $params = [
            'limit'          => $limit,
            'available_only' => $available_only
        ];

        if (is_array($product_id)) {
            if ($key) {
                foreach ($product_id as &$r) {
                    $r = $r[$key];
                }
                unset($r);
            }
            $product_model = new shopProductModel();
            $sql = "SELECT p.* FROM  shop_product p JOIN shop_type t ON p.type_id = t.id
            WHERE (p.id IN (i:id)) AND (t.cross_selling !=  '' OR p.cross_selling = 2)
            ORDER BY RAND() LIMIT 1";
            $p = $product_model->query($sql, array('id' => $product_id))->fetchAssoc();
            $p = new shopProduct($p);
        } else {
            $params['exclude'] = is_array($key) ? $key : array();
            $p = new shopProduct($product_id);
        }

        if ($available_only == self::CROSS_SELLING_IN_STOCK) {
            unset($params['available_only']);
            $result = call_user_func_array([$p, 'crossSellingInStock'], $params);
        } else {
            $result = call_user_func_array([$p, 'crossSelling'], $params);
        }

        if (is_array($result) && is_array($product_id)) {
            foreach ($result as $p_id => $pr) {
                if (in_array($p_id, $product_id)) {
                    unset($result[$p_id]);
                }
            }
        }
        return $result;
    }

    public function __get($name)
    {
        if ($name == 'cart') {
            return $this->cart();
        } elseif ($name == 'customer') {
            return $this->customer();
        }

        return parent::__get($name);
    }

    public function compare()
    {
        $compare = waRequest::cookie('shop_compare', array(), waRequest::TYPE_ARRAY_INT);
        if ($compare) {
            return $this->products('id/'.implode(',', $compare));
        }
        return null;
    }

    /**
     * @param $id
     * @return array|null
     */
    public function category($id)
    {
        $category_model = new shopCategoryModel();
        $category = $category_model->getById($id);
        if ($category) {
            $route = $this->getRoute();
            if (!$route) {
                $category['subcategories'] = array();
            } else {
                $category['subcategories'] = $category_model->getSubcategories($category, $route['domain'].'/'.$route['url']);
                $category_url = wa()->getRouteUrl('shop/frontend/category', array('category_url' => '%CATEGORY_URL%'));
                foreach ($category['subcategories'] as &$sc) {
                    $sc['url'] = str_replace('%CATEGORY_URL%', isset($route['url_type']) && $route['url_type'] == 1 ? $sc['url'] : $sc['full_url'], $category_url);
                }
                unset($sc);
            }

            $category_params_model = new shopCategoryParamsModel();
            $category['params'] = $category_params_model->get($category['id']);

            if ($this->config('can_use_smarty') && $category['description']) {
                $category['description'] = wa()->getView()->fetch('string:'.$category['description']);
            }
        }
        /**
         * Output single category in the smarty template.
         *
         * @param array $category
         *
         * @event view_category
         */
        $is_from_template = waConfig::get('is_template');
        waConfig::set('is_template', null);
        wa('shop')->event('view_category', $category);
        waConfig::set('is_template', $is_from_template);
        return $category;
    }

    public function categoryUrl($c)
    {
        return wa()->getRouteUrl('shop/frontend/category', array('category_url' => waRequest::param('url_type') == 1 ? $c['url'] : $c['full_url']));
    }

    protected function getRoute($domain = null, $route_url = null)
    {
        $current_domain = wa()->getRouting()->getDomain(null, true);
        $current_route = wa()->getRouting()->getRoute();
        if (wa()->getApp() != 'shop' || ($domain && $current_domain != $domain) || ($route_url && $route_url != $current_route['url'])) {
            $routes = wa()->getRouting()->getByApp('shop');
            if (!$routes) {
                return false;
            }
            if (!$domain) {
                $domain = $current_domain;
                if (!isset($routes[$domain])) {
                    $domain = key($routes);
                }
            }
            if (!isset($routes[$domain])) {
                return false;
            }
        } else {
            $current_route['domain'] = $current_domain;
            return $current_route;
        }
        if ($route_url) {
            $route = false;
            foreach ($routes[$domain] as $r) {
                if ($r['url'] === $route_url) {
                    $route = $r;
                    break;
                }
            }
        } else {
            $route = end($routes[$domain]);
        }
        if ($route) {
            $route['domain'] = $domain;
        }
        return $route;
    }

    /**
     * Whether Shop app has at least one frontend route.
     * @return bool
     * @since 8.15.0
     */
    public function hasRoutes()
    {
        $routing = wa()->getRouting()->getByApp('shop');
        return !empty($routing);
    }

    public function categories($id = 0, $depth = null, $tree = false, $params = false, $route = null)
    {
        if ($id === true) {
            $id = 0;
            $tree = true;
        }
        $category_model = new shopCategoryModel();
        if ($route && !is_array($route)) {
            $route = trim($route);
            if (substr($route, -1) !== '*') {
                $route = rtrim($route, '/');
                $route .= '/*';
            }
            $route = explode('/', $route, 2);
            $route = $this->getRoute($route[0], isset($route[1]) ? $route[1] : null);
        }
        if (!$route) {
            $route = $this->getRoute();
        }
        if (!$route) {
            return array();
        }
        $cats = $category_model->getTree($id, $depth, false, $route['domain'].'/'.$route['url']);
        $url = wa()->getRouteUrl('shop/frontend/category', array('category_url' => '%CATEGORY_URL%'), false, $route['domain'], $route['url']);

        foreach ($cats as $c_id => $c) {
            if ($c['parent_id'] && $c['id'] != $id && !isset($cats[$c['parent_id']])) {
                unset($cats[$c_id]);
            } else {
                $cats[$c_id]['url'] = str_replace('%CATEGORY_URL%', isset($route['url_type']) && $route['url_type'] == 1 ? $c['url'] : $c['full_url'], $url);
                $cats[$c_id]['name'] = htmlspecialchars($cats[$c_id]['name']);
            }
        }

        if ($id && isset($cats[$id])) {
            unset($cats[$id]);
        }

        if ($params) {
            $category_params_model = new shopCategoryParamsModel();
            $rows = $category_params_model->getByField('category_id', array_keys($cats), true);
            foreach ($rows as $row) {
                $cats[$row['category_id']]['params'][$row['name']] = $row['value'];
            }
        }

        $event_params = [
            'categories' => &$cats,
            'tree'       => $tree,
        ];


        /**
         * Output categories in the smarty template. If the tree, then before formatting
         *
         * @param array $categories
         * @param bool $tree
         *
         * @event view_categories
         */
        $is_from_template = waConfig::get('is_template');
        waConfig::set('is_template', null);
        wa('shop')->event('view_categories', $event_params);
        waConfig::set('is_template', $is_from_template);

        if ($tree) {
            $stack = array();
            $result = array();
            foreach ($cats as $c) {
                $c['childs'] = array();

                // Number of stack items
                $l = count($stack);

                // Check if we're dealing with different levels
                while ($l > 0 && $stack[$l - 1]['depth'] >= $c['depth']) {
                    array_pop($stack);
                    $l--;
                }

                // Stack is empty (we are inspecting the root)
                if ($l == 0) {
                    // Assigning the root node
                    $i = count($result);
                    $result[$i] = $c;
                    $stack[] = &$result[$i];
                } else {
                    // Add node to parent
                    $i = count($stack[$l - 1]['childs']);
                    $stack[$l - 1]['childs'][$i] = $c;
                    $stack[] = &$stack[$l - 1]['childs'][$i];
                }
            }
            return $result;
        } else {
            return $cats;
        }
    }

    public function tags($limit = 50)
    {
        if ($limit == 50 && ($cache = $this->wa()->getCache())) {
            $tags = $cache->get('tags');
            if ($tags !== null) {
                return $tags;
            }
        }
        $tag_model = new shopTagModel();
        $tags = $tag_model->getCloud(null, $limit);
        if (!empty($cache)) {
            $cache->set('tags', $tags, 7200);
        }

        /**
         * Output tags in the smarty template
         *
         * @param array $tags
         * @event view_tags
         */
        $is_from_template = waConfig::get('is_template');
        waConfig::set('is_template', null);
        wa('shop')->event('view_tags', $tags);
        waConfig::set('is_template', $is_from_template);

        return $tags;
    }

    public function payment()
    {
        $plugin_model = new shopPluginModel();
        return $plugin_model->listPlugins('payment');
    }

    public function orderId($id)
    {
        return shopHelper::encodeOrderId($id);
    }

    /**
     * @param $url_or_class
     * @param bool $is_url if need use relative URL or any other strange path
     * @return string
     */
    public function icon16($url_or_class, $is_url = false)
    {
        // Hack to hide icon that is common for all customers
        $app_icon = '/wa-apps/shop/img/shop16.png';
        if (substr($url_or_class, -strlen($app_icon)) == $app_icon) {
            return '';
        }

        $url_or_class = htmlspecialchars($url_or_class, ENT_QUOTES, 'utf-8');

        if ($is_url || substr($url_or_class, 0, 7) == 'http://' || substr($url_or_class, 0, 8) == 'https://'
            || substr($url_or_class, 0, 2) == '//') {
            return '<i class="icon16" style="background-image:url('.$url_or_class.')"></i>';
        } else {
            return '<i class="icon16 '.$url_or_class.'"></i>';
        }
    }

    public function ratingHtml($rating, $size = 10, $show_when_zero = false)
    {
        return shopHelper::getRatingHtml($rating, $size, $show_when_zero);
    }

    public function shipping()
    {
        $plugin_model = new shopPluginModel();
        return $plugin_model->listPlugins('shipping');
    }

    /**
     * @param $product_id
     * @return array - return array ids in comparison or array()
     */
    public function inComparison($product_id = null)
    {
        $ids = waRequest::cookie('shop_compare', array(), waRequest::TYPE_ARRAY_INT);
        if (!$product_id) {
            return $ids;
        }
        return in_array($product_id, $ids) ? $ids : array();
    }

    /**
     * @param int $abtest_id id in shop_abtest
     * @return string 'A', or 'B', or etc. from existing codes in shop_abtest_variants
     */
    public function ABtest($abtest_id)
    {
        if (empty($abtest_id) || !wa_is_int($abtest_id)) {
            return null;
        }

        static $cache = array();
        if (array_key_exists($abtest_id, $cache)) {
            return $cache[$abtest_id];
        }

        static $abtest_variants_model = null;
        if (!$abtest_variants_model) {
            $abtest_variants_model = new shopAbtestVariantsModel();
        }

        // Existing variant in cookie?
        $variant_id = waRequest::cookie('waabt'.$abtest_id);
        if ($variant_id) {
            $v = $abtest_variants_model->getById($variant_id);
            if (!$v || $v['abtest_id'] != $abtest_id) {
                $variant_id = null;
            } else {
                $cache[$abtest_id] = $v['code'];
                return $cache[$abtest_id];
            }
        }

        // Choose A/B test option randomly
        $rows = $abtest_variants_model->getByField('abtest_id', $abtest_id, 'id');
        if (!$rows) {
            $cache[$abtest_id] = null;
            return $cache[$abtest_id];
        }
        $v = $rows[array_rand($rows)];
        wa()->getResponse()->setCookie('waabt'.$abtest_id, $v['id']);
        $cache[$abtest_id] = $v['code'];
        return $cache[$abtest_id];
    }

    /**
     * @param string|array $type_or_ids
     * @param null|string $size
     * @return array
     */
    public function promos($type_or_ids = 'link', $size = null)
    {
        $list_params = [
            'status'        => shopPromoModel::STATUS_ACTIVE,
            'ignore_paused' => true,
            'with_rules'    => true,
            'rule_type'     => 'banner',
        ];

        $promo_model = new shopPromoModel();
        if (is_array($type_or_ids)) {
            $list_params['id'] = $type_or_ids;
        } else {
            $storefront = shopPromoRoutesModel::FLAG_ALL;
            $domain = wa()->getRouting()->getDomain();
            if ($domain) {
                $routing_url = wa()->getRouting()->getRootUrl();
                $storefront = $domain.($routing_url ? '/'.$routing_url : '');
            }
            $list_params['storefront'] = $storefront;
        }

        $promos = $promo_model->getList($list_params);
        foreach ($promos as $promo) {
            $promo_banner = null;
            unset($promo['name']);

            foreach ($promo['rules'] as $rule_id => $rule) {
                if ($rule['rule_type'] !== 'banner') {
                    unset($promo['rules'][$rule_id]);
                    continue;
                }

                foreach ($rule['rule_params']['banners'] as $i => $banner) {
                    if (!is_array($type_or_ids) && $banner['type'] != $type_or_ids) {
                        unset($rule['rule_params']['banners'][$i]);
                    }
                }

                if (empty($rule['rule_params']['banners'])) {
                    unset($promo['rules'][$rule_id]);
                    continue;
                }

                $promo_banner = array_shift($rule['rule_params']['banners']);
                break;
            }

            if (empty($promo['rules']) || empty($promo_banner)) {
                unset($promos[$promo['id']]);
                continue;
            }

            $promo_banner['image'] = $this->cdn.shopPromoBannerHelper::getPromoBannerUrl($promo['id'], $promo_banner['image_filename'], $size);
            $promos[$promo['id']] = array_merge($promo_banner, $promo);
        }

        /**
         * Output promo in the smarty template
         *
         * @param array $promos
         * @event view_promos
         */
        $is_from_template = waConfig::get('is_template');
        waConfig::set('is_template', null);
        wa('shop')->event('view_promos', $promos);
        waConfig::set('is_template', $is_from_template);

        return $promos;
    }

    /**
     * @return shopCheckoutViewHelper
     */
    public function checkout()
    {
        return new shopCheckoutViewHelper();
    }

    public function schedule()
    {
        $schedule = $this->shopConfig()->getStorefrontSchedule();
        $schedule['current_week'] = $schedule['week'];

        $monday_time = strtotime('monday this week');
        $sunday_time = strtotime('sunday this week');

        foreach ($schedule['extra_weekends'] as $extra_weekend) {
            $time = strtotime($extra_weekend);
            if ($time >= $monday_time && $time <= $sunday_time) {
                $day_number = date('N', $time);
                $schedule['current_week'][$day_number]['work'] = false;
            }
        }

        foreach ($schedule['extra_workdays'] as $extra_workday) {
            $time = strtotime($extra_workday['date']);
            if ($time >= $monday_time && $time <= $sunday_time) {
                $day_number = date('N', $time);
                $schedule['current_week'][$day_number]['work'] = true;
                $schedule['current_week'][$day_number] = array_merge($schedule['current_week'][$day_number], $extra_workday);
            }
        }

        return $schedule;
    }

    /**
     * @param int $contact_id
     * @param string $type
     * @param array $options Extra options, reserved for future
     * @return string
     */
    public function backendContactUrl($contact_id, $type = 'default', $options = array())
    {
        if (wa()->getEnv() !== 'backend') {
            return '';
        }

        $is_template = waConfig::get('is_template');
        waConfig::set('is_template', null);

        if (wa()->appExists('crm')) {
            if ($type === 'edit') {
                $url = wa('crm')->getAppUrl('crm')."contact/{$contact_id}/edit/";
            } elseif ($type === 'delete') {
                $url = wa('crm')->getAppUrl('crm')."contact/{$contact_id}/delete/";
            } elseif ($type === 'info') {
                $url = wa('crm')->getAppUrl('crm')."contact/{$contact_id}/info/";
            } else {
                $url = wa('crm')->getAppUrl('crm')."contact/{$contact_id}/";
            }
        } else {
            if ($type === 'edit') {
                $url = wa()->getAppUrl('contacts')."#/contact/{$contact_id}/contact/edit/";
            } elseif ($type === 'delete') {
                $url = wa()->getAppUrl('contacts')."#/contact/{$contact_id}/contact/delete/";
            } else {
                $url = wa()->getAppUrl('contacts')."#/contact/{$contact_id}/";
            }
        }

        waConfig::set('is_template', $is_template);

        return $url;
    }

    /**
     * @return shopConfig
     */
    protected function shopConfig()
    {
        if (!$this->shop_config) {
            $this->shop_config = $this->wa()->getConfig();
        }
        return $this->shop_config;
    }
}
