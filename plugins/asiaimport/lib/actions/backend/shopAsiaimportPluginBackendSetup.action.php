<?php
class shopAsiaimportPluginBackendSetupAction extends waViewAction
{
    public function execute()
    {
        /**
         *
         * @var shopAsiaImportPlugin
         */
        $plugin = wa()->getPlugin('asiaimport');
        //$this->view->assign('transports', $plugin->getTransports());
    }
}
