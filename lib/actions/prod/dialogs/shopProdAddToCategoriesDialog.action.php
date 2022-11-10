<?php

class shopProdAddToCategoriesDialogAction extends waViewAction
{
    public function execute()
    {
        if (!$this->getUser()->getRights('shop', 'setscategories')) {
            throw new waRightsException(_w('Access denied'));
        }

        $this->view->assign([
            'categories' => $this->getCategories()
        ]);

        $this->setTemplate('templates/actions/prod/main/dialogs/products.add_to_categories.html');
    }

    protected function getCategories() {
        return shopProdCategoriesAction::getCategories();
    }
}
