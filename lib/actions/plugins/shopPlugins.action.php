<?php

class shopPluginsAction extends waViewAction
{
    public function execute()
    {
        if (!$this->getUser()->isAdmin('shop')) {
            throw new waException(_w('Access denied'));
        }
        $this->setLayout(new shopBackendLayout());
        $this->layout->assign('no_level2', true);
        $this->view->assign('plugins', $this->getConfig()->getPlugins());
    }
}
