<?php

class shopProductMassUpdateMethod extends shopApiMethod
{
    protected $method = 'POST';

    public function execute()
    {
        $raw_products = self::getArrayParam('product');
        $raw_skus = self::getArrayParam('sku');
        if (!$raw_products && !$raw_skus) {
            throw new waAPIException('invalid_param', 'no data to update', 400);
        }

        try {
            shopProductMassUpdate::update($raw_skus, $raw_products);
        } catch (waException $e) {
            throw new waAPIException('invalid_param', $e->getMessage());
        }

        $this->response = 'ok';
    }

    protected static function getArrayParam($name)
    {
        $data = waRequest::post($name, null, 'array');
        if (!$data) {
            $data_json = waRequest::post('j'.$name, null, 'string');
            if ($data_json) {
                $data = @json_decode($data_json, true);
            }
        }
        if (!$data) {
            $data = array();
        }
        return $data;
    }
}
