<?php

class shopDialogTypesAction extends waViewAction
{
    public function execute()
    {
        $model = new shopTypeModel();
        $this->view->assign('types', $model->getTypes());
    }
}