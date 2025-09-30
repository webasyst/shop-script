<?php

class shopApiCart
{
    protected $token;
    protected $cart_items_model;

    /**
     * Constructor
     * @param string $token Cart unique ID
     *
     * @throws waException
     */
    public function __construct($token)
    {
        $this->token = (string) $token;
        $this->cart_items_model = new shopCartItemsModel();
    }

    /**
     * Returns current shopping cart's unique id.
     * @return string
     */
    public function getToken()
    {
        return $this->token;
    }

    /**
     * @return string
     */
    public static function generateToken()
    {
        return md5(uniqid(mt_rand().mt_rand().mt_rand().mt_rand(), true));
    }

    public static function getAntispamCartKey($customer_token, $timestamp=null)
    {
        if (!$customer_token || !wa()->getSetting('headless_api_antispam_enabled', false, 'shop')) {
            return null;
        }
        $antispam_key = wa()->getSetting('headless_api_antispam_key', '', 'shop');
        if (!$antispam_key) {
            $antispam_key = shopApiActions::generateAntispamKey();
            (new waAppSettingsModel())->set('shop', 'headless_api_antispam_key', $antispam_key);
        }
        if (!$timestamp) {
            $timestamp = time();
        }
        return md5($antispam_key.'shop-api-antispam'.$customer_token.date('YmdH', $timestamp));
    }

    /**
     * Adds a new entry to table 'shop_cart_items'
     *
     * @param $item
     * @return int New cart item id
     * @throws waException
     */
    public function addItem($item)
    {
        if (!isset($item['create_datetime'])) {
            $item['create_datetime'] = date('Y-m-d H:i:s');
        }
        $item['code'] = $this->token;
        $item['contact_id'] = null;
        $item['id'] = $this->cart_items_model->insert($item);

        return $item['id'];
    }

    public function setQuantity($item_id, $quantity)
    {
        if ($item = $this->cart_items_model->getByField(['code' => $this->token, 'id' => $item_id])) {
            if ($quantity > 0) {
                $this->cart_items_model->updateByField([
                    'code' => $this->token,
                    'id'   => $item['id'],
                ], ['quantity' => $quantity]);

                if ($item['type'] == 'product') {
                    $this->cart_items_model->updateByField([
                        'code'      => $this->token,
                        'parent_id' => $item['id'],
                    ], ['quantity' => $quantity]);
                }
            } else {
                $this->cart_items_model->deleteByField(['code' => $this->token, 'id' => $item['id']]);
                $this->cart_items_model->deleteByField(['code' => $this->token, 'parent_id' => $item['id']]);
            }
        }
    }

    public function getItem($id)
    {
        if ($id) {
            return $this->cart_items_model->getItem($this->token, $id);
        }

        return [];
    }

    public function getItems()
    {
        $items = $this->cart_items_model->getByCode($this->token, false, false);

        return array_values($items);
    }

    public function getTotal()
    {
        return $this->cart_items_model->total($this->token);
    }

    /**
     * Removes all items from current customer's shopping cart
     */
    public function clear()
    {
        $this->cart_items_model->deleteByField('code', $this->token);
    }
}
