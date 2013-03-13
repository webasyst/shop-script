<?php
class shopSettingsFeaturesFeatureDeleteController extends waJsonController
{
    public function execute()
    {
        $model = new shopFeatureModel();
        $model->deleteById(waRequest::post('feature_id'));
    }
}
