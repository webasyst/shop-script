<?php

class shopProdCategoryChangeFilterController extends waJsonController
{
    public function execute()
    {
        $category_id = waRequest::post('category_id', null, waRequest::TYPE_INT);
        $switch = waRequest::post('switch', 0, waRequest::TYPE_INT);

        $category_model = new shopCategoryModel();
        $category_model->update($category_id, [
            'filter' => ($switch ? 'price' : null)
        ]);
    }
}