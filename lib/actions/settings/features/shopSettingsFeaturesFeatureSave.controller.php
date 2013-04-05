<?php
class shopSettingsFeaturesFeatureSaveController extends waJsonController
{
    public function execute()
    {
        if ($features = waRequest::post('feature')) {

            $model = new shopFeatureModel();
            $type_features_model = new shopTypeFeaturesModel();

            foreach ($features as $feature_id => & $feature) {

                $feature['id'] = $model->save($feature, $feature_id);
                if ($feature['selectable']) {
                    $feature['values'] = $model->setValues($feature, $feature['values']);
                }

                $feature['types'] = $type_features_model->updateByFeature($feature['id'], $feature['types']);
                if ($feature_id < $feature['id']) {
                    foreach ($feature['types'] as $type) {
                        $type_features_model->move(array('feature_id' => $feature['id'], 'type_id' => $type), null, $type);
                    }
                }
            }
            unset($feature);
            shopFeatureModel::appendTypeNames($features);
        }
        $this->response = $features;
    }
}
