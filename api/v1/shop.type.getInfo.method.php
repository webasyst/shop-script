<?php

class shopTypeGetInfoMethod extends shopApiMethod
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
            throw new waAPIException('invalid_param', _w('Product type not found.'), 404);
        }
    }
}
