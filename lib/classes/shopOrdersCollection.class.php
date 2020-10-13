<?php

class shopOrdersCollection
{

    protected $hash;
    protected $info = array();

    protected $options = array();

    protected $prepared = false;
    protected $title;

    protected $where;
    protected $having = array();
    protected $count;
    protected $order_by = 'o.create_datetime DESC, o.id';
    protected $group_by = 'o.id';
    protected $joins;
    protected $join_index = array();

    protected static $models = array();

    /**
     * Creates a new order collection.
     *
     * @param string|array $hash Order selection conditions. Examples:
     *     array(12,23,34) or 'id/12,23,34' — explicitly specified order ids
     *     'search/state_id=new||processing||paid' — search by 'state' field of shop_order table; supported comparison/matching operators:
     *         $=    inexact matching by value end (LIKE 'value%')
     *         ^=    inexact matching by value start (LIKE '%value')
     *         *=    inexact matching (LIKE '%value%')
     *         ==    exact matching; if NULL is specified, IS NULL condition is used
     *         =     the same
     *         !=    non-matching
     *         >=    greater or equal
     *         <=    less or equal
     *         >     greater
     *         <     less
     *     'search/state_id=new||processing&total>=100' — search by several fields of shop_order table; multiple conditions are separated by ampersand &
     *     'search/params.shipping_id=64' — search by values stored in table shop_order_params
     *     'search/items.service_id=2' — search by values stored in table shop_order_items
     *     'search/items.product_id=10' — search orders related to specific (id=10) product
     *     'search/promo_id=10' — search orders related to specific (id=10) promo
     * @param array $options Extra options
     */
    public function __construct($hash = '', $options = array())
    {
        foreach ($options as $k => $v) {
            $this->options[$k] = $v;
        }
        $this->setHash($hash);

        /**
         * @event orders_collection.filter
         * @param array shopOrdersCollection $this
         */
        wa('shop')->event('orders_collection.filter', $this);
    }

    public function setOptions($options)
    {
        foreach ($options as $k => $v) {
            $this->options[$k] = $v;
        }
    }

    protected function setHash($hash)
    {
        if (is_array($hash)) {
            $hash = '/id/'.implode(',', $hash);
        }
        if (substr($hash, 0, 1) == '#') {
            $hash = substr($hash, 1);
        }
        $this->hash = ltrim($hash, '/');
        if ($this->hash == 'all') {
            $this->hash = '';
        }
        $this->hash = explode('/', $this->hash, 2);
    }

    protected function prepare($add = false, $auto_title = true)
    {
        // Filter orders allowed for the courier
        if (!$this->prepared && !empty($this->options['courier_id'])) {
            // orders assigned to courier...
            $this->options['courier_id'] = (int) $this->options['courier_id'];
            $this->addJoin(array(
                'type'  => '',
                'table' => 'shop_order_params',
                'on' => "o.id = :table.order_id AND :table.name = 'courier_id'",
                'where' => ":table.value = '".$this->options['courier_id']."'",
            ));
            // ...either not completed at all or completed in last 24 hours
            $this->addJoin(array(
                'type'  => 'LEFT',
                'table' => 'shop_order_log',
                'on' => "o.id = :table.order_id AND :table.action_id IN ('complete', 'delete')",
                'where' => "(:table.id IS NULL OR datetime >= '".date('Y-m-d H:i:s', time() - 3600*24)."')",
            ));
        }

        if (!$this->prepared || $add) {
            $type = $this->hash[0];
            if ($type) {
                $method = strtolower($type).'Prepare';
                if (method_exists($this, $method)) {
                    $this->$method(isset($this->hash[1]) ? $this->hash[1] : '', $auto_title);
                } else {
                    // custom collections
                    $params = array(
                        'collection' => $this,
                        'auto_title' => $auto_title,
                        'add'        => $add,
                    );
                    /**
                     * @event orders_collection
                     * @param array [string]mixed $params
                     * @param array [string]shopOrdersCollection $params['collection']
                     * @param array [string]boolean $params['auto_title']
                     * @param array [string]boolean $params['add']
                     * @return bool null if ignored, true when something changed in the collection
                     */
                    $processed = wa('shop')->event('orders_collection', $params);
                    if (!$processed) {
                        throw new waException('Unknown collection hash type: '.htmlspecialchars($type, ENT_COMPAT, 'utf-8'));
                    }
                }
            } else {
                if ($auto_title) {
                    $this->addTitle(_w('All orders'));
                }
            }

            $this->prepared = true;
        }
    }

    protected function idPrepare($ids_str)
    {
        $this->info = array(
            'hash' => 'id'
        );
        $ids = array();
        foreach (explode(',', $ids_str) as $id) {
            $ids[] = (int)$id;
        }
        if (!$ids) {
            $this->where[] = '0';
        }
        $this->where[] = "o.id IN (".implode(',', $ids).")";
    }

