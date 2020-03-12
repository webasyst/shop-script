<?php
class shopSettingsTypefeatFeatureSortController extends waJsonController
{
    public function execute()
    {
        $type_id = waRequest::post('type_id', 0, 'int');
        $feature_ids = waRequest::post('ids', 0, 'array_int');

        $type_features_model = new shopTypeFeaturesModel();
        $type_features_model->setSortOrder($type_id, $feature_ids);
    }
}
