<?php

class shopProdAddToSetsDialogAction extends waViewAction
{
    public function execute()
    {
        if (!$this->getUser()->getRights('shop', 'setscategories')) {
            throw new waRightsException(_w('Access denied'));
        }

        $set_model = new shopSetModel();
        $sets = $set_model->where('type = ' . $set_model::TYPE_STATIC)->order('sort')->fetchAll('id');

        $this->view->assign([
            'sets' => $sets,
        ]);
    }
}
