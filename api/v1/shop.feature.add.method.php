<?php

class shopFeatureAddMethod extends waAPIMethod
{
    protected $method = 'POST';

    public function execute()
    {
        if (!$this->getRights('settings')) {
            throw new waAPIException('access_denied', 403);
        }

        $code = $this->post('code', true);
        $type = $this->post('type', true);
        $this->post('name', true);
        $feature_model = new shopFeatureModel();
        if ($feature_model->getByCode($code)) {
            throw new waAPIException('invalid_param', 'Code '.$code.' already exists');
        }

        $types =  array(
            shopFeatureModel::TYPE_BOOLEAN,
            shopFeatureModel::TYPE_DOUBLE,
            shopFeatureModel::TYPE_TEXT,
            shopFeatureModel::TYPE_VARCHAR,
            shopFeatureModel::TYPE_COLOR,
        );

        if (!in_array($type, $types)) {
            throw new waAPIException('invalid_param', 'Invalid type: '.$type);
        }

        $data = waRequest::post();
        $feature_id = $feature_model->insert($data);
        if ($feature_id) {
            $_GET['id'] = $feature_id;
            $method = new shopFeatureGetInfoMethod();
            $this->response = $method->getResponse(true);
        } else {
            throw new waAPIException('server_error', 500);
        }
    }
}