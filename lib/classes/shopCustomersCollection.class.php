<?php

class shopCustomersCollection extends waContactsCollection
{
    protected $customer_table_alias;
    protected $order_table_alias;
    protected $model;
    protected $join_table_index = array();
    protected $left_join_table_index = array();
    protected $fields_cache = array();
    protected $join_table_aliases = array();
    protected $left_join_table_aliases = array();

    public function __construct($hash = '', $options = array()) {
        parent::__construct($hash, $options);
        $this->customer_table_alias = $this->addJoinOnce('shop_customer');
        $this->order_table_alias = $this->addJoinOnce('shop_order');
    }

    public function getCustomers($fields = "*", $offset = 0, $limit = 50) {
        return $this->getContacts($fields, $offset, $limit);
    }

    protected function getFields($fields)
    {
        if (!isset($this->fields_cache[$fields])) {
            $model = $this->getModel('customer');
            $contact_fields = array(
                parent::getFields($fields)
            );

            $ignore_post_fields = array();

            if ($fields === '*') {
                $contact_fields = array_merge($contact_fields, $this->getCustomerFields());
            } else {
                foreach (explode(',', $fields) as $f) {
                    if ($model->fieldExists($f)) {
                        $contact_fields[] = $this->customer_table_alias . '.' . $f;
                    } else if (strstr($f, '.') !== false) {
                        if (!preg_match('/([a-z_]+)\.([a-z_]+)(?:\s+AS\s+([a-z_]+)){0,1}/i', $f, $m)) {
                            continue;
                        }

                        $table = trim($m[1]);
                        $fld = trim($m[2]);
                        $fld_alias = null;
                        if (!empty($m[3])) {
                            $fld_alias = trim($m[3]);
                        }

                        $tbl_model = $this->getModel($table);
                        if ($tbl_model->fieldExists($fld)) {
                            $tbl_name = $this->getTableName($table);
                            if ($table !== 'order') {
                                $alias = $this->addLeftJoinOnce($tbl_name);
                            } else {
                                $alias = $this->order_table_alias;
                            }
                            $contact_fields[] = $alias . '.' . $fld . ($fld_alias ? " AS {$fld_alias}" : '');
                            $ignore_post_fields[$f] = true;
                        }
                    } else if ($f === '*') {
                        $contact_fields = array_merge($contact_fields, $this->getCustomerFields());
                    }
                }
            }

            $this->fields_cache[$fields] = implode(',', $contact_fields);
        }

        if (!empty($this->post_fields['data'])) {
            foreach ($this->post_fields['data'] as $k => $post_field) {
                if (isset($ignore_post_fields[$post_field])) {
                    unset($this->post_fields['data'][$k]);
                }
            }
            if (empty($this->post_fields['data'])) {
                unset($this->post_fields['data']);
            }
        }

        $this->setGroupBy('c.id');

        return $this->fields_cache[$fields];
    }

    public function addJoinOnce($table, $on = null, $where = null, $options = array())
    {
        if (empty($this->join_table_aliases[$table])) {
            return $this->addJoin($table, $on, $where, $options);
        } else if (!empty($this->join_table_aliases[$table])) {
            return $this->join_table_aliases[$table][0];
        }
    }

    public function addLeftJoinOnce($table, $on = null, $where = null, $options = array())
    {
        if (empty($this->left_join_table_aliases[$table])) {
            return $this->addLeftJoin($table, $on, $where, $options);
        } else {
            return $this->left_join_table_aliases[$table][0];
        }
    }

    public function addJoin($table, $on = null, $where = null, $options = array()) {
        $alias = parent::addJoin($table, $on, $where, $options);
        $this->join_table_index[$table] = ifset($this->join_table_index[$table], 0);
        $this->join_table_index[$table] += 1;
        $this->join_table_aliases[$table] = ifset($this->join_table_aliases[$table], array());
        $this->join_table_aliases[$table][] = $alias;
        return $alias;
    }

