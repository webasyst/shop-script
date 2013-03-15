<?php
class shopMigratePluginBackendSetupAction extends waViewAction
{
    public function execute()
    {
        /**
         *
         * @var shopMigratePlugin
         */
        $plugin = wa()->getPlugin('migrate');
        $this->view->assign('transports', $plugin->getTransports());
    }
}
