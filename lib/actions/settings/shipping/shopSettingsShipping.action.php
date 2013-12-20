<?php

class shopSettingsShippingAction extends waViewAction
{
    public function execute()
    {
        if (!$this->getUser()->getRights('shop', 'settings')) {
            throw new waRightsException(_w('Access denied'));
        }
        $model = new shopPluginModel();
        $this->view->assign('instances', $model->listPlugins(shopPluginModel::TYPE_SHIPPING, array('all' => true, )));
        $this->view->assign('plugins', shopShipping::getList());
        $feature_model = new shopFeatureModel();
        $this->view->assign('no_weight', $feature_model->getByCode('weight') ? false : true);
        $this->view->assign('installer', $this->getUser()->getRights('installer', 'backend'));
    }

}
