<?php

class shopReportsCheckoutflowDismissController extends waJsonController
{
    public function execute() 
    {
        if (waRequest::post()) {
            $app_settings_model = new waAppSettingsModel();
            $app_settings_model->del('shop', 'checkout_flow_changed');
        }    
    }
}