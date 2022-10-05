<?php

class shopProdCategoryNameController extends waJsonController
{
    public function execute()
    {
        $category_id = waRequest::post('category_id', null, waRequest::TYPE_INT);
        $name = waRequest::post('name', null, waRequest::TYPE_STRING_TRIM);

        $this->validateData($category_id, $name);

        if (!$this->errors) {
            $this->save($category_id, $name);
        }
    }

    protected function validateData($category_id, $name)
    {
        if (!is_string($name)) {
            $this->errors = [
                'id' => 'incorrect_name',
                'text' => _w('No name is specified.')
            ];
        } elseif (mb_strlen($name) > 255) {
            $this->errors = [
                'id' => 'incorrect_name',
                'text' => _w('The name is too long.')
            ];
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

    protected function save($category_id, $name)
    {
        $category_model = new shopCategoryModel();
        $result = $category_model->updateById($category_id, ['name' => $name]);
        if ($result) {
            $this->logAction('category_edit', $category_id);
            $this->response['updated'] = true;
        } else {
            $this->errors = [
                'id' => 'updated',
                'text' => _w('Failed to update the category.')
            ];
        }
    }
}
