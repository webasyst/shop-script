<?php

class shopPluginsActions extends waPluginsActions {
    protected $plugins_hash = '#';
    protected $is_ajax = false;

    protected function preExecute() {
        $current_user = $this->getUser();

        if (!$current_user->getRights('shop', 'settings')) {
            throw new waException(_w('Access denied'));
        }
    }

    public function defaultAction() {
        $this->setLayout(new shopBackendLayout());

        if (wa()->whichUI() === '1.3') {
            $this->layout->assign('no_level2', true);
            $this->getView()->assign([
                'container_class'       => 'content left15px right15px s-nolevel2-box',
                'container_before_html' => '<div class="sidebar right15px">
    <div class="block s-nolevel2-sidebar"></div>
</div>
<div class="sidebar left15px">
    <div class="block s-nolevel2-sidebar"></div>
</div>',
            ]);
        } else {

            $this->getView()->assign([
                "plugins_list_url"     => $this->getListUrl(waRequest::request('page')),
                'backend_plugins_list' => wa('shop')->event('backend_plugins_list'),
            ]);

        }

        parent::defaultAction();
    }

    protected function getListUrl($type)
    {
        if (!wa()->getUser()->isAdmin('installer')) {
            return null;
        }
        $installer_url = wa()->getConfig()->getBackendUrl(true).'installer/';
        switch ($type) {
            case 'apps':
                return $installer_url.'?module=store&action=inApp&filter[type]=app';
            case 'onlinecash':
                return $installer_url.'?module=store&action=inApp&filter[tag]=fz54';
            case 'marketplaces':
                return $installer_url.'?module=plugins&action=view&slug=shop&filter[tag]=marketplaces';
            case 'topplugins':
                return $installer_url . '?module=plugins&action=view&slug=shop';
            case 'home':
            default:
                return $installer_url.'?module=store&action=inApp&options[]=storeSearch&options[filter_type]=plugin&options[filter_app]=shop';
        }
    }

    public function installedAction() {

        $template = $this->getTemplatePath('installed');

        $installer_available = wa()->appExists('installer') && $this->getUser()->getRights('installer', 'backend');
        $installer_available = $installer_available && is_readable(wa()->getConfig()->getRootPath() . '/wa-installer/lib/config/sources.php');

        $this->display([
            'plugins_hash' => $this->plugins_hash,
            'plugins'      => $this->getConfig()->getPlugins(),
            'installer'    => $installer_available,
            'is_ajax'      => $this->is_ajax,
            'shadowed'     => $this->shadowed,
        ], $template);
    }

    protected function getTemplatePath($action = null) {
        if ($action == 'settings') {
            if (wa()->whichUI() === '1.3') {
                return $this->getConfig()->getAppPath('templates/actions-legacy/plugins/') . 'PluginsSettings.html';
            }
            return $this->getConfig()->getAppPath('templates/actions/plugins/') . 'PluginsSettings.html';
        }

        if (wa()->whichUI() !== '1.3') {
            //if ($action == 'installed') {
                return $this->getConfig()->getRootPath() . '/wa-system/plugin/templates/Plugins.html';
            //}else{
            //    return $this->getConfig()->getAppPath('templates/actions/plugins/') . 'PluginsList.html';
            //}
        }

        return parent::getTemplatePath($action);
    }
}
