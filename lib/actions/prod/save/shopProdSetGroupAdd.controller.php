<?php

class shopProdSetGroupAddController extends waJsonController
{
    public function execute()
    {
        $data = [
            'name'     => _w('New folder'),
            'sort'     => 0,
            'type'     => 'group',
            'group_id' => null,
            'is_group' => true,
            'sets'     => []
        ];
        $set_group_model = new shopSetGroupModel();
        $new_group_id = $set_group_model->add($data);
        if ($new_group_id) {
            $data["group_id"] = $new_group_id;
            $this->response = $data;
        } else {
            $this->errors = [
                'id' => 'add_failed',
                'text' => _w('Failed to create a folder')
            ];
        }
    }
}
