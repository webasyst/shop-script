<?php

class shopSetDeleteMethod extends waAPIMethod
{
    protected $method = 'POST';

    public function execute()
    {
        $id = $this->post('id', true);
        $set_model = new shopSetModel();
        $set = $set_model->getById($id);
        if (!$set) {
            throw new waAPIException('invalid_param', 'Set not found', 404);
        }
        $set_model->delete($id);
        $this->response = true;
    }
}