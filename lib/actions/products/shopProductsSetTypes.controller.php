<?php

class shopProductsSetTypesController extends waJsonController
{
    /**
     * @var shopTypeModel
     */
    private $type_model;
    /**
     * @var shopProductModel
     */
    private $product_model;

    public function __construct()
    {
        $this->type_model = new shopTypeModel();
        $this->product_model = new shopProductModel();
    }

    public function execute()
    {
        $type_id = $this->getType();
        if (!$type_id) {
            return;
        }

        $hash = waRequest::post('hash', '', waRequest::TYPE_STRING_TRIM);
        if (!$hash) {
            $product_ids = waRequest::post('product_id', array(), waRequest::TYPE_ARRAY_INT);
            $product_ids = $this->product_model->filterAllowedProductIds($product_ids);
            if (!$product_ids) {
                return;
            }
            $this->product_model->updateType($product_ids, $type_id);

            $this->response['types'] = $this->type_model->getTypes();
        } else if (substr($hash, 0, 5) != 'type/') {
            $collection = new shopProductsCollection($hash);
            $offset = 0;
            $count = 100;
            $total_count = $collection->count();
            while ($offset < $total_count) {
                $ids = array_keys($collection->getProducts('*', $offset, $count));
                $filtered = $this->product_model->filterAllowedProductIds($ids);
                $this->product_model->updateType($filtered, $type_id);
                $offset += count($ids);
            }
            $this->response['types'] = $this->type_model->getTypes();
        } else {
            $this->product_model->changeType(substr($hash, 5), $type_id);
            $this->response['types'] = $this->type_model->getTypes();
        }
    }

    public function getType()
    {
        $type_id = waRequest::post('type_id', null, waRequest::TYPE_INT);
        if (!$type_id) {
            return null;
        } else {
            $types = shopTypeModel::extractAllowed(array($type_id));
            if (!$types) {
                return null;
            } else {
                return $type_id;
            }
        }
    }
}