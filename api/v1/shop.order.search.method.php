<?php

class shopOrderSearchMethod extends waAPIMethod
{
    protected $method = 'GET';

    public function execute()
    {
        $hash = $this->get('hash');
        $collection = new shopOrdersCollection($hash);

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
        $this->response['orders'] = array_values($collection->getOrders('*,items,params', $offset, $limit));
        if ($this->response['orders']) {
            foreach ($this->response['orders'] as &$o) {
                foreach (array('auth_code', 'auth_pin') as $k) {
                    if (!empty($o['params'][$k])) {
                        unset($o['params'][$k]);
                    }
                }
            }
            unset($o);
        }
    }
}