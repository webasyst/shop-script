<?php

class shopProductsDeleteFromCategoryController extends waJsonController
{
    public function execute()
    {
        if (!$this->getUser()->getRights('shop', 'setscategories')) {
            throw new waRightsException(_w('Access denied'));
        }

        $model = new shopCategoryProductsModel();
        $hash = shopProductsAddToCategoriesController::getHash();
        $category_id = waRequest::get('id');

        if (!$hash) {
            $all_product_ids = waRequest::post('product_id', array(), waRequest::TYPE_ARRAY_INT);
            $hash = 'id/'.join(',', $all_product_ids);
        }

        $collection = new shopProductsCollection($hash);
        $processed = 0;
        $count = 100;
        $total_count = $collection->count();
        $all_updated_products = [];
        while ($processed < $total_count) {
            $offset = $total_count - $count - $processed < 0 ? 0 : $total_count - $count - $processed;
            $product_ids = array_keys($collection->getProducts('id', $offset, $count));
            if (!$product_ids) {
                break;
            }
            $model->deleteProducts($category_id, $product_ids);
            $all_updated_products = array_merge($all_updated_products, $product_ids);
            $processed += count($product_ids);
        }
        $count_all_updated_products = count($all_updated_products);
        if ($count_all_updated_products > 1) {
            for ($offset = 0; $offset < $count_all_updated_products; $offset += 5000) {
                $part_updated_products = array_slice($all_updated_products, $offset, 5000);
                $this->logAction('products_edit', count($part_updated_products) . '$' . implode(',', $part_updated_products));
            }
        } elseif (isset($all_updated_products[0]) && is_numeric($all_updated_products[0])) {
            $this->logAction('product_edit', $all_updated_products[0]);
        }
    }
}
