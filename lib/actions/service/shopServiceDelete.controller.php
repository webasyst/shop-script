<?php

class shopServiceDeleteController extends waJsonController
{
    public function execute()
    {
        $id = waRequest::get('id', null, waRequest::TYPE_INT);
        if (!$id) {
            $this->errors[] = _w("Unknown service to delete");
            return;
        }
        $service_model = new shopServiceModel();
        $service_model->delete($id);
    }
}