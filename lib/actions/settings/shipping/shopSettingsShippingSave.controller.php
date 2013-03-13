<?php
class shopSettingsShippingSaveController extends waJsonController
{
    public function execute()
    {
        if ($plugin = waRequest::post('shipping')) {
            shopShipping::savePlugin($plugin);
        }
    }
}
