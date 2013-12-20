<?php

class shopReportsCheckoutflowClearController extends waJsonController
{
    public function execute() 
    {
        if (waRequest::post()) {
            $checkout_flow = new shopCheckoutFlowModel();
            $checkout_flow->clear();
            $app_settings_model = new waAppSettingsModel();
            $app_settings_model->del('shop', 'checkout_flow_changed');
        }    
    }
}