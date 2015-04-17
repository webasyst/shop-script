<?php

class shopMigratePluginBackendSetupAction extends waViewAction
{
    public function execute()
    {
        $this->view->assign('platform',waRequest::request('platform'));
        $this->view->assign('transports', shopMigratePlugin::getTransports());
    }
}
