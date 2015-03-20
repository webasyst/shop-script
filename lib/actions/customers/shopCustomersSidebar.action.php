<?php

/**
 * Left sidebar. Included in layout, as well as loaded via XHR.
 */
class shopCustomersSidebarAction extends waViewAction
{
    public function execute()
    {
        // Category counts
        // !!! Probably not the best idea to fetch category counts on the fly...
        $cm = new shopCustomerModel();
        $counts = $cm->getCategoryCounts();

        // Categories
        $ccm = new waContactCategoryModel();
        $categories = array();
        foreach($ccm->getAll() as $c) {
            if ($c['app_id'] == 'shop') {
                $c['cnt'] = ifset($counts[$c['id']], 0);
                $categories[$c['id']] = $c;
            }
        }

        $cfm = new shopCustomersFilterModel();

        $col = new shopCustomersCollection();

        $this->view->assign('all_customers_count', $col->count());
        $this->view->assign('contacts_url', wa()->getAppUrl('contacts'));
        $this->view->assign('categories', $categories);
        $this->view->assign('filters', $cfm->getFilters());
    }
}

