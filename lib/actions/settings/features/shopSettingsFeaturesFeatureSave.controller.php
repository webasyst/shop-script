<?php

class shopSettingsFeaturesFeatureSaveController extends waJsonController
{
    public function execute()
    {
        if (!$this->getUser()->getRights('shop', 'settings')) {
            throw new waRightsException(_w('Access denied'));
        }
        if ($features = waRequest::post('feature')) {

            $model = new shopFeatureModel();
            $type_features_model = new shopTypeFeaturesModel();
            foreach ($features as $feature_id => & $feature) {

                $feature['id'] = $model->save($feature, $feature_id);
                if ($feature['selectable']) {
                    if (($feature_id < 0) && is_array($feature['values']) && (count($feature['values']) == 1)) {
                        $value = reset($feature['values']);
                        if (is_array($value) && isset($value['value'])) {
                            if ($value['value'] === '') {
                                $feature['values'] = array();
                            }

                        } else {
                            if ($value === '') {
                                $feature['values'] = array();
                            }
                        }
                    }
                    $feature['values'] = $model->setValues($feature, $feature['values']);
                }

                $feature['types'] = $type_features_model->updateByFeature($feature['id'], $feature['types']);
                if ($feature_id < $feature['id']) {
                    $feature['sort'] = array();
                    foreach ($feature['types'] as $type) {
                        $feature['sort'][$type] = $type_features_model->move(array('feature_id' => $feature['id'], 'type_id' => $type), null, $type);
                    }
                }
            }
            unset($feature);
            shopFeatureModel::appendTypeNames($features);

            // ->fillTypes() fills in 'sort' and 'types' keys. We want 'sort',
            // but do not want to break our own pre-made 'types'. So, we run the method on a copy.
            $copy = $features;
            $type_features_model->fillTypes($copy);
            foreach ($features as $feature_id => & $feature) {
                $feature['sort'] = $copy[$feature_id]['sort'];
                if (empty($copy[$feature_id]['sort'])) {
                    $feature['sort_json'] = '{}';
                } else {
                    $feature['sort_json'] = json_encode($copy[$feature_id]['sort']);
                }
            }
            unset($feature);
        }
        $this->response = $features;
    }
}
