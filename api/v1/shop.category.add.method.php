<?php

class shopCategoryAddMethod extends waAPIMethod
{
    protected $method = 'POST';

    public function execute()
    {
        $data = waRequest::post();

        $exclude = array('left_key', 'right_key', 'type', 'full_url');
        foreach ($exclude as $k) {
            if (isset($data[$k])) {
                unset($data[$k]);
            }
        }
        // check required param name
        $this->post('name', true);

        $category_model = new shopCategoryModel();
        $parent_id = waRequest::post('parent_id', 0, 'int');
        if ($parent_id && !$category_model->getById($parent_id)) {
            throw new waAPIException('invalid_request', 'Parent category not found', 404);
        }
        if ($id = $category_model->add($data, $parent_id)) {
            // return info of the new category
            $_GET['id'] = $id;
            $method = new shopCategoryGetInfoMethod();
            $this->response = $method->getResponse(true);
        } else {
            throw new waAPIException('server_error', 500);
        }
    }
}