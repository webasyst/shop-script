<?php

class shopPluginsAction extends waViewAction
{
    public function execute()
    {
        if (!$this->getUser()->getRights('shop', 'settings')) {
            throw new waException(_w('Access denied'));
        }
        $this->setLayout(new shopBackendLayout());
        $this->layout->assign('no_level2', true);
        $this->view->assign('plugins', $this->getConfig()->getPlugins());
        $this->view->assign('installer', $this->getUser()->getRights('installer', 'backend'));
    }
}
