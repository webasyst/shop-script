<?php

/**
 * Add contact to category. Used with drad-and-drop in list view.
 */
class shopCustomersCategoryController extends waJsonController
{
    public function execute()
    {
        $customer_id = waRequest::request('customer_id', 0, 'int');
        $category_id = waRequest::request('category_id', 0, 'int');
        
        if (!$customer_id || !$category_id) {
            return;
        }

        $ccm = new waContactCategoriesModel();
        $ccm->add($customer_id, $category_id);

        $cm = new shopCustomerModel();
        $this->response['count'] = $cm->getCategoryCounts($category_id);
        
    }
}

