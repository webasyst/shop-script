<?php

class shopProdSetRemoveController extends waJsonController
{
    public function execute()
    {
        $id = waRequest::post('set_id', null, waRequest::TYPE_STRING_TRIM);

        $set_model = new shopSetModel();
        $result = $set_model->delete($id);
        if (!$result) {
            $this->errors = [
                'id' => 'remove_failed',
                'text' => _w('Failed to delete the set.')
            ];
        }
    }
}
