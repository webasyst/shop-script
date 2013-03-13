<?php
class shopSettingsPaymentSaveController extends waJsonController
{
    public function execute()
    {
        if ($plugin = waRequest::post('payment')) {
            shopPayment::savePlugin($plugin);
        }
    }
}