    /**
     * Returns collection hash.
     *
     * @return string
     */
    public function getHash()
    {
        return $this->hash;
    }

    public function getJoinedAlias($table)
    {
        $alias = $this->getTableAlias($table);
        if (!isset($this->join_index[$alias])) {
            $this->join_index[$alias] = 1;
        } else {
            $this->join_index[$alias]++;
        }
        return $alias.$this->join_index[$alias];
    }

    protected function getTableAlias($table)
    {
        $t = explode('_', $table);
        $alias = '';
        foreach ($t as $tp) {
            $alias .= substr($tp, 0, 1);
        }
        if (!$alias) {
            $alias = $table;
        }
        return $alias;
    }

    /**
     * Adds a simple JOIN clause to order selection query.
     *
     * @param string|array $table Table name to be used in JOIN clause.
     *     Alternatively an associative array may be specified containing values for all method parameters.
     *     In this case $on and $where parameters are ignored.
     * @param string $on ON condition FOR JOIN, must not include 'ON' keyword
     * @param string $where WHERE condition for SELECT, must not include 'WHERE' keyword
     * @return string Specified table's alias to be used in SQL query
     */
    public function addJoin($table, $on = null, $where = null)
    {
        $type = '';
        if (is_array($table)) {
            if (isset($table['on'])) {
                $on = $table['on'];
            }
            if (isset($table['where'])) {
                $where = $table['where'];
            }
            if (isset($table['type'])) {
                $type = $table['type'];
            }
            $table = $table['table'];
        }

        $alias = $this->getJoinedAlias($table);

        $join = array(
            'table' => $table,
            'alias' => $alias,
            'type'  => $type
        );
        if ($on) {
            $join['on'] = str_replace(':table', $alias, $on);
        }
        $this->joins[] = $join;
        if ($where) {
            $this->where[] = str_replace(':table', $alias, $where);
        }
        return $alias;
    }

    /**
     * Adds a WHERE condition to order selection query.
     *
     * @param string $condition Additional WHERE condition; WHERE keyword must not be specified
     * @return self
     */
    public function addWhere($condition)
    {
        $this->where[] = $condition;
        return $this;
    }

    /**
     * Returns order selection SQL query
     *
     * @return string
     */
    public function getSQL($opts = [])
    {
        $this->prepare();

        /**
         * Orders collection after prepare
         *
         * @event orders_collection.prepared
         * @param shopOrdersCollection $this
         */
        wa('shop')->event('orders_collection.prepared', $this);

        $parts = [
            'joins' => $this->joins,
            'where' => $this->where,
        ];

        if (ifset($opts, 'group_by', true)) {
            $parts['group_by'] = $this->group_by;
        }
        if (ifset($opts, 'order_by', true)) {
            $parts['order_by'] = $this->order_by;
        }

        $sql = $this->buildSQL($parts);
        return $sql;
    }

    /**
     * Helper for building SQL query
     *
     * @param array $statements
     *
     *   - array 'joins'
     *   - array 'where'
     *   - string 'order_by'
     *   - string 'group_by'
     *   - array 'having'
     *
     * @return string
     */
    protected function buildSQL($statements)
    {
        $statements = is_array($statements) ? $statements : array();

        // Prepare SQL statements

        $joins = array();
        if (isset($statements['joins']) && is_array($statements['joins'])) {
            $joins = $statements['joins'];
        }

        $where = array();
        if (isset($statements['where']) && is_array($statements['where'])) {
            $where = $statements['where'];
        }

        $order_by = '';
        if (isset($statements['order_by']) && is_scalar($statements['order_by'])) {
            $order_by = (string)$statements['order_by'];
        }

        $group_by = '';
        if (isset($statements['group_by']) && is_scalar($statements['group_by'])) {
            $group_by = (string)$statements['group_by'];
        }

        $having = array();
        if (isset($statements['having']) && is_array($statements['having'])) {
            $having = $statements['having'];
        }

        // Build SQL query

        $sql = "\nFROM shop_order o";

        if ($joins) {
            foreach ($joins as $join) {
                $alias = isset($join['alias']) ? $join['alias'] : '';
                if (isset($join['on'])) {
                    $on = $join['on'];
                } else {
                    $on = "o.id = ".($alias ? $alias : $join['table']).".order_id";
                }
                $sql .= "\n\t".trim((!empty($join['type']) ? " ".$join['type'] : '')." JOIN").' '.$join['table']." AS ".$alias." ON ".$on;
            }
        }

        if ($where) {
            $sql .= "\nWHERE ".join("\n\tAND ", $where);
        }

        if ($group_by) {
            $sql .= "\nGROUP BY ".trim($group_by);
        }

        if ($order_by) {
            $sql .= "\nORDER BY ".trim($order_by);
        }

        if ($having) {
            $sql .= "\nHAVING ".join("\n\tAND ", $having);
        }

        // Return result
        return $sql;
    }

