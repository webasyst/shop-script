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
            $hash = shopProductsAddToCategoriesController::getHash();    // hash of 'source' list (which provides products)
            if (!$hash) {
                $product_ids = waRequest::post('product_id', array(), waRequest::TYPE_ARRAY_INT);
                if (!$product_ids) {
                    return;
                }
                if ($type == 'set') {
                    $this->addToSetEvent($id, $hash, $product_ids);
                }
                $model->add($product_ids, $id);
                if ($type == 'set') {
                    $this->addToSetEvent($id, $hash, $product_ids, 'after');
                }
            } else {
                $collection = new shopProductsCollection($hash);
                $offset = 0;
                $count = 100;
                $total_count = $collection->count();
                while ($offset < $total_count) {
                    $product_ids = array_keys($collection->getProducts('*', $offset, $count));
                    if ($type == 'set') {
                        $this->addToSetEvent($id, $hash, $product_ids);
                    }
                    $model->add($product_ids, $id);
                    if ($type == 'set') {
                        $this->addToSetEvent($id, $hash, $product_ids, 'after');
                    }
                    $offset += count($product_ids);
                    if (!$product_ids) {
                        break;
                    }
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

    protected function addToSetEvent($id, $hash, $product_ids, $type = 'before')
    {
        /**
         * Attaches a product to the sets
         *
         * @param array $id
         * @param string $hash
         * @param array|string $product_ids products_id
         */
        $params = [
            'set_ids' => $id,
            'hash' => $hash,
            'products_id' => $product_ids,
        ];
        wa('shop')->event('products_add_sets.'.$type, $params);
    }
}
