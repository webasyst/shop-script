<?php

class shopMigratePluginBackendOzonTagModeController extends waJsonController
{
    public function execute()
    {
        $mode = waRequest::post('mode', shopMigratePluginOzonSettings::TAG_MODE_PRODUCT_ONLY, waRequest::TYPE_STRING_TRIM);
        $settings = new shopMigratePluginOzonSettings();
        $settings->setTagImportMode($mode);
        $this->response = array(
            'mode' => $settings->getTagImportMode(),
        );
    }
}
