<?php

class shopImagesProductcreatesController extends waJsonController
{
    /**
     * @var shopProductModel
     */
    private $product_model;

    /**
     * @var shopProductSkusModel
     */
    private $product_skus_model;

    /**
     * @var string
     */
    private $currency;

    /**
     * @var integer
     */
    private $user_id;

    /**
     * @var integer
     */
    private $type_id;

    public function __construct()
    {
        $this->type_id = (int)waRequest::post('type_id');
        if (!$this->type_id) {
            $this->type_id = null;
        }

        /*
        if (!($this->type_id = (int)waRequest::post('type_id'))) {
            throw new waException(_w("Unkown type of product"));
        }
        */

        $this->product_model = new shopProductModel();
        $this->product_skus_model = new shopProductSkusModel();
        $this->currency = $this->getConfig()->getCurrency();
        $this->user_id  = $this->getUser()->getId();
    }

    public function execute()
    {
        $products = waRequest::post('product', array());

        $data = array();
        foreach ($products as $index => $product) {
            $product['id'] = (int) $product['id'];
            if ($product['id']) {
                continue;
            }
            $product['id'] = $this->addNewProduct(
                $this->formatData($product)
            );
            $data[$index] = $product['id'];
        }
        $this->response = $data;
    }

    public function formatData($product)
    {
        $product['name'] = trim($product['name']);
        if (empty($product['name'])) {
            $product['name'] = _w('New product');
        }
        $product['type_id']         = $this->type_id;
        $product['contact_id']      = wa()->getUser()->getId();
        $product['create_datetime'] = date('Y-m-d H:i:s');
        $product['currency']        = $this->currency;

        $product['skus'][-1] =
            array(
                'name' => '',
                'sku' => '',
                'price' => $product['price'],
                'available' => 1
            );

        return $product;
    }

    public function addNewProduct($data)
    {
        $product = new shopProduct();
        $product->save($data);
        return $product->getId();
    }
}