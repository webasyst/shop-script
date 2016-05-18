<?php

class shopInvoiceruPluginPrintformDisplayAction extends waViewAction
{
    public function execute()
    {
        if (!wa()->getUser()->getRights('shop', 'orders')) {
            throw new waRightsException('Access denied');
        }

        $plugin = wa('shop')->getPlugin('invoiceru');
        /**
         * @var shopInvoiceruPlugin $plugin
         */
        $order_id = waRequest::request('order_id', null, waRequest::TYPE_INT);
        // For backward compatibility with SS6 use renderForm
        $this->view->assign('content', $plugin->renderForm($order_id));
    }
}
