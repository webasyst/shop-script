<?php
class shopSettingsShippingSaveController extends waJsonController
{
    public function execute()
    {
        if ($plugin = waRequest::post('shipping')) {
            try {
                shopShipping::savePlugin($plugin);
                $this->response['message'] = _w('Saved');
            } catch (waException $ex) {
                $this->setError($ex->getMessage());
            }
        }
    }
}
