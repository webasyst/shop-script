<?php
class shopSettingsFeaturesFeatureDeleteController extends waJsonController
{
    public function execute()
    {
        if (!$this->getUser()->getRights('shop', 'settings')) {
            throw new waRightsException(_w('Access denied'));
        }
        $feature_id = waRequest::post('feature_id');

        if ($feature_id) {
            $model = new shopFeatureModel();
            $model->delete($feature_id);
        }
    }
}
