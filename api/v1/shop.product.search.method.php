<?php

class shopProductSearchMethod extends waAPIMethod
{
    protected $method = 'GET';

    public function execute()
    {
        $hash = $this->get('hash');
        $filters = waRequest::get('filters', null, 'array');
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

        $collection = new shopProductsCollection($hash);
        if ($filters) {
            $collection->filters($filters);
        }
        $products = $collection->getProducts(self::getColelctionFields(), $offset, $limit);

        $this->response['count'] = $collection->count();
        $this->response['offset'] = $offset;
        $this->response['limit'] = $limit;
        $this->response['products'] = array_values($products);
        $image_size = wa('shop')->getConfig()->getImageSize('thumb');
        foreach ($this->response['products'] as &$p) {
            if ($p['image_id']) {
                $p['image_url'] = shopImage::getUrl(array(
                    'id'         => $p['image_id'],
                    'filename'   => $p['image_filename'],
                    'ext'        => $p['ext'],
                    'product_id' => $p['id'],
                ), $image_size, true);
            }
        }
        unset($p);
    }

    protected static function getColelctionFields()
    {
        $fields = array('*' => 1);
        $additional_fields = waRequest::request('fields', '', 'string');
        if ($additional_fields) {
            foreach(explode(',', $additional_fields) as $f) {
                $fields[$f] = 1;
            }
        }
        return join(',', array_keys($fields));
    }
}