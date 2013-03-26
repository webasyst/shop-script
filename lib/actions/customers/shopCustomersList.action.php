<?php

/**
 * Builds all customer list views.
 */
class shopCustomersListAction extends waViewAction
{
    public function execute()
    {
        $category_id = waRequest::request('category', 0, 'int');
        $search = waRequest::request('search');
        $start = waRequest::request('start', 0, 'int');
        $limit = 50;
        $order = waRequest::request('order', '!last_order');

        $config = $this->getConfig();
        $use_gravatar     = $config->getGeneralSettings('use_gravatar');
        $gravatar_default = $config->getGeneralSettings('gravatar_default');

        // Get customers
        $scm = new shopCustomerModel();
        list ($customers, $total) = $scm->getList($category_id, $search, $start, $limit, $order);
        $has_more = $start + count($customers) < $total;
        $countries = array();
        foreach ($customers as &$c) {
            $c['affiliate_bonus'] = (float) $c['affiliate_bonus'];
            if (!$c['photo'] && $use_gravatar) {
                $c['photo'] = shopHelper::getGravatar(!empty($c['email']) ? $c['email'] : '', 50, $gravatar_default);
            } else {
                $c['photo'] = waContact::getPhotoUrl($c['id'], $c['photo'], 50, 50);
            }
            $c['categories'] = array();
            if (!empty($c['address']['region']) && !empty($c['address']['country'])) {
                $countries[$c['address']['country']] = array();
            }
        }
        unset($c);

        // Add region names to addresses
        if ($countries) {
            $rm = new waRegionModel();
            foreach($rm->where('country_iso3 IN (?)', array_keys($countries))->query() as $row) {
                $countries[$row['country_iso3']][$row['code']] = $row['name'];
            }
            foreach ($customers as &$c) {
                if (!empty($c['address']['region']) && !empty($c['address']['country'])) {
                    $country = $c['address']['country'];
                    $region = $c['address']['region'];
                    if (!empty($countries[$country]) && !empty($countries[$country][$region])) {
                        $c['address']['region_formatted'] = $countries[$country][$region];
                    }
                }
            }
            unset($c);
        }

        // Contact categories
        $ccm = new waContactCategoryModel();
        $categories = $ccm->getAll('id');
        if ($customers) {
            $ccsm = new waContactCategoriesModel();
            foreach($ccsm->getContactsCategories(array_keys($customers)) as $c_id => $list) {
                foreach($list as $cat_id) {
                    if (!empty($categories[$cat_id])) {
                        $customers[$c_id]['categories'][$cat_id] = $categories[$cat_id];
                    }
                }
            }
        }

        // Set up lazy loading
        if (!$has_more) {
            // Do not trigger lazy loading, show total count at end of list
            $total_customers_number = $start + count($customers);
        } else {
            $total_customers_number = null; // trigger lazy loading
        }


        // List title and other params depending on list type
        if ($search) {
            $title = _w('Search results');
            $hash_start = '#/search/0/'.urlencode($search).'/';
            $discount = null;
        } else if ($category_id) {
            if (!empty($categories[$category_id])) {
                $title = $categories[$category_id]['name'];
            } else {
                $title = _w('Unknown category').' '.$category_id;
            }
            $hash_start = '#/category/'.$category_id.'/';

            if (wa()->getSetting('discount_category')) {
                $ccdm = new shopContactCategoryDiscountModel();
                $discount = sprintf_wp('%s%% discount', $ccdm->getDiscount($category_id));
            } else {
                $discount = null;
            }
        } else {
            $title = _w('All customers');
            $hash_start = '#/all/0/';
            $discount = null;
        }

        $lazy_loading_params = array(
            'limit='.$limit,
            'start='.($start+$limit),
            'order='.$order,
        );
        if ($search) {
            $lazy_loading_params[] = 'search='.$search;
        } else if ($category_id) {
            $lazy_loading_params[] = 'category='.$category_id;
        }
        $lazy_loading_params = implode('&', $lazy_loading_params);

        $this->view->assign('cols', self::getCols());
        $this->view->assign('title', $title);
        $this->view->assign('order', $order);
        $this->view->assign('total', $total);
        $this->view->assign('discount', $discount);
        $this->view->assign('customers', $customers);
        $this->view->assign('hash_start', $hash_start);
        $this->view->assign('category_id', $category_id);
        $this->view->assign('lazy_loading_params', $lazy_loading_params);
        $this->view->assign('total_customers_number', $total_customers_number);
    }

    public static function getCols()
    {
        return array(
            'name' => _w('Customer name'),
            'total_spent' => _w('Total spent'),
            'affiliate_bonus' => _w('Affiliate bonus'),
            'number_of_orders' => _w('Number of orders'),
            'last_order' => _w('Last order'),
            'registered' => _w('Registered'),
        );
    }
}

