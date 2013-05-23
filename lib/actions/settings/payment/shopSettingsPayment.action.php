<?php

class shopSettingsPaymentAction extends waViewAction
{
    public function execute()
    {
        $model = new shopPluginModel();
        $this->view->assign('instances', $model->listPlugins(shopPluginModel::TYPE_PAYMENT, array('all' => true, )));
        $this->view->assign('plugins', waPayment::enumerate());
    }

}
