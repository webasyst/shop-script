<?php

class shopTagGetListMethod extends shopApiMethod
{
    protected $method = 'GET';

    public function execute()
    {
        $tag_model = new shopTagModel();

        $limit = waRequest::get('limit', 100, 'int');
        if ($limit < 0) {
            throw new waAPIException('invalid_param',  _w('A “limit” parameter value must be greater than or equal to zero.'));
        }
        if ($limit > 1000) {
            throw new waAPIException('invalid_param', sprintf_wp('A “limit” parameter value must not exceed %s.', 1000));
        }

        $this->response = $tag_model->getCloud(null, $limit);
    }
}
