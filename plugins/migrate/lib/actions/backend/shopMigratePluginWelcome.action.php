<?php

class shopMigratePluginWelcomeAction extends waViewAction
{
    public function execute()
    {
        $this->view->assign([
            'transports' => shopMigratePlugin::getTransports(),
        ]);
    }
}
