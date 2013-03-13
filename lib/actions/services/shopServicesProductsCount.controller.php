<?php

class shopServicesProductsCountController extends waJsonController
{
    public function execute()
    {
        $type_id = waRequest::post('type_id', null, waRequest::TYPE_ARRAY_INT);

        $product_model = new shopProductModel();
        $count = $product_model->countByField(array('type_id' => $type_id));
        $this->response = array(
            'count_text' => $count > 0 ? '≈'._w('%d product', '%d products', $count) : ''
        );
    }
}