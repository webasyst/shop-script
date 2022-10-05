<?php

class shopProdAddToCategoriesDialogAction extends waViewAction
{
    public function execute()
    {
        if (!$this->getUser()->getRights('shop', 'setscategories')) {
            throw new waRightsException(_w('Access denied'));
        }

        $category_model = new shopCategoryModel();
        $categories = $category_model->getFullTree('id, name, parent_id', true);
        $categories_tree = $category_model->buildNestedTree($categories);

        $this->view->assign([
            'categories' => $categories,
            'categories_tree' => $categories_tree,
        ]);
    }
}
