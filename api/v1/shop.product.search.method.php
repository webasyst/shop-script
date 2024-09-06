<?php

class shopProductSearchMethod extends shopApiMethod
{
    protected $method = 'GET';
    protected $courier_allowed = true;

    public function execute()
    {
        $hash = $this->get('hash');
        $filters = waRequest::get('filters', null, 'array');
        $offset = waRequest::get('offset', 0, 'int');
        $escape = !!waRequest::get('escape', true);
        if ($offset < 0) {
            throw new waAPIException('invalid_param', 'Param offset must be greater than or equal to zero');
        }
        $limit = waRequest::get('limit', 100, 'int');
        if ($limit < 0) {
            throw new waAPIException('invalid_param', 'Param limit must be greater than or equal to zero');
        }
        if ($limit > 1000) {
            throw new waAPIException('invalid_param', sprintf_wp('The “limit” parameter value must not exceed %s.', 1000));
        }


        $collection = new shopProductsCollection($hash);
        if ($filters) {
            $collection->filters($filters);
        }

        // Check courier access rights
        if ($this->courier) {
            $alias = $collection->addJoin('shop_order_items', ":table.product_id=p.id AND :table.type='product'");
            $collection->addJoin('shop_order_params',
                ":table.order_id={$alias}.order_id AND :table.name='courier_id'",
                ":table.value='{$this->courier['id']}'"
            );
            $collection->groupBy('p.id');
        }

        $products = $collection->getProducts(self::getCollectionFields(), $offset, $limit, $escape);

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

    protected static function getCollectionFields()
    {
        $fields = array('*' => 1);
        $additional_fields = waRequest::request('fields', '', 'string');
        if ($additional_fields) {
            foreach (explode(',', $additional_fields) as $f) {
                $fields[$f] = 1;
            }
        }
        return join(',', array_keys($fields));
    }
}
