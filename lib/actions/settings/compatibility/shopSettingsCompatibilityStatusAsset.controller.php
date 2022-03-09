<?php

class shopSettingsCompatibilityStatusAssetController extends waJsonController
{
    public function execute()
    {
        if (wa()->appExists('installer')) {
            wa('installer');
            $app_id = waRequest::post('app_id', '', waRequest::TYPE_STRING_TRIM);
            $plugin_id = waRequest::post('plugin_id', '', waRequest::TYPE_STRING_TRIM);
            $asset_status = !!waRequest::post('enabled', 0, waRequest::TYPE_INT);

            if (empty($plugin_id)) {
                $result = installerHelper::appSetStatus($app_id, $asset_status);
            } else {
                $result = installerHelper::pluginSetStatus($app_id, $plugin_id, $asset_status);
            }

            return $result;
        }
    }
}