<?php

class shopServicesProductsCountController extends waJsonController
{
    public function execute()
    {
        $type_id = waRequest::post('type_id', null, waRequest::TYPE_ARRAY_INT);

        $product_model = new shopProductModel();
        $count = $product_model->countByField(array('type_id' => $type_id));

        $product_id = waRequest::post('product_id', null, waRequest::TYPE_ARRAY_INT);
        $where = array();
        if ($type_id) {
            $where[] = "type_id NOT IN(".  implode(',', $type_id) . ")";
        }
        if ($product_id) {
            $where[] = "id IN (" . implode(',', $product_id) . ")";
            $count += $product_model->select('COUNT(*)')->where(implode(' AND ', $where))->fetchField();
        }

        $this->response = array(
            'count_text' => '≈'._w('%d product', '%d products', $count)
        );
    }
}