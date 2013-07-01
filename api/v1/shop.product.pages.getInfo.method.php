<?php

class shopProductPagesGetInfoMethod extends waAPIMethod
{
    public function execute()
    {
        $id = $this->get('id', true);
        $pages_model = new shopProductPagesModel();
        $page = $pages_model->getById($id);

        if (!$page) {
            throw new waAPIException('invalid_param', 'Product page not found', 404);
        }

        $this->response = $page;
    }
}