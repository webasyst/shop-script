<?php

class shopMarketingDiscountsCategorySaveController extends waJsonController
{
    public function execute()
    {
        $ccdm = new shopContactCategoryDiscountModel();

        $categories = waRequest::post('categories', array());
        if (is_array($categories)) {
            $ccdm->save($categories);
        }
    }
}