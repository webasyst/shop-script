<?php

class shopCustomersCollectionPreparator
{
    protected $customer_table_alias;
    protected $last_order_table_alias;
    protected $filtering_order_table_alias;
    protected $model;
    protected $join_table_index = array();
    protected $left_join_table_index = array();
    protected $join_table_aliases = array();
    protected $left_join_table_aliases = array();
    protected $models = array();
    protected $options = array();
    protected $filter_data = null;

    /** @var waContactsCollection */
    protected $collection;

    public function __construct(waContactsCollection $collection, $options = array())
    {
        $this->options = $options;
        $this->collection = $collection;
        $hash = $collection->getHash();
        if (ifset($hash, 0, '') === 'search') {
            $this->options = $this->parseOptionsFromSearchQuery(ifset($hash, 1, '')) + $this->options;
        } else if (ifset($hash, 0, '') === 'filter') {
            $filter_data = $this->getFilterData();
            if ($filter_data && !empty($filter_data['hash'])) {
                $this->options = $this->parseOptionsFromSearchQuery($filter_data['hash']) + $this->options;
            }
        }

        // Add shop_customer join
        $this->customer_table_alias = $this->addJoinOnce([
            'table' => 'shop_customer',
            'type'  => empty($this->options['left_customer_join']) ? null : 'LEFT',
        ]);

        // Last order join
        $this->last_order_table_alias = $this->addJoinOnce([
            'table' => 'shop_order',
            'on'    => $this->customer_table_alias.'.last_order_id = :table.id',
            'type'  => 'LEFT'
        ]);
    }

    public function getCustomerTableAlias()
    {
        return $this->customer_table_alias;
    }

    public function getOrderTableAlias()
    {
        return $this->last_order_table_alias;
    }

    protected function getFilteringOrderTableAlias()
    {
        if (!$this->filtering_order_table_alias) {

            $on = [':table.contact_id = c.id'];
            if (empty($this->options['include_unpaid_orders'])) {
                $on[] = ':table.paid_date IS NOT NULL';
            } else if ($this->options['include_unpaid_orders'] === 'only') {
                $on[] = ':table.paid_date IS NULL';
            }

            $this->filtering_order_table_alias = $this->addJoin([
                'table' => 'shop_order',
                'on' => join(' AND ', $on),
            ]);
        }
        return $this->filtering_order_table_alias;
    }

    protected function addJoinOnce($table, $on = null, $where = null, $options = array())
    {
        if (is_array($table)) {
            $table_name = ifset($table, 'table', null);
        } else {
            $table_name = $table;
        }

        if (empty($this->join_table_aliases[$table_name])) {
            return $this->addJoin($table, $on, $where, $options);
        } else if (!empty($this->join_table_aliases[$table_name])) {
            return $this->join_table_aliases[$table_name][0];
        }
    }

    protected function addLeftJoinOnce($table, $on = null, $where = null, $options = array())
    {
        if (empty($this->left_join_table_aliases[$table])) {
            return $this->addLeftJoin($table, $on, $where, $options);
        } else {
            return $this->left_join_table_aliases[$table][0];
        }
    }

    protected  function addJoin($table, $on = null, $where = null, $options = array()) {
        $alias = $this->collection->addJoin($table, $on, $where, $options);

        if (is_array($table)) {
            $table_name = ifset($table, 'table', null);
        } else {
            $table_name = $table;
        }

        $this->join_table_index[$table_name] = ifset($this->join_table_index[$table_name], 0);
        $this->join_table_index[$table_name] += 1;
        $this->join_table_aliases[$table_name] = ifset($this->join_table_aliases[$table_name], array());
        $this->join_table_aliases[$table_name][] = $alias;
        return $alias;
    }

    protected  function addLeftJoin($table, $on = null, $where = null, $options = array()) {
        $alias = $this->collection->addLeftJoin($table, $on, $where, $options);
        $this->left_join_table_index[$table] = ifset($this->join_table_index[$table], 0);
        $this->left_join_table_index[$table] += 1;
        $this->left_join_table_aliases[$table] = ifset($this->join_table_aliases[$table], array());
        $this->left_join_table_aliases[$table][] = $alias;
        return $alias;
    }

