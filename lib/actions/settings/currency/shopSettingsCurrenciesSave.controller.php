<?php

class shopSettingsCurrenciesSaveController extends waJsonController
{
    public function execute()
    {
        $app_settings_model = new waAppSettingsModel();
        $app_settings_model->set('shop', 'use_product_currency', waRequest::post('use_product_currency', 0, waRequest::TYPE_INT));
    }
}