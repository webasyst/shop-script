<?php

/**
 * @example
 * $product = new shopProduct(123);
 * $product->name = "Product 123";
 * // or $product['name'] = "Product 123"
 * $product->save();
 * // UPDATE shop_product SET name = "Product 123" WHERE id = 123;
 *
 * @property int $id
 * @property string $name
 * @property string $summary
 * @property string $meta_title
 * @property string $meta_keywords
 * @property string $meta_description
 * @property string $description
 * @property int $contact_id
 * @property waContact $contact
 * @property string $create_datetime
 * @property string $edit_datetime
 * @property int $status
 * @property int $sku_id
 * @property int $sku_type
 * @property string $url
 * @property int $sort
 * @property float $price
 * @property float $compare_price
 * @property float $base_price_selectable
 * @property float $compare_price_selectable
 * @property float $purchase_price_selectable
 * @property string $currency
 * @property-read string $currency_html
 * @property float $min_price
 * @property float $max_price
 * @property int $tax_id
 * @property int|null $count
 * @property mixed[string] $video
 * @property string $video_url
 * @property-read int $video['product_id']
 * @property-read string[string] $video['url']
 * @property-read int[string] $video['width']
 * @property-read int[string] $video['height']
 * @property-read string[string] $video['images']
 *
 *
 * @property-read array $pages
 *
 * @property int $type_id
 * @property array $type
 * @property array $features
 * @example
 * // read product feature
 * $product->features['feature_code'];
 * @property array $features_selectable
 * @property array $skus
 * @property-read int $sku_count
 * @property array $categories
 * @property int $category_id
 * @property-read array $canonical_category available primary category at current storefront (see ->getCanonicalCategory)
 * @property-read string $category_url url
 * @property array $sets
 * @property array $tags
 * @property array $params
 * @property array $og
 * @property array $images
 * @property array $next_forecast
 * @property array $reviews
 * @property array $sku_features
 */
class shopProduct implements ArrayAccess
{
    // ->getNextForecast() treats this number of days as "supply will never end"
    const MAX_FORECAST_DAYS = 3652;

    protected $data = array();
    protected $is_dirty = array();
    protected static $data_storages = array();
    protected $is_frontend = false;
    protected $options = [];

    /**
     * @var shopProductModel
     */
    protected $model;

    /**
     * Creates a new product object or a product object corresponding to existing product.
     *
     * @param int|array $data Product id or product data array
     * @param boolean|array $options If the value is boolean, then the old $ is_frontend is passed.
     */
    public function __construct($data = array(), $options = false)
    {
        if (is_bool($options)) {
            $is_frontend = $options;
        } else {
            $is_frontend = isset($options['is_frontend']) ? $options['is_frontend'] : false;
        }

        $this->is_frontend = $is_frontend;
        $this->options = $options;
        $this->model = new shopProductModel();

        if ($data instanceof shopProduct) {
            $this->data = $data->data;
            $this->is_frontend = func_num_args() > 1 ? $is_frontend : $data->is_frontend;
            $this->is_dirty = $data->is_dirty;
        } elseif (is_array($data)) {
            $this->data = $data;
        } elseif ($data) {
            $this->data = $this->model->getById($data);
        }

        $this->preparePromoPrices();

        if ($is_frontend) {
            $tmp = array(&$this->data);
            shopRounding::roundProducts($tmp);
        }

        if (isset($options['round_currency'])) {
            $tmp = array(&$this->data);
            shopRounding::roundProducts($tmp, $options['round_currency']);
        }
    }

    /**
     * @return shopPromoProductPrices
     */
    protected function promoProductPrices()
    {
        static $promo_product_prices_class;

        if (empty($promo_product_prices_class)) {
            $promo_prices_model = new shopProductPromoPriceTmpModel();
            $options = [
                'model' => $promo_prices_model,
            ];
            if (!empty($this->options['storefront_context']) && is_scalar($this->options['storefront_context'])) {
                $options['storefront'] = (string)$this->options['storefront_context'];
            }
            $promo_product_prices_class = new shopPromoProductPrices($options);
        }

        return $promo_product_prices_class;
    }

    protected function preparePromoPrices()
    {
        $tmp = array(&$this->data);
        $this->promoProductPrices()->workupPromoProducts($tmp);
    }

    public function isFrontend()
    {
        return $this->is_frontend;
    }

    /**
     * @param $key
     * @return mixed|shopProductStorageInterface|null
     * @throws waException
     */
    private function getStorage($key)
    {
        if (!self::$data_storages) {
            self::$data_storages = array(
                'tags'                => true,
                'features_selectable' => 'shopProductFeaturesSelectableModel', //should be before skus
                'skus'                => true, //should be before features
                'features'            => true,
                'params'              => true,
                'categories'          => 'shopCategoryProductsModel',
                'sets'                => 'shopSetProductsModel',
                'og'                  => true,
            );
        }
        if (isset(self::$data_storages[$key])) {
            $storage = self::$data_storages[$key];
            if ($storage === true) {
                $storage = "shopProduct".ucfirst($key)."Model";
                $obj = new $storage();
                if (!($obj instanceof shopProductStorageInterface)) {
                    throw new waException($storage.' must implement shopProductStorageInterface');
                }
                return self::$data_storages[$key] = $obj;
            } elseif (is_string($storage)) {
                return self::$data_storages[$key] = new $storage();
            } elseif (is_object(self::$data_storages[$key])) {
                return self::$data_storages[$key];
            }
        }
        return null;
    }

