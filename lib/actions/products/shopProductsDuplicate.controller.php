<?php

class shopProductsDuplicateController extends waJsonController
{
    public function execute()
    {
        $hash = shopProductsAddToCategoriesController::getHash();
        $new_ids = array();
        if (!$hash) {
            $product_ids = waRequest::post('product_id', array(), waRequest::TYPE_ARRAY_INT);
            if (!$product_ids) {
                return;
            }
            if (!$this->checkProductRights($product_ids)) {
                return $this->errors = [
                    'code'    => 403,
                    'message' => _w('Insufficient access rights to edit selected products.'),
                ];
            }
            // add just selected products
            foreach ($product_ids as $id) {
                $p = new shopProduct($id);
                $new_p = $p->duplicate(array(), $this->errors);
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
            $collection->orderBy('create_datetime');
            $offset = max(0, waRequest::post('offset', 0, waRequest::TYPE_INT));
            $limit = min(100, max(1, waRequest::post('limit', 1, waRequest::TYPE_INT)));
            $total_count = $collection->count();
            while ($offset < $total_count) {
                $limit = min($limit, $total_count - $offset);
                $product_ids = array_keys($collection->getProducts('id,name,url', $offset, $limit));
                if (!$product_ids) {
                    break;
                }
                if (!$this->checkProductRights($product_ids)) {
                    return $this->errors = [
                        'code'    => 403,
                        'message' => _w('Insufficient access rights to edit selected products.'),
                    ];
                }
                foreach ($product_ids as $id) {
                    $p = new shopProduct($id);
                    $new_p = $p->duplicate(array(), $this->errors);
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

        $count_all_new_products = count($new_ids);
        if ($count_all_new_products > 1) {
            for ($offset = 0; $offset < $count_all_new_products; $offset += 5000) {
                $part_new_products = array_slice($new_ids, $offset, 5000);
                $this->logAction('products_duplicate', count($part_new_products) . '$' . implode(',', $part_new_products));
            }
        } elseif (isset($new_ids[0]) && is_numeric($new_ids[0])) {
            $this->logAction('product_duplicate', $new_ids[0]);
        }
    }

    protected function checkProductRights($product_ids)
    {
        if (empty($product_ids) || wa()->getUser()->isAdmin('shop')) {
            return true;
        }

        $product_model = new shopProductModel();
        foreach ($product_ids as $product_id) {
            try {
                if (!$product_model->checkRights($product_id)) {
                    return false;
                }
            } catch (waException $e) {
                return false;
            }
        }

        return true;
    }

}