    public function addLeftJoin($table, $on = null, $where = null, $options = array()) {
        $alias = parent::addLeftJoin($table, $on, $where, $options);
        $this->left_join_table_index[$table] = ifset($this->join_table_index[$table], 0);
        $this->left_join_table_index[$table] += 1;
        $this->left_join_table_aliases[$table] = ifset($this->join_table_aliases[$table], array());
        $this->left_join_table_aliases[$table][] = $alias;
        return $alias;
    }

    protected function filterPrepare($filter_id, $auto_title = true)
    {
        $filter_id = (int) $filter_id;
        $m = $this->getModel('customers_filter');
        $filter = $m->getById($filter_id);
        if (!$filter['hash']) {
            $this->where[] = 1;
        } else {
            $this->searchPrepare($filter['hash'], false);
        }
        $this->title = $filter['name'];
    }


    protected function searchPrepare($query, $auto_title = true)
    {
        $this->middlewareSearchPrepare($query, $auto_title);

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
            if ($f === 'email|name' || $f === 'name|email') {
                $this->searchPrepareEmailName($parts[1], ifset($parts[2], ''), $auto_title = true);
                unset($query[$k]);
            }
        }

        $query = implode('&', $query);
        $query = str_replace($escapedAmp, '\\&', str_replace($escapedBS, '\\\\', $query));

        parent::searchPrepare($query, $auto_title);
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

        if ($contact_info_hash) {
            parent::searchPrepare(implode('&', $contact_info_hash), $auto_title);
        }
        if (!empty($hash_ar['app'])) {
            foreach ($hash_ar['app'] as $k => $h) {
                $method_name = 'searchPrepare' . implode('', array_map('ucfirst', explode('_', $k)));
                if (method_exists($this, $method_name)) {
                    $this->$method_name($h['op'], $h['val'], $auto_title);
                }
            }
            unset($hash_ar['app']);
        }

