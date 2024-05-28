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

        $this->view->assign('all_customers_count', (new shopCustomersCollection('search/app.show_contacts=customers'))->count());
        $this->view->assign('all_contacts_count', (new shopCustomersCollection())->count());
        $this->view->assign('contacts_url', wa()->getAppUrl('contacts'));
        $this->view->assign('categories', $categories);
        $this->view->assign('filters', $cfm->getFilters());
    }
}

