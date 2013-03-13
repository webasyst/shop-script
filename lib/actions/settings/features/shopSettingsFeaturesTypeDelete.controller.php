<?php
class shopSettingsFeaturesTypeDeleteController extends waJsonController
{
    public function execute()
    {
        $model = new shopTypeModel();
        $model->deleteById(waRequest::post('id'));
    }
}
