<?php

class shopSetGetListMethod extends shopApiMethod
{
    protected $method = 'GET';
    public function execute()
    {
        $set_model = new shopSetModel();
        $this->response = $set_model->getAll();
    }
}
