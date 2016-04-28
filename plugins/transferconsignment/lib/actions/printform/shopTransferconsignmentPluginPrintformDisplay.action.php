<?php

class shopTransferconsignmentPluginPrintformDisplayAction extends waViewAction
{
    public function execute()
    {
        $plugin = wa('shop')->getPlugin('transferconsignment');
        /**
         * @var shopTransferconsignmentPlugin $plugin
         */
        $transfer_id = waRequest::request('transfer_id', null, waRequest::TYPE_INT);
        $this->view->assign('content', $plugin->renderPrintform($transfer_id));
    }
}
