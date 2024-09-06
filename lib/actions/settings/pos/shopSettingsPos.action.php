<?php

class shopSettingsPosAction extends waViewAction
{
    public function execute()
    {
        $this->view->assign([
            'backend_settings_pos' => wa('shop')->event('backend_settings_pos'),
        ]);

        $this->setTemplate('templates/actions'.(wa()->whichUI() == '1.3' ? '-legacy' : '').'/settings/SettingsPOS.html');
    }
}