    protected  function addWhere($condition, $or = false)
    {
        return $this->collection->addWhere($condition, $or);
    }

    protected  function addHaving($condition)
    {
        $this->collection->setGroupBy('c.id');
        return $this->collection->addHaving($condition);
    }

    protected  function addTitle($title, $delim = ', ')
    {
        return $this->collection->addTitle(ifset($this->options['title_prefix'], '').$title, $delim);
    }

    public function setTitle($title) {
        return $this->collection->setTitle(ifset($this->options['title_prefix'], '').$title);
    }

    public function filterPrepare($filter_id, $auto_title = true)
    {
        $filter = $this->getFilterData();
        if (empty($filter)) {
            return;
        }
        if (!$filter['hash']) {
            $this->where[] = 1;
        } else {
            // Pretend collection has another hash.
            // This will eventually call $this->searchPrepare()
            $this->collection->setHash("search/" . $filter['hash']);
            $this->collection->prepare(false, false);
        }
        $this->addTitle($filter['name']);
    }

    protected function getFilterData()
    {
        $hash = $this->collection->getHash();
        if (ifset($hash, 0, '') !== 'filter' || empty($hash[1])) {
            return $this->filter_data;
        }
        $filter_id = intval($hash[1]);
        if (!$filter_id) {
            return null;
        }

        if (empty($this->filter_data) || $this->filter_data['id'] != $filter_id) {
            $this->filter_data = $this->getModel('customers_filter')->getById($filter_id);
        }

        return $this->filter_data;
    }

    public function categoryPrepare($id, $auto_title = false)
    {
        $category_model = new waContactCategoryModel();
        $category = $category_model->getById($id);
        if ($category) {
            if ($auto_title) {
                $this->setTitle($category['name']);
            }
        }
        $this->addJoin('wa_contact_categories', null, ':table.category_id = '.(int)$id);
    }

    public function searchPrepare($query, $auto_title = true, $rest_prepare = true)
    {
        $query = $this->middlewareSearchPrepare($query, $auto_title);

        $escapedBS = 'ESCAPED_BACKSLASH';
        while(FALSE !== strpos($query, $escapedBS)) {
            $escapedBS .= rand(0, 9);
        }
        $escapedAmp = 'ESCAPED_AMPERSAND';
        while(FALSE !== strpos($query, $escapedAmp)) {
            $escapedAmp .= rand(0, 9);
        }
        $query = str_replace('\\&', $escapedAmp, str_replace('\\\\', $escapedBS, $query));
        $query = explode('&', $query);

        foreach ($query as $k => $part) {
            if (!( $part = trim($part))) {
                continue;
            }
            $part = str_replace(array($escapedBS, $escapedAmp), array('\\', '&'), $part);
            $parts = preg_split("/(\\\$=|\^=|\*=|==|!=|>=|<=|=|>|<|@=)/uis", $part, 2, PREG_SPLIT_DELIM_CAPTURE);
            if (empty($parts[0])) {
                continue;
            }
            $f = $parts[0];
            $op = $parts[1];
            $val = ifset($parts[2], '');
            if ($f === 'email|name' || $f === 'name|email') {
                $this->searchPrepareEmailName($op, $val, $auto_title);
                unset($query[$k]);
            } else if (substr($f, 0, 13) == 'order_params.') {
                $param_name = $this->getModel()->escape(substr($f, 13));
                $expr = $this->getExpression($op, $val);
                $on = ":table.order_id = {$this->getFilteringOrderTableAlias()}.id AND :table.name = '{$param_name}'";
                if (strtolower($val) === ':null') {
                    $this->addLeftJoin("shop_order_params", $on, ":table.value IS NULL");
                } else {
                    $this->addJoin("shop_order_params", $on, ":table.value {$expr}");
                }
                unset($query[$k]);
            } else if ($f === 'coupon') {
                $this->searchPrepareCoupon($op, $val, $auto_title);
                unset($query[$k]);
            }
        }

        $query = implode('&', $query);
        $query = str_replace($escapedAmp, '\\&', str_replace($escapedBS, '\\\\', $query));

        if ($rest_prepare) {
            $this->collection->setHash("search/" . $query);
            $this->collection->prepare(false, $auto_title);
            return null;
        } else {
            return $query;
        }
    }

