<?php

/**
 * General settings form, and submit controller for it.
 */
class shopSettingsGeneralAction extends waViewAction
{
    public function execute()
    {
        if (waRequest::post()) {
            $app_settings = new waAppSettingsModel();
            foreach ($this->getData() as $name => $value) {
                $app_settings->set('shop', $name, $value);
            }
        }

        $cm = new waCountryModel();
        $this->view->assign('countries', $cm->all());
        $this->view->assign($this->getConfig()->getGeneralSettings());
    }

    public function getData()
    {
        $data = array(
            'name' => waRequest::post('name', '', waRequest::TYPE_STRING_TRIM),
            'email' => waRequest::post('email', '', waRequest::TYPE_STRING_TRIM),
            'phone' => waRequest::post('phone', '', waRequest::TYPE_STRING_TRIM),
            'country' => waRequest::post('country', '', waRequest::TYPE_STRING_TRIM),
            'order_format' => waRequest::post('order_format', '', waRequest::TYPE_STRING_TRIM)
        );
        return $data;
    }
}