    /**
     * If $storefront is unknown, the link to the first settlement is returned
     * @param string $storefront
     * @param bool $set_canonical
     * @return null|string
     */
    public function getProductUrl($storefront = null, $set_canonical = false, $absolute = true)
    {
        $storefront_domain = null;
        $storefront_route_url = null;
        $storefront_route = null;

        $routing = wa()->getRouting();

        $route = null;

        if ($storefront === true) {
            $storefront = preg_replace('@^https?://@', '', $routing->getUrl('shop/frontend', true));
        }

        if (isset($storefront) && $storefront !== 'backend') {
            $storefront = rtrim($storefront, '/');
            foreach ($routing->getByApp('shop') as $domain => $routes) {
                foreach ($routes as $r) {
                    if (!isset($r['url'])) {
                        continue;
                    }
                    $st = rtrim(rtrim($domain, '/').'/'.$r['url'], '/.*');
                    if ($st == $storefront) {
                        $storefront_route_url = $r['url'];
                        $storefront_domain = $domain;
                        $storefront_route = $r;
                        break 2;
                    }
                }
            }
        }

        if (!$storefront_domain && !$storefront_route_url && !wa()->getRouting()->getRoute()) {
            return '';
        }

        $url_params = array(
            'product_url' => $this->url,
        );

        if ($set_canonical) {
            if ($category_url = $this->getCategoryUrl($storefront_route)) {
                $url_params['category_url'] = $category_url;
            }
        }

        return $routing->getUrl('shop/frontend/product', $url_params, $absolute, $storefront_domain, $storefront_route_url);
    }

    /**
     * Important: this method also filter array of product's categories
     * @param array $route
     * @return array Primary category data if it available at $route
     */
    public function getCanonicalCategory($route = null)
    {
        $category = null;

        if ($this->categories) {
            $categories = $this->getCategoriesByRoute($route);
            $this->categories = $categories;

            if ($this->category_id && isset($categories[$this->category_id])) {
                $category = $categories[$this->category_id];
            } else {
                $this->category_id = null;
            }
        }

        if ($category) {
            $this['category_url'] = (ifset($route['url_type']) == 1) ? $category['url'] : $category['full_url'];
        } else {
            $this['category_url'] = null;
        }

        return $category;
    }

    /**
     * Searches for all categories that fit current route
     *
     * @param null $route
     * @return array
     */
    protected function getCategoriesByRoute($route = null)
    {
        $category = null;
        $categories = $this->categories;

        if ($categories) {
            if ($route === null) {
                $route = wa()->getRouting()->getRoute();
                $route['full_url'] = wa()->getRouting()->getDomain(null, true).'/'.$route['url'];
            } elseif (!is_array($route)) {
                $route = array(
                    'url'      => $route,
                    'full_url' => $route,
                );
            }

            // check categories, only keeping those enabled for current storefront
            $category_routes_model = new shopCategoryRoutesModel();
            $routes = $category_routes_model->getRoutes(array_keys($categories));
            foreach ($categories as $c) {
                if (isset($routes[$c['id']]) && !in_array($route['full_url'], $routes[$c['id']])) {
                    unset($categories[$c['id']]);
                }
            }
        }

        return $categories;
    }

    public function getCategoryUrl($route = null)
    {
        $category = $this->canonical_category;
        $category_url = '';
        if ($category) {
            if ($route) {
                $url_type = ifset($route['url_type']);
            } else {
                $url_type = wa()->getRouting()->getRoute('url_type');
            }
            $category_url = ($url_type == 1) ? $category['url'] : $category['full_url'];
        }
        return $category_url;
    }

    /**
     * @param bool $product_link
     * @param array $route
     * @return array
     */
    public function getBreadcrumbs($product_link = false, $route = null)
    {
        $breadcrumbs = array();

        if (empty($route)) {
            $route = wa('shop')->getRouting()->getRoute();
        }
        $short_url_type = ifset($route['url_type']) == 1;
        $routing = wa()->getRouting();

        //Search canonical or first suitable category
        $categories = $this->getCategoriesByRoute();
        if ($this->category_id && isset($categories[$this->category_id])) {
            $category = $categories[$this->category_id];
        } else {
            $category = reset($categories);
        }

        if ($category) {
            $category_model = new shopCategoryModel();
            $path = $category_model->getPath($category['id']);
            if ($path) {
                $path = array_reverse($path);
            } else {
                $path = array();
            }

            $path[] = $category;
            foreach ($path as $row) {
                $url_params = array(
                    'category_url' => $short_url_type ? $row['url'] : $row['full_url'],
                );
                $breadcrumbs[$row['id']] = array(
                    'url'  => $routing->getUrl('/frontend/category', $url_params),
                    'name' => $row['name'],
                );
            }
        }

        if ($product_link) {
            $url_params = array(
                'product_url' => $this->url,
            );
            if ($category) {
                $url_params['category_url'] = $short_url_type ? $category['url'] : $category['full_url'];
            }
            $breadcrumbs[0] = array(
                'url'  => $routing->getUrl('/frontend/product', $url_params),
                'name' => $this->name,
            );
        }

        return $breadcrumbs;
    }

    /**
     * Returns product id.
     *
     * @return int
     */
    public function getId()
    {
        return $this->getData('id');
    }

    /**
     * Returns information about product's images.
     *
     * @param string|array $sizes Image size id or array of size ids.
     *     Acceptable values: 'big', 'default', 'thumb', 'crop', 'crop_small'. If empty, 'crop' is assumed by default.
     * @param bool $absolute Whether absolute or relative image URLs must be returned.
     *
     * @return array Array containing sub-arrays of individual product images
     * @see shopConfig::$image_sizes — actual image size values correspondings to size ids
     *
     */
    public function getImages($sizes = array(), $absolute = false)
    {
        if ($this->getId()) {
            $images_model = new shopProductImagesModel();
            if (empty($sizes)) {
                $sizes = 'crop';
            }
            $images = $images_model->getImages($this->getId(), $sizes, 'id', $absolute);
            foreach ($images as &$image) {
                if ($image['description'] == '') {
                    $image['description'] = $this->name;
                }
                unset($image);
            }
            return $images;
        } else {
            return array();
        }
    }

