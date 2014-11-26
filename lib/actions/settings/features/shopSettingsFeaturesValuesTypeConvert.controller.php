<?php

class shopSettingsFeaturesValuesTypeConvertController extends waJsonController
{
    public function execute()
    {
        $feature_id = waRequest::post('feature_id');
        $feature_model = new shopFeatureModel();
        $feature = $feature_model->getById($feature_id);
        if (!$feature) {
            throw new waException(_w('Unknown feature'));
        }

        if (waRequest::post('subtype')) {
            $to = waRequest::post('subtype');
        } else {
            $to = waRequest::post('type');
        }

        $to = array(
            'type'       => $to,
            'selectable' => (int)waRequest::post('selectable', 0, waRequest::TYPE_INT),
            'multiple'   => (int)waRequest::post('multiple', 0, waRequest::TYPE_INT)
        );

        $result = shopFeatureValuesConverter::run($feature_id, $to);
        if (!$result) {
            $this->errors[] = _w('Feature type conversion is not allowed or failed');
        } else {

            if ($feature = $feature_model->getById($feature_id)) {
                $this->response = array($feature_id => &$feature);
                $feature['selectable'] = (int)$feature['selectable'];
                $feature['multiple'] = (int)$feature['multiple'];

                if ($feature['selectable']) {
                    $this->response = $feature_model->getValues($this->response, true);
                    $sort = 0;
                    foreach ($feature['values'] as $id => &$value) {
                        if (!is_object($value)) {
                            $value = array(
                                'id'    => $id,
                                'value' => $value,
                                'sort'  => $sort++,
                            );
                            unset($value);
                        } else {
                            if (method_exists($value, 'getRaw')) {
                                $value = $value->getRaw();
                            } else {

                                $value = array(
                                    'id'    => $id,
                                    'value' => (string)$value,
                                    'sort'  => $sort++,
                                );
                            }
                        }
                    }
                    $feature['values'] = array_values($feature['values']);
                }


                shopFeatureModel::appendTypeNames($this->response);
                $type_features_model = new shopTypeFeaturesModel();
                $type_features_model->fillTypes($this->response);
                $feature['types'] = array_keys($feature['types']);
                sort($feature['types']);
            }
        }
    }
}
