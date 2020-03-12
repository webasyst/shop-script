<?php
/**
 * Add existing features to product type.
 */
class shopSettingsTypefeatTypeAddFeaturesController extends waJsonController
{
    public function execute()
    {
        $type_id = waRequest::request('type_id', '', waRequest::TYPE_INT);
        $feature_ids = waRequest::post('features', [], waRequest::TYPE_ARRAY_INT);
        if (!$type_id || !$feature_ids) {
            return;
        }

        $type_features_model = new shopTypeFeaturesModel();
        $type_features_model->addFeaturesToType($type_id, $feature_ids);
    }
}
