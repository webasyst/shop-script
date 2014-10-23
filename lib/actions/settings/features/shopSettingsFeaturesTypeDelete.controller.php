<?php
class shopSettingsFeaturesTypeDeleteController extends waJsonController
{
    public function execute()
    {
        if (!$this->getUser()->getRights('shop', 'settings')) {
            throw new waRightsException(_w('Access denied'));
        }
        $model = new shopTypeModel();
        $model->deleteById(waRequest::post('id'));
    }
}
