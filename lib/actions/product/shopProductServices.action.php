<?php

class shopProductServicesAction extends waViewAction
{
    /**
     * @var shopProductServicesModel
     */
    protected $model;

    /**
     * @var array
     */
    protected $product;

    /**
     * @throws waException
     */
    public function preExecute()
    {
        parent::preExecute();
        $this->model = new shopProductServicesModel();

        $id = max(0, waRequest::get('id', null, waRequest::TYPE_INT));
        if ($id) {
            $product_model = new shopProductModel();
            $this->product = $product_model->getById($id);
            if (!$product_model->checkRights($this->product)) {
                throw new waException(_w("Access denied"));
            }
        }
        if (!$this->product) {
            throw new waException(_w("Unknown product"));
        }
    }

    public function execute()
    {
        $service_id = $this->getServiceId();

        $services = $this->model->getServices($this->product);

        if ($service_id !== 0) {
            if (!empty($services)) {
                if (!$service_id || !isset($services[$service_id])) {
                    $service = reset($services);
                    $service_id = $service['id'];
                    unset($service);
                }
            }
        } else {

        }

        if (!$service_id) {
            $this->assign(array(
                'product' => $this->product,
            ));
            return;
        }

        $service = $this->model->getProductServiceFullInfo($this->product['id'], $service_id);

        $this->assign(array(
            'service'  => $service,
            'product'  => $this->product,
            'services' => $services,
            'count'    => $this->model->countServices($this->product['id']),
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

    public function assign($data = array())
    {
        $this->view->assign($data + $this->getDefaultData());
    }

    public function getDefaultData()
    {
        return array(
            'services' => array(),
            'service'  => array(),
            'product'  => array(),
            'variants' => array(),
        );
    }
}
