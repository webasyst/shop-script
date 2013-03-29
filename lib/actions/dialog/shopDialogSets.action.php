<?php

class shopDialogSetsAction extends waViewAction
{
    public function execute()
    {
        $set_model = new shopSetModel();
        $this->view->assign('sets', $set_model->select('*')->where('type = '.shopSetModel::TYPE_STATIC)->order('sort')->fetchAll('id'));
    }
}