    public function getVideo($sizes = array(), $absolute = false)
    {
        if (!$this['video_url']) {
            return array();
        }

        $video = array(
            'product_id' => $this->getId(),
            'orig_url'   => $this['video_url'],
            'url'        => $this['video_url'],
            'width'      => '',
            'height'     => '',
            'images'     => array()
        );

        $domain = parse_url($video['url'], PHP_URL_HOST);
        $url = '';
        switch ($domain) {
            case 'youtube.com':
                $video['width'] = 560;
                $video['height'] = 315;
                // https://www.youtube.com/watch?v=...&t=77
                if (preg_match('/(\?|&)v=([^&]+)/i', $video['url'], $match)) {
                    $url = '//www.youtube.com/embed/'.$match[2];
                }
                if ($url && preg_match('/(\?|&)t=(\d+)/i', $video['url'], $match)) {
                    $url .= '?start='.$match[2];
                }
                break;
            case 'youtu.be':
                $video['width'] = 560;
                $video['height'] = 315;
                // https://youtu.be/...?t=77
                if (preg_match('/youtu.be\/([^&\?]+)/i', $video['url'], $match)) {
                    $url = '//www.youtube.com/embed/'.$match[1];
                }
                if ($url && preg_match('/(\?|&)t=(\d+)/i', $video['url'], $match)) {
                    $url .= '?start='.$match[2];
                }
                break;
            case 'vimeo.com':
                $video['width'] = 600;
                $video['height'] = 338;
                if (preg_match('/vimeo.com\/([0-9]+)/i', $video['url'], $match)) {
                    $url = '//player.vimeo.com/video/'.$match[1];
                }
                break;
        }

        if ($url && shopVideo::checkVideoThumb($this->getId(), $video['url'])) {
            if (empty($sizes)) {
                $sizes = '96x96';
            }
            foreach ((array)$sizes as $k => $size) {
                $video['images'][$k] = shopVideo::getThumbUrl($this->getId(), $size, $absolute);
            }
        }
        $video['orig_url'] = $video['url'];
        $video['url'] = $url;
        return $video;
    }

    /**
     * Returns product's subpages.
     *
     * @return array
     */
    public function getPages()
    {
        $product_pages_model = new shopProductPagesModel();
        return $product_pages_model->getPages($this->getId(), true);
    }

    /**
     * Returns the contact who has created the product.
     *
     * @return waContact
     */
    public function getContact()
    {
        return new waContact($this->contact_id);
    }

    public function getCurrencyHtml()
    {
        $info = waCurrency::getInfo($this->currency);
        return ifset($info['sign_html'], $info['sign']);
    }

    /**
     * @return mixed
     * @throws waException
     */
    public function getSkus()
    {
        $data = $this->getStorage('skus')->getData($this);
        $this->promoProductPrices()->workupPromoSkus($data, [$this->getId() => $this->data]);

        if ($this->is_frontend) {
            shopRounding::roundSkus($data, array($this->data));
        }

        if (isset($this->options['round_currency'])) {
            shopRounding::roundSkus($data, array($this->data), $this->options['round_currency']);
        }

        return $data;
    }

    /**
     * @return mixed
     * @throws waException
     */
    public function getListFeatures()
    {
        return $this->getStorage('features')->getListFeatures($this->type_id, false);
    }

    /**
     * @param null $status
     * @return mixed
     * @throws waException
     */
    public function getFeatures($status = null)
    {
        switch ($status) {
            case 'public':
                $public_only = true;
                break;
            case 'all':
                $public_only = false;
                break;
            default:
                $public_only = $this->is_frontend;
        }
        return $this->getStorage('features')->getData($this, $public_only);
    }

    /**
     * Saves product data to database.
     *
     * @param array $data
     * @param bool $validate
     * @param array $errors
     * @return bool Whether saved successfully
     * @throws waException
     */
    public function save($data = array(), $validate = true, &$errors = array())
    {
        if ($this->is_frontend) {
            throw new waException('Unable to save shopProduct: data is converted for frontend');
        }

        $result = false;
        $id = $this->getId();
        $search = new shopIndexSearch();

        foreach ($data as $name => $value) {
            // name have to be not empty
            if ($name == 'name' && !$value) {
                $value = _w('New product');
            }

            // url have to be not empty
            if ($name == 'url' && !$value && $id) {
                $value = $id;
            }

            $this->__set($name, $value);
        }
        if ($this->is_dirty) {

            if (!$this->preSave($errors)) {
                return false;
            }

            $product = array();
            $id_changed = !empty($this->is_dirty['id']);
            foreach ($this->is_dirty as $field => $v) {
                if ($this->model->fieldExists($field)) {
                    $product[$field] = $this->data[$field];
                    if ($id && ($field == 'video_url')) {
                        waFiles::delete(shopVideo::getPath($id));
                        waFiles::delete(shopVideo::getThumbsPath($id));
                    }
                    unset($this->is_dirty[$field]);
                }
            }

            if ($id && !$id_changed) {
                if (!isset($product['edit_datetime'])) {
                    $product['edit_datetime'] = date('Y-m-d H:i:s');
                }
                if (isset($product['type_id'])) {
                    $this->model->updateType($id, $product['type_id']);
                    unset($product['type_id']);
                }
                if ($this->model->updateById($id, $product)) {
                    $this->saveData($errors);
                    $search->onUpdate($id);
                    $this->is_dirty = array();

                    $result = true;
                }
            } else {
                if (!isset($product['contact_id'])) {
                    $product['contact_id'] = wa()->getUser()->getId();
                }
                if (!isset($product['create_datetime'])) {
                    $product['create_datetime'] = date('Y-m-d H:i:s');
                }
                if (!isset($product['currency'])) {
                    $product['currency'] = wa('shop')->getConfig()->getCurrency();
                }
                if (!isset($product['sku_count'])) {
                    if (isset($this->data['skus'])) {
                        $product['sku_count'] = count($this->data['skus']);
                    } else {
                        $product['sku_count'] = 0;
                    }
                }

                if ($id = $this->model->insert($product)) {
                    $this->data = $this->model->getById($id) + $this->data;

                    // update empty url by ID
                    if (empty($this->data['url'])) {
                        $this->data['url'] = $id;
                        $this->model->updateById($id, array('url' => $this->data['url']));
                    }

                    $this->saveData();
                    $search->onAdd($id);
                    $this->is_dirty = array();

                    if (!empty($this->data['type_id'])) {
                        $type_model = new shopTypeModel();
                        // increment on +1
                        $type_model->incCounters(
                            array(
                                $this->data['type_id'] => 1
                            )
                        );
                    }

                    $result = true;
                }
            }

        } else {
            $result = true;
        }

        $params = array(
            'data'     => $this->getData(),
            'instance' => & $this,
        );

        /**
         * Plugin hook for handling product entry saving event
         * @event product_save
         *
         * @param array [string]mixed $params
         * @param array [string][string]mixed $params['data'] raw product data fields (see shop_product table description and related storages)
         * @param array [string][string]int $data['data']['id'] product ID
         * @param array [string]shopProduct $params['instance'] current shopProduct entry instance (avoid recursion)
         * @return void
         */
        wa('shop')->event('product_save', $params);
        return $result;
    }

