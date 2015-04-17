<?php

class shopProductBadgeDeleteController extends waJsonController
{
    public function execute()
    {
        $product_model = new shopProductModel();
        $hash = waRequest::post('hash', '', 'string');
        if ($hash) {
            $offset = 0;
            $count = 100;
            $collection = new shopProductsCollection($hash);
            $total_count = $collection->count();
            while ($offset < $total_count) {
                $product_ids = array_keys($collection->getProducts('*', $offset, $count));
                // !!! check access rights?..
                $product_model->updateById($product_ids, array('badge' => null));
                $offset += count($product_ids);
            }
        } else {
            $id = waRequest::request('product_id', array(), waRequest::TYPE_ARRAY_INT);
            if ($id) {
                // !!! check access rights?..
            } else {
                $id = waRequest::get('id', null, waRequest::TYPE_INT);
                $product = $product_model->getById($id);
                if (!$product) {
                    $this->errors[] = _w("Unknown product");
                    return;
                }
                if (!$product_model->checkRights($product)) {
                    throw new waException(_w("Access denied"));
                }
            }
            $product_model->updateById($id, array('badge' => null));
        }
    }
}

