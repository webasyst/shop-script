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
 * @property float $compare_pricese
 * @property float $base_price_selectable
 * @property float $compare_price_selectable
 * @property float $purchase_price_selectable
 * @property string $currency
 * @property-read string $currency_html
 * @property float $min_price
 * @property float $max_price
 * @property int $tax_id
 * @property int|null $count
 *
 * @property-read array $pages
 *
 * @property int $type_id
 * @property array $type
 * @property int $category_id
 * @property array $features
 * @example
 * // read product feature
 * $product->features['feature_code'];
 * @property array $features_selectable
 * @property array $skus
 * @property-read int $sku_count
 * @property array $categories
 * @property array $sets
 * @property array $tags
 * @property array $params
 */
class shopProduct implements ArrayAccess
{
    protected $data = array();
    protected $is_dirty = array();
    protected static $data_storages = array();
    protected $is_frontend = false;

    /**
     * @var shopProductModel
     */
    protected $model;

    /**
     * Creates a new product object or a product object corresponding to existing product.
     *
     * @param int|array $data Product id or product data array
     */
    public function __construct($data = array(), $is_frontend=false)
    {
        $this->is_frontend = $is_frontend;
        $this->model = new shopProductModel();
        if (is_array($data)) {
            $this->data = $data;
        } elseif ($data) {
            $this->data = $this->model->getById($data);
        }

        if ($is_frontend) {
            $tmp = array(&$this->data);
            shopRounding::roundProducts($tmp);
        }
    }

