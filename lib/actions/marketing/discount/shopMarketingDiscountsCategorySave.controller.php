<?php

class shopMarketingDiscountsCategorySaveController extends shopMarketingSettingsJsonController
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