    /**
     * Check for errors before saving.
     * @param $errors
     * @return bool
     */
    protected function preSave(&$errors)
    {
        $text_error = null;
        $validate = true;
        $params = array(
            'data'     => $this->getData(),
            'dirty'    => &$this->is_dirty,
            'new_data' => &$this->data,
            'instance' => &$this,
        );
        $pre_save = wa('shop')->event('product_presave', $params);

        if ($pre_save && is_array($pre_save)) {
            foreach ($pre_save as $plugin) {
                if (ifset($plugin, 'result', null) === false) {
                    $validate = false;
                    if (ifset($plugin, 'error', null)) {
                        $text_error[] = $plugin['error'];
                    }
                }
            }
        }

        if ($text_error) {
            $errors = implode(' ', $text_error);
        }

        return $validate;
    }

    /**
     * @param array $errors
     * @throws waException
     */
    protected function saveData(&$errors = array())
    {
        #fix empty SKUs
        if (!$this->sku_count && empty($this->is_dirty) && empty($this->data['skus'])) {
            $this->setData('skus', [-1 => ['name' => '']]);
        }
        if ($this->is_dirty) {
            $this->getStorage(null);
            //save external data in right storage order
            foreach (array_keys(self::$data_storages) as $field) {
                if (($field == 'skus') && !$this->sku_count && empty($this->is_dirty[$field]) && empty($this->data['skus'])) {
                    #fix empty SKUs on missed virtual SKUs
                    $this->setData('skus', [-1 => ['name' => '']]);
                }
                if (!empty($this->is_dirty[$field]) && ($storage = $this->getStorage($field))) {
                    $this->data[$field] = $storage->setData($this, $raw = $this->data[$field]);
                }
            }
        }
    }

    /**
     * Executed on attempts to retrieve product property values.
     * @see http://www.php.net/manual/en/language.oop5.overloading.php
     *
     * @param string $name Property name
     * @return mixed|null Property value or null on failure
     * @throws waException
     */
    public function __get($name)
    {
        if (isset($this->data[$name])) {
            return $this->data[$name];
        }

        $method = "get".preg_replace_callback('@(^|_)([a-z])@', array(__CLASS__, 'camelCase'), $name);
        if (method_exists($this, $method)) {
            $this->data[$name] = $this->$method();
            return $this->data[$name];
        } elseif ($storage = $this->getStorage($name)) {
            $this->data[$name] = $storage->getData($this);
            return $this->data[$name];
        }
        return null;
    }

    private static function camelCase($m)
    {
        return strtoupper($m[2]);
    }

    /**
     * Returns product property value.
     *
     * @param string|null $name Value name. If not specified, all properties' values are returned.
     * @return mixed
     */
    public function getData($name = null)
    {
        if ($name) {
            return isset($this->data[$name]) ? $this->data[$name] : null;
        } else {
            return $this->data;
        }
    }

    /**
     * Executed on attempts to change product property values.
     * @see http://www.php.net/manual/en/language.oop5.overloading.php
     *
     * @param string $name Property name
     * @param mixed $value New value
     * @return mixed New value
     */
    public function __set($name, $value)
    {
        return $this->setData($name, $value);
    }

    /**
     * Changes product property values without saving them to database.
     *
     * @param string $name Property name
     * @param mixed $value New value
     * @return mixed New value
     */
    public function setData($name, $value)
    {
        switch ($name) {
            case 'name':
                $value = preg_replace('@[\r\n]+@', ' ', $value);
                break;
            case 'video_url':
                $value = shopVideo::checkVideo($value);
                break;
        }

        if ($this->getData($name) !== $value) {
            $this->data[$name] = $value;
            $this->is_dirty[$name] = true;
        }
        return $value;
    }

    /**
     * Whether a offset exists
     * @link http://php.net/manual/en/arrayaccess.offsetexists.php
     * @param mixed $offset an offset to check for.
     * @return boolean true on success or false on failure.
     * The return value will be casted to boolean if non-boolean was returned.
     * @throws waException
     */
    public function offsetExists($offset)
    {
        if (isset($this->data[$offset]) || $this->model->fieldExists($offset) || $this->getStorage($offset)) {
            return true;
        }

        $method = "get".preg_replace_callback('@(^|_)([a-z])@', array(__CLASS__, 'camelCase'), $offset);
        return method_exists($this, $method);
    }

