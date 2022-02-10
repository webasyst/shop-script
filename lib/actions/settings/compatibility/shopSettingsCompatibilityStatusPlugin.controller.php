<?php

class shopSettingsCompatibilityStatusPluginController extends waJsonController
{
    public function execute()
    {
        if (wa()->appExists('installer')) {
            wa('installer');
            $app_id = waRequest::post('app_id', '', waRequest::TYPE_STRING_TRIM);
            $plugin_id = waRequest::post('plugin_id', '', waRequest::TYPE_STRING_TRIM);
            $plugin_status = !!waRequest::post('enabled', 0, waRequest::TYPE_INT);

            return installerHelper::pluginSetStatus($app_id, $plugin_id, $plugin_status);
        }
    }
}