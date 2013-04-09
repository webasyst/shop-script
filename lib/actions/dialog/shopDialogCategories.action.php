<?php

class shopDialogCategoriesAction extends waViewAction
{
    public function execute()
    {
        $category_model = new shopCategoryModel();
        $this->view->assign('categories', $category_model->getFullTree('', true));
    }
}