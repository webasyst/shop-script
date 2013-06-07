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
 * @property string $currency
 * @property float $min_price
 * @property float $max_price
 * @property int $tax_id
 * @property int|null $count
 *
 * @property int $type_id
 * @property array $type
 * @property string $type['name']
 * @property int $category_id
 * @property array $features
 * @example
 * // read product feature
 * $product->features['feature_code'];
 * @property array $skus
 * @property array $categories
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
     * @param int|array $data id or data array
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
                'tags'       => true,
                'features'   => true,
                'skus'       => true,
                'params'     => true,
                'categories' => 'shopCategoryProductsModel'
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
     * @return int
     */
    public function getId()
    {
        return $this->getData('id');
    }

    public function getImages($sizes = array())
    {
        if ($this->getId()) {
            $images_model = new shopProductImagesModel();
            if (empty($sizes)) {
                $sizes = 'crop';
            }
            return $images_model->getImages($this->getId(), $sizes);
        } else {
            return array();
        }
    }

    public function getPages()
    {
        $product_pages_model = new shopProductPagesModel();
        return $product_pages_model->getPages($this->getId(), true);
    }

    /**
     * @return waContact
     */
    public function getContact()
    {
        return new waContact($this->contact_id);
    }

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
         * Handle product entry save
         * @event product_save
         *
         * @param array[string]mixed $params
         * @param array[string][string]mixed $params['data'] raw product data fields (see shop_product table description and related storages)
         * @param array[string][string]int $data['data']['id'] product ID
         * @param array[string]shopProduct $params['instance'] current shopProduct entry instance (avoid recursion)
         * @return void
         */
        wa()->event('product_save', $params);
        return $result;
    }

    protected function saveData(&$errors = array())
    {
        foreach ($this->is_dirty as $field => $v) {
            if ($storage = $this->getStorage($field)) {
                $this->data[$field] = $storage->setData($this, $this->data[$field]);
            }
        }
    }

    /**
     * @param string $name
     * @return mixed
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

    private function preloadExtInfo($ext_info)
    {
        foreach ($ext_info as $name) {
            if (!isset($this->data[$name])) {
                $this->__get($name);
            }
        }
    }

    /**
     * @param string|null $name
     *   If $name is comma-separated enumeration of fields, than preloading corresponding data first
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
     * @param string $name
     * @param mixed $value
     * @return mixed
     */
    public function __set($name, $value)
    {
        return $this->setData($name, $value);
    }

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
     * Folder of product files
     * @param int $product_id
     * @return string
     */
    public static function getFolder($product_id)
    {
        $str = str_pad($product_id, 4, '0', STR_PAD_LEFT);
        return 'products/'.substr($str, -2).'/'.substr($str, -4, 2);
    }

    /**
     * Path of product file
     * @param int $product_id
     * @param string $file subpath of the file
     * @param bool $public
     * @return string
     */
    public static function getPath($product_id, $file = null, $public = false)
    {
        $path = self::getFolder($product_id)."/$product_id/".($file ? ltrim($file, '/') : '');
        return wa()->getDataPath($path, $public, 'shop');
    }

    public function getType()
    {
        $model = new shopTypeModel();
        return $this->type_id ? $model->getById($this->type_id) : null;
    }

    public function upSelling($limit = 5)
    {
        $upselling = $this->getData('upselling');
        // upselling on (usign similar settting for type)
        if ($upselling == 1 || $upselling === null) {
            $type_upselling_model = new shopTypeUpsellingModel();
            $conditions = $type_upselling_model->getByField('type_id', $this->getData('type_id'), true);
            if ($conditions) {
                $collection = new shopProductsCollection('upselling/'.$this->getId(), array('product' => $this, 'conditions' => $conditions));
                return $collection->getProducts('*', $limit);
            } else {
                return array();
            }
        } elseif (!$upselling) {
            return array();
        } // upselling on (manually)
        else {
            $collection = new shopProductsCollection('related/upselling/'.$this->getId());
            return $collection->getProducts('*', $limit);
        }
    }

    public function crossSelling($limit = 5)
    {
        $cross_selling = $this->getData('cross_selling');
        // upselling on (usign similar settting for type)
        if ($cross_selling == 1 || $cross_selling === null) {
            $type = $this->getType();
            if ($type['cross_selling']) {
                $collection = new shopProductsCollection($type['cross_selling'].($type['cross_selling'] == 'alsobought' ? '/'.$this->getId() : ''));
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
            return $collection->getProducts('*', $limit);
        }
    }

    /**
     * Check current user rights to product with its type id
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
