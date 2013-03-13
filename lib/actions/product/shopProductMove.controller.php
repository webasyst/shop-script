<?php

class shopProductMoveController extends waJsonController
{
    public function execute()
    {
        $product_ids = waRequest::post('product_ids', null, waRequest::TYPE_ARRAY_INT);
        if (!$product_ids || !$product_ids[0]) {
            throw new waException("Unknown product id(s)");
        }
        $hash = $this->getHash();
        if ($hash) {
            $before_id = waRequest::post('before_id', null, waRequest::TYPE_INT);
            $this->move($hash[1], $hash[0], $product_ids, $before_id);
        }
    }

    public function getHash()
    {
        $category_id = waRequest::get('category_id', null, waRequest::TYPE_INT);
        if ($category_id) {
            return array('category', $category_id);
        }
        $set_id = waRequest::get('set_id');
        if ($set_id) {
            return array('set', $set_id);
        }
        return null;
    }

    public function move($list_id, $type, $product_ids, $before_id = null)
    {
        if ($type == 'category') {
            $this->moveInsideCategory($product_ids, $before_id, $list_id);
        }
        if ($type == 'set') {
            $this->moveInsideSet($product_ids, $before_id, $list_id);
        }
    }

    public function moveInsideCategory($product_ids, $before_id = null, $list_id)
    {
        $category_products_model = new shopCategoryProductsModel();
        $category_products_model->move($product_ids, $before_id, $list_id);
    }

    public function moveInsideSet($product_ids, $before_id = null, $list_id = 0)
    {
        $set_products_model = new shopSetProductsModel();
        $set_products_model->move($product_ids, $before_id, $list_id);
    }
}