    /**
     * Offset to retrieve
     * @link http://php.net/manual/en/arrayaccess.offsetget.php
     * @param mixed $offset The offset to retrieve.
     * @return mixed Can return all value types.
     * @throws waException
     */
    public function offsetGet($offset)
    {
        return $this->__get($offset);
    }

    /**
     * Offset to set
     * @link http://php.net/manual/en/arrayaccess.offsetset.php
     * @param mixed $offset The offset to assign the value to.
     * @param mixed $value The value to set.
     * @return void
     */
    public function offsetSet($offset, $value)
    {
        $this->__set($offset, $value);
    }

    /**
     * Offset to unset
     * @link http://php.net/manual/en/arrayaccess.offsetunset.php
     * @param mixed $offset The offset to unset.
     * @return void
     */
    public function offsetUnset($offset)
    {
        $this->__set($offset, null);
    }

    /**
     * Returns relative path to directory containing specified product's files.
     *
     * @param int $product_id
     * @return string
     */
    public static function getFolder($product_id)
    {
        $str = str_pad($product_id, 4, '0', STR_PAD_LEFT);
        return 'products/'.substr($str, -2).'/'.substr($str, -4, 2);
    }

    /**
     * Returns path to specified product's data file or directory.
     *
     * @param int $product_id
     * @param string $file Sub-path of file or directory
     * @param bool $public Whether path to either directly available or authorization-protected file/directory must be returned
     * @return string
     */
    public static function getPath($product_id, $file = null, $public = false)
    {
        $path = self::getFolder($product_id)."/$product_id/".($file ? ltrim($file, '/') : '');
        return wa()->getDataPath($path, $public, 'shop', false);
    }

    /**
     * Returns information on product's type.
     *
     * @return array|null Product type info array, or null if product has no type
     */
    public function getType()
    {
        $model = new shopTypeModel();
        return $this->type_id ? $model->getById($this->type_id) : null;
    }

    /**
     * Returns products identified as upselling items for current product.
     *
     * @param int $limit Maximum number of items to be returned
     * @param bool $available_only Whether only products with positive or unlimited stock count must be returned
     *
     * @return array Array of upselling products' data sub-arrays
     * @throws waException
     */
    public function upSelling($limit = 5, $available_only = false)
    {
        $upselling = $this->getData('upselling');
        if ($upselling == 1 || $upselling === null) {
            // upselling on (using similar setting for type)
            $type_upselling_model = new shopTypeUpsellingModel();
            $conditions = $type_upselling_model->getByField('type_id', $this->getData('type_id'), true);
            if (!$conditions) {
                return array();
            }
            $collection = new shopProductsCollection('upselling/'.$this->getId(), array(
                'conditions' => $conditions,
                'product'    => $this,
            ));
        } elseif (!$upselling) {
            return array();
        } else {
            // upselling on (manually)
            $collection = new shopProductsCollection('related/upselling/'.$this->getId());
        }

        if ($available_only) {
            $collection->addWhere('(p.count > 0 OR p.count IS NULL)');
        }
        return $collection->getProducts('*,skus_filtered', $limit);
    }

    /**
     * Returns products identified as cross-selling items for current product.
     *
     * @param int $limit Maximum number of items to be returned
     * @param bool $available_only Whether only products with positive or unlimited stock count must be returned
     * @param array $exclude
     *
     * @return array Array of cross-selling products' data sub-arrays
     * @throws waException
     */
    public function crossSelling($limit = 5, $available_only = false, $exclude = array())
    {
        $collection = $this->getCrossSellingCollection($exclude);
        $result = [];
        if (!empty($collection)) {
            if ($available_only) {
                $collection->addWhere('(p.count > 0 OR p.count IS NULL)');
            }
            $result = $collection->getProducts('*,skus_filtered', $limit);
            unset($result[$this->getId()]);
        }

        return $result;
    }

    /**
     * @param int $limit
     * @param array $exclude
     * @return array
     * @throws waDbException
     * @throws waException
     */
    public function crossSellingInStock($limit = 5, $exclude = [])
    {
        $collection = $this->getCrossSellingCollection($exclude);
        $result = [];

        if (!empty($collection)) {
            $result = $collection->getProducts('*,skus_filtered', $limit);
            $ignore_stock_count = wa('shop')->getSetting('ignore_stock_count');

            if (!$ignore_stock_count) {
                //We remove products that are not in stock
                foreach ($result as $id => $product) {
                    if (!is_null($product['count']) && $product['count'] <= 0) {
                        unset($result[$id]);
                    }
                }
            }

            unset($result[$this->getId()]);
        }
        return $result;
    }

    /**
     * @param $exclude
     * @return array|shopProductsCollection
     * @throws waException
     */
    protected function getCrossSellingCollection($exclude)
    {
        $collection = [];
        $cross_selling = $this->getData('cross_selling');

        // cross selling on (using similar setting for type)
        if ($cross_selling == 1 || $cross_selling === null) {
            $type = $this->getType();
            if ($type['cross_selling']) {
                $hash = $type['cross_selling'].($type['cross_selling'] == 'alsobought' ? '/'.$this->getId() : '');
                $collection = new shopProductsCollection($hash);
                if ($type['cross_selling'] != 'alsobought') {
                    $collection->orderBy('RAND()');
                }
            }
        } elseif ($cross_selling) {
            $collection = new shopProductsCollection('related/cross_selling/'.$this->getId());
        }

        if ($collection instanceof shopProductsCollection && $exclude) {
            $ids = [];
            foreach ($exclude as $exclude_id) {
                $exclude_id = (int)$exclude_id;
                if ($exclude_id) {
                    $ids[] = $exclude_id;
                }
            }
            if ($ids) {
                $collection->addWhere('p.id NOT IN ('.(implode(',', $ids)).')');
            }
        }

        return $collection;
    }

