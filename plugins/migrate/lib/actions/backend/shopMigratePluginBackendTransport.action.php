<?php

class shopMigratePluginBackendTransportAction extends waViewAction
{
    public function execute()
    {
        $options = waRequest::post();
        if (isset($options['transport'])) {
            unset($options['transport']);
        }
        $transport = shopMigrateTransport::getTransport(waRequest::request('transport'), $options);
        $errors = array();
        $this->view->assign('validate', $options ? $transport->validate(true, $errors) : false);
        $this->view->assign('errors', $errors);
        $this->view->assign('controls', $transport->getControls($errors));
    }
}
