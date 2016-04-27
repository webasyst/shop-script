<?php

class shopDialogCategoriesAction extends waViewAction
{
    public function execute()
    {
        if (!$this->getUser()->getRights('shop', 'setscategories')) {
            throw new waRightsException(_w('Access denied'));
        }
        
        $category_model = new shopCategoryModel();
        $this->view->assign('categories', $category_model->getFullTree('', true));
    }
}