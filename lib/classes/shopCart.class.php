<?php

class shopCart
{
    const COOKIE_KEY = 'shop_cart';
    protected $code;
    /**
     * @var shopCartItemsModel
     */
    protected $model;

    /**
     * Constructor
     * @param string $code Cart unique ID
     */
    public function __construct($code='')
    {
        $this->code = waRequest::cookie(self::COOKIE_KEY, $code);
        $this->model = new shopCartItemsModel();
    }

    public function getCode()
    {
        return $this->code;
    }

    protected function getSessionData($key, $default = null)
    {
        $data = wa()->getStorage()->get('shop/cart');
        return isset($data[$key]) ? $data[$key] : $default;
    }

    protected function setSessionData($key, $value)
    {
        $data = wa()->getStorage()->get('shop/cart', array());
        $data[$key] = $value;
        wa()->getStorage()->set('shop/cart', $data);
    }

    public function total($discount = true)
    {
        if (!$discount) {
            return (float) $this->model->total($this->code);
        }
        $total = $this->getSessionData('total');
        if ($total === null) {
            $total = $this->model->total($this->code);
            $order = array(
                'total' => $total,
                'items' => $this->items(false)
            );
            $discount = shopDiscounts::calculate($order);
            $total = $total - $discount;
            $this->setSessionData('total', $total);
        }
        return (float) $total;
    }

    public function discount()
    {
        $total = $this->model->total($this->code);
        $order = array(
            'total' => $total,
            'items' => $this->items(false)
        );
        return shopDiscounts::calculate($order);
    }

    public function count()
    {
        return (int)$this->model->count($this->code, 'product');
    }

    public function items($hierarchy = true)
    {
        return $this->model->getByCode($this->code, true, $hierarchy);
    }

    public function setQuantity($item_id, $quantity)
    {
        $this->model->updateByField(array('code' => $this->code, 'id' => $item_id), array('quantity' => $quantity));
        $this->model->updateByField(array('code' => $this->code, 'parent_id' => $item_id), array('quantity' => $quantity));
        $this->setSessionData('total', null);
    }

    public function setServiceVariantId($item_id, $variant_id)
    {
        $this->model->updateByField(array('code' => $this->code, 'id' => $item_id), array('service_variant_id' => $variant_id));
        $this->setSessionData('total', null);
    }

    public function addItem($item)
    {
        $item_id = $this->model->insert($item);
        $this->setSessionData('total', null);
        return $item_id;
    }

    public function getItem($item_id)
    {
        return $this->model->getItem($this->code, $item_id);
    }

    public function getItemTotal($item_id)
    {
        if (is_array($item_id)) {
            $item = $item_id;
        } else {
            $item = $this->getItem($item_id);
        }
        $cart_items_model = new shopCartItemsModel();
        $items = $cart_items_model->getByField('parent_id', $item['id'], true);
        $price = shop_currency($item['price'] * $item['quantity'], $item['currency'], null, false);
        if (!$items) {
            return $price;
        }

        $variants = array();
        foreach ($items as $s) {
            $variants[] = $s['service_variant_id'];
        }

        $product_services_model = new shopProductServicesModel();
        $sql = "SELECT v.id, s.currency, ps.sku_id, ps.price, v.price base_price FROM shop_service_variants v
                LEFT JOIN shop_product_services ps ON
                v.id = ps.service_variant_id AND ps.product_id = i:0 AND (ps.sku_id = i:1 OR ps.sku_id IS NULL)
                JOIN shop_service s ON v.service_id = s.id
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

        foreach ($items as $s) {
            $v = $prices[$s['service_variant_id']];
            if ($v['currency'] == '%') {
                $v['price'] = $v['price'] * $item['price'] / 100;
                $v['currency'] = $item['currency'];
            }
            $price += shop_currency($v['price'] * $item['quantity'], $v['currency'], null, false);
        }
        return $price;
    }

    public function clear()
    {
        $this->model->deleteByField('code', $this->code);
        wa()->getStorage()->remove('shop/cart');
    }

    public function deleteItem($id)
    {
        $item = $this->model->getById($id);
        if ($item) {
            if ($item['type'] == 'product') {
                // remove all services
                $this->model->deleteByField(array('code' => $this->code, 'parent_id' => $id));
            }
            $this->model->deleteByField(array('code' => $this->code, 'id' => $id));
            $this->setSessionData('total', null);
        }
        return $item;
    }
}