    // Used in tonstructor to set options depending on search query
    protected function parseOptionsFromSearchQuery($query)
    {
        $options = [];
        $hash_ar = self::parseSearchHash($query);

        //
        // We have two important parameters that affect how whole collection behaves.
        //
        // * show_contacts customers|paid|unpaid|all
        //   when customers: JOIN shop_customer, WHERE number_of_orders > 0
        //   when unpaid:    JOIN shop_customer, WHERE number_of_orders > 0 AND total_spent <= 0
        //   when paid:      JOIN shop_customer, WHERE number_of_orders > 0 AND total_spent > 0
        //   when all:       LEFT JOIN shop_customer
        //
        // * consider_orders all|paid|unpaid
        //   when paid:   Total amount spent and number of orders are taken from shop_customer table.
        //                In case contact has no shop_customer record, they are taken to be zero.
        //                (this forces show_contacts=paid)
        //   when unpaid: Aggregated sum with join to shop_order, filter unpaid orders only.
        //   when all:    Aggregated sum with join to shop_order.
        //

        if (empty($hash_ar['app']['consider_orders'])) {
            $hash_ar['app']['consider_orders'] = [
                'val' => 'all',
                'op' => '=',
            ];
        }
        if ($hash_ar['app']['consider_orders']['val'] === 'unpaid') {
            $options['include_unpaid_orders'] = 'only';
        } else if ($hash_ar['app']['consider_orders']['val'] !== 'paid') {
            $options['include_unpaid_orders'] = true;
        } // also see middlewareSearchPrepare

        $show_contacts_val = ifempty($hash_ar, 'app', 'show_contacts', 'val', 'customers');
        if ($show_contacts_val === 'all') {
            $options['left_customer_join'] = true;
            // also see searchPrepareShowContacts
        }

        return $options;
    }

    protected function middlewareSearchPrepare(&$query, $auto_title = true)
    {
        $hash_ar = self::parseSearchHash($query);

        $contact_info_hash = array();
        if (!empty($hash_ar['contact_info'])) {
            foreach ($hash_ar['contact_info'] as $field_id => $conditions) {
                $field = waContactFields::get($field_id);
                if ($field && $field instanceof waContactCompositeField) {
                    foreach ($conditions as $subfield_id => $val) {
                        $contact_info_hash[] = "{$field_id}:{$subfield_id}{$val['op']}{$val['val']}";
                    }
                    continue;
                }
                if ($field_id === 'create_method') {
                    $val = ifset($conditions['val'], '');
                    $val = explode('.', $val);
                    if (!empty($val[1])) {
                        $contact_info_hash[] = "create_method={$val[1]}";
                    }
                    $contact_info_hash[] = "create_app_id=shop";
                    continue;
                }
                $contact_info_hash[] = "{$field_id}{$val['op']}{$val['val']}";
            }
            unset($hash_ar['contact_info']);
        }

        if (empty($hash_ar['app']['consider_orders'])) {
            $hash_ar['app']['consider_orders'] = [
                'val' => 'all',
                'op' => '=',
            ];
        }

        if (ifset($hash_ar, 'app', 'consider_orders', 'val', '') === 'paid') {
            $hash_ar['app']['show_contacts'] = [
                'val' => 'paid',
                'op' => '=',
            ];
        } else if (empty($hash_ar['app']['show_contacts'])) {
            $hash_ar['app']['show_contacts'] = [
                'val' => 'customers',
                'op' => '=',
            ];
        }

        if (!empty($hash_ar['app'])) {
            foreach ($hash_ar['app'] as $k => $h) {
                /** call
                 * searchPrepareShowContacts
                 * searchPrepareConsiderOrders
                 * searchPrepareEmailName
                 * searchPrepareShipmentMethod
                 * searchPreparePaymentMethod
                 * searchPrepareTotalSpent
                 * searchPrepareOrdersTotalSum
                 * searchPrepareNumberOfOrders
                 * searchPrepareOrderDatetime
                 * searchPrepareProduct
                 * searchPrepareStorefront
                 * searchPrepareReferer
                 * searchPrepareFirstOrderDatetime
                 * searchPrepareLastOrderDatetime
                 * searchPrepareCoupon
                 * searchPrepareUtmCampaign
                 */
                $method_name = 'searchPrepare' . implode('', array_map('ucfirst', explode('_', $k)));
                if (method_exists($this, $method_name)) {
                    $this->$method_name($h['op'], $h['val'], $auto_title);
                }
            }
            unset($hash_ar['app']);
        }

        self::recursiveCleanArray($hash_ar);

        $address_hash = '';
        if (isset($hash_ar['address'])) {
            $address_hash = self::buildSearchHash(array('address' => $hash_ar['address']));
            $address_hash = str_replace('.', ':', $address_hash);
            unset($hash_ar['address']);
        }
        $rest_hash = self::buildSearchHash($hash_ar);

        $query = array();
        if ($contact_info_hash) {
            $query[] = implode('&', $contact_info_hash);
        }
        if ($address_hash) {
            $query[] = $address_hash;
        }
        if ($rest_hash) {
            $query[] = $rest_hash;
        }
        return implode('&', $query);
    }

