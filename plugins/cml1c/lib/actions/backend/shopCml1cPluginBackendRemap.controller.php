<?php

class shopCml1cPluginBackendRemapController extends waJsonController
{
    public function execute()
    {
        if (waRequest::post('map') == 'reset') {
            $p = wa('shop')->getPlugin('cml1c');
            $s = $p->getSettings();
            $s['features_map'] = array();
            $s['stock_map'] = array();
            $p->saveSettings($s);
        }
    }
}
