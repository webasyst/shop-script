<?php

/**
 * Class shopViewHelper
 * @property-read shopCart $cart
 * @property-read shopCustomer $customer
 */
class shopViewHelper extends waAppViewHelper
{
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
     */
    public function features(&$products, $public_only = true)
    {
        if (!$products) {
            return array();
        }
        $product_features_model = new shopProductFeaturesModel();
        $rows = $product_features_model->getByField(array(
            'product_id' => array_keys($products),
            'sku_id'     => null
        ), true);

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

        $type_values = $product_features = array();
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
                    if (isset($product_features[$p['id']][$feature_id])) {
                        $value_ids = $product_features[$p['id']][$feature_id];
                        if ($type == shopFeatureModel::TYPE_BOOLEAN || $type == shopFeatureModel::TYPE_DIVIDER) {
                            /**
                             * @var shopFeatureValuesBooleanModel|shopFeatureValuesDividerModel $model
                             */
                            $model = shopFeatureModel::getValuesModel($type);
                            $values = $model->getValues('id', $value_ids);
                            $p['features'][$f['code']] = reset($values);
                        } else {
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
                    } elseif ($type == shopFeatureModel::TYPE_DIVIDER) {
                        $p['features'][$f['code']] = '';
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

    public function reviews($limit = 10)
    {
        $product_reviews_model = new shopProductReviewsModel();
        $reviews = $product_reviews_model->getList('*,product,contact', array(
            'where'  => array(
                'review_id' => 0,
                'status'    => shopProductReviewsModel::STATUS_PUBLISHED
            ),
            'limit'  => $limit,
            'escape' => true
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
     */
    public function imgUrl($image, $size, $absolute = false)
    {
        $url = '';

        if (is_string($image)) {
            if (parse_url($image, PHP_URL_SCHEME)) {
                //If is url set back
                $url = $image;
            } else {
                $data_path = realpath(waConfig::get('wa_path_data').DIRECTORY_SEPARATOR.'public');
                $root_path = wa()->getConfig()->getRootPath();

                //Replace url separator to directory separator
                $path = str_replace('/', DIRECTORY_SEPARATOR, $image);

                //Check if this path start
                if (substr($image, 0, 1) === DIRECTORY_SEPARATOR) {

                    //If it doesn't start with root_path before adding it
                    if (strpos($image, $root_path) !== 0) {
                        $path = $root_path.$image;
                    }
                } else {
                    //If relative reference, then do absolute
                    $path = $root_path.DIRECTORY_SEPARATOR.$image;
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
                    $path = str_replace($root_path.DIRECTORY_SEPARATOR, '', $path);
                    $path = wa()->getRootUrl().$path;
                    if ($this->cdn) {
                        $path = $this->cdn.$path;
                    }
                    $url = str_replace(DIRECTORY_SEPARATOR, '/', $path);
                }
            }
        } elseif ($image && !empty($image['id'])) {
            $url = $this->cdn.shopImage::getUrl($image, $size, $absolute && !$this->cdn);
        }

        return $url;
    }


    public function product($id)
    {
        return new shopProduct($id, true);
    }

    public function crossSelling($product_id, $limit = 5, $available_only = false, $key = false)
    {
        if (!is_numeric($limit)) {
            $key = $available_only;
            $available_only = $limit;
            $limit = 5;
        }
        if (is_string($available_only)) {
            $key = $available_only;
            $available_only = false;
        }
        if (!$product_id) {
            return array();
        }
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
            $result = $p->crossSelling($limit, $available_only);
            foreach ($result as $p_id => $pr) {
                if (in_array($p_id, $product_id)) {
                    unset($result[$p_id]);
                }
            }
            return $result;
        } else {
            $p = new shopProduct($product_id);
            return $p->crossSelling($limit, $available_only, is_array($key) ? $key : array());
        }
    }

    public function __get($name)
    {
        if ($name == 'cart') {
            return $this->cart();
        } elseif ($name == 'customer') {
            return $this->customer();
        }
        return null;
    }

    public function compare()
    {
        $compare = waRequest::cookie('shop_compare', array(), waRequest::TYPE_ARRAY_INT);
        if ($compare) {
            return $this->products('id/'.implode(',', $compare));
        }
        return null;
    }

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
            if ($domain && !isset($routes[$domain])) {
                return false;
            }
            $domain = $current_domain;
            if (!isset($routes[$domain])) {
                $domain = key($routes);
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

    public function categories($id = 0, $depth = null, $tree = false, $params = false, $route = null)
    {
        if ($id === true) {
            $id = 0;
            $tree = true;
        }
        $category_model = new shopCategoryModel();
        if ($route && !is_array($route)) {
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
     * @param string $type_or_ids
     * @param string $size
     * @return array
     */
    public function promos($type_or_ids = 'link', $size = null)
    {
        $promo_model = new shopPromoModel();
        if (is_array($type_or_ids)) {
            $prs = $promo_model->getById($type_or_ids);
            $promos = array();
            foreach ($type_or_ids as $id) {
                if (!empty($prs[$id])) {
                    $promos[$id] = $prs[$id];
                }
            }
        } else {
            $storefront = '%all%';
            $domain = wa()->getRouting()->getDomain();
            if ($domain) {
                $routing_url = wa()->getRouting()->getRootUrl();
                $storefront = $domain.($routing_url ? '/'.$routing_url : '');
            }
            $promos = $promo_model->getByStorefront($storefront, $type_or_ids, true);
        }

        foreach ($promos as &$p) {
            $p['image'] = $this->cdn.shopHelper::getPromoImageUrl($p['id'], $p['ext'], $size);
        }
        unset($p);


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
