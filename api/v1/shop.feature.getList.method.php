<?php

class shopFeatureGetListMethod extends waAPIMethod
{
    public function execute()
    {
        $feature_model = new shopFeatureModel();

        if ($type_id = waRequest::get('type_id')) {
            $features = $feature_model->getByType($type_id);
        } else {
            $features = $feature_model->getAll();
        }

        $this->response = $features;
        $this->response['_element'] = 'feature';
    }
}