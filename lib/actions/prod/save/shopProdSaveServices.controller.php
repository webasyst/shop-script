<?php
/**
 * Save services from product services page
 */
class shopProdSaveServicesController extends waJsonController
{
    /** @var int */
    protected $product_id = null;

    /**
     * @throws waException
     */
    public function preExecute()
    {
        $this->product_id = max(0, waRequest::post('product_id', null, waRequest::TYPE_INT));

        if (!$this->product_id) {
            $this->errors[] = [
                'id' => 'general',
                'text' => _w('Unable to save the service.'),
            ];
            return;
        }

        $services = waRequest::post('services', [], waRequest::TYPE_ARRAY);
        $this->validateData($services);
        if ($this->errors) {
            return;
        }

        $product_model = new shopProductModel();
        $product = $product_model->getById($this->product_id);
        if (!$product) {
            $this->errors[] = [
                'id' => 'not_found',
                'text' => _w('Product not found.'),
            ];
            return;
        }
        if (!$product_model->checkRights($product)) {
            throw new waException(_w("Access denied"));
        }
    }

    public function execute()
    {
        $services = waRequest::post('services', [], waRequest::TYPE_ARRAY);

        $product_services_model = new shopProductServicesModel();
        foreach ($services as $service_id => $service) {
            if ((empty($service['product_id']) && !empty($service['type_id']))
                || (empty($service['status']) && empty($service['type_id']))
            ) {
                $product_services_model->deleteByField(array('product_id' => $this->product_id, 'service_id' => $service_id));
            } else {
                $product_services_model->save($this->product_id, $service_id, $this->getData($service_id, $service));
                $this->logAction('product_edit', $this->product_id);
            }
        }

        $this->response = array(
            'services'   => $services,
            'product_id' => $this->product_id
        );
    }


    protected function getData($service_id, $service_data)
    {
        $data = array();

        foreach ($service_data['variants'] as &$variant) {
            $variant['status'] = empty($variant['status']) ? shopProductServicesModel::STATUS_FORBIDDEN : shopProductServicesModel::STATUS_PERMITTED;
        }
        unset($variant);

        if (!empty($service_data['variant_id']) && !empty($service_data['status'])) {
            $variant_id = $service_data['variant_id'];
            if (isset($service_data['variants'][$variant_id])) {
                $service_data['variants'][$variant_id]['status'] = shopProductServicesModel::STATUS_DEFAULT;
            }
        }

        $service_variants = $this->getServiceVariants($service_id);
        foreach ($service_variants as $variant_id) {
            $variant_data = ifset($service_data, 'variants', $variant_id, array());
            $data[$variant_id] = $this->getVariant($variant_data);
        }

        return $data;
    }

    protected function getServiceVariants($service_id)
    {
        static $data = null;

        if (!isset($data[$service_id])) {
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

    protected function getVariant($variant_data)
    {
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

    protected function getEmptyVariant($skus = null)
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

    protected function getSkus($variant_skus = array())
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

    protected function getProductSkus()
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

    protected function formatPrice($price)
    {
        return $price === "" ? null : str_replace(',', '.', $price);
    }

    protected function validateData($services)
    {
        foreach ($services as $service_id => $service_data) {
            foreach ($service_data['variants'] as $variant_id => $variant) {
                $default_price = floatval($this->formatPrice($variant['price']));
                if ($default_price < 0) {
                    $this->errors[] = [
                        'id' => "service[$service_id][price]",
                        'text' => _w('Service price cannot be negative.'),
                    ];
                }
                if (!empty($variant['skus'])) {
                    foreach ($variant['skus'] as $sku_id => $sku) {
                        if (!empty($sku['status'])) {
                            $sku_price = floatval($this->formatPrice($sku['price']));
                            if ($sku_price < 0) {
                                $this->errors[] = [
                                    'id' => "service[$service_id][variant][$variant_id][price]",
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
