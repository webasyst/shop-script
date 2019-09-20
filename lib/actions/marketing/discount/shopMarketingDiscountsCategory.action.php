<?php

/**
 * Form for discounts settings by category, and submit controller for that form.
 */
class shopMarketingDiscountsCategoryAction extends shopMarketingDiscountsViewAction
{
    protected $discount_type_id = 'category';

    public function execute()
    {
        $ccdm = new shopContactCategoryDiscountModel();

        // Categories
        $categories = array();
        $ccm = new waContactCategoryModel();
        $values = $ccdm->getAll('category_id', true);
        foreach ($ccm->getAll('id') as $c) {
            if ($c['app_id'] == 'shop') {
                $c['value'] = (float) ifset($values[$c['id']], 0);
                $categories[$c['id']] = $c;
            }
        }

        $enabled = shopDiscounts::isEnabled('category');

        $this->view->assign('enabled', $enabled);
        $this->view->assign('categories', $categories);
    }
}

