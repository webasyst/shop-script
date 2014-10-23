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
 * @property float $min_price
 * @property float $max_price
 * @property int $tax_id
 * @property int|null $count
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
 * @property array $tags
 * @property array $params
 */
class shopProduct implements ArrayAccess
{
    protected $data = array();
    protected $is_dirty = array();
    protected static $data_storages = array();
    /**
     * @var shopProductModel
     */
    protected $model;

    /**
     * Creates a new product object or a product object corresponding to existing product. 
     * 
     * @param int|array $data Product id or product data array
     */
    public function __construct($data = array())
    {
        $this->model = new shopProductModel();
        if (is_array($data)) {
            $this->data = $data;
        } elseif ($data) {
            $this->data = $this->model->getById($data);
        }
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
                'categories'          => 'shopCategoryProductsModel'
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

    /**
     * Saves product data to database.
     * 
     * @param array $data
     * @return bool Whether saved successfully
     */
    public function save($data = array(), $validate = true, &$errors = array())
    {
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
                        $type_model->incCounters(array(
                            $this->data['type_id'] => 1
                        ));
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
            $this->setData('skus', array(
                -1 => array(
                    'name' => '',
                )
            ));
        }
        if ($this->is_dirty) {
            $this->getStorage(null);
            //save external data in right storage order
            foreach (array_keys(self::$data_storages) as $field) {
                if (($field == 'skus') && !$this->sku_count && empty($this->is_dirty[$field]) && empty($this->data['skus'])) {
                    #fix empty SKUs on missed virtual SKUs
                    $this->setData('skus', array(
                        -1 => array(
                            'name' => '',
                        )
                    ));
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

        $method = "get".ucfirst($name);
        if (method_exists($this, $method)) {
            $this->data[$name] = $this->$method();
            return $this->data[$name];
        } elseif ($storage = $this->getStorage($name)) {
            $this->data[$name] = $storage->getData($this);
            return $this->data[$name];
        }
        return null;
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
     * 
     * @return array Array of cross-selling products' data sub-arrays
     */
    public function crossSelling($limit = 5, $available_only = false)
    {
        $cross_selling = $this->getData('cross_selling');
        // upselling on (usign similar settting for type)
        if ($cross_selling == 1 || $cross_selling === null) {
            $type = $this->getType();
            if ($type['cross_selling']) {
                $collection = new shopProductsCollection($type['cross_selling'].($type['cross_selling'] == 'alsobought' ? '/'.$this->getId() : ''));
                if ($available_only) {
                    $collection->addWhere('(p.count > 0 OR p.count IS NULL)');
                }
                if ($type['cross_selling'] != 'alsobought') {
                    $collection->orderBy('RAND()');
                }
                $result = $collection->getProducts('*', $limit);
                if (isset($result[$this->getId()])) {
                    unset($result[$this->getId()]);
                }
                return $result;
            } else {
                return array();
            }
        } elseif (!$cross_selling) {
            return array();
        } else {
            $collection = new shopProductsCollection('related/cross_selling/'.$this->getId());
            if ($available_only) {
                $collection->addWhere('(p.count > 0 OR p.count IS NULL)');
            }
            return $collection->getProducts('*', $limit);
        }
    }

    /**
     * Returns estimated information on product's sales based on specified sales rate
     *
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
}
