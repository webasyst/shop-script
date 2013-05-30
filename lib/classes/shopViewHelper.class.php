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

    public function sortUrl($sort, $name)
    {

        $inverted = in_array($sort, array('rating', 'create_datetime', 'total_sales', 'count'));
        $html = '<a href="?sort='.$sort.'&order=';
        if ($sort == waRequest::get('sort')) {
            $order = waRequest::get('order') == 'asc' ? 'desc' : 'asc';
        } else {
            $order = $inverted ? 'desc' : 'asc';
        }
        $html .= $order.'">'.$name.($sort == waRequest::get('sort') ? ' <i class="sort-'.($order == 'asc' ? 'desc' : 'asc').'"></i>' : '').'</a>';
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


    public function crossSelling($product_id, $limit = 5, $key = false)
    {
        if (!is_numeric($limit)) {
            $key = $limit;
            $limit = 5;
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
            $result = $p->crossSelling($limit);
            foreach ($result as $p_id => $pr) {
                if (in_array($p_id, $product_id)) {
                    unset($result[$p_id]);
                }
            }
            return $result;
        } else {
            $p = new shopProduct($product_id);
            return $p->crossSelling($limit);
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

        $category['subcategories'] = $category_model->getSubcategories($category, true);
        $category_url = wa()->getRouteUrl('shop/frontend/category', array('category_url' => '%CATEGORY_URL%'));
        foreach ($category['subcategories'] as &$sc) {
            $sc['url'] = str_replace('%CATEGORY_URL%', waRequest::param('url_type') == 1 ? $sc['url'] : $sc['full_url'], $category_url);
        }
        unset($sc);

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

    public function categories($id = 0, $depth = null, $tree = false, $params = false)
    {
        if ($id === true) {
            $id = 0;
            $tree = true;
        }
        $route = wa()->getRouting()->getDomain(null, true).'/'.wa()->getRouting()->getRoute('url');
        $category_model = new shopCategoryModel();
        $cats = $category_model->getTree($id, $depth, false, array("route IS NULL OR route = '".$category_model->escape($route)."'"));
        $url = $this->wa->getRouteUrl('shop/frontend/category', array('category_url' => '%CATEGORY_URL%'));
        $hidden = array();
        foreach ($cats as $c_id => $c) {
            if ($c['status'] && !isset($hidden[$c['parent_id']])) {
                $cats[$c_id]['url'] = str_replace('%CATEGORY_URL%', waRequest::param('url_type') == 1 ? $c['url'] : $c['full_url'], $url);
                $cats[$c_id]['name'] = htmlspecialchars($cats[$c_id]['name']);
            } else {
                $hidden[$c_id] = 1;
                unset($cats[$c_id]);
            }
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

    public function tags()
    {
        $tag_model = new shopTagModel();
        return $tag_model->getCloud();
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
