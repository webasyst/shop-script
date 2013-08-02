<?php

class shopCategoryGetParentsMethod extends waAPIMethod
{
    protected $method = 'GET';

    public function execute()
    {
        $id = $this->get('id', true);
        $category_model = new shopCategoryModel();
        $category = $category_model->getById((int)$id);

        if ($category) {
            if ($category['parent_id']) {
                $parents = $category_model->getPath($category['id']);
                if (waRequest::get('reverse')) {
                    $parents = array_reverse($parents);
                }
                $this->response = array_values($parents);
                $this->response['_element'] = 'category';
            } else {
                $this->response = array();
            }
        } else {
            throw new waAPIException('invalid_request', 'Category not found', 404);
        }
    }
}