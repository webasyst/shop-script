<?php

/**
 * Class shopOrderSearchMethod
 *
 * PHPUnit : tests/wa-apps/shop/api/order/shopOrderSearchMethodTest.php
 */
class shopOrderSearchMethod extends shopApiMethod
{
    protected $method = 'GET';
    protected $courier_allowed = true;

    public function execute()
    {
        $this->validateRequest();

        $offset = waRequest::get('offset', 0, 'int');
        $limit = waRequest::get('limit', 100, 'int');

        $this->response['offset'] = $offset;
        $this->response['limit'] = $limit;

        if ($this->courier || wa()->getUser()->getRights('shop', 'orders')) {

            $collection = $this->getCollection();
            $collection->orderBy($this->getSort());

            $this->response['count'] = $collection->count();
            $orders = array_values($collection->getOrders(self::getCollectionFields(), $offset, $limit));

            if ($orders) {
                foreach ($orders as &$o) {
                    foreach (array('auth_code', 'auth_pin') as $k) {
                        if (!empty($o['params'][$k])) {
                            unset($o['params'][$k]);
                        }
                    }

                    if (isset($o['items'])) {
                        $i = 0;
                        foreach ($o['items'] as $index => &$item) {
                            $item['sort'] = $i++;
                        }
                        unset($item);
                    }
                }
                unset($o);
            }
            $this->response['orders'] = $orders;
        } else {
            $this->response['count'] = 0;
            $this->response['orders'] = array();
        }
    }

    protected function getCollection()
    {
        $hash = $this->get('hash');

        return new shopOrdersCollection($hash, !$this->courier ? array() : array(
            'courier_id' => $this->courier['id'],
        ));
    }

    protected function validateRequest()
    {
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
    }

    protected static function getCollectionFields()
    {
        $fields = array_fill_keys(array('*', 'items', 'params'), 1);
        $additional_fields = waRequest::request('fields', '', 'string');
        if ($additional_fields) {
            foreach (explode(',', $additional_fields) as $f) {
                $fields[$f] = 1;
            }
        }
        if (!empty($fields['contact_full'])) {
            unset($fields['contact']);
        }
        return join(',', array_keys($fields));
    }

    public function getSort()
    {
        $sort = waRequest::request('sort', null, 'string');
        if (!$sort) {
            $sort = 'create_datetime DESC';
        }

        $sort = explode(' ', $sort, 2);

        $sort_order = (string)ifset($sort[1]);
        if ($sort_order != 'DESC') {
            $sort_order = 'ASC';
        }

        $m = new shopOrderModel();
        $sort_field = (string)ifset($sort[0]);
        if (!$m->fieldExists($sort_field) && !in_array($sort_field, array('updated', 'amount', 'state_id'))) {
            $sort_field = 'create_datetime';
        }

        return array($sort_field => $sort_order);
    }
}
