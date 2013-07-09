<?php

class shopFeatureGetInfoMethod extends waAPIMethod
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
            $feature['values'] = array_values($feature_model->getFeatureValues($feature));
            $feature['values']['_element'] = 'value';
        }

        $this->response = $feature;
    }
}