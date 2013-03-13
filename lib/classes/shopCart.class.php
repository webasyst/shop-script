<?php

class shopCart
{
    const COOKIE_KEY = 'shop_cart';
    protected $code;
    /**
     * @var shopCartItemsModel
     */
    protected $model;

    public function __construct()
    {
        $this->code = waRequest::cookie(self::COOKIE_KEY);
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
            return $this->model->total($this->code);
        }
        $total = $this->getSessionData('total');
        if ($total === null) {
            $total = $this->model->total($this->code);
            $order = array(
                'total' => $total
            );
            $discount = shopDiscounts::calculate($order);
            $total = $total - $discount;
            $this->setSessionData('total', $total);
        }
        return $total;
    }

    public function discount()
    {
        $total = $this->model->total($this->code);
        $order = array(
            'total' => $total
        );
        return shopDiscounts::calculate($order);
    }

    public function count()
    {
        return $this->model->count($this->code, 'product');
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
    }
}
