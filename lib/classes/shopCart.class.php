<?php

class shopCart
{
    const COOKIE_KEY = 'shop_cart';
    protected $code;

    /**
     * Constructor
     * @param string $code Cart unique ID
     */
    public function __construct($code = '', $options = array())
    {
        $this->code = waRequest::cookie(self::COOKIE_KEY, $code);
        if (!$this->code) {
            if (wa()->getUser()->isAuth()) {
                $code = $this->model()->getLastCode(wa()->getUser()->getId());
                if ($code) {
                    $this->code = $code;
                }
            }
            if (!$this->code && !empty($options['generate_code'])) {
                $this->code = self::generateCode();
            }
            if ($this->code) {
                wa()->getResponse()->setCookie(self::COOKIE_KEY, $this->code, time() + 30 * 86400, null, '', false, true);
                $this->clearSessionData();
            }
        }
    }

    /**
     * Returns current shopping cart's unique id.
     *
     * @return string
     */
    public function getCode()
    {
        return $this->code;
    }

    public static function generateCode()
    {
        return md5(uniqid(mt_rand().mt_rand().mt_rand().mt_rand(), true));
    }

    /**
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    protected function getSessionData($key, $default = null)
    {
        $data = wa()->getStorage()->get('shop/cart');
        return isset($data[$key]) ? $data[$key] : $default;
    }

    /**
     * @param string $key
     * @param mixed $value
     */
    protected function setSessionData($key, $value)
    {
        $data = wa()->getStorage()->get('shop/cart', array());
        $data[$key] = $value;
        wa()->getStorage()->set('shop/cart', $data);
    }

    public function clearSessionData()
    {
        wa()->getStorage()->remove('shop/cart');
    }

    /**
     * Returns total cost of current shopping cart's items, expressed in default currency.
     *
     * @param bool $discount Whether applicable discounts must be taken into account
     * @return int
     */
    public function total($discount = true)
    {
        if (!$discount) {
            return (float)$this->model()->total($this->code);
        }
        $total = $this->getSessionData('total');
        if ($total === null) {
            $total = $this->model()->total($this->code);
            if ($total > 0) {
                $order = array(
                    'currency' => wa('shop')->getConfig()->getCurrency(false),
                    'total'    => $total,
                    'items'    => $this->items(false)
                );
                $discount = shopDiscounts::calculate($order);
                $total = $total - $discount;
            }
            if ($this->code) {
                $this->setSessionData('total', $total);
            }
        }
        return (float)$total;
    }

    /**
     * Returns discount applicable to current customer's shopping cart contents, expressed in default currency.
     *
     * @param $order
     * @return float
     */
    public function discount(&$order = array())
    {
        $total = $this->model()->total($this->code);
        $order = array(
            'currency' => wa('shop')->getConfig()->getCurrency(false),
            'total'    => $total,
            'items'    => $this->items(false)
        );
        return shopDiscounts::calculate($order);
    }

    /**
     * Returns number of items in current customer's shopping cart
     *
     * @return int
     */
    public function count()
    {
        return (int)$this->model()->count($this->code, 'product');
    }

    /**
     * Returns information about current shopping cart's items.
     *
     * @param bool $hierarchy Whether selected services must be included as 'services' sub-array for applicable items.
     *     If false, services are included as separate array items.
     * @return array
     */
    public function items($hierarchy = true)
    {
        return $this->model()->getByCode($this->code, true, $hierarchy);
    }

    /**
     * Changes quantity for current shopping cart's item with specified id.
     *
     * @param int $item_id Item id
     * @param int $quantity New quantity
     */
    public function setQuantity($item_id, $quantity)
    {
        if ($quantity > 0) {
            $this->updateItem($item_id, [
                'quantity' => $quantity,
            ]);
        }
    }

    /**
     * Update quantity and sku_id for product item,
     * or variant_id for service item.
     *
     * @param int $item_id Item id
     * @param array
     */
    public function updateItem($item_id, $data)
    {
        if (!is_array($data) || !wa_is_int($item_id)) {
            return;
        }

        // Do not allow to change product or service of an item.
        // Have to delete the whole item and create anew to do that.
        $data = array_intersect_key($data, [
            //'product_id' => 1, // nope
            'sku_id'             => 1,
            //'service_id' => 1, // nope
            'service_variant_id' => 1,
            'quantity'           => 1,
        ]);

        if ($data) {
            $item = $this->model()->getById($item_id);
            if (!$item || $item['code'] != $this->code) {
                return;
            }
            if ($item['type'] == 'product') {
                unset($data['service_variant_id']);
            } else {
                unset($data['quantity'], $data['sku_id']);
            }
        }
        if (!$data) {
            return;
        }

        $this->model()->updateByField([
            'code' => $this->code,
            'id'   => $item_id,
        ], $data);

        if ($item['type'] == 'product') {
            $this->model()->updateByField([
                'code'      => $this->code,
                'parent_id' => $item_id,
            ], $data);
        }

        wa('shop')->event('cart_update', ref([
            'item'     => $data + $item,
            'old_item' => $item,
            'update'   => $data,
        ]));

        $this->setSessionData('total', null);
    }

