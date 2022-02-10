<?php

class shopSettingsPremiumAction extends waViewAction
{
    public function execute()
    {
        $this->view->assign('backend_settings_premium', wa('shop')->event('backend_settings_premium'));
    }
}