    /**
     * Returns estimated information on product's sales based on specified sales rate
     *
     * @param double $rate Average number of product's sales per day
     * @return array
     * @deprecated use getNextForecast instead
     */
    public function getRunout($rate)
    {
        $runout = array();
        $sku_runout = array();
        if ($rate > 0) {
            // for whole product
            if ($this->count !== null) {
                $runout['days'] = round($this->count / $rate);
                $runout['date'] = date('Y-m-d', strtotime("+{$runout['days']} days"));
            }
            // for each sku
            foreach ($this->skus as $sku_id => $sku) {
                if (empty($sku['stock'])) {
                    if ($sku['count'] !== null) {
                        $days = round($sku['count'] / $rate);
                        $sku_runout[$sku_id]['days'] = $days;
                        $sku_runout[$sku_id]['date'] = date('Y-m-d', strtotime("+{$days} days"));
                    }
                } else {
                    foreach ($sku['stock'] as $stock_id => $count) {
                        $days = round($count / $rate);
                        $sku_runout[$sku_id]['stock'][$stock_id]['days'] = $days;
                        $sku_runout[$sku_id]['stock'][$stock_id]['date'] = date('Y-m-d', strtotime("+{$days} days"));
                    }
                }
            }
        }
        return array(
            'product' => $runout,
            'sku'     => $sku_runout
        );
    }

    /**
     *
     * @return array
     */
    public function getNextForecast()
    {
        $product_skus_model = new shopProductSkusModel();
        $skus = $product_skus_model->getData($this);

        $sales = 0;
        $sold = 0;
        $purchase = 0;

        // Fetch number of sales per month (averaged for 3 months, normalized for new products)
        $sql = "SELECT oi.product_id, oi.sku_id, sum(oi.quantity) AS sold
                FROM shop_order_items AS oi
                    JOIN shop_order AS o
                        ON o.id=oi.order_id
                WHERE oi.product_id = ?
                    AND o.paid_date >= ?
                    AND oi.type='product'
                GROUP BY product_id, sku_id";

        $time_threshold = time() - 90 * 24 * 3600;
        $params = array(
            $this->id,
            date('Y-m-d', $time_threshold),
        );
        foreach ($product_skus_model->query($sql, $params) as $oi) {
            if (isset($skus[$oi['sku_id']])) {
                // Normalize number of sales for products created recently
                if (!empty($this->create_datetime)) {
                    $create_ts = strtotime($this->create_datetime);
                    if ($create_ts > $time_threshold) {
                        $days = max(30, (time() - $create_ts) / 24 / 3600);
                        $oi['sold'] = $oi['sold'] * 90 / $days;
                    }
                }
                $skus[$oi['sku_id']]['sold'] = ifset($skus[$oi['sku_id']]['sold'], 0);
                $skus[$oi['sku_id']]['sold'] += $oi['sold'];
            }
        }

        foreach ($skus as $sku) {
            $sales += ifset($sku['sold'], 0) * $sku['price'];
            $sold += ifset($sku['sold'], 0);
            $purchase += ifset($sku['sold'], 0) * $sku['purchase_price'];
        }

        $data = array(
            'sales'              => $sales / 3,
            'sold'               => $sold / 3,
            'sold_rounded'       => round($sold / 3),
            'sold_rounded_1'     => round($sold / 3, 1),
            'sold_rounded_1_str' => number_format(round($sold / 3, 1), 1),
            'profit'             => ($sales - $purchase) / 3,
            'days'               => 0,
            'date'               => null,
            'count'              => $this->count
        );

        if ($this->count !== null && $data['sold_rounded'] > 0) {
            $sold_per_day = $data['sold_rounded'] / 30;
            $days = round($this->count / $sold_per_day);

            // This limits the date to reasonable period.
            // Without this check, large stock would cause
            // dates millions of years in future.
            if ($days > shopProduct::MAX_FORECAST_DAYS) {
                $days = shopProduct::MAX_FORECAST_DAYS;
            }
            $data['days'] = $days;
            $data['date'] = strtotime("+ ".$days."days");
        }

        return $data;
    }

    /**
     * @return array
     */
    public function getReviews()
    {
        if ($this->getId()) {
            $reviews_model = new shopProductReviewsModel();
            return $reviews_model->getFullTree($this->getId(), 0, null, 'datetime DESC', array('escape' => true));
        }
        return array();
    }

