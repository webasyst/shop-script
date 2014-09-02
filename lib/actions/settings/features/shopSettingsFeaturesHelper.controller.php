<?php
class shopSettingsFeaturesHelperController extends waJsonController
{
    public function execute()
    {
        if (!$this->getUser()->getRights('shop', 'settings')) {
            throw new waRightsException(_w('Access denied'));
        }
        if ($code = waRequest::get('code')) {

            $this->response['name'] = shopColorValue::getName($code);
        } elseif ($name = waRequest::get('name')) {
            $this->response['code'] = shopColorValue::getCode($name);
        }
    }
}
