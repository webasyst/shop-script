<?php

class shopSettingsAffiliateEnableController extends waJsonController
{
    public function execute()
    {
        $asm = new waAppSettingsModel();
        $asm->set('shop', 'affiliate', waRequest::post('enable') ? '1' : null);
    }
}
