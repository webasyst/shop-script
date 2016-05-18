<?php

/**
 * Left sidebar. Included in layout, as well as loaded via XHR.
 */
class shopCustomersSidebarAction extends waViewAction
{
    public function execute()
    {
        $categories = shopCustomer::getAllCategories();

        $cfm = new shopCustomersFilterModel();
        $col = new shopCustomersCollection();

        $this->view->assign('all_customers_count', $col->count());
        $this->view->assign('contacts_url', wa()->getAppUrl('contacts'));
        $this->view->assign('categories', $categories);
        $this->view->assign('filters', $cfm->getFilters());
    }
}

