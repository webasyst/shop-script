<?php

class shopSettingsPaymentAction extends waViewAction
{
    public function execute()
    {
        $model = new shopPluginModel();
        $this->view->assign('instances', $model->listPlugins(shopPluginModel::TYPE_PAYMENT));
        $this->view->assign('plugins', waPayment::enumerate());
    }

}
