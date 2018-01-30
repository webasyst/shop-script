<?php

class shopFeatureGetListMethod extends shopApiMethod
{
    public function execute()
    {
        $feature_model = new shopFeatureModel();

        if ($type_id = waRequest::get('type_id')) {
            $features = $feature_model->getByType($type_id, 'id');
        } else {
            $features = $feature_model->getAll('id');
        }

        $selectable = array();
        foreach ($features as $f_id => $f) {
            if ($f['selectable']) {
                $selectable[$f_id] = $f;
            }
        }
        if ($selectable) {
            $selectable = $feature_model->getValues($selectable);
            foreach ($selectable as $f_id => $f) {
                $values = array();
                foreach ($f['values'] as $value) {
                    $values[] = (string) $value;
                }
                $f['values'] = $values;
                $f['values']['_element'] = 'value';
                $features[$f_id] = $f;
            }
        }

        $this->response = $features;
        $this->response['_element'] = 'feature';
    }
}
