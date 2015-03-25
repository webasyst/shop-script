<?php

class shopProductsSortCategoriesController extends waController
{
    public function execute()
    {
        if (waRequest::post()) {
            $model = new shopCategoryModel();
            $model->sortTree(true);
        }
        $this->getResponse()->redirect('?action=products');
    }
}