    /**
     * @return array
     * @throws waException
     */
    public function getSkuFeatures()
    {
        if ($this->getId()) {
            $product_features_model = new shopProductFeaturesModel();
            $sql = "SELECT * FROM ".$product_features_model->getTableName()." WHERE product_id = i:0 AND sku_id IS NOT NULL ORDER BY id";
            $rows = $product_features_model->query($sql, $this->getId())->fetchAll();
            if (!$rows) {
                return array();
            }
            $features = array();
            foreach ($rows as $row) {
                $features[$row['feature_id']] = true;
            }
            $features_model = new shopFeatureModel();
            $features = $features_model->getById(array_keys($features));
            $type_values = array();
            foreach ($rows as $row) {
                if (empty($features[$row['feature_id']])) {
                    continue;
                }
                $f = $features[$row['feature_id']];
                $type = preg_replace('/\..*$/', '', $f['type']);
                if ($type == shopFeatureModel::TYPE_BOOLEAN) {
                    $type_values[$type][] = $row['feature_id'];
                } else {
                    $type_values[$type][] = $row['feature_value_id'];
                }
            }

            foreach ($type_values as $type => $value_ids) {
                $model = shopFeatureModel::getValuesModel($type);
                if ($type == shopFeatureModel::TYPE_BOOLEAN) {
                    $type_values[$type] = $model->getValues('feature_id', array_unique($value_ids));
                } else {
                    $type_values[$type] = $model->getValues('id', $value_ids);
                }
            }

            $result = [];
            foreach ($rows as $row) {
                if (empty($features[$row['feature_id']])) {
                    continue;
                }
                $f = $features[$row['feature_id']];
                $type = preg_replace('/\..*$/', '', $f['type']);
                if (!$type_values[$type][$row['feature_id']][$row['feature_value_id']]) {
                    continue;
                }
                if (null === $features[$row['feature_id']]['parent_id']) {
                    $result[$row['sku_id']][$f['code']] = $type_values[$type][$row['feature_id']][$row['feature_value_id']];
                } else {
                    $name_feature = preg_replace('#\.\d$#', '', $f['code']);
                    if (isset($result[$row['sku_id']][$name_feature])) {
                        $result[$row['sku_id']][$name_feature] .= '&times;'.$type_values[$type][$row['feature_id']][$row['feature_value_id']];
                    } else {
                        $result[$row['sku_id']][$name_feature] = $type_values[$type][$row['feature_id']][$row['feature_value_id']];
                    }
                }
            }
            return $result;
        } else {
            return array();
        }
    }

    /**
     * Verifies current user's access rights to product by its type id.
     *
     * @param array $options extra options
     *  - int|string $options['level'] [optional]
     *      If numeric, that min level to check
     *      If string 'delete' - check can contact delete product
     *      If skipped just return rights level as it (shopRightConfig::RIGHT_*)
     *
     * @return boolean|int
     * @throws waException
     */
    public function checkRights($options = array())
    {
        if (isset($this->data['type_id'])) {
            return $this->model->checkRights($this->data, $options);
        } else {
            return $this->model->checkRights($this->getId(), $options);
        }
    }

