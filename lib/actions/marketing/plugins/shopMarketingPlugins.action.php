<?php

class shopMarketingPluginsAction extends shopMarketingViewAction
{
    public function execute()
    {
        $current_user = wa()->getUser();
        if (!$current_user->isAdmin('shop') || !$current_user->isAdmin('installer')) {
            throw new waRightsException(_ws('Access denied'));
        }

        $installer_url = wa()->getConfig()->getBackendUrl(true);
        $installer_url .= 'installer/?module=plugins&action=view&slug=shop&filter[tag]=marketing';

        $this->view->assign([
            'installer_url' => $installer_url,
        ]);
    }
}