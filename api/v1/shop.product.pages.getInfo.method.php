<?php

class shopProductPagesGetInfoMethod extends shopApiMethod
{
    public function execute()
    {
        $id = $this->get('id', true);
        $pages_model = new shopProductPagesModel();
        $page = $pages_model->getById($id);

        if (!$page) {
            throw new waAPIException('invalid_param', _w('Product page not found.'), 404);
        }

        $this->response = $page;
    }
}
