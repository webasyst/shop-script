<?php

class shopCategoryUpdateMethod extends waAPIMethod
{
    protected $method = 'POST';

    public function execute()
    {
        $id = $this->get('id', true);
        $category_model = new shopCategoryModel();
        $category = $category_model->getById($id);
        if ($category) {
            $data = waRequest::post();
            $exclude = array('left_key', 'right_key', 'type', 'full_url');
            foreach ($exclude as $k) {
                if (isset($data[$k])) {
                    unset($data[$k]);
                }
            }
            if (isset($data['parent_id']) && $category['parent_id'] != $data['parent_id']) {
                if (!$category_model->getById($data['parent_id'])) {
                    throw new waAPIException('invalid_param', 'Parent category not found', 404);
                }
                if (!$category_model->move($id, null, $data['parent_id'])) {
                    throw new waAPIException('server_error', 500);
                }
            }
            if ($category_model->update($id, $data)) {
                $method = new shopCategoryGetInfoMethod();
                $this->response = $method->getResponse(true);
            } else {
                throw new waAPIException('server_error', 500);
            }
        } else {
            throw new waAPIException('invalid_param', 'Category not found', 404);
        }
    }
}