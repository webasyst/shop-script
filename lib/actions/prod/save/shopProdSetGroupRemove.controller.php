<?php

class shopProdSetGroupRemoveController extends waJsonController
{
    public function execute()
    {
        $id = waRequest::post('group_id', null, waRequest::TYPE_INT);

        $set_group_model = new shopSetGroupModel();
        $result = $set_group_model->delete($id);
        if (!$result) {
            $this->errors = [
                'id' => 'remove_failed',
                'text' => _w('Failed to delete the group.')
            ];
        }
    }
}
