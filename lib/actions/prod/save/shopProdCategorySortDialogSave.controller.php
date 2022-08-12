<?php

class shopProdCategorySortDialogSaveController extends waJsonController
{
    public function execute()
    {
        $category_id = waRequest::post('category_id', null, waRequest::TYPE_INT);
        $sort_products = waRequest::post('sort_products', null, waRequest::TYPE_STRING_TRIM);

        $this->validateData($category_id, $sort_products);
        if (!$this->errors) {
            if ($sort_products) {
                $products = $this->getCategoryProducts($category_id, $sort_products);
            } else {
                $products = waRequest::post('products', [], waRequest::TYPE_ARRAY_INT);
            }
            if (empty($products)) {
                $this->errors = [
                    'id' => 'empty_products',
                    'text' => _w('Products not specified')
                ];
            }
            if (!$this->errors) {
                $category_products_model = new shopCategoryProductsModel();
                $category_products_model->clearCategory($category_id);
                $response = $category_products_model->add($products, $category_id);
                if ($response !== false) {
                    $this->logAction('category_edit', $category_id);
                    $this->response['sort_products_changed'] = $products;
                } else {
                    $this->errors = [
                        'id' => 'update_fail',
                        'text' => _w('Failed to update category')
                    ];
                }
            }
        }
    }

    protected function validateData($category_id, $sort_products)
    {
        if ($sort_products) {
            $correct_sort = preg_match('/(name|price)\s(ASC|DESC)/', $sort_products);
            if (empty($correct_sort)) {
                $this->errors = [
                    'id' => 'incorrect_sort',
                    'text' => _w('No sort order specified')
                ];
            }
        }

        if (!$this->errors) {
            if ($category_id > 0) {
                $category_model = new shopCategoryModel();
                $category = $category_model->select('id')->where('id = ?', $category_id)->fetchField('id');
                if ($category) {
                    return;
                }
            }
            $this->errors = [
                'id' => 'not_found',
                'text' => _w('Category to update not found')
            ];
        }
    }

    protected function getCategoryProducts($category_id, $sort_products)
    {
        $product_model = new shopProductModel();
        $products = $product_model->select('id')->where('category_id = ?', $category_id)->order($sort_products)->fetchAll('id');
        return array_keys($products);
    }
}