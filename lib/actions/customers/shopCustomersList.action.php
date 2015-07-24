<?php

/**
 * Builds all customer list views.
 */
class shopCustomersListAction extends waViewAction
{
    protected $categories;
    protected $query;
    protected $category_id;
    protected $filter_id;
    protected $offset;
    protected $limit;
    protected $order;

    public function execute()
    {
        $offset = $this->getOffset();
        $limit = $this->getLimit();
        $order = $this->getOrder();
        $hash = $this->getHash();

        $collection = $this->getCollection($hash, $order);
        $customers = $collection->getCustomers('*,order.create_datetime AS  last_order_datetime', $offset, $limit);
        $this->workupList($customers);

        $total_count = $this->getTotalCount($collection);
        $count = count($customers);

        $title = _w('All customers');
        if ($hash && $hash !== 'all') {
            $title = $this->workupTitle($hash, $collection->getTitle());
        }

        $filter_id = $this->getFilterId();
        $filter = $this->getFilter($filter_id, array(
            'name' => $title,
            'hash' => $this->getQuery()
        ));

        $this->view->assign(array(
            'cols' => $this->getCols(),
            'title' => $title,
            'count' => $count,
            'offset' => $offset,
            'order' => $this->getOrder(false),
            'total_count' => $total_count,
            'customers' => $customers,
            'hash_start' => $this->getHashStart(),
            'category_id' => $this->getCategoryId(),
            'query' => $this->getQuery(true),
            'is_admin' => wa()->getUser()->isAdmin(),
            'icons' => wa()->getConfig()->getOption('customers_filter_icons'),
            'filter' => $filter,
            'filter_id' => $filter_id,
            'groups' => $this->getGroups(),
            'in_lazy_process' => waRequest::get('lazy', false),     // is now lazy loading?
            'lazy_loading_url' => $this->getLazyLoadingUrl()
        ));

        /*
         * @event backend_customers_list
         * @return array[string]array $return[%plugin_id%] array of html output
         * @return array[string][string]string $return[%plugin_id%]['top_li'] html output
         */
        $params = array('hash' => $hash, 'filter' => $filter);
        $this->view->assign('backend_customers_list', wa()->event('backend_customers_list', $params));

    }

    public function getHash()
    {
        $filter_id = $this->getFilterId();
        $category_id = $this->getCategoryId();
        $search = $this->getQuery();
        $hash = 'all';
        if ($category_id) {
            $hash = 'category/' . $category_id;
        } else if ($search) {
            $hash = 'search/' . $search;
        } else if ($filter_id) {
            $hash = 'filter/' . $filter_id;
        }
        return $hash;
    }

    public function getQuery($prepare_for_view = false)
    {
        $query = $this->query === null ? ($this->query = urldecode(waRequest::request('search'))) : $this->query;
        if ($prepare_for_view) {
            return str_replace('/', "\\/", $query);
        }
        return $query;
    }

    public function getCategoryId()
    {
        return $this->category_id === null ? ($this->category_id = waRequest::request('category', 0, 'int')) : $this->category_id;
    }

    public function getFilterId()
    {
        return $this->filter_id === null ? ($this->filter_id = waRequest::request('filter_id', 0, 'int')) : $this->filter_id;
    }

    public function getHashStart()
    {
        if ($this->getQuery()) {
            return '#/search/'.urlencode($this->getQuery()).'/';
        } else if ($this->getCategoryId()) {
            return '#/category/'.$this->getCategoryId().'/';
        } else {
            return '#/all/';
        }
    }

    public function getOffset()
    {
        return $this->offset === null ? ($this->offset = waRequest::request('offset', 0, 'int')) : $this->offset;
    }

    public function getLazyLoadingUrl()
    {
        $hash = array();
        $filter_id = $this->getFilterId();
        $category_id = $this->getCategoryId();
        $query = $this->getQuery(true);
        if ($filter_id) {
            $hash[] = 'filter_id=' . $filter_id;
        }
        if ($category_id) {
            $hash[] = 'category=' . $category_id;
        }
        if ($query) {
            $hash[] = 'search=' . $query;
        }
        $order = $this->getOrder(false);
        return '?module=customers&action=list' . ($hash ? '&'.implode('&', $hash) : '') . '&order=' . $order;
    }

