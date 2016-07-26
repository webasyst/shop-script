<?php

class shopInvoicePluginPrintformDisplayAction extends waViewAction
{
    public function execute()
    {
        if (!wa()->getUser()->getRights('shop', 'orders')) {
            throw new waRightsException('Access denied');
        }

        $plugin = wa('shop')->getPlugin('invoice');
        /**
         * @var shopInvoicePlugin $plugin
         */
        $order_id = waRequest::request('order_id', null, waRequest::TYPE_INT);
        $this->view->assign('content', $plugin->renderPrintform($order_id));
    }
}
