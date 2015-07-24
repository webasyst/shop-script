<?php

class shopConsignmentruPluginPrintformDisplayAction extends waViewAction
{
    public function execute()
    {
        $plugin = wa('shop')->getPlugin('consignmentru');
        /**
         * @var shopConsignmentruPlugin $plugin
         */
        $order_id = waRequest::request('order_id', null, waRequest::TYPE_INT);
        $this->view->assign('content', $plugin->renderForm($order_id));
    }
}
