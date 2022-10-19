<?php

class shopProdCategorySortDialogSaveController extends waJsonController
{
    protected $category = [];

    public function execute()
    {
        $category_id = waRequest::post('category_id', null, waRequest::TYPE_INT);
        $sort_products = waRequest::post('sort_products', null, waRequest::TYPE_STRING_TRIM);

        $this->validateData($category_id, $sort_products);
        if (!$this->errors) {
            if ($sort_products) {
                $products = $this->getCategoryProducts($sort_products);
            } else {
                $products = waRequest::post('products', [], waRequest::TYPE_ARRAY_INT);
            }
            if (empty($products)) {
                $this->errors = [
                    'id' => 'empty_products',
                    'text' => _w('Products not specified.')
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
                        'text' => _w('Failed to update the category.')
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
                    'text' => _w('No sort order specified.')
                ];
            }
        }

        if (!$this->errors) {
            if ($category_id > 0) {
                $category_model = new shopCategoryModel();
                $this->category = $category_model->getById($category_id);
                if ($this->category) {
                    return;
                }
            }
            $this->errors = [
                'id' => 'not_found',
                'text' => _w('The category to update was not found.')
            ];
        }
    }

    protected function getCategoryProducts($sort_products)
    {
        $products = [];
        $category_model = new shopCategoryModel();
        $category['subcategories'] = $category_model->descendants($this->category, true)->where('type = '.$category_model::TYPE_STATIC)->fetchAll('id');
        $descendant_ids = array_keys($category['subcategories']);
        if ($descendant_ids) {
            $sql = "SELECT p.id 
                    FROM shop_product p
                        JOIN shop_category_products cp ON p.id = cp.product_id
                    WHERE cp.category_id IN (" . implode(',', $descendant_ids) . ")
                    ORDER BY p.$sort_products";
            $products = $category_model->query($sql)->fetchAll('id');
        }
        return array_keys($products);
    }
}