    /**
     * Returns number of orders included in collection.
     *
     * @return int
     */
    public function count()
    {
        if ($this->count !== null) {
            return $this->count;
        }
        $sql = $this->getSQL([
            'group_by' => false,
            'order_by' => false,
        ]);
        $sql = "SELECT COUNT(".($this->joins ? 'DISTINCT ' : '')."o.id) ".$sql;
        return $this->count = (int)self::getModel()->query($sql)->fetchField();
    }

    /**
     * @return float
     * @throws waException
     */
    public function getSum()
    {
        $sql = $this->getSQL([
            'group_by' => false,
        ]);

        $sql = "SELECT ".($this->joins ? 'DISTINCT o.id, ' : '')."SUM(o.total * o.rate) as amount {$sql}";
        $num = (float)self::getModel()->query($sql)->fetchField('amount');
        return $num;
    }

    /**
     * @param string $name
     * @return shopOrderModel
     */
    protected static function getModel($name = 'order')
    {
        if (!isset(self::$models[$name])) {
            if (in_array($name, array('order', 'items', 'params', 'log'))) {
                $class_name = 'shop'.($name != 'order' ? 'Order' : '').ucfirst($name).'Model';
                self::$models[$name] = new $class_name();
            } else if ($name === 'customer') {
                self::$models['customer'] = new shopCustomerModel();
            } else if ($name === 'contact') {
                self::$models['contact'] = new waContactModel();
            }
        }
        return self::$models[$name];
    }

    /**
     * Parses order selection condition string of the form acceptable by class constructor.
     * @see __construct()
     *
     * @param string $query Order selection query; e.g., 'total>=3&state_id>=new||paid'
     * @return array Parsed condition data; e.g.:
     *     total => Array
     *     (
     *         [0] => '>='
     *         [1] => '3'
     *     )
     *     state_id => Array
     *     (
     *         [0] => '>='
     *         [1] => 'new||paid'
     *     )
     */
    public static function parseConditions($query)
    {
        $escapedBS = 'ESCAPED_BACKSLASH';
        while (false !== strpos($query, $escapedBS)) {
            $escapedBS .= rand(0, 9);
        }
        $escapedAmp = 'ESCAPED_AMPERSAND';
        while (false !== strpos($query, $escapedAmp)) {
            $escapedAmp .= rand(0, 9);
        }
        $query = str_replace('\\&', $escapedAmp, str_replace('\\\\', $escapedBS, $query));
        $query = explode('&', $query);
        $result = array();
        foreach ($query as $part) {
            if (!($part = trim($part))) {
                continue;
            }
            $part = str_replace(array($escapedBS, $escapedAmp), array('\\\\', '\\&'), $part);
            $parts = self::splitConditions($part);
            if ($parts) {
                $name = array_shift($parts);

                if ($name == 'tag') {
                    $parts[1] = explode('||', $parts[1]);
                }
                if ($name != 'price') {
                    $result[$name] = $parts;
                } else {
                    if ($parts[0] == '>=') {
                        $result[$name][0] = $parts;
                    } elseif ($parts[0] == '<=') {
                        $result[$name][1] = $parts;
                    } else {
                        $result[$name] = $parts;
                    }
                }
            }
        }
        return $result;
    }

    /**
     * @see __construct()
     * @param $string
     * @return string[]
     * @return string[0] field
     * @return string[1] relation operand eg $=, ==, > etc
     * @return string[2] value
     * @
     */
    private static function splitConditions($string)
    {
        return preg_split('/(\$=|\^=|\*=|==|!=|>=|<=|=|>|<)/uis', $string, 2, PREG_SPLIT_DELIM_CAPTURE);
    }

    private static function splitValues($value, $ignore_empty_string = false)
    {
        if (strstr($value, '||') !== false) {
            $values = array_map('trim', explode('||', $value));
            if ($ignore_empty_string) {
                $values = array_filter($values, 'strlen');
            }
            if ($values) {
                $model = self::getModel();
                foreach ($values as &$v) {
                    $v = $model->escape(trim($v));
                }

                unset($v);
            }
            $value = $values;
        }
        return $value;
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
        $model = self::getModel();
        switch ($op) {
            case '!=':
                if ($value === 'NULL') {
                    return ' IS NOT NULL';
                }

                $value = self::splitValues($value);
                if (is_array($value)) {
                    return " NOT IN ('".implode("','", $value)."')";
                }
            //no-break
            case '>':
            case '>=':
            case '<':
            case '<=':
                return " ".$op." '".$model->escape($value)."'";
            case "^=":
                return " LIKE '".$model->escape($value, 'like')."%'";
            case "$=":
                return " LIKE '%".$model->escape($value, 'like')."'";
            case "*=":
                return " LIKE '%".$model->escape($value, 'like')."%'";
            case "==":
            case "=":
            default:
                if ($value === 'NULL') {
                    return ' IS NULL';
                }
                $value = self::splitValues($value);
                if (is_array($value)) {
                    return " IN ('".implode("','", $value)."')";
                }
                return " = '".$model->escape($value)."'";
        }
    }

