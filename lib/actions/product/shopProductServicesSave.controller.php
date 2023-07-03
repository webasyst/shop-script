<?php

class shopProductServicesSaveController extends waJsonController
{
    /** @var int */
    private $product_id;

    /**
     * @throws waException
     */
    public function preExecute()
    {
        parent::preExecute();
        $this->product_id = max(0, waRequest::get('product_id', null, waRequest::TYPE_INT));

        if (!$this->product_id) {
            throw new waException(_w("Unknown product"));
        }

        $product_model = new shopProductModel();
        if (!$product_model->checkRights($this->product_id)) {
            throw new waException(_w("Access denied"));
        }
    }

    /**
     * @throws waException
     */
    public function execute()
    {
        $product_services_model = new shopProductServicesModel();

        //product_services[%service_id%][default_variant]
        //product_services[%service_id%][variants][%variant_id%][price]
        //product_services[%service_id%][variants][%variant_id%][status]
        //product_services[%service_id%][variants][%variant_id%][skus][%sku_id%][price]
        //product_services[%service_id%][variants][%variant_id%][skus][%sku_id%][status]

        $post_data = waRequest::post('product_services');
        $service_ids = array_keys($post_data);

        $this->getServiceVariants($service_ids);

        $this->validateData($post_data);
        if ($this->errors) {
            return;
        }

        foreach ($post_data as $service_id => $service_data) {
            $product_services_model->save($this->product_id, $service_id, $this->getData($service_id, $service_data));
        }

        $this->response = array(
            'status' => $product_services_model->getProductStatus($this->product_id, $service_ids),
            'count'  => $product_services_model->countServices($this->product_id),
        );
    }

    private function getData($service_id, $service_data)
    {
        $data = array();

        //$service_data[default_variant]
        //$service_data[variants][%variant_id%][price]
        //$service_data[variants][%variant_id%][status]
        //$service_data[variants][%variant_id%][skus][%sku_id%][price]
        //$service_data[variants][%variant_id%][skus][%sku_id%][status]

        foreach ($service_data['variants'] as &$variant) {
            $variant['status'] = empty($variant['status']) ? shopProductServicesModel::STATUS_FORBIDDEN : shopProductServicesModel::STATUS_PERMITTED;
            unset($variant);
        }

        if (!empty($service_data['default_variant'])) {
            $variant_id = $service_data['default_variant'];
            if (isset($service_data['variants'][$variant_id])) {
                $service_data['variants'][$variant_id]['status'] = shopProductServicesModel::STATUS_DEFAULT;
            }
        }

        foreach ($this->getServiceVariants($service_id) as $variant_id) {
            $variant_data = ifset($service_data, 'variants', $variant_id, array());
            $data[$variant_id] = $this->getVariant($variant_data);
        }
        return $data;
    }

    private function getVariant($variant_data)
    {
        //$data = $this->getRowData();

        //$variant_data[price]
        //$variant_data[status]
        //$variant_data[skus][%sku_id%][price]
        //$variant_data[skus][%sku_id%][status]
        $data = array();

        if (!empty($variant_data)) {
            $skus = $this->getSkus(ifset($variant_data, 'skus', array()));

            $variant = array(
                'price'  => $this->formatPrice(ifset($variant_data, 'price', '')),
                'status' => $variant_data['status'],
                'skus'   => $skus,
            );
        } else {
            $variant = $this->getEmptyVariant($this->getSkus());
        }

        return $variant;
    }

    private function getEmptyVariant($skus = null)
    {
        $empty = array(
            'price'  => null,
            'status' => shopProductServicesModel::STATUS_FORBIDDEN,
        );

        if ($skus !== null) {
            $empty['skus'] = $skus;
        }

        return $empty;
    }

    private function getServiceVariants($service_id)
    {
        static $data = null;

        if ($data === null) {
            $service_variants_model = new shopServiceVariantsModel();
            $variants = $service_variants_model->getByField('service_id', $service_id, 'id');
            foreach ($variants as $variant_id => $variant) {
                $_service_id = $variant['service_id'];
                if (!isset($data[$_service_id])) {
                    $data[$_service_id] = array();
                }
                $data[$_service_id][$variant_id] = $variant_id;
            }
        }

        return is_array($service_id) ? $data : ifset($data, $service_id, array());
    }

    private function getProductSkus()
    {
        static $data = null;

        if ($data === null) {
            $product_skus_model = new shopProductSkusModel();
            $skus = $product_skus_model->getByField('product_id', $this->product_id, 'id');
            $skus = array_keys($skus);
            $data = array_combine($skus, $skus);
        }

        return $data;
    }

    private function getSkus($variant_skus = array())
    {
        $data = array();

        $product_skus = $this->getProductSkus();

        foreach ($product_skus as $sku_id) {
            if (!empty($variant_skus[$sku_id]['status'])) {
                $sku = $variant_skus[$sku_id];
                $sku += array(
                    'price' => '',
                );

                $data[$sku_id] = array(
                    'price'  => $this->formatPrice($sku['price']),
                    'status' => shopProductServicesModel::STATUS_PERMITTED,
                );
            } else {
                $data[$sku_id] = $this->getEmptyVariant();
            }
        }

        return $data;
    }

    private function formatPrice($price)
    {
        return $price === "" ? null : str_replace(',', '.', $price);
    }

    protected function validateData($post_data)
    {
        foreach ($post_data as $service_id => $service_data) {
            foreach ($service_data['variants'] as $variant_id => $variant) {
                $default_price = floatval($this->formatPrice(ifset($variant, 'price', '')));
                if ($default_price < 0) {
                    $this->errors[] = [
                        'id' => "product_services[$service_id][variants][$variant_id][price]",
                        'text' => _w('Service price cannot be negative.'),
                    ];
                }
                if (!empty($variant['skus'])) {
                    foreach ($variant['skus'] as $sku_id => $sku) {
                        if (!empty($sku['status'])) {
                            $sku_price = floatval($this->formatPrice(ifset($sku, 'price', '')));
                            if ($sku_price < 0) {
                                $this->errors[] = [
                                    'id' => "product_services[$service_id][variants][$variant_id][skus][$sku_id][price]",
                                    'text' => _w('Service price cannot be negative.'),
                                ];
                            }
                        }
                    }
                }
            }
        }
    }
}
