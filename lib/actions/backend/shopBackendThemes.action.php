<?php
class shopBackendThemesAction extends waViewAction
{
    public function execute()
    {
        $current_user = $this->getUser();
        if (!$current_user->isAdmin('shop') || !$current_user->isAdmin('installer')) {
            throw new waRightsException(_ws('Access denied'));
        }

        $this->setLayout(new shopBackendLayout());

        $installer_url = $this->getConfig()->getBackendUrl(true);
        $themes_list_url = $installer_url . 'installer/?module=themes&action=view';

        $this->view->assign([
            "themes_list_url" => $themes_list_url,
            'backend_themes_list' => wa('shop')->event('backend_themes_list'),
        ]);
    }
}
