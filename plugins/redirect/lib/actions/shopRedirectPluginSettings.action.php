<?php

class shopRedirectPluginSettingsAction extends shopPluginsSettingsAction
{

    public function execute()
    {
        $_GET['id'] = 'redirect';
        parent::execute();
    }
}
