<?php

class shopSettingsPaymentAction extends waViewAction
{
    public function execute()
    {
        if (!$this->getUser()->getRights('shop', 'settings')) {
            throw new waRightsException(_w('Access denied'));
        }
        $model = new shopPluginModel();
        $this->view->assign('instances', $model->listPlugins(shopPluginModel::TYPE_PAYMENT, array('all' => true, )));
        $this->view->assign('plugins', shopPayment::getList());
        $this->view->assign('installer', $this->getUser()->getRights('installer', 'backend'));
    }
}
