<?php

class shopCategoryDeleteMethod extends waAPIMethod
{
    protected $method = 'POST';

    public function execute()
    {
        $id = $this->post('id', true);
        $category_model = new shopCategoryModel();
        $category = $category_model->getById((int)$id);

        if ($category) {
            if ($category_model->delete($id)) {
                $this->response = true;
            } else {
                throw new waAPIException('server_error', 500);
            }
        } else {
            throw new waAPIException('invalid_request', 'Category not found', 404);
        }
    }
}