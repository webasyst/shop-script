<?php

class shopProdCategoryDeleteDialogAction extends waViewAction
{
    public function execute()
    {
        $category_id = waRequest::request('category_id', null, waRequest::TYPE_INT);

        $category_model = new shopCategoryModel();
        $category = $category_model->getById($category_id);

        if ($category) {
            if ($category['type'] == shopCategoryModel::TYPE_STATIC) {
                if ($category['include_sub_categories']) {
                    $category['count_total'] = $category['count'];
                } else {
                    $category['count_total'] = $category_model->count($category, true);
                }
            } else {
                $category['count_total'] = $category['count'];
            }
        }

        $this->view->assign([
            'category' => $category,
        ]);

        $this->setTemplate('templates/actions/prod/main/dialogs/categories.category.delete.html');
    }
}