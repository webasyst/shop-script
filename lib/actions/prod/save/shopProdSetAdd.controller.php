<?php

class shopProdSetAddController extends waJsonController
{
    public function execute()
    {
        $name = waRequest::post('name', '', waRequest::TYPE_STRING_TRIM);
        if (!mb_strlen($name)) {
            $name = _w('(no name)');
        }

        $set_model = new shopSetModel();
        $id = str_replace('-', '_', shopHelper::transliterate($name));
        $id = $set_model->suggestUniqueId($id);
        $new_set_id = $set_model->add([
            'id' => $id,
            'name' => $name,
        ]);

        if ($new_set_id) {
            $set = $set_model->getById($new_set_id);
            $set['set_id'] = $set['id'];
            $set['is_group'] = false;
            $this->response = $set;
        } else {
            $this->errors = [
                'id' => 'add_failed',
                'text' => _w('Failed to create a set.')
            ];
        }
    }
}
