<?php

class shopCategoryGetInfoMethod extends waAPIMethod
{
    protected $method = 'GET';

    public function execute()
    {
        $id = $this->get('id', true);
        $category_model = new shopCategoryModel();
        $category = $category_model->getById((int)$id);

        if ($category) {
            $this->response = $category;
        } else {
            throw new waAPIException('invalid_request', 'Category not found', 404);
        }
    }
}