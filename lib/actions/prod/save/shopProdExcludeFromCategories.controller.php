<?php

class shopProdExcludeFromCategoriesController extends waJsonController
{
    public function execute()
    {
        if (!$this->getUser()->getRights('shop', 'setscategories')) {
            $this->errors = [
                'id' => 'access_denied',
                'text' => _w('Access denied'),
            ];
        }

        $product_ids = waRequest::post('product_ids', [], waRequest::TYPE_ARRAY_INT);
        $category_ids = waRequest::post('category_ids', [], waRequest::TYPE_ARRAY_INT);
        $presentation_id = waRequest::post('presentation_id', null, waRequest::TYPE_INT);
        $this->validateData($product_ids, $category_ids, $presentation_id);
        if (!$this->errors) {
            if ($presentation_id) {
                $presentation = new shopPresentation($presentation_id, true);
                $options = [];
                if ($presentation->getFilterId() > 0) {
                    $options['prepare_filter'] = $presentation->getFilterId();
                    $options['exclude_products'] = $product_ids;
                }
                $collection = new shopProductsCollection('', $options);
                $product_ids = $presentation->getProducts($collection, [
                    'fields' => ['id'],
                    'offset' => max(0, waRequest::post('offset', 0, waRequest::TYPE_INT)),
                ]);
                $product_ids = array_keys($product_ids);
            }

            if ($product_ids) {
                $this->exclude($product_ids, $category_ids);
            }

            $this->response = [
                'product_ids' => $product_ids,
                'category_ids' => $category_ids
            ];
        }
    }

    protected function validateData($product_ids, $category_ids, $presentation_id)
    {
        if (!$category_ids) {
            $this->errors = [
                'id' => 'empty_categories',
                'text' => _w('Product categories not found.')
            ];
        } elseif (!$presentation_id && !$product_ids) {
            $this->errors = [
                'id' => 'empty_products',
                'text' => _w('Products not specified.')
            ];
        }
    }

    protected function exclude($product_ids, $category_ids)
    {
        $category_products_model = new shopCategoryProductsModel();
        $category_products_model->deleteProducts($category_ids, $product_ids);
        $product_model = new shopProductModel();
        $product_model->correctMainCategory($product_ids);
        $category_model = new shopCategoryModel();
        $category_model->recount($category_ids);
    }
}