    protected function searchPrepareShowContacts($op, $val = '', $auto_title = true)
    {
        if ($val == 'all') {
            return; // already done at this point, see parseOptionsFromSearchQuery
        }

        $al = $this->customer_table_alias;
        $this->addWhere("{$al}.number_of_orders > 0");

        if ($val == 'unpaid') {
            $this->addWhere("{$al}.total_spent <= 0");
        } else if ($val == 'paid') {
            $this->addWhere("{$al}.total_spent > 0");
        }
    }

    protected function searchPrepareConsiderOrders($op, $val = '', $auto_title = true)
    {
        if ($val === 'unpaid') {
            // Only show customers who has at least one unpaid order.
            // We only require a join, no conditions necessary.
            $this->getFilteringOrderTableAlias();
        }
    }

    protected function searchPrepareEmailName($op, $val = '', $auto_title = true)
    {
        $alias = $this->addLeftJoin('wa_contact_emails');
        $expr = $this->getExpression($op, $val);
        $this->addWhere("{$alias}.email {$expr} OR c.name {$expr}");
    }

    protected function searchPrepareShipmentMethod($op, $val = '', $auto_title = true)
    {
        $val = (int) $val;
        $this->addJoin('shop_order_params', ":table.order_id = {$this->getFilteringOrderTableAlias()}.id AND :table.name = 'shipping_id' AND :table.value = '{$val}'");
        if ($auto_title) {
            $item = $this->getModel('plugin')->getById($val);
            $this->addTitle(_w('Shipping option') . '=' . ($item ? $item['name'] : $val));
        }
    }

    protected function searchPreparePaymentMethod($op, $val = '', $auto_title = true)
    {
        $val = (int) $val;
        $this->addJoin('shop_order_params', ":table.order_id = {$this->getFilteringOrderTableAlias()}.id AND :table.name = 'payment_id' AND :table.value = '{$val}'");
        if ($auto_title) {
            $item = $this->getModel('plugin')->getById($val);
            $this->addTitle(_w('Payment option') . '=' . ($item ? $item['name'] : $val));
        }
    }

