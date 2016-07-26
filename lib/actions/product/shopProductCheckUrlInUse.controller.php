<?php

class shopProductCheckUrlInUseController extends waJsonController
{
    public function execute()
    {
        $info = array(
            'url' => (string) $this->getRequest()->request('url'),
            'id' => (int) $this->getRequest()->request('id')
        );
        $this->response['url_in_use'] = shopHelper::isProductUrlInUse($info);
    }
}