    public function isFrontend()
    {
        return $this->is_frontend;
    }

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
     * @see shopConfig::$image_sizes — actual image size values correspondings to size ids
     *
     * @return array Array containing sub-arrays of individual product images
     */
    public function getImages($sizes = array(), $absolute = false)
    {
        if ($this->getId()) {
            $images_model = new shopProductImagesModel();
            if (empty($sizes)) {
                $sizes = 'crop';
            }
            return $images_model->getImages($this->getId(), $sizes, 'id', $absolute);
        } else {
            return array();
        }
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
     * Saves product data to database.
     *
     * @param array $data
     * @return bool Whether saved successfully
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
            $product = array();
            $id_changed = !empty($this->is_dirty['id']);
            foreach ($this->is_dirty as $field => $v) {
                if ($this->model->fieldExists($field)) {
                    $product[$field] = $this->data[$field];
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
                if ($id = $this->model->insert($product)) {
                    $this->data['id'] = $id;

                    // update empty url by ID
                    if (empty($product['url'])) {
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
        wa()->event('product_save', $params);
        return $result;
    }

    protected function saveData(&$errors = array())
    {
        #fix empty SKUs
        if (!$this->sku_count && empty($this->is_dirty) && empty($this->data['skus'])) {
            $this->setData(
                'skus',
                array(
                    -1 => array(
                        'name' => '',
                    )
                )
            );
        }
        if ($this->is_dirty) {
            $this->getStorage(null);
            //save external data in right storage order
            foreach (array_keys(self::$data_storages) as $field) {
                if (($field == 'skus') && !$this->sku_count && empty($this->is_dirty[$field]) && empty($this->data['skus'])) {
                    #fix empty SKUs on missed virtual SKUs
                    $this->setData(
                        'skus',
                        array(
                            -1 => array(
                                'name' => '',
                            )
                        )
                    );
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
            if ($name == 'skus' && $this->is_frontend) {
                shopRounding::roundSkus($this->data[$name], array($this->data));
            }
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
        if ($name =='name') {
            $value = preg_replace('@[\r\n]+@',' ',$value);
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
     */
    public function offsetExists($offset)
    {
        return isset($this->data[$offset]) || $this->model->fieldExists($offset) || $this->getStorage($offset);
    }

    /**
     * Offset to retrieve
     * @link http://php.net/manual/en/arrayaccess.offsetget.php
     * @param mixed $offset The offset to retrieve.
     * @return mixed Can return all value types.
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
        return wa()->getDataPath($path, $public, 'shop');
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
     */
    public function upSelling($limit = 5, $available_only = false)
    {
        $upselling = $this->getData('upselling');
        // upselling on (usign similar settting for type)
        if ($upselling == 1 || $upselling === null) {
            $type_upselling_model = new shopTypeUpsellingModel();
            $conditions = $type_upselling_model->getByField('type_id', $this->getData('type_id'), true);
            if ($conditions) {
                $collection = new shopProductsCollection('upselling/'.$this->getId(), array('product' => $this, 'conditions' => $conditions));
                if ($available_only) {
                    $collection->addWhere('(p.count > 0 OR p.count IS NULL)');
                }
                return $collection->getProducts('*', $limit);
            } else {
                return array();
            }
        } elseif (!$upselling) {
            return array();
        } // upselling on (manually)
        else {
            $collection = new shopProductsCollection('related/upselling/'.$this->getId());
            if ($available_only) {
                $collection->addWhere('(p.count > 0 OR p.count IS NULL)');
            }
            return $collection->getProducts('*', $limit);
        }
    }

    /**
     * Returns products identified as cross-selling items for current product.
     *
     * @param int $limit Maximum number of items to be returned
     * @param bool $available_only Whether only products with positive or unlimited stock count must be returned
     * @param array $exclude
     *
     * @return array Array of cross-selling products' data sub-arrays
     */
    public function crossSelling($limit = 5, $available_only = false, $exclude = array())
    {
        $cross_selling = $this->getData('cross_selling');
        // cross selling on (usign similar settting for type)
        if ($cross_selling == 1 || $cross_selling === null) {
            $type = $this->getType();
            if ($type['cross_selling']) {
                $collection = new shopProductsCollection($type['cross_selling'].($type['cross_selling'] == 'alsobought' ? '/'.$this->getId() : ''));
                if ($type['cross_selling'] != 'alsobought') {
                    $collection->orderBy('RAND()');
                }
            } else {
                return array();
            }
        } elseif (!$cross_selling) {
            return array();
        } else {
            $collection = new shopProductsCollection('related/cross_selling/'.$this->getId());
        }
        if (!empty($collection)) {
            if ($available_only) {
                $collection->addWhere('(p.count > 0 OR p.count IS NULL)');
            }
            if ($exclude) {
                $ids = array();
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
            $result = $collection->getProducts('*', $limit);
            if (isset($result[$this->getId()])) {
                unset($result[$this->getId()]);
            }
            return $result;
        }
        return array();
    }

    /**
     * Returns estimated information on product's sales based on specified sales rate
     *
     * @deprecated use getNextForecast instead
     * @param double $rate Average number of product's sales per day
     * @return array
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

        $time_threshold = time() - 90*24*3600;
        foreach($product_skus_model->query($sql,
                array(
                    $this->id,
                    date('Y-m-d', $time_threshold))) as $oi)
        {
            if (isset($skus[$oi['sku_id']])) {
                // Normalize number of sales for products created recently
                if (!empty($this->create_datetime)) {
                    $create_ts = strtotime($this->create_datetime);
                    if ($create_ts > $time_threshold) {
                        $days = max(30, (time() - $create_ts) / 24 / 3600);
                        $oi['sold'] = $oi['sold']*90/$days;
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
            'sales' => $sales / 3,
            'sold' => $sold / 3,
            'sold_rounded' => round($sold / 3),
            'sold_rounded_1' => round($sold / 3, 1),
            'sold_rounded_1_str' => number_format(round($sold / 3, 1), 1),
            'profit' => ($sales - $purchase) / 3,
            'days' => 0,
            'date' => null,
            'count' => $this->count
        );

        if ($this->count !== null && $data['sold_rounded'] > 0) {
            $sold_per_day = $data['sold_rounded'] / 30;
            $days = round($this->count / $sold_per_day);
            $data['days'] = $days;
            $data['date'] = strtotime("+ " . $days . "days");
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
            return $reviews_model->getFullTree(
                $this->getId(), 0, null, 'datetime DESC', array('escape' => true)
            );
        }
        return array();
    }

    public function getSkuFeatures()
    {
        if ($this->getId()) {
            $product_features_model = new shopProductFeaturesModel();
            $sql = "SELECT * FROM ".$product_features_model->getTableName()." WHERE product_id = i:0 AND sku_id IS NOT NULL";
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
                $type_values[$type][] = $row['feature_value_id'];
            }

            foreach ($type_values as $type => $value_ids) {
                $model = shopFeatureModel::getValuesModel($type);
                $type_values[$type] = $model->getValues('id', $value_ids);
            }

            $result = array();
            foreach ($rows as $row) {
                if (empty($features[$row['feature_id']])) {
                    continue;
                }
                $f = $features[$row['feature_id']];
                $type = preg_replace('/\..*$/', '', $f['type']);
                if (!$type_values[$type][$row['feature_id']][$row['feature_value_id']]) {
                    continue;
                }
                $result[$row['sku_id']][$f['code']] = $type_values[$type][$row['feature_id']][$row['feature_value_id']];
            }
            return $result;
        } else {
            return array();
        }
    }

    /**
     * Verifies current user's access rights to product by its type id.
     *
     * @throws waException
     * @return boolean
     */
    public function checkRights()
    {
        if (isset($this->data['type_id'])) {
            return $this->model->checkRights($this->data);
        } else {
            return $this->model->checkRights($this->getId());
        }
    }

    /**
     * @param array $options
     * @return shopProduct
     * @throws waException
     */
    public function duplicate($options = array())
    {
        if ($this->is_frontend) {
            throw new waException('Unable duplicate shopProduct: data is converted for frontend');
        }

        if (!$this->checkRights()) {
            throw new waRightsException('Access denied');
        }
        $data = $this->data;
        $skip = array(
            'id',
            'create_datetime',
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

        $ignore_select = true;

        foreach (self::$data_storages as $key => $i) {
            $raw = $this->getStorage($key)->getData($this);
            switch ($key) {
                case 'features_selectable':
                    $storage_data = array();
                    if (!$ignore_select) {
                        if ($this->sku_type == shopProductModel::SKU_TYPE_SELECTABLE) {
                            if (!is_array($raw)) {
                                $raw = array();
                            }

                            foreach ($raw as $id => $f) {
                                if (!empty($f['selected'])) {
                                    foreach ($f['values'] as $value_id => &$value) {
                                        if (!empty($value['selected'])) {

                                            $value = array(
                                                'id' => $value_id,
                                            );
                                        } else {
                                            unset($f['values'][$value_id]);
                                        }
                                    }
                                    $storage_data[$id] = $f;
                                }
                            }
                        }
                    }
                    break;
                case 'skus':
                    $storage_data = array();
                    $i = 0;
                    foreach ($raw as $sku_id => $sku) {
                        if (!empty($sku['virtual']) || $ignore_select) {
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
        $data['name'] = $this->name.sprintf(' %d', $counter ? $counter : 1);

        $duplicate->save($data);
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
        $callback = create_function('$a, $b', 'return (max(-1, min(1, $a["sort"] - $b["sort"])));');
        usort($images, $callback);
        foreach ($images as $id => $image) {
            $source_path = shopImage::getPath($image);
            $original_file = shopImage::getOriginalPath($image);
            $image['product_id'] = $duplicate->getId();
            if ($sku_id = array_search($image['id'], $sku_images)) {
                $sku_id = $sku_map[$sku_id];
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
        foreach ($skus_features as $sku_id => $features) {
            $sku_id = $sku_map[$sku_id];
            foreach ($features as $feature_id => $feature_value_id) {
                $skus_features_data[] = compact('product_id', 'sku_id', 'feature_id', 'feature_value_id');
            }
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
        wa()->event('product_duplicate', $params);
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
