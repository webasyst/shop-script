<?php

class shopProductSearchMethod extends waAPIMethod
{
    protected $method = 'GET';

    public function execute()
    {
        $hash = $this->get('hash');
        $collection = new shopProductsCollection($hash);

        $offset = waRequest::get('offset', 0, 'int');
        if ($offset < 0) {
            throw new waAPIException('invalid_param', 'Param offset must be greater than or equal to zero');
        }
        $limit = waRequest::get('limit', 100, 'int');
        if ($limit < 0) {
            throw new waAPIException('invalid_param', 'Param limit must be greater than or equal to zero');
        }
        if ($limit > 1000) {
            throw new waAPIException('invalid_param', 'Param limit must be less or equal 1000');
        }
        $this->response['count'] = $collection->count();
        $this->response['offset'] = $offset;
        $this->response['limit'] = $limit;
        $this->response['products'] = array_values($collection->getProducts('*', $offset, $limit));
        $image_size = wa('shop')->getConfig()->getImageSize('thumb');
        foreach ($this->response['products'] as &$p) {
            if ($p['image_id']) {
                $p['image_url'] = shopImage::getUrl(array(
                    'id'         => $p['image_id'],
                    'product_id' => $p['id'],
                    'ext'        => $p['ext'],
                ), $image_size, true);
            }
        }
        unset($p);
    }
}