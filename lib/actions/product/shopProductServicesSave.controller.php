<?php

class shopProductServicesSaveController extends waJsonController
{
    /**
     * @var int
     */
    private $product_id;
    /**
     * @var int
     */
    private $service_id;

    /**
     * @var shopProductServicesModel
     */
    private $model;

    public function execute()
    {
        $this->product_id = waRequest::get('product_id', null, waRequest::TYPE_INT);
        $this->service_id = waRequest::get('service_id', null, waRequest::TYPE_INT);
        if (!$this->product_id) {
            $this->errors[] = _w("Unknown product");
            return;
        }

        $product_model = new shopProductModel();
        if (!$product_model->checkRights($this->product_id)) {
            throw new waException(_w("Access denied"));
        }

        // check rights
        if (!$this->service_id) {
            $this->errors = _w("Unkown service");
            return;
        }

        $product_services_model = new shopProductServicesModel();
        $product_services_model->save($this->product_id, $this->service_id, $this->getData());
        $this->response = array(
            'status' => $product_services_model->getProductStatus($this->product_id, $this->service_id),
            'count'  => $product_services_model->countServices($this->product_id)
        );
    }

    public function getRowData()
    {
        static $data = array();
        if (!$data) {
            $data['variants']           = waRequest::post('variant', array());
            $data['variant_prices']     = waRequest::post('variant_price', array());
            $data['variant_skus']       = waRequest::post('variant_sku', array());
            $data['variant_sku_prices'] = waRequest::post('variant_sku_price', array());
            $data['default']            = waRequest::post('default', 0, waRequest::TYPE_INT);
        }
        return $data;
    }

    public function getData()
    {
        $data = array();
        foreach ($this->getServiceVariants() as $variant_id => $variant) {
            $data[$variant_id] = $this->getVariant($variant_id);
        }
        return $data;
    }

    public function getVariant($id)
    {
        $data = $this->getRowData();
        return !empty($data['variants'][$id]) ?
            array(
                'price' => $this->formatPrice($data['variant_prices'][$id]),
                'status'=> $this->getStatus($data['default'] == $id),
                'skus'  => $this->getSkus(
                    $this->getProductSkus(),
                    isset($data['variant_skus'][$id]) ? $data['variant_skus'][$id] : array(),
                    $data['variant_sku_prices'][$id]
                )
            ) :
            $this->getEmptyVariant($this->getSkus($this->getProductSkus()));
    }

    public function getEmptyVariant($skus = null)
    {
        return $skus !== null ?
            array('price' => null, 'status' => shopProductServicesModel::STATUS_FORBIDDEN, 'skus'  => $skus) :
            array('price' => null, 'status' => shopProductServicesModel::STATUS_FORBIDDEN);
    }

    public function getServiceVariants()
    {
        static $data = null;
        if ($data === null) {
            $service_variants_model = new shopServiceVariantsModel();
            $data = $service_variants_model->getByField('service_id', $this->service_id, 'id');
        }
        return $data;
    }

    public function getProductSkus()
    {
        static $data = null;
        if ($data === null) {
            $product_skus_model = new shopProductSkusModel();
            $data = $product_skus_model->getByField('product_id', $this->product_id, 'id');
        }
        return $data;
    }

    public function getSkus($product_skus, $variant_skus = array(), $variant_sku_prices = array())
    {
        $data = array();
        foreach ($product_skus as $sku_id => $sku) {
            $price = !empty($variant_sku_prices[$sku_id]) ? $variant_sku_prices[$sku_id] : "";
            $price = $this->formatPrice($price);
            $data[$sku_id] =
                !empty($variant_skus[$sku_id]) ?
                    array(
                        'price' => $price,
                        'status'=> shopProductServicesModel::STATUS_PERMITTED
                    ) :
                    $this->getEmptyVariant()
            ;
        }
        return $data;
    }

    public function formatPrice($price)
    {
        return $price === "" ? null : $price;
    }

    public function getStatus($is_default)
    {
        return $is_default ? shopProductServicesModel::STATUS_DEFAULT : shopProductServicesModel::STATUS_PERMITTED;
    }
}