    /**
     * @param array $options
     * @param $errors
     * @return shopProduct
     * @throws Exception
     * @throws waException
     * @throws waRightsException
     */
    public function duplicate($options = array(), &$errors = array())
    {
        if ($this->is_frontend) {
            throw new waException('Unable duplicate shopProduct: data is converted for frontend');
        }

        $data = $this->data;
        $skip = array(
            'id',
            'create_datetime',
            'edit_datetime',
            'id_1c',
            'rating',
            'rating_count',
            'total_sales',
            'image_id',
            'contact_id',
            'ext',//?
            'count',
            'sku_count',
        );

        foreach ($skip as $field) {
            if (isset($data[$field])) {
                unset($data[$field]);
            }
        }

        $duplicate = new shopProduct();
        $this->getStorage(null);
        $sku_files = array();
        $sku_images = array();

        $data_storages = self::$data_storages;
        unset($data_storages['features_selectable']);   // will duplicate features selectable manually without voodoo magic

        foreach ($data_storages as $key => $i) {
            $raw = $this->getStorage($key)->getData($this);
            switch ($key) {
                case 'skus':
                    $storage_data = array();
                    $i = 0;
                    $new_product_id = $this->model->select('MAX(id) as max_id')->fetchField('max_id') + 1;
                    foreach ($raw as $sku_id => $sku) {
                        if (isset($sku['sku']) && ifset($options, 'remove_sku', true)) {
                            $sku['sku'] .= '-' . $new_product_id;
                        }

                        if ($file_path = shopProductSkusModel::getPath($sku)) {
                            $sku_files[$sku['id']] = array(
                                'file_name'        => $sku['file_name'],
                                'file_description' => $sku['file_description'],
                                'file_size'        => $sku['file_size'],
                                'file_path'        => $file_path,
                            );
                        }
                        if (!empty($sku['image_id'])) {
                            $sku_images[$sku['id']] = $sku['image_id'];
                        }

                        foreach (array('id', 'id_1c', 'product_id', 'image_id', 'file_name', 'file_size', 'file_description') as $field) {
                            if (isset($sku[$field])) {
                                unset($sku[$field]);
                            }
                        }

                        $storage_data[--$i] = $sku;
                    }
                    break;
                case 'tags':
                    $storage_data = array_values($raw);
                    break;
                case 'categories':
                    $storage_data = array_keys($raw);
                    break;
                default:
                    $storage_data = $raw;
                    break;
            }
            $duplicate->{$key} = $storage_data;
        }

        $counter = 0;
        $data['url'] = shopHelper::genUniqueUrl($this->url, $this->model, $counter);

        if (ifset($options, 'change_name', true)) {
            $data['name'] = $this->name.sprintf(' %d', $counter ? $counter : 1);
        }

        if (!$duplicate->save($data, true, $errors)) {
            return false;
        }

        // clone features selectable
        $features_selectable_model = new shopProductFeaturesSelectableModel();
        $features_selectable = $features_selectable_model->getByField(['product_id' => $this->getId()], true);
        foreach ($features_selectable as &$item) {
            $item['product_id'] = $duplicate->getId();
        }
        unset($item);
        $features_selectable_model->multipleInsert($features_selectable);

        $product_id = $duplicate->getId();

        $sku_map = array_combine(array_keys($this->skus), array_keys($duplicate->skus));

        $config = wa('shop')->getConfig();
        $image_thumbs_on_demand = $config->getOption('image_thumbs_on_demand');
        /**
         * @var shopConfig $config
         */
        if ($this->pages) {
            $product_pages_model = new shopProductPagesModel();
            foreach ($this->pages as $page) {
                unset($page['id']);
                unset($page['create_time']);
                unset($page['update_datetime']);
                unset($page['create_contact_id']);

                $page['product_id'] = $duplicate->getId();
                $product_pages_model->add($page);
            }
        }

        #duplicate images
        $product_skus_model = new shopProductSkusModel();
        $images_model = new shopProductImagesModel();
        $images = $images_model->getByField('product_id', $this->getId(), $images_model->getTableId());
        $callback = wa_lambda('$a, $b', 'return (max(-1, min(1, $a["sort"] - $b["sort"])));');
        usort($images, $callback);
        foreach ($images as $id => $image) {
            $source_path = shopImage::getPath($image);
            $original_file = shopImage::getOriginalPath($image);
            $image['product_id'] = $duplicate->getId();
            $sku_id = array();
            if ($source_sku_id = array_keys($sku_images, $image['id'])) {
                foreach ($source_sku_id as $_sku_id) {
                    $sku_id[] = $sku_map[$_sku_id];
                }
            }
            unset($image['id']);
            try {
                if ($image['id'] = $images_model->add($image, $id == $this->image_id)) {

                    waFiles::copy($source_path, shopImage::getPath($image));
                    if (file_exists($original_file)) {
                        waFiles::copy($original_file, shopImage::getOriginalPath($image));
                    }
                    if ($sku_id) {
                        $product_skus_model->updateById($sku_id, array('image_id' => $image['id']));
                    }

                    if (!$image_thumbs_on_demand) {
                        shopImage::generateThumbs($image, $config->getImageSizes());
                        //TODO use dummy copy  with rename files
                    }
                }
            } catch (waDbException $ex) {
                //just ignore it
                waLog::log('Error during copy product: '.$ex->getMessage(), 'shop.log');
            } catch (Exception $ex) {
                if (!empty($image['id'])) {
                    $images_model->deleteById($image['id']);
                }
                waLog::log('Error during copy product: '.$ex->getMessage(), 'shop.log');
            }

        }

        foreach ($sku_files as $sku_id => $data) {
            $source_path = $data['file_path'];
            unset($data['file_path']);
            $sku_id = $sku_map[$sku_id];
            $sku = array_merge($duplicate->skus[$sku_id], $data);
            $product_skus_model->updateById($sku_id, $data);

            $target_path = shopProductSkusModel::getPath($sku);
            try {
                waFiles::copy($source_path, $target_path);
            } catch (waException $ex) {
                $data = array(
                    'file_name'        => '',
                    'file_description' => '',
                    'file_size'        => 0,
                );
                $product_skus_model->updateById($sku_id, $data);
                print $ex->getMessage();
            }
        }

        $product_features_model = new shopProductFeaturesModel();
        $skus_features = $product_features_model->getSkuFeatures($this->id);
        $skus_features_data = array();
        $delete_features_id = array();

        foreach ($skus_features as $sku_id => $features) {
            $sku_id = $sku_map[$sku_id];
            foreach ($features as $feature_id => $feature_value_id) {
                $skus_features_data[] = compact('product_id', 'sku_id', 'feature_id', 'feature_value_id');
                $delete_features_id[] = $feature_id;
            }
        }

        // Delete the empty values. Reset only if sku are created in "Parameter selection"
        // For this mode, the characteristics included for the product break the filtering
        // Values are stored in the $duplicate->save
        if ($delete_features_id && $data['sku_type'] == 1) {
            $product_features_model->deleteByField(array('product_id' => $product_id, 'feature_id' => $delete_features_id));
        }

        if ($skus_features_data) {
            $product_features_model->multipleInsert($skus_features_data);
        }
        if ($this->sku_type == shopProductModel::SKU_TYPE_SELECTABLE) {
            $product_features_selectable_model = new shopProductFeaturesSelectableModel();
            if ($features_selectable = $product_features_selectable_model->getByField('product_id', $this->id, true)) {
                foreach ($features_selectable as &$feature_selectable) {
                    $feature_selectable['product_id'] = $product_id;
                }
                unset($feature_selectable);
                $product_features_selectable_model->multipleInsert($features_selectable);
            }
        }

        $product_services_model = new shopProductServicesModel();
        if ($services = $product_services_model->getByField('product_id', $this->id, true)) {
            foreach ($services as &$service) {
                unset($service['id']);
                $service['product_id'] = $product_id;
                $service['sku_id'] = ifset($sku_map[$service['sku_id']]);
                unset($service);
            }
            $product_services_model->multipleInsert($services);
        }

        $product_related_model = new shopProductRelatedModel();
        if ($related = $product_related_model->getByField('product_id', $this->id, true)) {
            foreach ($related as &$row) {
                $row['product_id'] = $product_id;
            }
            unset($row);
            $product_related_model->multipleInsert($related);
        }

        $params = array(
            'product'   => &$this,
            'duplicate' => &$duplicate,
        );
        /**
         * @wa-event product_duplicate
         */
        wa('shop')->event('product_duplicate', $params);
        return $duplicate;
    }

    public static function getDefaultMetaTitle($product)
    {
        return strip_tags($product['name']);
    }

    public static function getDefaultMetaKeywords($product)
    {
        $keywords = array(
            $product['name']
        );
        $num = 5;
        foreach ($product->skus as $sku_id => $sku) {
            $keywords[] = strip_tags($sku['name']);
            $num -= 1;
            if ($num <= 0) {
                break;
            }
        }
        if ($product->category_id && isset($product->categories[$product->category_id])) {
            $keywords[] = strip_tags($product->categories[$product->category_id]['name']);
        }
        $num = 5;
        foreach ($product->tags as $tag) {
            $keywords[] = strip_tags($tag);
            $num -= 1;
            if ($num <= 0) {
                break;
            }
        }
        $keywords = array_filter($keywords, 'trim');
        return str_replace('"', '', implode(', ', $keywords));
    }

    public static function getDefaultMetaDescription($product)
    {
        return strip_tags($product['summary']);
    }
}