    protected function searchPrepareTotalSpent($op, $val = '', $auto_title = true)
    {
        if (is_array($val)) {
            $range[0] = $this->getModel()->escape($val[0]);
            $range[1] = $this->getModel()->escape($val[1]);
            $operator = ($range[0] == 0 ? '>' : '>=');
            if (empty($this->options['include_unpaid_orders'])) {
                $al = $this->customer_table_alias;
                $this->addWhere("{$al}.total_spent {$operator} '{$range[0]}' AND {$al}.total_spent <= '{$range[1]}'");
            } else {
                $al = $this->getFilteringOrderTableAlias();
                $this->addHaving("SUM({$al}.total * {$al}.rate) {$operator} '{$range[0]}' AND SUM({$al}.total * {$al}.rate) <= '{$range[1]}'");
            }
            if ($auto_title)  {
                $this->addTitle(_w('Total spent') . '=' . $val[0] . '–' . $val[1] . ' ' . wa('shop')->getConfig()->getCurrency());
            }
        } else {
            $expr = $this->getExpression($op, $val);
            if (empty($this->options['include_unpaid_orders'])) {
                $this->addWhere("{$this->customer_table_alias}.total_spent {$expr}");
            } else {
                $al = $this->getFilteringOrderTableAlias();
                $this->addHaving("SUM({$al}.total * {$al}.rate) {$expr}");
            }
            if ($auto_title) {
                $this->addTitle(_w('Total spent') . $op . $val . ' ' . wa('shop')->getConfig()->getCurrency());
            }
        }
    }

    protected function searchPrepareOrdersTotalSum($op, $val = '', $auto_title = true)
    {
        if (is_array($val)) {
            $range[0] = $this->getModel()->escape($val[0]);
            $range[1] = $this->getModel()->escape($val[1]);
            $al = $this->addJoinOnce('shop_order');
            $this->addHaving("SUM({$al}.total * {$al}.rate) >= '{$range[0]}' AND SUM({$al}.total * {$al}.rate) <= '{$range[1]}'");
            if ($auto_title)  {
                $this->addTitle(_w('Total spent') . '=' . $val[0] . '–' . $val[1] . ' ' . wa('shop')->getConfig()->getCurrency());
            }
        } else {
            $expr = $this->getExpression($op, $val);
            $al = $this->addJoinOnce('shop_order');
            $this->addHaving("SUM({$al}.total * {$al}.rate) {$expr}");
            if ($auto_title) {
                $this->addTitle(_w('Total spent') . $op . $val . ' ' . wa('shop')->getConfig()->getCurrency());
            }
        }
    }

    protected function searchPrepareNumberOfOrders($op, $val = '', $auto_title = true)
    {
        if (in_array($op, array('<', '>', '=', '>=', '<='))) {
            $val = (int) $val;

            if (!empty($this->options['include_unpaid_orders'])) {
                $this->addWhere("{$this->customer_table_alias}.number_of_orders {$op} '{$val}'");
            } else {
                $this->addHaving("COUNT({$this->getFilteringOrderTableAlias()}.id) {$op} '{$val}'");
            }

            if ($auto_title) {
                $this->addTitle(_w('Number of orders') . $op . $val);
            }
        }
    }

    protected function searchPrepareOrderDatetime($op, $val = '', $auto_title = true)
    {
        if (is_array($val)) {
            $period[0] = $this->getModel()->escape($val[0]);
            $period[1] = $this->getModel()->escape($val[1]);
            $this->addWhere("{$this->getFilteringOrderTableAlias()}.create_datetime >= '{$period[0]}' AND {$this->getFilteringOrderTableAlias()}.create_datetime <= '{$period[1]}'");
            if ($auto_title) {
                $this->addTitle(_w('Order time frame') . '=' . $val[0] . '–' . $val[1]);
            }
        } else {
            $date = $val;
            $title = $op . $val;
            if (!is_numeric(substr($val, -1))) {
                $quantifier = substr($val, -1);
                $shift = substr($val, 0, -1);
                if ($quantifier === 'd') {
                    $date = date('Y-m-d', strtotime("{$shift} days"));
                    $title = $op . "{$shift} days";
                } else {
                    return;
                }
            }
            $expr = $this->getExpression($op, $date);
            $this->addWhere("{$this->getFilteringOrderTableAlias()}.create_datetime {$expr}");
            if ($auto_title) {
                $this->addTitle(_w('Order time frame') . $title);
            }
        }
    }

