<?php

class shopServiceSaveController extends waJsonController
{
    protected $service_model;

    public function execute()
    {
        $this->service_model = new shopServiceModel();
        $service_product_model = new shopProductServicesModel();

        $id = waRequest::get('id', null, waRequest::TYPE_INT);

        $edit = waRequest::get('edit', null, waRequest::TYPE_STRING_TRIM);
        if ($edit == 'name') {
            $this->service_model->updateById($id, array(
                'name' => waRequest::post('name', '', waRequest::TYPE_STRING_TRIM)
            ));
            return;
        }

        $data = $this->getData();
        $this->validateData($id, $data);
        if ($this->errors) {
            return;
        }

        if ($id) {
            // delete products
            $delete_products = waRequest::post('delete_product', array(), waRequest::TYPE_ARRAY_INT);
            $service_product_model->deleteByProducts($delete_products, $id);
        }

        $id = $this->service_model->save($data, $id, true);
        $this->response = array(
            'id' => $id
        );
    }

    public function getData()
    {
        $data = array(
            'name'     => waRequest::post('service_name', '', waRequest::TYPE_STRING_TRIM),
            'currency' => waRequest::post('currency', null, waRequest::TYPE_STRING_TRIM),
            'variants' => array()
        );

        $data['currency'] = !$data['currency'] ?
                wa('shop')->getConfig()->getCurrency() :
                $data['currency'];

        $variants = waRequest::post('variant', array(), waRequest::TYPE_ARRAY_INT);
        $names    = waRequest::post('name', array());
        $prices   = waRequest::post('price', array());

        $default = waRequest::post('default', null, waRequest::TYPE_INT);
        foreach ($variants as $k => $variant_id)
        {
            $data['variants'][] = array(
                'id'      => $variant_id,
                'name'    => !empty($names[$k]) ? $names[$k] : '',
                'price'   => $prices[$k] === "" ? null : $prices[$k],
                'default' => ($k == $default)
            );
        }

        $data['types'] = waRequest::post('type', array(), waRequest::TYPE_ARRAY_INT);
        $data['products'] = array_unique(waRequest::post('product', array(), waRequest::TYPE_ARRAY_INT));
        $data['tax_id'] = waRequest::post('tax_id', null, waRequest::TYPE_INT);
        if ($data['tax_id'] == -1) {
            $data['tax_id'] = null;
        }

        if (count($data['variants']) == 1) {
            $data['variants'][0]['default'] = true;
        }

        return $data;
    }

    protected function validateData($id, $data)
    {
        if ($id) {
            $service = $this->service_model->getById($id);
            if (!$service) {
                $this->errors[] = _w("Unknown service to update");
                return;
            }
        }

        foreach ($data['variants'] as $i => $variant) {
            if (is_numeric($variant['price']) && $variant['price'] < 0) {
                $this->errors[] = [
                    'id' => $i,
                    'text' => _w('Service price cannot be negative.'),
                ];
            }
        }
    }
}
