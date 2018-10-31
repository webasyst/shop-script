<?php

class shopFeatureGetInfoMethod extends shopApiMethod
{
    public function execute()
    {
        $feature_model = new shopFeatureModel();
        if ($id = $this->get('id')) {
            $feature = $feature_model->getById($id);
        } elseif ($code = $this->get('code')) {
            $feature = $feature_model->getByCode($code);
        } else {
            throw new waAPIException('invalid_param', 'Required parameter is missing: id or code', 400);
        }

        if (!$feature) {
            throw new waAPIException('invalid_param', 'Feature not found', 404);
        }


        if ($feature['selectable']) {
            $features = array_values($feature_model->getFeatureValues($feature));

            foreach ($features as $f) {
                if ($f instanceof shopColorValue) {
                    $feature['values'][] = $f->getRaw();
                } else {
                    $feature['values'][] = $f;
                }
            }
            $feature['values']['_element'] = 'value';
        }

        $this->response = $feature;
    }
}