    protected function searchPrepareProduct($op, $val = '', $auto_title = true)
    {
        if (is_numeric($val)) {
            $val = (int) $val;
            $this->addJoinOnce('shop_order_items',
                    ":table.order_id = {$this->getFilteringOrderTableAlias()}.id AND :table.type = 'product'",
                    ":table.product_id = '{$val}'");
            if ($auto_title) {
                $m = $this->getModel('product');
                $product = $m->getById($val);
                if ($product) {
                    $this->addTitle(_w('Product') . '=' . $product['name']);
                } else {
                    $this->addTitle(_w('Product') . '=' . $val);
                }
            }
        } else {
            $name = $this->getModel()->escape($val, 'like');
            $oi = $this->addJoinOnce('shop_order_items',
                    ":table.order_id = {$this->getFilteringOrderTableAlias()}.id AND :table.type = 'product'");
            $this->addJoinOnce('shop_product', ":table.id = {$oi}.product_id",
                    ":table.name LIKE '%{$name}%'");
            if ($auto_title) {
                $this->addTitle(_w('Product') . '*=' . $val);
            }
        }
    }

    protected function searchPrepareStorefront($op, $val = '', $auto_title = true)
    {
        if ($val === ':backend') {
            $this->addLeftJoin('shop_order_params',
                    ":table.order_id = {$this->getFilteringOrderTableAlias()}.id AND :table.name = 'storefront'",
                    ":table.value IS NULL");
            if ($auto_title) {
                $this->addTitle(_w('Storefront') . '=' . _w('Backend'));
            }
        } else {
            $val = rtrim($this->getModel()->escape($val), '/');
            $storefronts = array($val, $val . '/');
            $this->addJoin('shop_order_params', ":table.order_id = {$this->getFilteringOrderTableAlias()}.id AND :table.name = 'storefront'",
                    ":table.value IN ('".  implode("','", $storefronts)."')");
            if ($auto_title) {
                $this->addTitle(_w('Storefront') . '=' . $val);
            }
        }
    }

    protected function searchPrepareReferer($op, $val = '', $auto_title = true)
    {
        $val = $this->getModel()->escape($val);
        $this->addJoin('shop_order_params', ":table.order_id = {$this->getFilteringOrderTableAlias()}.id AND :table.name = 'referer_host'",
                ":table.value = '{$val}'");
        if ($auto_title) {
            $this->addTitle(_w('Referer') . '=' . $val);
        }
    }

    protected function searchPrepareFirstOrderDatetime($op, $val = '', $auto_title = true)
    {
        return $this->_seachPrepareOrderDatetime('first', $op, $val, $auto_title);
    }

    protected function searchPrepareLastOrderDatetime($op, $val = '', $auto_title = true)
    {
        return $this->_seachPrepareOrderDatetime('last', $op, $val, $auto_title);
    }

    protected function _seachPrepareOrderDatetime($type, $op, $val = '', $auto_title = true)
    {
        if ($type === 'last') {
            $arg_func = 'MAX';
            $title = array(_w('Last order'));
            $order_table_alias = $this->getOrderTableAlias();
        } else {
            $arg_func = 'MIN';
            $title = array(_w('First order'));
            $order_table_alias = $this->getFilteringOrderTableAlias();
        }
        if (is_array($val)) {
            $period[0] = $this->getModel()->escape($val[0]);
            $period[1] = $this->getModel()->escape($val[1]);
            $this->addHaving("{$arg_func}({$order_table_alias}.create_datetime) >= '{$period[0]}' AND {$arg_func}({$order_table_alias}.create_datetime) <= '{$period[1]}'");
            if ($auto_title) {
                $this->addTitle($title[0] . '=' . $val[0] . '–' . $val[1]);
            }
        } else {
            $date = $val;
            $title[1] = $op . $val;
            if (!is_numeric(substr($val, -1))) {
                $quantifier = substr($val, -1);
                $shift = substr($val, 0, -1);
                if ($quantifier === 'd') {
                    $date = date('Y-m-d', strtotime("{$shift} days"));
                    $title[1] = $op . "{$shift} days";
                } else {
                    return;
                }
            }
            $expr = $this->getExpression($op, $date);
            $this->addHaving("{$arg_func}({$order_table_alias}.create_datetime) {$expr}");
            if ($auto_title) {
                $this->addTitle($title[0] . $title[1]);
            }
        }
    }

