<?php
/**
 * Return warning message when given url is used in another product.
 */
class shopProdCheckUrlInUseController extends waJsonController
{
    public function execute()
    {
        $info = array(
            'url' => waRequest::request('url', '', 'string'),
            'id' => waRequest::request('id', '', 'int'),
        );
        $this->response['url_in_use'] = shopHelper::isProductUrlInUse($info);
    }
}
