<?php

class shopMarketingPluginsAction extends shopMarketingViewAction
{
    public function execute()
    {
        $installer_url = wa()->getConfig()->getBackendUrl(true);
        $installer_url .= 'installer/?module=plugins&action=view&slug=shop&filter[tag]=marketing';

        $this->view->assign([
            'installer_url' => $installer_url,
        ]);
    }
}