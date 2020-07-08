<?php

class shopSettingsFeaturesFeatureSaveController extends waJsonController
{
    public function execute()
    {
        if (!$this->getRights('settings')) {
            throw new waRightsException();
        }
        $features = waRequest::post('feature', array(), waRequest::TYPE_ARRAY);

        if ($features) {
            $model = new shopFeatureModel();
            $type_features_model = new shopTypeFeaturesModel();
            foreach ($features as $feature_id => & $feature) {
                if (!empty($feature['status_private'])) {
                    $feature['status'] = 'private';
                } else {
                    $feature['status'] = 'public';
                }
                unset($feature['status_private']);
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
            shopFeatureModel::appendTypeNames($features, true);

            // ->fillTypes() fills in 'sort' and 'types' keys. We want 'sort',
            // but do not want to break our own pre-made 'types'. So, we run the method on a copy.
            $copy = $features;
            $type_features_model->fillTypes($copy);
            foreach ($features as $feature_id => & $feature) {
                $sort = ifset($copy, $feature_id, 'sort', null);
                $feature['sort'] = $sort;
                if (!$sort) {
                    $feature['sort_json'] = '{}';
                } else {
                    $feature['sort_json'] = json_encode($copy[$feature_id]['sort']);
                }
            }
            unset($feature);

            /**
             * @event features_save
             * @param array $features
             * @return void
             */
            wa('shop')->event('features_save', $features);
        }

        $this->response = $features;
    }
}