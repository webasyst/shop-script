<?php

class shopSetGetInfoMethod extends waAPIMethod
{
    public function execute()
    {
        $id = $this->get('id', true);
        $set_model = new shopSetModel();
        $set = $set_model->getById($id);
        if (!$set) {
            throw new waAPIException('invalid_param', 'Set not found', 404);
        }
        $this->response = $set;
    }
}