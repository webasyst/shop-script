<?php

class shopProdCategoryStatusController extends waJsonController
{
    public function execute()
    {
        $category_id = waRequest::post('category_id', null, waRequest::TYPE_INT);
        $status = waRequest::post('status', null, waRequest::TYPE_INT) ? 1 : 0;

        $this->validateData($category_id);

        if (!$this->errors) {
            $category_model = new shopCategoryModel();
            $result = $category_model->updateById($category_id, ['status' => $status]);
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

    protected function validateData($category_id)
    {
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