    protected function searchPrepareCoupon($op, $val = '', $auto_title = true)
    {
        if ($val) {
            if ($val === ':any') {
                $this->addJoin('shop_order_params', ":table.order_id = {$this->getFilteringOrderTableAlias()}.id AND :table.name = 'coupon_id' AND :table.value <> '0'");
                if ($auto_title) {
                    $this->addTitle(_w('Any coupon'));
                }
            } else if (is_numeric($val)) {
                $val = (int) $val;
                $this->addJoin('shop_order_params', ":table.order_id = {$this->getFilteringOrderTableAlias()}.id AND :table.name = 'coupon_id' AND :table.value = '{$val}'");
                if ($auto_title) {
                    $name = $val;
                    $m = $this->getModel('coupon');
                    $item = $m->getById($val);
                    if ($item) {
                        $name = $item['code'];
                    }
                    $this->addTitle(_w('Coupon') . '=' . $name);
                }
            } else {
                $expr = $this->getExpression($op, $val);
                $al = $this->addJoin('shop_order_params', ":table.order_id = {$this->getFilteringOrderTableAlias()}.id AND :table.name = 'coupon_id'");
                $this->addJoin('shop_coupon', ":table.id = {$al}.value", ":table.code {$expr}");
                $this->addTitle(_w('Coupon') . $op . $val);
            }
        }
    }

    protected function searchPrepareUtmCampaign($op, $val = '', $auto_title = true)
    {
        if ($val) {
            if ($val === ':any') {
                $this->addJoin('shop_order_params', ":table.order_id = {$this->getFilteringOrderTableAlias()}.id AND :table.name = 'utm_campaign'");
                if ($auto_title) {
                    $this->addTitle(_w('Any UTM campaign'));
                }
            } else {
                $val = $this->getModel()->escape($val);
                $this->addJoin('shop_order_params', ":table.order_id = {$this->getFilteringOrderTableAlias()}.id AND :table.name = 'utm_campaign' AND :table.value = '{$val}'");
                if ($auto_title) {
                    $this->addTitle(_w('UTM campaign') . '=' . $val);
                }
            }
        }
    }

    /**
     *
     * @param null|string $type
     * @return waModel
     */
    protected function getModel($type = null) {
        if (is_string($type) && in_array($type, array(
                'customer',
                'order',
                'order_params',
                'plugin',
                'coupon',
                'customers_filter',
                'product'
            )))
        {
            return $this->loadModel($type);
        } else {
            if (!isset($this->models['wa_model'])) {
                $this->models['wa_model'] = new waModel();
            }
            return $this->models['wa_model'];
        }
    }

    /**
     *
     * @param string $type
     * @return waModel
     */
    private function loadModel($type)
    {
        if (!isset($this->models[$type])) {
            $name = 'shop'.implode('', array_map('ucfirst', explode('_',  $type))).'Model';
            $this->models[$type] = new $name();
        }
        return $this->models[$type];
    }

    protected function getTableName($type)
    {
        return 'shop_' . $type;
    }

    protected function getCustomerFields($with_alias = true)
    {
        $customer_fields = array_keys($this->getModel('customer')->getMetadata());
        if ($with_alias) {
            foreach ($customer_fields as &$f) {
                $f = $this->customer_table_alias . '.' . $f;
            }
            unset($f);
        }
        return $customer_fields;
    }

