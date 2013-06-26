<?php

class shopTagGetListMethod extends waAPIMethod
{
    protected $method = 'GET';

    public function execute()
    {
        $tag_model = new shopTagModel();

        $limit = waRequest::get('limit', 100, 'int');
        if ($limit < 0) {
            throw new waAPIException('invalid_param', 'Param limit must be greater than or equal to zero');
        }
        if ($limit > 1000) {
            throw new waAPIException('invalid_param', 'Param limit must be less or equal 1000');
        }

        $this->response = $tag_model->getCloud(null, $limit);
    }
}