<?php

class shopSetGetListMethod extends waAPIMethod
{
    protected $method = 'GET';
    public function execute()
    {
        $set_model = new shopSetModel();
        $this->response = $set_model->getAll();
    }
}