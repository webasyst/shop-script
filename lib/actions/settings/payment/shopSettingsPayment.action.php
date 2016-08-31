<?php

class shopSettingsPaymentAction extends waViewAction
{
    public function execute()
    {
        if (!$this->getUser()->getRights('shop', 'settings')) {
            throw new waRightsException(_w('Access denied'));
        }

        $plugins = shopPayment::getList();

        $model = new shopPluginModel();
        $instances = $model->listPlugins(shopPluginModel::TYPE_PAYMENT, array('all' => true,));
        foreach ($instances as &$instance) {
            $instance['installed'] = isset($plugins[$instance['plugin']]);
            unset($instance);
        }

        $this->view->assign(array(
            'instances' => $instances,
            'plugins'   => $plugins,
            'installer' => $this->getUser()->getRights('installer', 'backend'),
        ));
    }
}
