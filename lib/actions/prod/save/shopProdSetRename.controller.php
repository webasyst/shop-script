<?php

class shopProdSetRenameController extends waJsonController
{
    protected $model = null;

    public function execute()
    {
        $id = waRequest::post('set_id', null, waRequest::TYPE_STRING_TRIM);
        $name = waRequest::post('name', null, waRequest::TYPE_STRING_TRIM);
        if ($id) {
            $this->model = new shopSetModel();
        } else {
            $id = waRequest::post('group_id', null, waRequest::TYPE_INT);
            $this->model = new shopSetGroupModel();
        }

        $this->validateData($id, $name);

        if (!$this->errors) {
            $this->save($id, $name);
        }
    }

    protected function validateData($id, $name)
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
            if (mb_strlen($id)) {
                $row = $this->model->select('id')->where('id = ?', $id)->fetchField('id');
                if ($row) {
                    return;
                }
            }
            $this->errors = [
                'id' => 'not_found',
                'text' => _w('Failed to update the name.')
            ];
        }
    }

    protected function save($id, $name)
    {
        $result = $this->model->updateById($id, ['name' => $name]);
        if ($result) {
            $this->response['updated'] = true;
        } else {
            $this->errors = [
                'id' => 'failed_update',
                'text' => _w('Failed to update the name.')
            ];
        }
    }
}
