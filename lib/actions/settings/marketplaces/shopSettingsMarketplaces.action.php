<?php
/**
 * @description "shop/?action=settings#/marketplaces/"
 */
class shopSettingsMarketplacesAction extends waViewAction
{
    public function execute()
    {
        $current_user = wa()->getUser();
        if (!$current_user->isAdmin('shop') || !$current_user->isAdmin('installer')) {
            throw new waRightsException(_ws('Access denied'));
        }

        $installer_url = wa()->getConfig()->getBackendUrl(true);
        $marketplace_plugins_url = $installer_url . 'installer/?module=plugins&action=view&slug=shop&filter[tag]=marketplaces';

        $this->view->assign([
            "marketplace_plugins_url" => $marketplace_plugins_url,
            'backend_settings_marketplaces' => wa('shop')->event('backend_settings_marketplaces'),
        ]);
    }
}
