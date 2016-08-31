<?php

class shopSettingsShippingAction extends waViewAction
{
    public function execute()
    {
        if (!$this->getUser()->getRights('shop', 'settings')) {
            throw new waRightsException(_w('Access denied'));
        }

        $model = new shopPluginModel();
        $feature_model = new shopFeatureModel();

        $plugins = shopShipping::getList();

        $instances = $model->listPlugins(shopPluginModel::TYPE_SHIPPING, array('all' => true));
        foreach ($instances as &$instance) {
            $instance['installed'] = isset($plugins[$instance['plugin']]);
            unset($instance);
        }

        $this->view->assign(array(
            'instances' => $instances,
            'plugins'   => $plugins,
            'no_weight' => !$feature_model->getByCode('weight'),
            'installer' => $this->getUser()->getRights('installer', 'backend'),
        ));
    }
}
