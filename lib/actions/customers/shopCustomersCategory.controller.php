<?php

/**
 * Add contact to category. Used with drad-and-drop in list view.
 */
class shopCustomersCategoryController extends waJsonController
{
    public function execute()
    {
        $customer_id = waRequest::request('customer_id', 0, 'int');
        $category_ids = waRequest::request('category_id', array(), waRequest::TYPE_ARRAY_INT);

        if (!$customer_id) {
            return;
        }

        $all_category_ids = array_keys(shopCustomer::getAllCategories());

        $ccm = new waContactCategoriesModel();
        if (waRequest::request('set')) {
            $customer = new shopCustomer($customer_id);
            $ccm->remove($customer_id, array_keys($customer->getCategories()));
        }
        
        if ($category_ids) {
            $ccm->add($customer_id, $category_ids);
        }

        $cm = new shopCustomerModel();
        $this->response['counts'] = $cm->getCategoryCounts($all_category_ids);

    }
}

