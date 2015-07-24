<?php

class shopOrdersCollection
{

    protected $hash;
    protected $info = array();

    protected $options = array();

    protected $prepared = false;
    protected $title;

    protected $fields = array();
    protected $where;
    protected $having = array();
    protected $count;
    protected $order_by = 'o.create_datetime DESC, o.id';
    protected $group_by;
    protected $joins;
    protected $join_index = array();

    protected $other_fields = array();

    protected $models = array();

    /**
     * Creates a new order collection.
     *
     * @param string|array $hash Order selection conditions. Examples:
     *     array(12,23,34) or 'id/12,23,34' — explicitely specified order ids
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
     *     'search/items.product_id=10' - search orders related to specific (id=10) product
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
        wa()->event('orders_collection.filter', $this);
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
                    $processed = wa()->event('orders_collection', $params);
                    if (!$processed) {
                        throw new waException('Unknown collection hash type: '.htmlspecialchars($type));
                    }
                }
            } else {
                if ($auto_title) {
                    $this->addTitle(_w('All orders'));
                }
            }

            if ($this->prepared) {
                return;
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
            $ids[] = (int) $id;
        }
        if (!$ids) {
            $this->where[] = '0';
        }
        $this->where[] = "o.id IN (".implode(',', $ids).")";
    }

    public function getFields($fields)
    {
        if ($fields == '*') {
            return 'o.*'.($this->fields ? ",".implode(",", $this->fields) : '');
        }

        if (!is_array($fields)) {
            $fields = explode(",", $fields);
            $fields = array_map('trim', $fields);
        }
        foreach ($fields as $i => $f) {
            if ($f == '*') {
                $fields[$i] = 'o.*';
                continue;
            } else {
                $this->other_fields[] = $f;
                unset($fields[$i]);
            }
        }
        if ($this->fields) {
            foreach ($this->fields as $f) {
                $fields[] = $f;
            }
        }
        return implode(",", $fields);
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
            'type' => $type
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
    public function getSQL()
    {
        $this->prepare();
        $sql = "FROM shop_order o";

        if ($this->joins) {
            foreach ($this->joins as $join) {
                $alias = isset($join['alias']) ? $join['alias'] : '';
                if (isset($join['on'])) {
                    $on = $join['on'];
                } else {
                    $on = "o.id = ".($alias ? $alias : $join['table']).".order_id";
                }
                $sql .= (!empty($join['type']) ? " ".$join['type'] : '')." JOIN ".$join['table']." ".$alias." ON ".$on;
            }
        }

        if ($this->where) {
            $sql .= " WHERE ".implode(" AND ", $this->where);
        }
        $sql .= $this->getOrderBy();
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
        $sql = $this->getSQL();
        $sql = "SELECT COUNT(".($this->joins ? 'DISTINCT ' : '')."o.id) ".$sql;
        return $this->count = (int)$this->getModel()->query($sql)->fetchField();
    }

    /**
     * @param string $name
     * @return shopOrderModel
     */
    protected function getModel($name = 'order')
    {
        if (!isset($this->models[$name])) {
            if (in_array($name, array('order', 'items', 'params', 'log'))) {
                $class_name = 'shop'.($name != 'order' ? 'Order' : '').ucfirst($name).'Model';
                $this->models[$name] = new $class_name();
            }
        }
        return $this->models[$name];
    }

