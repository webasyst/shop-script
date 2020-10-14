<?php

class shopCart
{
    const COOKIE_KEY = 'shop_cart';
    protected $code;

    /**
     * Constructor
     * @param string $code Cart unique ID
     *
     * @param array $options
     *   - bool $options['merge_carts'] Merge authorized user cart with current guess cart (one time only, save flag in session).
     *              Default value gets from general settings
     *
     * @throws waException
     */
    public function __construct($code = '', $options = array())
    {
        $options = is_array($options) ? $options : array();

        if (!array_key_exists('merge_carts', $options)) {
            $need_merge_carts = (bool)wa('shop')->getConfig()->getGeneralSettings('merge_carts');
        } else {
            $need_merge_carts = (bool)$options['merge_carts'];
        }

        $cookie_expire_time = time() + 30 * 86400;

        $user = wa()->getUser();
        $is_auth = $user->isAuth();

        $this->code = waRequest::cookie(self::COOKIE_KEY, $code);
        if (!$this->code) {
            if ($is_auth) {
                $code = $this->model()->getLastCode($user->getId());
                if ($code) {
                    $this->code = $code;
                }
            }
            if (!$this->code && !empty($options['generate_code'])) {
                $this->code = self::generateCode();
            }
            if ($this->code) {
                wa()->getResponse()->setCookie(self::COOKIE_KEY, $this->code, $cookie_expire_time, null, '', false, true);
                $this->clearSessionData();
            }
        } else {

            // Merge guest cart into cart of authorized user biz logic. Merge one time (save in session 'merged' flag)
            if ($need_merge_carts && $is_auth && !$this->getSessionData('merged')) {

                // cart of authorized user is destination cart
                $dst_cart_code = $this->model()->getLastCode($user->getId());

                // There is no cart of authorized user, bind these guest cart to authorized user
                if (!$dst_cart_code) {

                    $this->model()->updateByField(array('code' => $this->code), array(
                        'contact_id' => $user->getId()
                    ));
                    $this->clearSessionData();

                } else {

                    // if current cart code authorized user's cart code - just skip merging (means is all merged already)
                    if ($dst_cart_code !== $this->code) {

                        // merge guest cart into cart of authorized user
                        $this->mergeCarts($this->code, $dst_cart_code);

                        // replace guest cart code with code of cart of authorized user
                        wa()->getResponse()->setCookie(self::COOKIE_KEY, $dst_cart_code, $cookie_expire_time, null, '', false, true);
                        $this->clearSessionData();

                        $this->code = $dst_cart_code;
                    }
                }

                $this->setSessionData('merged', true);
            }

        }

        if ($this->code) {
            // Shop cart is a dynamic content.
            // Send proper Last-Modified header to make sure browser understands that.
            wa()->getResponse()->setLastModified(date('Y-m-d H:i:s'));
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
     * @throws waException
     */
    protected function getSessionData($key, $default = null)
    {
        $data = wa()->getStorage()->get('shop/cart');
        return isset($data[$key]) ? $data[$key] : $default;
    }

    /**
     * @param string $key
     * @param mixed $value
     * @throws waException
     */
    protected function setSessionData($key, $value)
    {
        $data = wa()->getStorage()->get('shop/cart');
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
     * @throws waException
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
     * @throws waException
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

        $rounding_enabled = shopRounding::isEnabled('services');
        $frontend_currency = wa('shop')->getConfig()->getCurrency(false);

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
            if ($rounding_enabled && $v['currency'] != $frontend_currency) {
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

    /**
     * Merge guess cart into contact's cart
     * After merging source cart will be deleted
     *
     * @param string $src_cart_code - Code of source cart
     * @param string $dst_cart_code - Code of source cart where need to merge into source cart
     * @throws waException
     */
    protected function mergeCarts($src_cart_code, $dst_cart_code)
    {
        $src_cart_items = $this->model()->getByCode($src_cart_code, false, true);
        $dst_cart_items = $this->model()->getByCode($dst_cart_code, false, true);

        $contact_id = null;
        if ($dst_cart_items) {
            $cart_item = reset($dst_cart_items);
            $contact_id = $cart_item['contact_id'];
        }

        $new_dst_cart_items = $this->mergeCartItems($src_cart_items, $dst_cart_items, $dst_cart_code, $contact_id);
        $new_dst_cart_items = $this->flattenCartItemList($new_dst_cart_items, true);

        $this->model()->deleteByField(array('code' => array($src_cart_code, $dst_cart_code)));
        $this->model()->multipleInsert($new_dst_cart_items);

    }

    /**
     * @param array $src_cart_items - Cart items in hierarchy format (see shopCartItemsModel->getByCode), source items for merge
     * @param array $dst_cart_items - Cart items in hierarchy format (see shopCartItemsModel->getByCode), destination list where merge into
     * @param string $new_cart_code - Cart code for items of result list
     * @param int $new_contact_id - Contact ID for items of result list
     * @return array
     */
    protected function mergeCartItems($src_cart_items, $dst_cart_items, $new_cart_code, $new_contact_id)
    {
        // Found equals items and update quantity
        foreach ($dst_cart_items as &$dst_cart_item) {
            foreach ($src_cart_items as $src_cart_item_id => $src_cart_item) {

                if (!$this->isCartItemsEqual($src_cart_item, $dst_cart_item)) {
                    continue;
                }

                // update quantity of sku
                $quantity = $src_cart_item['quantity'];
                $dst_cart_item['quantity'] += $quantity;

                // update quantity of services
                if (isset($dst_cart_item['services'])) {
                    foreach ($dst_cart_item['services'] as &$service) {
                        $service['quantity'] += $quantity;
                    }
                    unset($service);
                }

                // not take into account this item in further logic
                unset($src_cart_items[$src_cart_item_id]);
            }
        }
        unset($dst_cart_item);

        // Add items to dst cart
        foreach ($src_cart_items as $src_cart_item) {
            $dst_cart_items[$src_cart_item['id']] = $src_cart_item;
        }

        // Update cart code and contact ID
        foreach ($dst_cart_items as &$cart_item) {

            $cart_item['code'] = $new_cart_code;
            $cart_item['contact_id'] = $new_contact_id;

            if (isset($cart_item['services'])) {
                foreach ($cart_item['services'] as &$service) {
                    $service['code'] = $new_cart_code;
                    $service['contact_id'] = $new_contact_id;
                }
                unset($service);
            }
        }
        unset($cart_item);

        return $dst_cart_items;
    }

    protected function flattenCartItemList($cart_items, $reset_keys = false)
    {
        if (!is_array($cart_items)) {
            return array();
        }

        // Flatten items
        $flat_cart_items = array();
        foreach ($cart_items as $cart_item) {

            $services = isset($cart_item['services']) ? $cart_item['services'] : array();
            unset($cart_item['services']);

            $flat_cart_items[$cart_item['id']] = $cart_item;

            foreach ($services as $service) {
                $flat_cart_items[$service['id']] = $service;
            }
        }
        if ($reset_keys) {
            $flat_cart_items = array_values($flat_cart_items);
        }
        return $flat_cart_items;
    }

    /**
     * Helper for merge cart items
     * It is not about deep equality. It is about sku_id - service_variant_id structure equality
     * Cart items in hierarchy format (see shopCartItemsModel->getByCode)
     * @see shopCartItemsModel::getByCode()
     * @param array $item1 - Product cart item in hierarchy format (see shopCartItemsModel->getByCode)
     * @param array $item2 - Product cart item in hierarchy format (see shopCartItemsModel->getByCode)
     * @return bool
     */
    protected function isCartItemsEqual($item1, $item2)
    {
        if (!is_array($item1) || !is_array($item2)) {
            return false;
        }

        if ($item1['sku_id'] !== $item2['sku_id']) {
            return false;
        }

        if (!isset($item1['services'])) {
            $item1['services'] = array();
        }

        if (!isset($item2['services'])) {
            $item2['services'] = array();
        }

        if (!is_array($item1['services']) || !is_array($item2['services'])) {
            return false;
        }

        if (count($item1['services']) != count($item2['services'])) {
            return false;
        }

        if (!$item1['services']) {
            return true;
        }

        $item1_service_variant_ids = waUtils::getFieldValues($item1['services'], 'service_variant_id');
        $item2_service_variant_ids = waUtils::getFieldValues($item2['services'], 'service_variant_id');

        sort($item1_service_variant_ids, SORT_NUMERIC);
        sort($item2_service_variant_ids, SORT_NUMERIC);

        return $item1_service_variant_ids === $item2_service_variant_ids;
    }
}
