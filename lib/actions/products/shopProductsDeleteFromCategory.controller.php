<?php

class shopProductsDeleteFromCategoryController extends waJsonController
{
    public function execute()
    {
        if (!$this->getUser()->getRights('shop', 'setscategories')) {
            throw new waRightsException(_w('Access denied'));
        }

        $model = new shopCategoryProductsModel();
        $hash = waRequest::post('hash', '');
        $category_id = waRequest::get('id');

        if (!$hash) {
            $all_product_ids = waRequest::post('product_id', array(), waRequest::TYPE_ARRAY_INT);
            $hash = 'id/'.join(',', $all_product_ids);
        }

        $collection = new shopProductsCollection($hash);
        $offset = 0;
        $count = 100;
        $total_count = $collection->count();
        $all_updated_products = [];
        while ($offset < $total_count) {
            $product_ids = array_keys($collection->getProducts('*', $offset, $count));
            $model->deleteProducts($category_id, $product_ids);
            $all_updated_products += $product_ids;
            $offset += count($product_ids);
            if (!$product_ids) {
                break;
            }
        }
        if (count($all_updated_products) > 1) {
            $this->logAction('products_edit', count($all_updated_products) . '$' . implode(',', $all_updated_products));
        } elseif (isset($all_updated_products[0]) && is_numeric($all_updated_products[0])) {
            $this->logAction('product_edit', $all_updated_products[0]);
        }
    }
}