    /**
     * Parses order selection condition string of the form acceptable by class constructor.
     * @see __constructor()
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
            if ($temp = preg_split("/(\\\$=|\^=|\*=|==|!=|>=|<=|=|>|<)/uis", $part, 2, PREG_SPLIT_DELIM_CAPTURE)) {
                $name = array_shift($temp);
                if ($name == 'tag') {
                    $temp[1] = explode('||', $temp[1]);
                }
                if ($name != 'price') {
                    $result[$name] = $temp;
                } else {
                    if ($temp[0] == '>=') {
                        $result[$name][0] = $temp;
                    } else if ($temp[0] == '<=') {
                        $result[$name][1] = $temp;
                    } else {
                        $result[$name] = $temp;
                    }
                }
            }
        }
        return $result;
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
            case '!=':
                if ($value === 'NULL') {
                    return ' IS NOT NULL';
                }
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
            case "=";
            default:
                if ($value === 'NULL') {
                    return ' IS NULL';
                }
                if (strstr($value, '||') !== false) {
                    $parts = explode('||', $value);
                    foreach ($parts as &$p) {
                        $p = $model->escape(trim($p));
                    }
                    unset($p);
                    if ($parts) {
                        return " IN ('".implode("','", $parts)."')";
                    }
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

        $sql = $this->getSQL();
        $sql = "SELECT ".$this->getFields($fields)." ".$sql;
        $sql .= " LIMIT ".($offset ? $offset.',' : '').(int) $limit;

        $data = $this->getModel()->query($sql)->fetchAll('id');
        if (!$data) {
            return array();
        }

        $ids = array_keys($data);

        // add other fields
        foreach ($this->other_fields as $field) {
            switch ($field) {
                case 'items':
                case 'params':
                    $rows = $this->getModel($field)->getByField('order_id', $ids, true);
                    foreach ($rows as $row) {
                        if ($field == 'params') {
                            $data[$row['order_id']][$field][$row['name']] = $row['value'];
                        } else {
                            if ($escape) {
                                $row['name'] = htmlspecialchars($row['name']);
                            }
                            $data[$row['order_id']][$field][] = $row;
                        }
                    }
                    break;
                case 'contact':
                    $contact_ids = array();
                    foreach ($data as $o) {
                        $contact_ids[] = $o['contact_id'];
                    }
                    $contact_model = new waContactModel();
                    $contacts = $contact_model->getById(array_unique($contact_ids));
                    foreach ($data as &$o) {
                        if (isset($contacts[$o['contact_id']])) {
                            $c = $contacts[$o['contact_id']];
                            $o['contact'] = array(
                                'id' => $c['id'],
                                'name' => waContactNameField::formatName($c),
                                'photo' => $c['photo']
                            );
                            if ($escape) {
                                $o['contact']['name'] = htmlspecialchars($o['contact']['name']);
                            }
                        }
                    }
                    unset($o);
                    break;
            }
        }
        unset($t);

        return $data;
    }

    /**
     * Returns position of specified order in collection.
     *
     * @param int $order Order id
     * @return int
     */
    public function getOrderOffset($order)
    {
        $model = $this->getModel();

        if (!is_array($order)) {
            $order_id = (int) $order;
            $order = $model->getById($order_id);
        } else {
            $order_id = (int) $order['id'];
        }
        if (!$order || !$order_id) {
            return false;
        }
        $create_datetime = $model->escape($order['create_datetime']);

        // for calling prepare
        $this->getSQL();

        // first, check existing in collection
        $this->where[] = 'o.id = '.$order_id;
        $sql = "SELECT * ".$this->getSQL();
        if (!$model->query($sql)) {
            return false;
        }
        array_pop($this->where);

        // than calculate offset
        $this->where[] = "(o.create_datetime > '{$create_datetime}' OR (o.create_datetime = '{$create_datetime}' AND o.id < '{$order_id}'))";
        $sql = "SELECT COUNT(o.id) offset ".$this->getSQL();
        $offset = $model->query($sql)->fetchField();
        array_pop($this->where);

        return $offset;

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

        $model = $this->getModel();
        $title = array();
        foreach ($query_parts as $part) {
            if (!($part = trim($part))) {
                continue;
            }
            $parts = preg_split("/(\\\$=|\^=|\*=|==|!=|>=|<=|=|>|<)/uis", $part, 2, PREG_SPLIT_DELIM_CAPTURE);
            if ($parts) {
                if (substr($parts[0], 0, 7) == 'params.') {
                    $this->addJoin(array('table' => 'shop_order_params', 'type' => $parts[2] === 'NULL' ? 'left' : ''),
                        "o.id = :table.order_id AND :table.name = '".$model->escape(substr($parts[0] ,7))."'",
                        ":table.value".$this->getExpression($parts[1], $parts[2]));
                } elseif (substr($parts[0], 0, 6) == 'items.' && $this->getModel('items')->fieldExists(substr($parts[0], 6))) {
                    $this->addJoin('shop_order_items', null, ':table.'.substr($parts[0], 6).$this->getExpression($parts[1], $parts[2]));
                } elseif (substr($parts[0], 0, 8) === 'address.') {
                    $subfield = $this->getModel()->escape(substr($parts[0], 8));
                    $fields = array(
                        'billing_address', 'shipping_address'
                    );
                    $op = ifset($parts[1], '');
                    $val = ifset($parts[2], '');

                    $on = array();
                    foreach ($fields as $field) {
                        $on[] = ":table.name = '" . $field . "." . $subfield . "'";
                    }
                    $on = ':table.order_id = o.id AND (' . implode(' OR ', $on) . ')';

                    if ($subfield === 'region' && strstr($val, ':') !== false) {
                        $val = explode(':', $val);
                        $val = ifset($val[1], '');
                        // TODO: for region join wa_region and filter by country
                    }

                    $where = array();
                    foreach ($fields as $field) {
                        $where[] = "(:table.name = '" . $field . "." . $subfield . "' AND :table.value " .
                                $this->getExpression($op, $val) . ')';
                    }
                    $where = implode(' OR ', $where);
                    $this->addJoin('shop_order_params', $on, $where);

                } elseif ($model->fieldExists($parts[0])) {
                    $title[] = $parts[0].$parts[1].$parts[2];
                    $this->where[] = 'o.'.$parts[0].$this->getExpression($parts[1], $parts[2]);
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
     * Returns ORDER BY clause
     * @return string
     */
    protected function getOrderBy()
    {
        if ($this->order_by) {
            return " ORDER BY ".$this->order_by;
        } else {
            return "";
        }
    }

    /**
     * Changes ORDER BY clause of order selection query.
     *
     * @param string $field Name of field in 'shop_order' table
     * @param string $order 'ASC' or 'DESC' modifier, defaults to 'ASC'
     */
    public function orderBy($field, $order = 'ASC')
    {
        $alias = 'o';
        $this->order_by = "{$alias}.{$field} {$order}";
    }

    /**
     * Adds custom string to current collection title, separated by optional delimiter.
     *
     * @param string $title Custom string to be added
     * @param string $delim Delimiter
     */
    public function addTitle($title, $delim = ', ')
    {
        if (!$title) {
            return;
        }
        if ($this->title) {
            $this->title .= $delim;
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