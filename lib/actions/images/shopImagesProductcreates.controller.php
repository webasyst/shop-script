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

    /**
     * @var integer
     */
    private $category_id;

    /**
     * @var string
     */
    private $set_id;

    public function __construct()
    {
        $this->type_id = (int)waRequest::post('type_id');
        $this->category_id = (int)waRequest::post('category_id');
        $this->set_id = (string)waRequest::post('set_id');
        if (!$this->type_id) {
            $this->type_id = null;
        }
        if (!$this->category_id) {
            $this->category_id = null;
        }
        if (!$this->set_id) {
            $this->set_id = null;
        }

        /*
        if (!($this->type_id = (int)waRequest::post('type_id'))) {
            throw new waException(_w("Unknown product type"));
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
        $product['category_id']     = $this->category_id;
        $product['contact_id']      = wa()->getUser()->getId();
        $product['create_datetime'] = date('Y-m-d H:i:s');
        $product['currency']        = $this->currency;

        $product['skus'][-1] =
            array(
                'name' => '',
                'sku' => '',
                'price' => $product['price'],
                'available' => 1,
                'status' => 1,
            );

        return $product;
    }

    public function addNewProduct($data)
    {
        $product = new shopProduct();
        $result = $product->save($data);
        if ($result && $this->set_id) {
            (new shopSetProductsModel())->add($product->getId(), $this->set_id);
        }
        return $product->getId();
    }
}
