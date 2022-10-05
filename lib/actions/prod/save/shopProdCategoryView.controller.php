<?php

class shopProdCategoryViewController extends waJsonController
{
    public function execute()
    {
        $category_id = waRequest::get('category_id', null, waRequest::TYPE_INT);

        $category_model = new shopCategoryModel();
        $urls = $category_model->getFrontendUrls($category_id, true);

        if (isset($urls[0])) {
            $this->redirect($urls[0]);
        } else {
            throw new waException('Page not found', 404);
        }
    }
}