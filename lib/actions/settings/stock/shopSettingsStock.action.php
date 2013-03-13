<?php
class shopSettingsStockAction extends waViewAction
{
    public function execute()
    {
        $model = new shopStockModel();
        if (waRequest::post()) {
            $this->save();
        }
        $this->view->assign('stocks', $model->getAll('id'));
    }
}
