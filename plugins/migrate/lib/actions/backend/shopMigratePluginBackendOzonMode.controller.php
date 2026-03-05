<?php

class shopMigratePluginBackendOzonModeController extends waJsonController
{
    public function execute()
    {
        $settings = new shopMigratePluginOzonSettings();
        $mode = waRequest::post('mode', shopMigratePluginOzonSettings::MODE_AUTO, waRequest::TYPE_STRING_TRIM);
        $mode = in_array($mode, array(shopMigratePluginOzonSettings::MODE_AUTO, shopMigratePluginOzonSettings::MODE_MANUAL), true) ? $mode : shopMigratePluginOzonSettings::MODE_AUTO;
        $settings->setOperationMode($mode);
        $this->response = array('mode' => $settings->getOperationMode());
    }
}
