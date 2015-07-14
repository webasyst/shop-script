<?php

class shopSettingsPrintformAction extends waViewAction
{
    public function execute()
    {
        if (!$this->getUser()->getRights('shop', 'settings')) {
            throw new waException(_w('Access denied'));
        }
        $this->view->assign('plugins', shopHelper::getPrintForms());
        $this->view->assign('installer', $this->getUser()->getRights('installer', 'backend'));
    }
}
