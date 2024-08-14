<?php

class shopFeatureDeleteMethod extends shopApiMethod
{
    protected $method = 'POST';

    public function execute()
    {
        if (!$this->getRights('settings')) {
            throw new waAPIException('access_denied', 403);
        }

        $feature_model = new shopFeatureModel();
        if ($id = $this->post('id')) {
            $feature = $feature_model->getById($id);
        } elseif ($code = $this->post('code')) {
            $feature = $feature_model->getByCode($code);
        } else {
            throw new waAPIException('invalid_param', sprintf_wp(
                'Missing required parameter: %s.',
                sprintf_wp('“%s” or “%s”', 'id', 'code')
            ), 400);
        }

        if (!$feature) {
            throw new waAPIException('invalid_param', _w('Feature not found.'), 404);
        }

        $this->response = (bool)$feature_model->delete($feature['id']);
    }
}
