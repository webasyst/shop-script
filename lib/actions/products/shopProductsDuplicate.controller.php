<?php

class shopProductsDuplicateController extends waJsonController
{
    public function execute()
    {
        $hash = waRequest::post('hash', '');
        $new_ids = array();
        if (!$hash) {
            $product_ids = waRequest::post('product_id', array(), waRequest::TYPE_ARRAY_INT);
            if (!$product_ids) {
                return;
            }
            // add just selected products
            foreach ($product_ids as $id) {
                $p = new shopProduct($id);
                $new_p = $p->duplicate();
                if ($new_p) {
                    $new_ids[] = $new_p->getId();
                }
            }
            $this->response = array(
                'total_count' => count($product_ids),
                'offset'      => count($product_ids),
            );
        } else {
            // add all products of collection with this hash
            $collection = new shopProductsCollection($hash);
            $offset = max(0, waRequest::post('offset', 0, waRequest::TYPE_INT));
            $limit = min(100, max(1, waRequest::post('limit', 1, waRequest::TYPE_INT)));
            $total_count = $collection->count();
            while ($offset < $total_count) {
                $product_ids = array_keys($collection->getProducts('id,name,url', $offset, $limit));
                foreach ($product_ids as $id) {
                    $p = new shopProduct($id);
                    $new_p = $p->duplicate();
                    if ($new_p) {
                        $new_ids[] = $new_p->getId();
                    }
                }
                $offset += count($product_ids);
            }
            $this->response = array(
                'total_count' => $total_count,
                'offset'      => $offset,
                'new_ids'     => $new_ids,
            );
        }
    }

}