    /**
     * Returns array of orders included in collection.
     *
     * @param string $fields List of order properties, comma-separated, to be included in returned array:
     *     '*' — values from shop_order table
     *     '*,params,items,contact' (different combinations are acceptable) — values from tables shop_order, shop_order_items, shop_order_params, wa_contact
     * @param int $offset Initial position in returned order array, 0 means first order in collection
     * @param int|bool $limit Maximum order limit.
     *     If a Boolean value is specified, then $escape = $limit and $limit = null
     *     If no value is specified, then $limit = 0.
     *     If no value is specified and $offset is non-zero, then $limit = $offset and $offset = 50
     * @param bool $escape Whether order parameters and contact names must be escaped using htmlspecialchars() function, defaults to true
     *
     * @return array Array of collection orders' sub-arrays
     */
    public function getOrders($fields = "*", $offset = 0, $limit = null, $escape = true)
    {
        if (is_bool($limit)) {
            $escape = $limit;
            $limit = null;
        }
        if ($limit === null) {
            if ($offset) {
                $limit = $offset;
                $offset = 0;
            } else {
                $limit = 50;
            }
        }

        list($sql_fields, $postprocess_fields) = $this->getFields($fields);

        $sql = $this->getSQL();
        $sql = "SELECT ".$sql_fields." ".$sql;
        $sql .= " LIMIT ".($offset ? $offset.',' : '').(int)$limit;

        $data = self::getModel()->query($sql)->fetchAll('id');
        if ($data) {
            $this->addPostprocessFields($data, $postprocess_fields, $escape);
        }
        return $data;
    }

    /**
     * Get distinct field values of order list
     * Be sure field_id input not involve any injection
     *
     * @param string $field_id
     * @return array
     */
    public function getDistinctFieldValues($field_id)
    {
        $sql = $this->getSQL([
            'group_by' => false,
        ]);
        $sql = "SELECT DISTINCT {$field_id} {$sql}";
        $data = self::getModel()->query($sql)->fetchAll($field_id, true);
        return array_keys($data);
    }

    /**
     * @return int
     * @throws waException
     */
    public function getTotalPaidNum()
    {
        $this->prepare();

        $where = $this->where;
        $where[] = 'o.paid_date IS NOT NULL';
        $sql = $this->buildSQL(array(
            'joins' => $this->joins,
            'where' => $where,
            'order_by' => $this->order_by
        ));
        $sql = "SELECT COUNT(".($this->joins ? 'DISTINCT ' : '')."o.id) ".$sql;
        $num = (int)self::getModel()->query($sql)->fetchField();
        return $num;
    }

    /**
     * @return float
     * @throws waException
     */
    public function getTotalPaidSum()
    {
        $this->prepare();

        $where = $this->where;
        $where[] = 'o.paid_date IS NOT NULL';
        $this->addWhere('o.paid_date IS NOT NULL');

        $statements = array(
            'joins' => $this->joins,
            'where' => $where,
            'order_by' => $this->order_by
        );

        $sql = $this->buildSQL($statements);
        $sql = "SELECT ".($this->joins ? 'DISTINCT o.id, ' : '')."SUM(o.total * o.rate) as amount".$sql;
        $num = (float)self::getModel()->query($sql)->fetchField('amount');
        return $num;
    }


    public function getFields($raw_fields)
    {
        if (!is_array($raw_fields)) {
            $raw_fields = array_map('trim', explode(",", $raw_fields));
        }
        $fields = array();
        $postprocess_fields = array();
        foreach ($raw_fields as $i => $f) {
            if ($f == '*') {
                $fields[$f] = 'o.*';
            } elseif (self::getModel('order')->fieldExists($f)) {
                $fields[$f] = 'o.'.$f;
            } else {
                $postprocess_fields[$f] = $f;
                if ($f == 'subtotal' || $f == 'products') {
                    $postprocess_fields['items'] = 'items';
                } elseif ($f == 'shipping_info' || $f == 'billing_info' || $f == 'courier') {
                    $postprocess_fields['params'] = 'params';
                }
                if (empty($fields['*'])) {
                    if ($f == 'shipping_info') {
                        $fields['shipping'] = 'shipping';
                    } elseif ($f == 'contact' || $f == 'contact_full') {
                        $fields['contact_id'] = 'contact_id';
                    } elseif ($f == 'state') {
                        $fields['state_id'] = 'state_id';
                    }
                }
            }
        }
        return array(implode(",", $fields), $postprocess_fields);
    }

