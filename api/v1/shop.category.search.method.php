<?php

class shopCategorySearchMethod extends waAPIMethod
{
    public function execute()
    {
        $name = $this->get('name', true);
        $category_model = new shopCategoryModel();
        $this->response = $category_model->getByName($name);
        $this->response['_element'] = 'category';
    }
}