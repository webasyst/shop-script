<?php

class shopServicesAction extends waViewAction
{
    public function execute()
    {
        $id = waRequest::get('id', null, waRequest::TYPE_INT);

        $service_model = new shopServiceModel();

        $service = array();
        $services = $service_model->getAll('id');
        if ($id !== 0) {
            if (!empty($services)) {
                if ($id && isset($services[$id])) {
                    $service = $services[$id];
                } else {
                    $service = current($services);
                }
            }
        }

        // blank area for adding new service
        if (!$service) {
            $services[] = $this->getEmptyService();

            $type_model = new shopTypeModel();
            $types = $type_model->getAll();
            foreach ($types as &$t) {
                $t['type_id'] = $t['id'];
            }
            unset($t);

            $this->assign(array(
                'services' => $services,
                'types' => $types,
                'count' => $service_model->countAll(),
                'products_count' => $this->getProductsCount($types),
                'taxes' => $this->getTaxes()
            ));
            return;
        }
        $service_variants_model = new shopServiceVariantsModel();
        $variants = $service_variants_model->get($service['id']);

        $type_services_model = new shopTypeServicesModel();
        $types = $type_services_model->getTypes($service['id']);

        $product_services_model = new shopProductServicesModel();
        $products = $product_services_model->getProducts($service['id']);
        $products_count = $this->getProductsCount($types, $products);

        $this->assign(array(
            'services'       => $services,
            'service'        => $service,
            'products'       => $products,
            'types'          => $types,
            'products_count' => $products_count,
            'variants'       => $variants,
            'count'          => $service_model->countAll(),
            'taxes'          => $this->getTaxes()
        ));
    }

    public function getTaxes()
    {
        $tax_model = new shopTaxModel();
        return $tax_model->getAll('id');
    }

    public function assign($data) {
        $this->view->assign($data + $this->getDefaultData() + $this->getCurrencyData());
    }

    public function getDefaultData() {
        return array(
            'services' => array(),
            'service'  => $this->getEmptyService(),
            'products' => array(),
            'types' => array(),
            'products_count' => 0,
            'variants' => array(
                $this->getEmptyVariant()
            )
        );
    }

    public function getCurrencyData() {
        return array(
            'use_product_currency' => wa()->getSetting('use_product_currency'),
            'currencies' => $this->getCurrencies(),
            'primary_currency' => $this->getConfig()->getCurrency()
        );
    }

    public function getCurrencies()
    {
        $model = new shopCurrencyModel();
        return $model->getCurrencies();
    }

    public function getEmptyService() {
        return array(
            'id' => 0,
            'name' => _w('New service'),
            'price' => 0,
            'variant_id' => 0,
            'tax_id' => 0,
            'currency' => '%'
        );
    }

    public function getEmptyVariant() {
        return array(
            'id' => 0,
            'name' => '',
            'price' => 0,
            'type_of_price' => 'p'
        );
    }

    public function getProductsCount($types, $products = array())
    {
        $products_count = 0;
        $selected_types = array();
        foreach ($types as $type) {
            if ($type['type_id']) {
                $selected_types[] = $type['type_id'];
            }
        }
        if (!empty($selected_types)) {
            $product_model = new shopProductModel();
            $products_count = $product_model->countByField(array('type_id' => $selected_types));
        }
        foreach ($products as $k => $product) {
            if (in_array($product['type_id'], $selected_types)) {
                unset($products[$k]);
            }
        }
        return $products_count + count($products);
    }

}
