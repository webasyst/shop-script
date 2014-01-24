<?php
class shopSettingsShippingSaveController extends waJsonController
{
    public function execute()
    {
        if (!$this->getUser()->getRights('shop', 'settings')) {
            throw new waRightsException(_w('Access denied'));
        }
        if ($plugin = waRequest::post('shipping')) {
            try {
                if (!isset($plugin['settings'])) {
                    $plugin['settings'] = array();
                }
                shopShipping::savePlugin($plugin);
                $this->response['message'] = _w('Saved');
            } catch (waException $ex) {
                $this->setError($ex->getMessage());
            }
        }
    }
}