    public static function addPostprocessFields(&$data, $postprocess_fields, $escape = true)
    {
        $ids = array_keys($data);
        $default_values = array_fill_keys($postprocess_fields, null);

        if (isset($postprocess_fields['items'])) {
            $default_values['items'] = array();

            $rows = self::getModel('items')->getByField('order_id', $ids, true);

            foreach ($rows as $row) {
                if ($escape) {
                    $row['name'] = htmlspecialchars($row['name'], ENT_COMPAT, 'utf-8');
                }
                $data[$row['order_id']]['items'][] = $row;
            }

            foreach ($data as $id => $order) {
                if (isset($order['items'])) {
                    shopOrderItemsModel::sortItemsByGeneralSettings($data[$id]['items']);
                }
            }
        }

        if (isset($postprocess_fields['items']) && isset($postprocess_fields['products'])) {
            $product_ids = array();
            foreach ($data as $o) {
                if (!empty($o['items'])) {
                    foreach ($o['items'] as $it) {
                        $product_ids[$it['product_id']] = $it['product_id'];
                    }
                }
            }
            $products_collection = new shopProductsCollection('id/'.join(',', $product_ids));
            $product_fields = array(
                'id',
                'name',
                'summary',
                'type_id',
                'image_id',
                'image_filename',
                'sku_id',
                'ext',
                'url',
                'rating',
                'rating_count',
                'currency',
                'tax_id',
                'cross_selling',
                'upselling',
                'category_id',
                'badge',
                'sku_type',
                'image',
                'image_crop_small',
                'frontend_url',
                'images2x',
            );
            $products = $products_collection->getProducts(implode(',', $product_fields));
            foreach ($data as &$o) {
                if (!empty($o['items'])) {
                    foreach ($o['items'] as &$it) {
                        $it['product'] = ifset($products[$it['product_id']]);
                    }
                }
            }
            unset($o, $it);
        }
        if (isset($postprocess_fields['items']) && isset($postprocess_fields['skus'])) {
            unset($default_values['skus']);

            // Collect sku ids
            $sku_ids = array();
            foreach ($data as $o) {
                if (!empty($o['items'])) {
                    foreach ($o['items'] as $it) {
                        if (!empty($it['sku_id'])) {
                            $sku_ids[$it['sku_id']] = $it['sku_id'];
                        }
                    }
                }
            }

            // Fetch sku data
            if ($sku_ids) {
                $product_skus_model = new shopProductSkusModel();
                $skus = $product_skus_model->getById($sku_ids);

                foreach ($data as &$o) {
                    if (!empty($o['items'])) {
                        foreach ($o['items'] as &$it) {
                            $it['sku'] = ifset($skus[$it['sku_id']]);
                        }
                    }
                }
                unset($o, $it);
            }
        }

        if (isset($postprocess_fields['params'])) {
            $default_values['params'] = array();
            $rows = self::getModel('params')->getByField('order_id', $ids, true);
            foreach ($rows as $row) {
                $data[$row['order_id']]['params'][$row['name']] = $row['value'];
            }
        }

        if (isset($postprocess_fields['contact']) || isset($postprocess_fields['contact_full'])) {
            unset($default_values['contact_full']);
            $contact_ids = array();
            foreach ($data as $o) {
                $contact_ids[$o['contact_id']] = $o['contact_id'];
            }
            $contact_ids = array_values($contact_ids);
            $contact_fields = 'id,name,photo,firstname,middlename,lastname,is_user';
            if (isset($postprocess_fields['contact_full'])) {
                $contact_fields .= ',phone,email,address,photo_url_40,photo_url_96';
            }
            $contacts_collection = new waContactsCollection('id/'.join(',', $contact_ids), array(
                'photo_url_2x' => true,
            ));
            $contacts = $contacts_collection->getContacts($contact_fields, 0, 100500);
            $use_gravatar = wa('shop')->getConfig()->getGeneralSettings('use_gravatar');
            $gravatar_default = wa('shop')->getConfig()->getGeneralSettings('gravatar_default');

            foreach ($contacts as &$c) {
                $c['name'] = waContactNameField::formatName($c);
                if (!isset($postprocess_fields['contact_full'])) {
                    unset($c['firstname'], $c['middlename'], $c['lastname']);
                }
                if ($escape) {
                    $c['name'] = htmlspecialchars($c['name'], ENT_COMPAT, 'utf-8');
                }

                $email = ifset($c, 'email', 0, null);
                if ($use_gravatar && $email && !$c['photo']) {
                    $c['photo_url_40'] = shopHelper::getGravatar($email, 40, $gravatar_default, true);
                    $c['photo_url_96'] = shopHelper::getGravatar($email, 96, $gravatar_default, true);
                }
            }
            unset($c);
            foreach ($data as &$o) {
                if (isset($contacts[$o['contact_id']])) {
                    $o['contact'] = $contacts[$o['contact_id']];
                } else {
                    $o['contact'] = array(
                        'id'    => $o['contact_id'],
                        'name'  => 'deleted contact id='.$o['contact_id'],
                        'photo' => '',
                    );
                }
            }
            unset($o);
        }

        if (isset($postprocess_fields['state'])) {
            $workflow = new shopWorkflow();
            $locale = wa()->getLocale();
            $states = $workflow->getAvailableStates();
            foreach ($data as &$o) {
                if (isset($states[$o['state_id']])) {
                    $s = $states[$o['state_id']];
                    $o['state'] = array(
                        'id'    => $o['state_id'],
                        'name'  => waLocale::fromArray($s['name'], $locale),
                        'style' => ifset($s['options']['style'], array()),
                        'icon'  => ifset($s['options']['icon'], ''),
                    );
                } else {
                    $o['state'] = array(
                        'id'    => $o['state_id'],
                        'name'  => $o['state_id'],
                        'style' => array(),
                        'icon'  => '',
                    );
                }
            }
            unset($o);
        }

        if (isset($postprocess_fields['items']) && isset($postprocess_fields['subtotal'])) {
            foreach ($data as &$o) {
                $subtotal = 0;
                if (!empty($o['items'])) {
                    foreach ($o['items'] as $i) {
                        $subtotal += $i['price'] * $i['quantity'];
                    }
                }
                $o['subtotal'] = $subtotal;
            }
            unset($o);
        }

        if (isset($postprocess_fields['courier'])) {
            // Figure out courier_ids to load
            $courier_ids = array();
            foreach ($data as &$o) {
                $o['courier'] = null;
                if (!empty($o['params']['courier_id'])) {
                    $courier_ids[$o['params']['courier_id']] = $o['params']['courier_id'];
                }
            }
            unset($o);

            if ($courier_ids) {
                // Fetch couriers info
                $courier_model = new shopApiCourierModel();
                $couriers = $courier_model->getById($courier_ids);

                // Hide sensitive api-related fields
                foreach($couriers as &$c) {
                    foreach($c as $k => $v) {
                        if(substr($k, 0, 4) == 'api_') {
                            unset($c[$k]);
                        }
                    }
                }
                unset($c);

                // Add courier info to orders
                foreach ($data as &$o) {
                    if (!empty($o['params']['courier_id']) && !empty($couriers[$o['params']['courier_id']])) {
                        $o['courier'] = $couriers[$o['params']['courier_id']];
                    }
                }
                unset($o);
            }
        }

        if (class_exists('waContactAddressField') && class_exists('waContactAddressDataFormatter')) {
            if (isset($postprocess_fields['shipping_info'])) {
                $formatter = new waContactAddressDataFormatter();
                foreach ($data as &$o) {
                    $o['shipping_info'] = array();
                    if(!empty($o['params'])) {
                        if (isset($o['params']['shipping_name'])) {
                            $o['shipping_info']['name'] = $o['params']['shipping_name'];
                        }
                        if (isset($o['params']['shipping_est_delivery'])) {
                            $o['shipping_info']['est_delivery'] = $o['params']['shipping_est_delivery'];
                        }
                        $shipping_address = shopHelper::getOrderAddress($o['params'], 'shipping');
                        if ($shipping_address) {
                            $o['shipping_info']['address'] = $formatter->format(array('data' => $shipping_address));
                        }

                        list($date, $time_from, $time_to) = shopHelper::getOrderShippingInterval($o['params']);
                        if ($date) {
                            $o['shipping_info']['interval_date'] = $date;
                            $o['shipping_info']['interval_time_from'] = $time_from;
                            $o['shipping_info']['interval_time_to'] = $time_to;
                            $o['shipping_info']['interval_formatted'] = wa_date('date', $date).' '.$time_from.'-'.$time_to;
                        }
                    }

                    if ($o['shipping'] || $o['shipping_info']) {
                        $o['shipping_info']['price'] = $o['shipping'];
                    }
                }
            }

            if (isset($postprocess_fields['billing_info'])) {
                $formatter = new waContactAddressDataFormatter();
                foreach ($data as &$o) {
                    $o['billing_info'] = array();
                    if (!empty($o['params'])) {
                        $billing_address = shopHelper::getOrderAddress($o['params'], 'billing');
                        if ($billing_address) {
                            $o['billing_info']['address'] = $formatter->format(array('data' => $billing_address));
                        }
                    }
                }
            }
        }

        foreach ($data as &$o) {
            $o['id_encoded'] = shopHelper::encodeOrderId($o['id']);
            $o += $default_values;
        }
        unset($o);
    }

