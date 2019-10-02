<?php

class shopMarketingAffiliateSaveController extends shopMarketingSettingsJsonController
{
    public function execute()
    {
        $asm = new waAppSettingsModel();
        $conf = waRequest::post('conf');
        if ($conf && is_array($conf)) {
            $conf['affiliate_credit_rate'] = str_replace(',', '.', (float) str_replace(',', '.', ifset($conf['affiliate_credit_rate'], '0')));
            $conf['affiliate_usage_rate'] = str_replace(',', '.', (float) str_replace(',', '.', ifset($conf['affiliate_usage_rate'], '0')));
            foreach($conf as $k => $v) {
                $asm->set('shop', $k, $v);
            }
        }
    }
}