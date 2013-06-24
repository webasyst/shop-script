<?php

class shopTypeGetInfoMethod extends waAPIMethod
{
    protected $method = 'GET';

    public function execute()
    {
        $id = $this->get('id', true);
        $type_model = new shopTypeModel();
        $type = $type_model->getById($id);
        if ($type) {
            $this->response = $type;
        } else {
            throw new waAPIException('invalid_param', 'Type not found', 404);
        }
    }
}