        self::recursiveCleanArray($hash_ar);
        $query = self::buildSearchHash($hash_ar);
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
        $this->addJoin('shop_order_params', ":table.order_id = {$this->order_table_alias}.id AND :table.name = 'shipping_id' AND :table.value = '{$val}'");
        if ($auto_title) {
            $item = $this->getModel('plugin')->getById($val);
            $this->addTitle(_w('Shipping option') . '=' . ($item ? $item['name'] : $val));
        }
    }

    protected function searchPreparePaymentMethod($op, $val = '', $auto_title = true)
    {
        $val = (int) $val;
        $this->addJoin('shop_order_params', ":table.order_id = {$this->order_table_alias}.id AND :table.name = 'payment_id' AND :table.value = '{$val}'");
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
            $this->addWhere("{$this->customer_table_alias}.total_spent >= '{$range[0]}' &&
                {$this->customer_table_alias}.total_spent <= '{$range[1]}'");
            if ($auto_title)  {
                $this->addTitle(_w('Total spent') . '=' . $val[0] . '–' . $val[1] . ' ' . wa()->getConfig()->getCurrency());
            }
        } else {
            $expr = $this->getExpression($op, $val);
            $this->addWhere("{$this->customer_table_alias}.total_spent {$expr}");
            if ($auto_title) {
                $this->addTitle(_w('Total spent') . $op . $val . ' ' . wa()->getConfig()->getCurrency());
            }
        }
    }

    protected function searchPrepareOrdersTotalSum($op, $val = '', $auto_title = true)
    {
        if (is_array($val)) {
            $range[0] = $this->getModel()->escape($val[0]);
            $range[1] = $this->getModel()->escape($val[1]);
            $al = $this->addJoinOnce('shop_order');
            $this->addHaving("SUM({$al}.total * {$al}.rate) >= '{$range[0]}' && SUM({$al}.total * {$al}.rate) >= '{$range[1]}'");
            if ($auto_title)  {
                $this->addTitle(_w('Total spent') . '=' . $val[0] . '–' . $val[1] . ' ' . wa()->getConfig()->getCurrency());
            }
        } else {
            $expr = $this->getExpression($op, $val);
            $this->addHaving("SUM({$al}.total * {$al}.rate) {$expr}");
            if ($auto_title) {
                $this->addTitle(_w('Total spent') . $op . $val . ' ' . wa()->getConfig()->getCurrency());
            }
        }
    }

    protected function searchPrepareNumberOfOrders($op, $val = '', $auto_title = true)
    {
        if (in_array($op, array('<', '>', '=', '>=', '<='))) {
            $val = (int) $val;
            $this->addWhere("{$this->customer_table_alias}.number_of_orders {$op} '{$val}'");
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
            $this->addWhere("{$this->order_table_alias}.create_datetime >= '{$period[0]}' AND {$this->order_table_alias}.create_datetime <= '{$period[1]}'");
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
            $this->addWhere("{$this->order_table_alias}.create_datetime {$expr}");
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
                    ":table.order_id = {$this->order_table_alias}.id AND :table.type = 'product'",
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
                    ":table.order_id = {$this->order_table_alias}.id AND :table.type = 'product'");
            $this->addJoinOnce('shop_product', ":table.id = {$oi}.product_id",
                    ":table.name LIKE '%{$name}%'");
            if ($auto_title) {
                $this->addTitle(_w('Product') . '*=' . $val);
            }
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
        $arg_func = $type === 'last' ? 'MAX' : 'MIN';
        $title = array($type === 'last' ? _w('Last order') : _w('First order'));
        if (is_array($val)) {
            $period[0] = $this->getModel()->escape($val[0]);
            $period[1] = $this->getModel()->escape($val[1]);
            $this->addHaving("{$arg_func}({$this->order_table_alias}.create_datetime) >= '{$period[0]}' AND {$arg_func}({$this->order_table_alias}.create_datetime) <= '{$period[1]}'");
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
            $this->addHaving("{$arg_func}({$this->order_table_alias}.create_datetime) {$expr}");
            if ($auto_title) {
                $this->addTitle($title[0] . $title[1]);
            }
        }
    }

    protected function searchPrepareCoupon($op, $val = '', $auto_title = true)
    {
        if ($val) {
            if ($val === ':any') {
                $this->addJoin('shop_order_params', ":table.order_id = {$this->order_table_alias}.id AND :table.name = 'coupon_id'");
                if ($auto_title) {
                    $this->addTitle(_w('Any coupon'));
                }
            } else {
                $val = (int) $val;
                $this->addJoin('shop_order_params', ":table.order_id = {$this->order_table_alias}.id AND :table.name = 'coupon_id' AND value = '{$val}'");
                if ($auto_title) {
                    $name = $val;
                    $m = $this->getModel('coupon');
                    $item = $m->getById($val);
                    if ($item) {
                        $name = $item['code'];
                    }
                    $this->addTitle(_w('Coupon') . '=' . $name);
                }
            }
        }
    }

    protected function searchPrepareUtmCampaign($op, $val = '', $auto_title = true)
    {
        if ($val) {
            if ($val === ':any') {
                $this->addJoin('shop_order_params', ":table.order_id = {$this->order_table_alias}.id AND :table.name = 'utm_campaign'");
                if ($auto_title) {
                    $this->addTitle(_w('Any UTM campaign'));
                }
            } else {
                $val = $this->getModel()->escape($val);
                $this->addJoin('shop_order_params', ":table.order_id = {$this->order_table_alias}.id AND :table.name = 'utm_campaign' AND value = '{$val}'");
                if ($auto_title) {
                    $this->addTitle(_w('UTM campaign') . '=' . $val);
                }
            }
        }
    }

    public function orderBy($field, $order = 'ASC')
    {
        $field = trim($field);
        if ($field == '~data') {
            return parent::orderBy($field, $order);
        } else {
            $model = $this->getModel();
            $model->escape($field);
            $this->order_by = $field . ' ' . (strtoupper($order) === 'ASC' ? 'ASC' : 'DESC');
            return $this->order_by;
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
                'plugin',
                'coupon',
                'customers_filter',
                'product'
            )))
        {
            return $this->loadModel($type);
        }
        return parent::getModel($type);
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
            if (!($val = trim($second_level_parts[2]))) {
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

    public static function buildSearchHash($hash)
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
                    $res_str_hash[] = "{$key}{$h['op']}{$h['val']}";
                } else {
                    $res_str_hash[] = "{$key}." . self::buildSearchHash($h);
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