<?php

class shopProductGetServicesMethod extends shopApiMethod
{
    protected $method = 'POST';

    protected $courier_allowed = true;

    public function execute()
    {
        if ($this->courier && $this->courier['rights_order_edit'] == 0) {
            throw new waAPIException('access_denied', 'Access denied to limited courier token.', 403);
        }


        $sku_ids = waRequest::post('sku_ids', array(), waRequest::TYPE_ARRAY_INT);

        if (!$sku_ids) {
            throw new waAPIException('invalid_param', 'Sku ids not found', 404);
        }

        $spsm = new shopProductServicesModel();
        $sku_m = new shopProductSkusModel();

        $products_id = $sku_m->getByField(array(
            'id' => $sku_ids,
        ), 'id');

        $services = array();

        foreach ($sku_ids as $sku_id) {
            $services[$sku_id] = $spsm->getAvailableServicesFullInfo(ifset($products_id, $sku_id, 'product_id', null), $sku_id);
        }

        $this->response['services'] = $services;
        $this->response['status'] = 'ok';
    }
}