    /**
     * Returns position of specified order in collection.
     *
     * @param int $order Order id
     * @return int
     */
    public function getOrderOffset($order)
    {
        $model = self::getModel();

        if (!is_array($order)) {
            $order_id = (int)$order;
            $order = $model->getById($order_id);
        } else {
            $order_id = (int)$order['id'];
        }
        if (!$order || !$order_id) {
            return false;
        }

        // Prepare beforehand to make sure calling getSQL() won't modify $this->where.
        $this->prepare();

        // first, check existing in collection
        $this->where[] = 'o.id = '.$order_id;
        $sql = "SELECT * ".$this->getSQL();
        if (!$model->query($sql)) {
            return false;
        }
        array_pop($this->where);

        // than calculate offset. Not in effective way, but in easy and accurate way
        $order_offset = false;
        $total_count = $this->count();
        $limit = 500;
        $number_of_tries = floor($total_count / $limit) + 1;
        for ($try = 0; $try < $number_of_tries; $try += 1) {
            $offset = $try * $limit;
            $sql = "SELECT o.id ".$this->getSQL() . " LIMIT {$offset}, {$limit}";
            $list = $model->query($sql)->fetchAll(null, true);
            $order_offset = array_search($order['id'], $list);
            if ($order_offset !== false) {
                $order_offset += $offset;
                break;
            }
        }



        return (int) $order_offset;
    }

