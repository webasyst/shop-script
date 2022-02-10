<?php
class shopSettingsTypefeatFeaturesHelperController extends waJsonController
{
    public function execute()
    {
        if (!$this->getUser()->getRights('shop', 'settings')) {
            throw new waRightsException(_w('Access denied'));
        }
        $code = waRequest::get('code');
        if (strlen($code)) {

            $this->response['name'] = shopColorValue::getName($code);
        } elseif ($name = waRequest::get('name')) {
            $this->response['code'] = shopColorValue::getCode($name);
        }
    }
}
