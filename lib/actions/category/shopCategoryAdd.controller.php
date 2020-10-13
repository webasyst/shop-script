<?php

class shopCategoryAddController extends waJsonController
{
    public function execute()
    {
        $name = waRequest::post('name', '', waRequest::TYPE_STRING_TRIM);
        $parent_id = waRequest::post('parent_id', null,  waRequest::TYPE_INT);
        if (empty($name)) {
            $this->errors = 'Name is empty';
            return;
        }
        $url = shopHelper::transliterate($name);

        $category_model = new shopCategoryModel();
        $data = array(
            'name' => $name,
            'url' => $url,
        );

        if (empty($parent_id)) {
            $data['full_url'] = $url;
        } else {
            $parent_category = $category_model->getById($parent_id);
            if (empty($parent_category)) {
                $this->errors = 'The parent category with the specified id does not exist';
                return;
            }

            $data['full_url'] = $parent_category['full_url'] . '/' . $url;
        }

        if ($id = $category_model->add($data, $parent_id)) {
            $new_category = $category_model->getById($id);
            $this->response = $new_category;
        } else {
            $this->errors = 'Category not created';
        }
    }
}