<?php

class shopMarketingAffiliateEnableController extends shopMarketingSettingsJsonController
{
    public function execute()
    {
        $asm = new waAppSettingsModel();
        $asm->set('shop', 'affiliate', waRequest::post('enable') ? '1' : null);
    }
}
