<?php

class shopProductAddToListController extends waJsonController
{
    public function execute()
    {
        $hash = $this->getHash();    // hash that identify 'destination' list (settings of which is changing)
        if ($hash) {
            list($type, $list_id) = $hash;
            $this->addProducts($list_id, $type);
            $model = $this->getModel($type);
            if ($model) {
                $this->response = $model->getById($list_id);
                if ($type == 'category') {
                    $this->response['total_count'] = $model->getTotalProductsCount($list_id);
                }
            }
        }
    }

    public function getHash()
    {
        $category_id = waRequest::get('category_id', null, waRequest::TYPE_INT);
        if ($category_id) {
            return array('category', $category_id);
        }
        $set_id = waRequest::get('set_id', null, waRequest::TYPE_STRING_TRIM);
        if ($set_id) {
            return array('set', $set_id);
        }
        return null;
    }

    public function addProducts($id, $type)
    {
        $model = $this->getProductsModel($type);
        if ($model) {
            $hash = waRequest::post('hash', '');    // hash of 'source' list (which provides products)
            if (!$hash) {
                $product_ids = waRequest::post('product_id', array(), waRequest::TYPE_ARRAY_INT);
                if (!$product_ids) {
                    return;
                }
                $model->add($product_ids, $id);
            } else {
                $collection = new shopProductsCollection($hash);
                $offset = 0;
                $count = 100;
                $total_count = $collection->count();
                while ($offset < $total_count) {
                    $product_ids = array_keys($collection->getProducts('*', $offset, $count));
                    $model->add($product_ids, $id);
                    $offset += count($product_ids);
                }
            }
        }
    }
    public function getModel($type)
    {
        if ($type == 'category') {
            return new shopCategoryModel();
        }
        if ($type == 'set') {
            return new shopSetModel();
        }
        return null;
    }

    public function getProductsModel($type)
    {
        if ($type == 'category') {
            return new shopCategoryProductsModel();
        }
        if ($type == 'set') {
            return new shopSetProductsModel();
        }
        return null;
    }
}
