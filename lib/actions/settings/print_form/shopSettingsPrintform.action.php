<?php
class shopSettingsPrintformAction extends waViewAction
{
    public function execute()
    {
        $this->view->assign('plugins', shopHelper::getPrintForms());
    }
}
