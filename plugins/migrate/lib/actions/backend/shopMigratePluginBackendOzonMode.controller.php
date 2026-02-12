<?php

class shopMigratePluginBackendOzonModeController extends waJsonController
{
    public function execute()
    {
        $settings = new shopMigrateOzonSettings();
        $mode = waRequest::post('mode', shopMigrateOzonSettings::MODE_AUTO, waRequest::TYPE_STRING_TRIM);
        $mode = in_array($mode, array(shopMigrateOzonSettings::MODE_AUTO, shopMigrateOzonSettings::MODE_MANUAL), true) ? $mode : shopMigrateOzonSettings::MODE_AUTO;
        $settings->setOperationMode($mode);
        $this->response = array('mode' => $settings->getOperationMode());
    }
}
