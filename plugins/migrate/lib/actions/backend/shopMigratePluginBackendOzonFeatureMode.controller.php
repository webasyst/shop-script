<?php

class shopMigratePluginBackendOzonFeatureModeController extends waJsonController
{
    public function execute()
    {
        $mode = waRequest::post('mode', shopMigrateOzonSettings::FEATURE_MODE_AUTO, waRequest::TYPE_STRING_TRIM);
        $force_text = (bool) waRequest::post('force_text', 0, waRequest::TYPE_INT);
        $settings = new shopMigrateOzonSettings();
        $settings->setFeatureImportMode($mode);
        $settings->setForceTextFeatures($force_text);
        $this->response = array(
            'mode'       => $settings->getFeatureImportMode(),
            'force_text' => $settings->shouldForceTextFeatures(),
        );
    }
}
