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

        $ccm = new waContactCategoriesModel();
        if (waRequest::request('set')) {
            $ccm->setContactCategories($customer_id, $category_ids);
        } else {
            if (!$category_ids) {
                return;
            }
            $ccm->add($customer_id, $category_ids);
        }

        $cm = new shopCustomerModel();
        $this->response['counts'] = $cm->getCategoryCounts();

    }
}

