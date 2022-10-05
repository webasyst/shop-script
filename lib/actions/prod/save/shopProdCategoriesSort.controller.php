<?php

class shopProdCategoriesSortController extends waJsonController
{
    public function execute()
    {
        $sort = waRequest::post('sort', null, waRequest::TYPE_STRING_TRIM);

        $this->validateData($sort);
        if (!$this->errors) {
            $this->sort($sort);
        }
    }

    protected function validateData($sort)
    {
        if ($sort != 'name ASC' && $sort != 'name DESC') {
            $this->errors = [
                'id' => 'incorrect_sort',
                'text' => _w('Failed to sort categories.')
            ];
        }
    }

    /**
     * @param string $sort
     * @throws waDbException
     * @throws waException
     */
    protected function sort($sort)
    {
        $category_model = new shopCategoryModel();
        $categories = $category_model->select('`id`, `name`, `parent_id`')->order('`left_key`, `right_key`')->fetchAll();
        $original_order = $this->getCategoriesOrder($categories);
        usort($categories, function($a, $b) use ($sort) {
            $set_order = strcmp($a['name'], $b['name']);
            return $sort == 'name ASC' ? $set_order : -$set_order;
        });
        $changed_order = $this->getCategoriesOrder($categories);
        foreach ($categories as $category) {
            if ($original_order != $changed_order) {
                $response = $category_model->move($category['id'], null, $category['parent_id']);
                if ($response !== true) {
                    $this->errors = [
                        'id' => 'sort_categories',
                        'text' => ifempty($response, _w('Failed to sort categories.')),
                    ];
                    return;
                }
            }
        }
    }

    /**
     * @param array $categories
     * @return array
     */
    protected function getCategoriesOrder($categories)
    {
        $order = [];
        foreach ($categories as $category) {
            $order[$category['parent_id']][] = $category['id'];
        }
        foreach ($order as &$items) {
            $items = array_flip($items);
        }

        return $order;
    }
}
