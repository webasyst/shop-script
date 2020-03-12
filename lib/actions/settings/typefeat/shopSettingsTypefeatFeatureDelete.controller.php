<?php
/**
 * Delete given feature.
 */
class shopSettingsTypefeatFeatureDeleteController extends waJsonController
{
    public function execute()
    {
        $feature_id = waRequest::post('feature_id');

        if ($feature_id) {
            $model = new shopFeatureModel();
            $model->delete($feature_id);
            // (this also takes care of other tables internally)
        }
    }
}
