<?php

class shopViewHelper extends waAppViewHelper
{
    protected $_cart;
    protected $_customer;
    protected $_currency;

    /**
     *
     * Get data array from product collection
     * @param string $hash selector hash
     * @param int $offset optional parameter
     * @param int $limit optional parameter
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
        $collection = new shopProductsCollection($hash, $options);
        if (!$limit && $offset) {
            $limit = $offset;
            $offset = 0;
        }
        if (!$offset && !$limit) {
            $offset = 0;
            $limit = 500;
        }
        return $collection->getProducts('*', $offset, $limit, true);
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
        if (!$offset && !$limit && !$options && ($cache = $this->wa->getCache())) {
            $route = $this->getRoute();
            $cache_key = 'set_'.$set_id.'_'.str_replace('/', '_', waRouting::clearUrl($route['domain'].'/'.$route['url']));
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
        $result = wa('shop')->getConfig()->getGeneralSettings((string)$name);
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
     * @return array
     */
    public function stocks()
    {
        $stock_model = new shopStockModel();
        return $stock_model->getAll('id');
    }

    /**
     * @param array $products
     * @return array
     */
    public function features(&$products)
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
            $sql = 'SELECT pf.* FROM shop_product_features pf
                    JOIN shop_product_features_selectable pfs ON pf.product_id = pfs.product_id AND pf.feature_id = pfs.feature_id
                    WHERE pf.sku_id IS NOT NULL AND pf.product_id IN (i:ids)';
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
        $sql = 'SELECT * FROM '.$feature_model->getTableName()." WHERE id IN (i:ids) OR type = 'divider'";
        $features = $feature_model->query($sql, array('ids' => array_keys($tmp)))->fetchAll('id');

        $type_values = $product_features = array();
        foreach ($rows as $row) {
            if (empty($features[$row['feature_id']])) {
                continue;
            }
            $f = $features[$row['feature_id']];
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
            $type_values[$type] = $model->getValues('id', $value_ids);
        }

        $tmp = array();
        foreach ($products as $p) {
            $tmp[(int)$p['type_id']] = true;
        }

        // get type features for correct sort
        $type_features_model = new shopTypeFeaturesModel();
        $sql = "SELECT type_id, feature_id FROM ".$type_features_model->getTableName()."
                WHERE type_id IN (i:type_id) ORDER BY sort";
        $rows = $type_features_model->query($sql, array('type_id' => array_keys($tmp)))->fetchAll();
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
                                foreach ($value_ids as $v_id) {
                                    if (isset($type_values[$type][$feature_id][$v_id])) {
                                        $p['features'][$f['code']][$v_id] = $type_values[$type][$feature_id][$v_id];
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
            $result[$f['code']] = $f;
        }
        return $result;
    }

    public function reviews($limit = 10)
    {
        $product_reviews_model = new shopProductReviewsModel();
        return $product_reviews_model->getList('*,product,contact', array(
            'where'  => array(
                'review_id' => 0,
                'status'    => shopProductReviewsModel::STATUS_PUBLISHED
            ),
            'limit'  => $limit,
            'escape' => true
        ));
    }

    public function customer()
    {
        if (!$this->_customer) {
            $this->_customer = new shopCustomer(wa()->getUser()->getId());
        }
        return $this->_customer;
    }

    public function cart()
    {
        if (!$this->_cart) {
            $this->_cart = new shopCart();
        }
        return $this->_cart;
    }

    public function primaryCurrency()
    {
        if (!$this->_currency) {
            $this->_currency = $this->wa->getConfig()->getCurrency(true);
        }
        return $this->_currency;
    }

    public function currency($full_info = false)
    {
        $currency = $this->wa->getConfig()->getCurrency(false);
        if ($full_info) {
            return waCurrency::getInfo($currency);
        } else {
            return $currency;
        }
    }

    public function currencies()
    {
        return $this->wa->getConfig()->getCurrencies();
    }

    public function productUrl($product, $key = '', $route_params = array())
    {
        $route_params['product_url'] = $product['url'];
        if (isset($product['category_url'])) {
            $route_params['category_url'] = $product['category_url'];
        } else {
            $route_params['category_url'] = '';
        }
        return $this->wa->getRouteUrl('shop/frontend/product'.ucfirst($key), $route_params);
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
        if (!$product['image_id']) {
            if (!empty($attributes['default'])) {
                return '<img src="'.$attributes['default'].'">';
            }
            return '';
        }

        if (!isset($product['image_filename'])) {
            $p = $this->wa->getView()->getVars('product');
            if ($p && ($p['id'] == $product['id'])) {
                $product['image_filename'] = $p['images'][$product['image_id']]['filename'];
            }
        }

        return $this->imgHtml(array(
            'id'         => $product['image_id'],
            'product_id' => $product['id'],
            'filename'   => $product['image_filename'],
            'ext'        => $product['ext']
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
        if (!isset($product['image_filename'])) {
            $p = $this->wa->getView()->getVars('product');
            if ($p && ($p['id'] == $product['id'])) {
                $product['image_filename'] = $p['images'][$product['image_id']]['filename'];
            }
        }
        return $this->imgUrl(array(
            'id'         => $product['image_id'],
            'product_id' => $product['id'],
            'filename'   => $product['image_filename'],
            'ext'        => $product['ext']
        ), $size);
    }

    public function imgUrl($image, $size, $absolute = false)
    {
        if (!$image || empty($image['id'])) {
            return '';
        }
        return $this->cdn.shopImage::getUrl($image, $size, $absolute && !$this->cdn);
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

            if ($this->wa->getConfig()->getOption('can_use_smarty') && $category['description']) {
                $category['description'] = wa()->getView()->fetch('string:'.$category['description']);
            }
        }
        return $category;
    }

    public function categoryUrl($c)
    {
        return $this->wa->getRouteUrl('shop/frontend/category', array('category_url' => waRequest::param('url_type') == 1 ? $c['url'] : $c['full_url']));
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
        $url = $this->wa->getRouteUrl('shop/frontend/category', array('category_url' => '%CATEGORY_URL%'), false, $route['domain'], $route['url']);
        $hidden = array();
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
        if ($limit == 50 && ($cache = $this->wa->getCache())) {
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

    public function icon16($url_or_class)
    {
        // Hack to hide icon that is common for all customers
        $app_icon = '/wa-apps/shop/img/shop16.png';
        if (substr($url_or_class, -strlen($app_icon)) == $app_icon) {
            return '';
        }

        if (substr($url_or_class, 0, 7) == 'http://') {
            return '<i class="icon16" style="background-image:url('.htmlspecialchars($url_or_class).')"></i>';
        } else {
            return '<i class="icon16 '.htmlspecialchars($url_or_class).'"></i>';
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
     *
     */
    public function promos($type_or_ids = 'link')
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
            $storefront = wa()->getRouting()->getDomain();
            if (!$storefront) {
                $storefront = '%all%';
            }
            $promos = $promo_model->getByStorefront($storefront, $type_or_ids);
        }

        foreach ($promos as &$p) {
            $p['image'] = $this->cdn.shopHelper::getPromoImageUrl($p['id'], $p['ext']);
        }
        unset($p);

        return $promos;
    }
}

