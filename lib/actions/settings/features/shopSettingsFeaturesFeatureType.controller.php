<?php
class shopSettingsFeaturesFeatureTypeController extends waJsonController
{
    public function execute()
    {
        $model = new shopTypeFeaturesModel();
        $data = array(
            'type_id'    => waRequest::post('type', 0, waRequest::TYPE_INT),
            'feature_id' => waRequest::post('feature', 0, waRequest::TYPE_INT),
        );
        $model->insert($data);
    }
}
