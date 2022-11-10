<?php

class shopProdAddToSetsDialogAction extends waViewAction
{
    public function execute()
    {
        if (!$this->getUser()->getRights('shop', 'setscategories')) {
            throw new waRightsException(_w('Access denied'));
        }

        $this->view->assign([
            'items' => $this->getItems(),
        ]);

        $this->setTemplate('templates/actions/prod/main/dialogs/products.add_to_sets.html');
    }

    protected function getItems()
    {
        $set_model = new shopSetModel();
        return $set_model->getSetsWithGroups();
    }
}
