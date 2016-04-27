<?php

class shopDialogSetsAction extends waViewAction
{
    public function execute()
    {
        if (!$this->getUser()->getRights('shop', 'setscategories')) {
            throw new waRightsException(_w('Access denied'));
        }
        
        $set_model = new shopSetModel();
        $this->view->assign('sets', $set_model->select('*')->where('type = '.shopSetModel::TYPE_STATIC)->order('sort')->fetchAll('id'));
    }
}