    /**
     * Returns expression for SQL
     *
     * @param string $op - operand ==, >=, etc
     * @param string $value - value
     * @return string
     */
    protected function getExpression($op, $value)
    {
        $model = $this->getModel();
        switch ($op) {
            case '>':
            case '>=':
            case '<':
            case '<=':
            case '!=':
                return " ".$op." '".$model->escape($value)."'";
            case "^=":
                return " LIKE '".$model->escape($value, 'like')."%'";
            case "$=":
                return " LIKE '%".$model->escape($value, 'like')."'";
            case "*=":
                return " LIKE '%".$model->escape($value, 'like')."%'";
            case '@=':
                $values = array();
                foreach (explode(',', $value) as $v) {
                    $values[] = "'".$model->escape($v)."'";
                }
                return ' IN ('.implode(',', $values).')';
            case "==":
            case "=";
            default:
                return " = '".$model->escape($value)."'";
        }
    }

    public static function isDate($val)
    {
        $v = $val;
        if (is_array($val)) {
            $v = ifset($val[0], '');
        }
        return preg_match('/[\d]{4,}\-[\d]{2,}-[\d]{2,}/', $v);
    }

    public static function parseSearchHash($hash)
    {
        $escapedBS = 'ESCAPED_BACKSLASH';
        while(FALSE !== strpos($hash, $escapedBS)) {
            $escapedBS .= rand(0, 9);
        }
        $escapedAmp = 'ESCAPED_AMPERSAND';
        while(FALSE !== strpos($hash, $escapedAmp)) {
            $escapedAmp .= rand(0, 9);
        }
        $hash = str_replace('\\&', $escapedAmp, str_replace('\\\\', $escapedBS, $hash));
        $first_level_parts = explode('&', $hash);

        $hash_ar = array();
        foreach ($first_level_parts as $flpart) {
            if (!($flpart = trim($flpart))) {
                continue;
            }
            $flpart = str_replace(array($escapedBS, $escapedAmp), array('\\', '&'), $flpart);
            $second_level_parts = preg_split("/(\\\$=|\^=|\*=|==|!=|>=|<=|=|>|<|@=)/uis", $flpart, 2, PREG_SPLIT_DELIM_CAPTURE);
            if (!($path = trim($second_level_parts[0]))) {
                continue;
            }
            if (!($op = trim($second_level_parts[1]))) {
                continue;
            }
            $val = trim($second_level_parts[2]);
            if (strlen($val) <= 0) {
                continue;
            }
            $path = str_replace(':', '.', $path);       // support composite fields with ':' separator
            if (strstr($val, '--') !== false) {
                $val = explode('--', $val, 2);
                $val[1] = ifset($val[1], '');
            }
            self::parsePath($path, array('op' => $op, 'val' => $val), $hash_ar);
        }
        //self::recursiveCleanArray($hash_ar);
        return $hash_ar;
    }

    public static function buildSearchHash($hash, $prefix = null)
    {
        if (empty($hash)) {
            return '';
        }
        $hash = (array) $hash;
        $res_str_hash = array();
        foreach ($hash as $key => $h) {
            if (is_array($h)) {
                if (isset($h['op']) && isset($h['val'])) {
                    if (is_array($h['val'])) {
                        $h['val'] = ifset($h['val'][0], '') . '--' . ifset($h['val'][1]);
                    }
                    $res_str_hash[] = ($prefix ? $prefix . '.' : '') . "{$key}{$h['op']}{$h['val']}";
                } else {
                    $res_str_hash[] = self::buildSearchHash($h, $prefix ? $prefix . '.' . $key : $key);
                }
            }
        }
        return implode('&', $res_str_hash);
    }

    protected static function parsePath($key, $val, &$ar, $sep = '.')
    {
        if (strstr($key, $sep) !== false) {
            $parts = explode($sep, $key, 2);
            $ar[$parts[0]] = ifset($ar[$parts[0]], array());
            self::parsePath($parts[1], $val, $ar[$parts[0]], $sep);
        } else {
            $ar[$key] = $val;
        }
        return $ar;
    }

    protected static function recursiveCleanArray(&$ar)
    {
        if (is_array($ar)) {
            foreach ($ar as $k => &$cnd) {
                self::recursiveCleanArray($cnd);
                if (empty($cnd)) {
                    unset($ar[$k]);
                }
            }
            unset($cnd);
        }
    }

}
