<?php
class shopSettingsPaymentSaveController extends waJsonController
{
    public function execute()
    {
        if ($plugin = waRequest::post('payment')) {
            try {
                shopPayment::savePlugin($plugin);
                $this->response['message'] = _w('Saved');
            } catch (waException $ex) {
                $this->setError($ex->getMessage());
            }
        }
    }
}
