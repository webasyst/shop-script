<?php

class shopWebasystSettings_sidebarHandler extends waEventHandler
{
    public function execute(&$params)
    {
        if (!isset($params['sms-template'])) {
            $settings_app_url = wa('webasyst')->getAppUrl().'webasyst/settings/';
            $sms_templates = array(
                'sms-template' => array(
                    'name' => _ws('SMS templates'),
                    'url'  => $settings_app_url.'sms/template/',
                ),
            );
            $params = waUtils::arrayInsertAfter($params, 'sms', $sms_templates);
        }
    }
}