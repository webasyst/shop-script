<?php

class shopProdCategorySortController extends waJsonController
{
    public function execute()
    {
        $category_id = waRequest::post('category_id', null, waRequest::TYPE_INT);
        $sort_products = waRequest::post('sort_products', null, waRequest::TYPE_STRING_TRIM);
        $sort_products = !empty($sort_products) ? $sort_products : null;

        $this->validateData($category_id, $sort_products);

        if (!$this->errors) {
            $category_model = new shopCategoryModel();
            $result = $category_model->updateById($category_id, ['sort_products' => $sort_products]);
            if ($result) {
                $this->logAction('category_edit', $category_id);
                $this->response['sort_products_changed'] = $sort_products;
            } else {
                $this->errors = [
                    'id' => 'update_fail',
                    'text' => _w('Failed to update the category.')
                ];
            }
        }
    }

    protected function validateData($category_id, $sort_products)
    {
        if ($sort_products) {
            if (mb_strlen($sort_products) > 32) {
                $this->errors = [
                    'id' => 'incorrect_sort',
                    'text' => _w('The sort field is too long.')
                ];
            } else {
                $sort = explode(' ', $sort_products);
                if (!isset($sort[1]) || ($sort[1] != 'DESC' && $sort[1] != 'ASC')) {
                    $this->errors = [
                        'id' => 'incorrect_sort',
                        'text' => _w('No sort order specified.')
                    ];
                }
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
                'text' => _w('The category to update was not found.')
            ];
        }
    }
}