    protected function searchPrepare($query, $auto_title = true)
    {
        $query = urldecode($query);
        $i = $offset = 0;
        $query_parts = array();
        while (($j = strpos($query, '&', $offset)) !== false) {
            // escaped &
            if ($query[$j - 1] != '\\') {
                $query_parts[] = substr($query, $i, $j - $i);
                $i = $j + 1;
            }
            $offset = $j + 1;
        }
        $query_parts[] = substr($query, $i);

        $model = self::getModel();
        $title = array();
        foreach ($query_parts as $part) {
            if (!($part = trim($part))) {
                continue;
            }

            $parts = self::splitConditions($part);
            if ($parts) {
                $param = $parts[0];
                $op = ifset($parts[1], '');
                $val = ifset($parts[2], '');

                if ($field = $this->dropPrefix($param, 'item_code.')) {
                    $where = ":table.value".$this->getExpression($op, $val);
                    if ($field != 'any') {
                        $where = "($where AND :table.code_id=".((int)$field).")";
                    }
                    $this->addJoin('shop_order_item_codes', "o.id = :table.order_id", $where);
                } elseif ($field = $this->dropPrefix($param, 'params.')) {

                    #search by order params
                    $join = array(
                        'table' => 'shop_order_params',
                        'type'  => $val === 'NULL' || $val === 'EMPTY' ? 'LEFT' : '',
                    );
                    $on = "o.id = :table.order_id AND :table.name = '" . $model->escape($field) . "'";
                    if ($val === 'EMPTY') {
                        $where = "(:table.value IS NULL OR :table.value = '')";
                    } else {
                        $where = ":table.value".$this->getExpression($op, $val);
                    }

                    $this->addJoin($join, $on, $where);
                } elseif (($field = $this->dropPrefix($param, 'items.')) && self::getModel('items')->fieldExists($field)) {

                    #search by order items fields
                    $where = ':table.'.substr($param, 6).$this->getExpression($op, $val);
                    $this->addJoin('shop_order_items', null, $where);

                } elseif ($sub_field = $this->dropPrefix($param, 'address.')) {

                    #search by address stored ad order
                    $sub_field = self::getModel()->escape($sub_field);
                    $fields = array(
                        'billing_address',
                        'shipping_address'
                    );

                    $on = array();
                    foreach ($fields as $field) {
                        $on[] = ":table.name = '" . $field . "." . $sub_field . "'";
                    }
                    $on = ':table.order_id = o.id AND (' . implode(' OR ', $on) . ')';

                    if ($sub_field === 'region' && strstr($val, ':') !== false) {
                        $val = explode(':', $val);
                        $val = ifset($val[1], '');
                        // TODO: for region join wa_region and filter by country
                    }

                    $where = array();
                    foreach ($fields as $field) {
                        $where[] = "(:table.name = '" . $field . "." . $sub_field . "' AND :table.value " .
                            $this->getExpression($op, $val) . ')';
                    }
                    $where = implode(' OR ', $where);
                    $this->addJoin('shop_order_params', $on, $where);

                } elseif (($customer_field = $this->dropPrefix($param, 'customer.')) ||
                    ($contact_field = $this->dropPrefix($param, 'contact.'))) {

                    // search by customer or contact field

                    $on = '';
                    $table = '';
                    if ($customer_field && self::getModel('customer')->fieldExists($customer_field)) {
                        $field = $customer_field;
                        $table = 'shop_customer';
                        $on = ':table.contact_id = o.contact_id';
                    } elseif ($contact_field && self::getModel('contact')->fieldExists($contact_field)) {
                        $field = $contact_field;
                        $table = 'wa_contact';
                        $on = ':table.id = o.contact_id';
                    }

                    $join = array(
                        'table' => $table,
                        'type' => $val === 'NULL' || $val === 'EMPTY' ? 'LEFT' : ''
                    );
                    if ($val === 'EMPTY') {
                        $where = "(:table.{$field} IS NULL OR :table.{$field} = '')";
                    } else {
                        $where = ":table.{$field}" . $this->getExpression($op, $val);
                    }

                    if ($table) {
                        $this->addJoin($join, $on, $where);
                    }

                } elseif ($field = $this->dropPrefix($param, 'params_')) {

                    // special params cases
                    if ($field === 'coupon') {

                        $t1 = $this->addJoin(
                            array(
                                'table' => 'shop_order_params',
                                'type'  => 'LEFT'
                            ),
                            ":table.order_id = o.id AND :table.name = 'coupon_id'"
                        );

                        $t2 = $this->addJoin(
                            array(
                                'table' => 'shop_order_params',
                                'type'  => 'LEFT'
                            ),
                            ":table.order_id = o.id AND :table.name = 'coupon_code'"
                        );

                        $field = "IFNULL(:t2.value, IFNULL(:t1.value, ''))";
                        $where = $field.$this->getExpression($op, $val);
                        $where = str_replace(':t1', $t1, $where);
                        $where = str_replace(':t2', $t2, $where);
                        $this->where[] = $where;

                    }

                } elseif ($param === 'promo_id') {

                    $this->addJoin(array(
                        'type'  => '',
                        'table' => 'shop_promo',
                        'on'    => ":table.id {$this->getExpression($op, $val)}",
                    ));

                    $this->addJoin(array(
                        'type'  => '',
                        'table' => 'shop_promo_orders',
                        'on'    => "o.id = :table.order_id AND :table.promo_id {$this->getExpression($op, $val)}",
                    ));

                } elseif ($model->fieldExists($param)) {

                    // Try to convert order id from frontend format
                    if ($param == 'id') {
                        $decoded_id = shopBackendAutocompleteController::decodeOrderId($val);
                        if ($decoded_id) {
                            $val = $decoded_id;
                        }
                    }

                    #search by own table fields
                    $title[] = $param.$op.$val;
                    $this->where[] = 'o.'.$param.$this->getExpression($op, $val);

                } else {

                    #condition ignored
                }
            }
        }

        if ($title) {
            $title = implode(', ', $title);
            // Strip slashes from search title.
            $bs = '\\\\';
            $title = preg_replace("~{$bs}(_|%|&|{$bs})~", '\1', $title);
        }
        if ($auto_title) {
            $this->addTitle($title, ' ');
        }
    }

