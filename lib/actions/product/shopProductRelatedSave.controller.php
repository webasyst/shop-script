<?php

class shopProductRelatedSaveController extends waJsonController
{
    protected $product_id;
    public function execute()
    {
        $this->product_id = waRequest::get('id');

        if (waRequest::post('cross_selling') !== null) {
            $this->save('cross_selling');
        }

        if (waRequest::post('upselling') !== null) {
            $this->save('upselling');
        }

        if (waRequest::post('product_id')) {
            if (waRequest::post('delete')) {
                $this->deleteRelatedProduct();
            } else {
                $this->saveRelatedProducts();
            }
        }

    }

    protected function save($type)
    {
        $value = waRequest::post($type);
        $product = new shopProduct($this->product_id);
        $product->save(array($type => $value));
        if ($value != 2) {
            $related_model = new shopProductRelatedModel();
            $related_model->deleteByField(array('product_id' => $this->product_id, 'type' => $type));
        }
    }

    protected function deleteRelatedProduct()
    {
        $related_model = new shopProductRelatedModel();
        $type = waRequest::post('type');
        $data = array(
            'product_id' => $this->product_id,
            'type' => $type,
            'related_product_id' => waRequest::post('product_id')
        );
        $related_model->deleteByField($data);
    }

    protected function saveRelatedProducts()
    {
        $related_model = new shopProductRelatedModel();
        $type = waRequest::post('type');
        $related_product_id = array_map('intval', (array) waRequest::post('product_id'));
        foreach ($related_product_id as $product_id) {
            $related_model->replace(array(
            'product_id' => $this->product_id,
            'type' => $type,
            'related_product_id' => $product_id
        ));
        }

    }
}