    public function getLimit()
    {
        return wa()->getConfig()->getOption('customers_per_page');  // use config
    }

    public function getOrder($for_collection = true)
    {
        $order = $this->order === null ? ($this->order = waRequest::request('order', '!last_order')) : $this->order;

        if ($for_collection) {
            $possible_orders = array(
                'name' => 'name',
                '!name' => 'name DESC',
                'total_spent' => 'total_spent',
                '!total_spent' => 'total_spent DESC',
                'affiliate_bonus' => 'affiliate_bonus',
                '!affiliate_bonus' => 'affiliate_bonus DESC',
                'number_of_orders' => 'number_of_orders',
                '!number_of_orders' => 'number_of_orders DESC',
                'last_order' => 'last_order_id',
                '!last_order' => 'last_order_id DESC',
                'registered' => 'create_datetime',
                '!registered' => 'create_datetime DESC',
            );
            if (isset($possible_orders[$order])) {
                $order = explode(' ', $possible_orders[$order]);
                $order[1] = !empty($order[1]) ? $order[1] : 'ASC';
                return $order;
            }
            return array('id', 'ASC');
        }

        return $order;

    }

    public function getCollection($hash, $order)
    {
        $collection = new shopCustomersCollection($hash);
        $collection->orderBy($order[0], $order[1]);
        return $collection;
    }

    protected function workupList(&$customers)
    {
        $config = $this->getConfig();
        $use_gravatar     = $config->getGeneralSettings('use_gravatar');
        $gravatar_default = $config->getGeneralSettings('gravatar_default');

        $countries = array();

        foreach ($customers as &$c) {
            $c['email'] = !empty($c['email']) ? reset($c['email']) : null;
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
            $c['name'] = waContactNameField::formatName($c);
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
        $categories = $this->getCategories();
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
    }

    public function getCategories()
    {
        if ($this->categories === null) {
            $ccm = new waContactCategoryModel();
            $this->categories = $ccm->getAll('id');
        }
        return $this->categories;
    }

    public function getCols()
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

    public function getFilter($filter_id, $default_fields = array())
    {
        $m = new shopCustomersFilterModel();
        $filter = $m->getById($filter_id);
        if (!$filter) {
            $filter = array_merge($m->getEmptyRow(), $default_fields);
        }
        return $filter;
    }

    public function getGroups()
    {
        $group_model = new waGroupModel();
        return wa()->getUser()->isAdmin() ? $group_model->getNames() : array();
    }

    public function workupTitle($col_hash, $col_title)
    {
        $hash = explode('/', $col_hash);
        $hash[0] = ifset($hash[0], '');
        $hash[1] = ifset($hash[1], '');

        $ops = '\\\$=|\^=|\*=|==|!=|>=|<=|=|>|<|@=';
        foreach(array(
            'email',
            'phone',
            'email\|name',
            'name\|email'
        ) as $h) {
            if (preg_match("/^({$h})({$ops})[^&]+$/uis", $hash[1])) {
                return preg_replace("/{$h}({$ops})/", '', $hash[1]);
            }
        }
        if ($hash[0] === 'filter') {
            return $col_title;
        } else if ($hash[0] === 'category') {
            return $col_title;
        }

        $title = array();
        foreach (explode(',', $col_title) as $part) {
            $tokens = preg_split("/({$ops})/uis", $part, 2, PREG_SPLIT_DELIM_CAPTURE);
            unset($tokens[0]);
            if (isset($tokens[1]) && $tokens[1] === '=') {
                unset($tokens[1]);
            }
            $title[] = implode('', $tokens);
        }
        return implode(',', $title);
    }

    public function getTotalCount(shopCustomersCollection $collection)
    {
        $total_count = waRequest::request('total_count');
        if ($total_count === null) {
            return $collection->count();
        } else {
            return $total_count;
        }
    }

}