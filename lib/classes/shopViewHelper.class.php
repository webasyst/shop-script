<?php

class shopViewHelper extends waAppViewHelper
{
    protected $_cart;
    protected $_customer;

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
        return $this->products('set/'.$set_id, $offset, $limit, $options);
    }

    public function settings($name, $escape = true)
    {
        $result = wa('shop')->getConfig()->getGeneralSettings($name);
        return $escape ? htmlspecialchars($result) : $result;
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
            $data['order'] = waRequest::get('order') == 'asc' ? 'desc' : 'asc';
        } else {
            $data['order'] = $inverted ? 'desc' : 'asc';
        }
        $html = '<a href="?'.http_build_query($data).'">'.$name.($sort == $active_sort ? ' <i class="sort-'.($data['order'] == 'asc' ? 'desc' : 'asc').'"></i>' : '').'</a>';
        return $html;
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

    public function currency($full_info = false)
    {
        $currency = $this->wa->getConfig()->getCurrency(false);
        if ($full_info) {
            return waCurrency::getInfo($currency);
        } else {
            return $currency;
        }
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
        if (!empty($product['image_desc']) && !isset($attributes['alt'])) {
            $attributes['alt'] = htmlspecialchars($product['image_desc']);
        }
        if (!empty($product['image_desc']) && !isset($attributes['title'])) {
            $attributes['title'] = htmlspecialchars($product['image_desc']);
        }
        $html = '<img';
        foreach ($attributes as $k => $v) {
            if ($k != 'default') {
                $html .= ' '.$k.'="'.$v.'"';
            }
        }
        $html .= ' src="'.shopImage::getUrl(array(
            'product_id' => $product['id'], 'id' => $product['image_id'], 'ext' => $product['ext']), $size).'">';
        return $html;
    }

    public function productImgUrl($product, $size)
    {
        if (!$product['image_id']) {
            return '';
        }
        return shopImage::getUrl(array('product_id' => $product['id'], 'id' => $product['image_id'], 'ext' => $product['ext']), $size);
    }

    public function product($id)
    {
        return new shopProduct($id);
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
            return $p->crossSelling($limit, $available_only);
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
                while($l > 0 && $stack[$l - 1]['depth'] >= $c['depth']) {
                    array_pop($stack);
                    $l--;
                }

                // Stack is empty (we are inspecting the root)
                if ($l == 0) {
                    // Assigning the root node
                    $i = count($result);
                    $result[$i] = $c;
                    $stack[] = & $result[$i];
                } else {
                    // Add node to parent
                    $i = count($stack[$l - 1]['childs']);
                    $stack[$l - 1]['childs'][$i] = $c;
                    $stack[] = & $stack[$l - 1]['childs'][$i];
                }
            }
            return $result;
        } else {
            return $cats;
        }
    }

    public function tags($limit = 50)
    {
        $tag_model = new shopTagModel();
        return $tag_model->getCloud(null, $limit);
    }

    public function payment()
    {
        return array();
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
}
