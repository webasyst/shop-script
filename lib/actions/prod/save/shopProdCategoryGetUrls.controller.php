<?php

class shopProdCategoryGetUrlsController extends waJsonController
{
    public function execute()
    {
        $category_id = waRequest::post('category_id', null, waRequest::TYPE_INT);

        $category_model = new shopCategoryModel();
        $urls = $category_model->getFrontendUrls($category_id, true);

        $this->response['urls'] = $urls;
    }
}