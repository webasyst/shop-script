<?php

class shopImagesProductsetupAction extends waViewAction
{
    public function execute()
    {
        $this->view->assign(array(
            'types' => $this->getTypes(),
            'currency' => $this->getConfig()->getCurrency()
        ));
    }

    public function getTypes() {
        $model = new shopTypeModel();
        return $model->getAll('id');
    }
}