    private function dropPrefix($string, $prefix)
    {
        $len = strlen($prefix);
        if (substr($string, 0, $len) === $prefix) {
            return substr($string, $len);
        }
        return '';
    }

    /**
     * Returns collection type by order selection method: 'id' or 'search'.
     *
     * @return string
     */
    public function getType()
    {
        return $this->hash[0];
    }

    /**
     * Changes ORDER BY clause of order selection query.
     *
     * @param string|array $field Name of field in 'shop_order' table
     * @param string $order 'ASC' or 'DESC' modifier, defaults to 'ASC'
     * @return string
     */
    public function orderBy($field, $order = 'ASC')
    {
        $order = strtoupper($order);
        if (!in_array($order, array('DESC', 'ASC'))) {
            $order = 'ASC';
        }

        if (is_array($field)) {
            $fields = $field;
            $order_by = array();
            foreach ($fields as $field => $field_order) {
                if (is_int($field)) {
                    $field = $field_order;
                    $field_order = $order;
                }
                $order_by[] = $this->orderBy($field, $field_order);
            }
            $this->order_by = implode(', ', $order_by);
        } else {
            $param = null;
            if (strpos($field, ':') !== false) {
                list($field, $param) = explode(':', $field, 2);
            }
            switch ($field) {
                case 'shipping_datetime':
                    $this->order_by = "(o.shipping_datetime IS NOT NULL) DESC, o.shipping_datetime {$order}";
                    break;
                case 'updated':
                    $this->order_by = "IFNULL(o.update_datetime, o.create_datetime) {$order}";
                    break;
                case 'amount':
                    if ($param !== null) {
                        $this->order_by = sprintf("ABS(o.total * o.rate - %s) %s", str_replace(',', '.', (float)$param), $order);
                    } else {
                        $this->order_by = "o.total * o.rate {$order}";
                    }
                    break;
                case 'state_id':
                    $workflow = new shopWorkflow();
                    $state_ids = array_keys($workflow->getAllStates());
                    if ($state_ids) {
                        $state_ids = "'".join("','", self::getModel()->escape($state_ids))."'";
                        $this->order_by = "FIELD(o.state_id, {$state_ids}) {$order}";
                    }
                    break;
                default:
                    if (self::getModel()->fieldExists($field)) {
                        $this->order_by = "o.{$field} {$order}";
                    }
                    break;
            }

        }
        return $this->order_by;
    }

    /**
     * Adds custom string to current collection title, separated by optional delimiter.
     *
     * @param string $title Custom string to be added
     * @param string $delimiter Delimiter
     */
    public function addTitle($title, $delimiter = ', ')
    {
        if (!$title) {
            return;
        }
        if ($this->title) {
            $this->title .= $delimiter;
        }
        $this->title .= $title;
    }

    /**
     * Returns collection title.
     *
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }
}
