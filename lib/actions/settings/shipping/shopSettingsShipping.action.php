<?php

class shopSettingsShippingAction extends waViewAction
{
    public function execute()
    {
        if (!$this->getUser()->getRights('shop', 'settings')) {
            throw new waRightsException(_w('Access denied'));
        }

        $model = new shopPluginModel();
        $instances = $model->listPlugins(shopPluginModel::TYPE_SHIPPING, array('all' => true));
        $feature_model = new shopFeatureModel();

        $this->view->assign(array(
            'instances' => $instances,
            'plugins' => shopShipping::getList(),
            'no_weight' => !$feature_model->getByCode('weight'),
            'installer' => $this->getUser()->getRights('installer', 'backend'),
        ));
    }
}
