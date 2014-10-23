<?php
class shopSettingsPaymentSaveController extends waJsonController
{
    public function execute()
    {
        if (!$this->getUser()->getRights('shop', 'settings')) {
            throw new waRightsException(_w('Access denied'));
        }
        if ($plugin = waRequest::post('payment')) {
            try {
                if (!isset($plugin['settings'])) {
                    $plugin['settings'] = array();
                }
                shopPayment::savePlugin($plugin);
                $this->response['message'] = _w('Saved');
            } catch (waException $ex) {
                $this->setError($ex->getMessage());
            }
        }
    }
}