    /**
     * Changes 'service_variant_id' value for current shopping cart's item with specified id.
     *
     * @param int $item_id
     * @param int $variant_id
     */
    public function setServiceVariantId($item_id, $variant_id)
    {
        $this->updateItem($item_id, [
            'service_variant_id' => $variant_id,
        ]);
    }

    /**
     * Adds a new entry to table 'shop_cart_items'
     *
     * @param array $item Cart item data array
     * @param array $services
     * @return int New cart item id
     */
    public function addItem($item, $services = array())
    {
        if (!isset($item['create_datetime'])) {
            $item['create_datetime'] = date('Y-m-d H:i:s');
        }
        $item['code'] = $this->code;
        $item['contact_id'] = wa()->getUser()->getId();
        $item['id'] = $this->model()->insert($item);

        // add services
        if (($item['type'] == 'product') && $services) {
            foreach ($services as $s) {
                $s['parent_id'] = $item['id'];
                $s['type'] = 'service';
                foreach (array('code', 'contact_id', 'product_id', 'sku_id', 'create_datetime') as $k) {
                    $s[$k] = $item[$k];
                }
                if (!empty($item['quantity'])) {
                    $s['quantity'] = $item['quantity'];
                }
                $s['id'] = $this->model()->insert($s);
                $item['services'][] = $s;
            }
        }
        // clear session cache
        $this->clearSessionData();

        /**
         * @event cart_add
         * @param array $item
         */
        wa('shop')->event('cart_add', $item);
        return $item['id'];
    }

    /**
     * Returns data array of current shopping cart's item with specified id.
     *
     * @param int $item_id
     * @return array
     */
    public function getItem($item_id)
    {
        return $this->model()->getItem($this->code, $item_id);
    }

    /**
     * Returns total cost of current shopping cart's item with specified id, expressed in default currency.
     *
     * @param int|array $item_id Item id or item data array.
     * @return float
     */
    public function getItemTotal($item_id)
    {
        if (is_array($item_id)) {
            $item_id = $item_id['id'];
            $item = $this->getItem($item_id);
        } else {
            // this gives price already rounded for frontend
            $item = $this->getItem($item_id);
        }

        $cart_items_model = new shopCartItemsModel();
        $items = $cart_items_model->getByField('parent_id', $item['id'], true);
        $price = shop_currency($item['price'], $item['currency'], null, false) * $item['quantity'];
        if (!$items) {
            return $price;
        }

        $variants = array();
        foreach ($items as $s) {
            $variants[] = $s['service_variant_id'];
        }

        $product_services_model = new shopProductServicesModel();
        $sql = "SELECT v.id, s.currency, ps.sku_id, ps.price, v.price base_price
                    FROM shop_service_variants v
                        LEFT JOIN shop_product_services ps
                            ON v.id = ps.service_variant_id
                                AND ps.product_id = i:0
                                AND (ps.sku_id = i:1 OR ps.sku_id IS NULL)
                        JOIN shop_service s
                            ON v.service_id = s.id
                WHERE v.id IN (i:2)
                ORDER BY ps.sku_id";
        $rows = $product_services_model->query($sql, $item['product_id'], $item['sku_id'], $variants)->fetchAll();
        $prices = array();
        foreach ($rows as $row) {
            if (!isset($prices[$row['id']]) || $row['price']) {
                if ($row['price'] === null) {
                    $row['price'] = $row['base_price'];
                }
                $prices[$row['id']] = $row;
            }
        }

        $rounding_enabled = shopRounding::isEnabled();
        $frontend_currency = wa('shop')->getConfig()->getCurrency(false);
        $round_services = wa()->getSetting('round_services');

        foreach ($items as $s) {
            if (!isset($prices[$s['service_variant_id']])) {
                continue;
            }
            $v = $prices[$s['service_variant_id']];
            if ($v['currency'] == '%') {
                $v['price'] = $v['price'] * $item['price'] / 100;
                $v['currency'] = $item['currency'];
            }

            $service_price = shop_currency($v['price'], $v['currency'], $frontend_currency, false);
            if ($rounding_enabled && (($v['currency'] != $frontend_currency) || ($round_services))) {
                $service_price = shopRounding::roundCurrency($service_price, $frontend_currency);
            }

            $price += $service_price * $item['quantity'];
        }
        return $price;
    }

    /**
     * Removes all items from current customer's shopping cart
     */
    public function clear()
    {
        $this->model()->deleteByField('code', $this->code);
        wa()->getStorage()->remove('shop/cart');
    }

    /**
     * Removes item with specified id from current customer's shopping cart.
     *
     * @param int $id
     * @return array Removed item's data array
     */
    public function deleteItem($id)
    {
        $item = $this->model()->getById($id);
        if ($item) {
            if ($item['type'] == 'product') {
                // remove all services
                $this->model()->deleteByField(array('code' => $this->code, 'parent_id' => $id));
            }
            $this->model()->deleteByField(array('code' => $this->code, 'id' => $id));
            /**
             * @event cart_delete
             * @param array $item
             */
            wa('shop')->event('cart_delete', $item);
            $this->clearSessionData();
        }
        return $item;
    }

    /**
     * @return shopCartItemsModel
     */
    protected function model()
    {
        static $cart_items_model = null;
        if (!$cart_items_model) {
            $cart_items_model = new shopCartItemsModel();
        }
        return $cart_items_model;
    }
}
