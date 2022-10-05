<?php

class shopProdCategoryMoveController extends waJsonController
{
    public function execute()
    {
        $moved_category_id = waRequest::post('moved_category_id', null, waRequest::TYPE_INT);
        $parent_id = waRequest::post('parent_category_id', 0, waRequest::TYPE_INT);
        $category_ids = waRequest::post('categories', [], waRequest::TYPE_ARRAY);

        $this->validateData($parent_id, $category_ids);
        if (!$this->errors) {
            $this->move($parent_id, $category_ids, $moved_category_id);
        }
    }

    protected function validateData($parent_id, $category_ids)
    {
        if (count($category_ids) != count(array_unique($category_ids))) {
            $this->errors = [
                'id' => 'incorrect_params',
                'text' => _w('Failed to move the category.')
            ];
            return;
        }

        if (false !== array_search($parent_id, $category_ids)) {
            $this->errors = [
                'id' => 'move_error',
                'text' => _w("A category cannot be the parent of itself.")
            ];
        }
    }

    protected function move($parent_id, $category_ids, $moved_category_id)
    {
        $category_model = new shopCategoryModel();

        $existing_subcategories = $category_model->getTree($parent_id, 1);
        $existing_subcategories = array_intersect_key($existing_subcategories, array_fill_keys($category_ids, 1));

        // Starting from right to left, move subcategories one by one in their proper place.
        // Do not attempt an expensive move operation if category is already in place.
        // Interrupt if unable to move one of categories: this can happen if there's
        // a URL conflict or if trying to move a static category inside dynamic
        // (see shopCategoryModel->move())

        $existing_subcategory_ids = array_reverse(array_keys($existing_subcategories));
        $category_ids = array_reverse($category_ids);

        $before_id = null;
        foreach($category_ids as $index => $category_id) {
            if (!isset($existing_subcategory_ids[$index]) || $existing_subcategory_ids[$index] != $category_id
                || $existing_subcategory_ids[$index] == $moved_category_id
            ) {
                $response = $category_model->move($category_id, $before_id, $parent_id);
                if ($response !== true) {
                    $this->errors = [
                        'id' => 'move_category',
                        'text' => ifempty($response, _w('Failed to move the category.')),
                    ];
                    return;
                }

                $existing_subcategory_ids = array_values(array_filter($existing_subcategory_ids, function($subcat_id) use ($category_id) {
                    return $category_id != $subcat_id;
                }));
                array_splice($existing_subcategory_ids, $index, 0, [$category_id]);
            }

            $before_id = $category_id;
        }

        $this->response['moved'] = true;
    }
}
