<?php

class shopSetGetInfoMethod extends shopApiMethod
{
    public function execute()
    {
        $id = $this->get('id', true);
        $set_model = new shopSetModel();
        $set = $set_model->getById($id);
        if (!$set) {
            throw new waAPIException('invalid_param', _w('Set not found.'), 404);
        }
        $this->response = $set;
    }
}
