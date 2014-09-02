<?php

class shopProductServicesAction extends waViewAction
{
    /**
     * @var shopProductServicesModel
     */
    protected $model;

    public function execute()
    {
        $id = waRequest::get('id', null, waRequest::TYPE_INT);

        $product_model = new shopProductModel();
        $product = $product_model->getById($id);
        if (!$product) {
            throw new waException(_w("Unknown product"));
        }

        $service_id = $this->getServiceId();

        $model = $this->getModel();
        $services = $model->getServices($id);

        if ($service_id !== 0) {
            if (!empty($services)) {
                if (!$service_id || !isset($services[$service_id])) {
                    $service = reset($services);
                    $service_id = $service['id'];
                }
            }
        }

        if (!$service_id) {
            $this->assign(array(
                'product' => $product
            ));
            return;
        }

        $this->assign(array(
            'service'  => $this->getModel()->getProductServiceFullInfo($product['id'], $service_id),
            'product'  => $product,
            'services' => $services,
            'count'    => $this->getModel()->countServices($product['id'])
        ));
    }

    public function getServiceId()
    {
        $service_id = null;
        $param = waRequest::get('param', array());
        if ($param && isset($param[0])) {
            $service_id = (int)$param[0];
        }
        return $service_id;
    }

    /**
     * @return shopProductServicesModel
     */
    public function getModel()
    {
        if ($this->model === null) {
            $this->model = new shopProductServicesModel();
        }
        return $this->model;
    }

    public function assign($data = array())
    {
        $this->view->assign($data + $this->getDefaultData());
    }

    public function getDefaultData()
    {
        return array(
            'services' => array(),
            'service'  => array(),
            'product' => array(),
            'variants' => array()
        );
    }
}
