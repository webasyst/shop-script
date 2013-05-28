<?php

class shopProductsCollection
{
    protected $hash;
    protected $info = array();

    protected $options = array(
        'check_rights' => true
    );
    protected $prepared = false;
    protected $filtered = false;
    protected $title;

    protected $fields = array();
    protected $where;
    protected $having = array();
    protected $count;
    protected $order_by = 'p.create_datetime DESC';
    protected $group_by;
    protected $joins;

    protected $post_fields = array();

    protected $models = array();

    /**
     * Constructor for collections of photos
     *
     * @param string|array $hash
     * @param array $options
     */
    public function __construct($hash = '', $options = array())
    {
        foreach ($options as $k => $v) {
            $this->options[$k] = $v;
        }
        $this->setHash($hash);
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
        $this->hash = trim($hash, '/');
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
                    $params = array(
                        'collection' => $this,
                        'auto_title' => $auto_title,
                        'add' => $add,
                    );
                    /**
                     * @event products_collection
                     * @param array[string]mixed $params
                     * @param array[string]shopProductsCollection $params['collection']
                     * @param array[string]boolean $params['auto_title']
                     * @param array[string]boolean $params['add']
                     * @return void
                     */
                    wa()->event('products_collection', $params);
                }
            } else {
                if (!empty($this->options['sort'])) {
                    // !!! temporarily removed
                }
                if ($auto_title) {
                    $this->addTitle(_w('All products'));
                }
            }

            if (wa()->getEnv() == 'frontend') {
                if (!empty($this->options['filters'])) {
                    $this->filters(waRequest::get());
                }

                if (($type_id = waRequest::param('type_id')) && is_array($type_id)) {
                    foreach ($type_id as & $t) {
                        $t = (int) $t;
                    }
                    if ($type_id) {
                        $this->where[] = 'p.type_id IN ('.implode(',', $type_id).')';
                    } else {
                        $this->where[] = '0';
                    }
                }

                $this->where[] = 'p.status = 1';

                if ($sort = waRequest::get('sort')) {
                    if ($sort == 'stock') {
                        $sort = 'count';
                    }
                    $model = $this->getModel();
                    if ($model->fieldExists($sort)) {
                        $this->order_by = 'p.'.$sort;
                        if (waRequest::get('order') == 'desc') {
                            $this->order_by .= ' DESC';
                        } else {
                            $this->order_by .= ' ASC';
                        }
                    }
                }
            }

            if ($this->prepared) {
                return;
            }
            $this->prepared = true;
        }
    }

    protected function alsoboughtPrepare($id)
    {
        $this->joins[] = array(
            'table' => 'shop_order_items',
            'alias' => 'items1',
        );

        $this->joins[] = array(
            'table' => 'shop_order_items',
            'alias' => 'items2',
            'on'    => "items1.order_id = items2.order_id AND items1.type = 'product'",
        );

        $this->fields[] = "COUNT(*) orders_count";
        $this->where[] = "items1.product_id != ".(int) $id;
        $this->where[] = "items2.product_id = ".(int) $id;
        $this->group_by = 'p.id';
        $this->order_by = 'orders_count DESC';
    }

    public function filters($data)
    {
        if ($this->filtered) {
            return;
        }
        $delete = array('page', 'sort', 'order');
        foreach ($delete as $k) {
            if (isset($data[$k])) {
                unset($data[$k]);
            }
        }

        if (isset($data['price_min']) && $data['price_min'] !== '') {
            $this->where[] = 'p.price >= '.(int) $data['price_min'];
            unset($data['price_min']);
        }
        if (isset($data['price_max']) && $data['price_max'] !== '') {
            $this->where[] = 'p.price <= '.(int) $data['price_max'];
            unset($data['price_max']);
        }
        $feature_model = new shopFeatureModel();
        $features = $feature_model->getByField('code', array_keys($data), 'code');
        $feature_join_index = 0;
        foreach ($data as $feature_id => $values) {
            if (!is_array($values)) {
                $values = array($values);
            }
            if (isset($features[$feature_id])) {
                $feature_join_index++;
                $this->joins[] = array(
                    'table' => 'shop_product_features',
                    'alias' => 'filter'.$feature_join_index,
                    'on'    => 'p.id = filter'.$feature_join_index.'.product_id AND filter'.$feature_join_index.'.feature_id = '.(int) $features[$feature_id]['id']
                );
                foreach ($values as & $v) {
                    $v = (int) $v;
                }
                $this->where[] = 'filter'.$feature_join_index.".feature_value_id IN (".implode(',', $values).")";
                $this->group_by = 'p.id';
            }
        }
        $this->filtered = true;
    }

    protected function relatedPrepare($hash, $auto_title = false)
    {
        list($type, $product_id) = explode('/', $hash, 2);
        $this->joins[] = array(
            'table' => 'shop_product_related',
            'alias' => 'pr',
            'on'    => 'p.id = pr.related_product_id'
        );
        $this->where[] = "pr.type = '".$this->getModel()->escape($type)."'";
        $this->where[] = 'pr.product_id = '.(int) $product_id;
    }

    /**
     * @param int $id - ID of the category
     * @param bool $auto_title
     */
    protected function categoryPrepare($id, $auto_title = true)
    {
        $category_model = $this->getModel('category');
        $category = $category_model->getById($id);

        $this->info = $category;

        // category not found
        if (!$this->info) {
            $this->where[] = '0';
            return;
        }

        $this->info['hash'] = 'category';
        if (wa()->getEnv() == 'frontend') {
            $this->info['frontend_url'] = wa()->getRouteUrl('shop/frontend/category', array(
                'category_url' => waRequest::param('url_type') == 1 ? $category['url'] : $category['full_url']), true);
        } else {
            $routing =  wa()->getRouting();
            $domain_routes = $routing->getByApp('shop');
            foreach ($domain_routes as $domain => $routes) {
                foreach ($routes as $r) {
                    if (!$category['route'] || $category['route'] == $domain.'/'.$r['url']) {
                        $routing->setRoute($r, $domain);
                        $this->info['frontend_url'] = $routing->getUrl('shop/frontend/category', array(
                            'category_url' => isset($r['url_type']) && ($r['url_type'] == 1) ? $category['url'] : $category['full_url']),
                            true);
                        break 2;
                    }
                }
            }
        }

        if ($auto_title) {
            $this->addTitle($this->info['name']);
        }

        if (!empty($this->info['sort_products'])) {
            $this->order_by = 'p.'.$this->info['sort_products'];
        } else {
            if ($this->info['type'] == shopCategoryModel::TYPE_STATIC) {
                $this->order_by = 'cp.sort ASC';
            } else {
                $this->order_by = 'p.create_datetime DESC';
            }
        }

        if ($this->info['type'] == shopCategoryModel::TYPE_STATIC) {

            $this->joins['shop_category_products'] = array(
                'table' => 'shop_category_products',
                'alias' => 'cp',
            );

            if (wa()->getEnv() == 'frontend' && $this->info['include_sub_categories']) {
                $this->info['subcategories'] = $category_model->descendants($this->info, true)->where('type = '.shopCategoryModel::TYPE_STATIC)->fetchAll('id');
                $descendant_ids = array_keys($this->info['subcategories']);
                if ($descendant_ids) {
                    $this->where[] = "cp.category_id IN(".implode(',', $descendant_ids).")";
                }
            } else {
                $this->where[] = "cp.category_id = ".(int) $id;
            }
        } else {

            //if ($this->info['conditions']) {
                $hash = $this->hash;
                $this->setHash('/search/'.$this->info['conditions']);
                $this->prepare(false, false);
                while ($this->info['parent_id']/* && $this->info['conditions']*/) {
                    $this->info = $category_model->getByid($this->info['parent_id']);
                    if ($this->info['conditions']) {
                        $this->setHash('/search/'.$this->info['conditions']);
                        $this->prepare(true, false);
                    } else {
                        $this->joins['shop_category_products'] = array(
                            'table' => 'shop_category_products',
                            'alias' => 'cp',
                        );
                        $this->where[] = "cp.category_id = ".(int) $this->info['id'];
                    }
                }
                $this->setHash(implode('/', $hash));
            //}
        }
    }

    /**
     * @param int $id - ID of the set
     * @param bool $auto_title
     */
    protected function setPrepare($id, $auto_title = true)
    {
        $set_model = new shopSetModel();
        $set = $set_model->getById($id);

        if (!$set) {
            $this->where[] = '0';
            return;
        }

        $this->info = $set;
        $this->info['hash'] = 'set';

        if ($auto_title) {
            $this->addTitle($set['name']);
        }

        if ($set['type'] == shopSetModel::TYPE_STATIC) {
            $this->joins['shop_set_products'] = array(
                'table' => 'shop_set_products',
                'alias' => 'sp'
            );
            $this->where[] = "sp.set_id = '".$set_model->escape($id)."'";
            $this->order_by = 'sp.sort ASC';
        } else {
            if (!empty($set['rule'])) {
                $this->order_by = $set['rule'];
            }
        }
    }

    protected function idPrepare($ids_str) {
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
        $this->where[] = "p.id IN (".implode(',', $ids).")";
    }

    protected function typePrepare($id, $auto_title = true)
    {
        $type_model = new shopTypeModel();
        $type = $type_model->getById($id);
        if (!$type) {
            $this->where[] = '0';
            return;
        }
        $this->info = $type;

        if ($auto_title) {
            $this->addTitle($type['name']);
        }

        $this->where[] = "p.type_id = ".(int) $id;
    }

    protected function tagPrepare($id, $auto_title = true)
    {
        $tag_model = new shopTagModel();
        $tag = false;
        if (is_numeric($id)) {
            $tag = $tag_model->getById($id);
        }
        if (!$tag) {
            $tag = $tag_model->getByName($id);
            $id = $tag['id'];
        }
        $this->joins['tags'] = array(
            'table' => 'shop_product_tags',
            'alias' => 'pt',
        );
        $this->where[] = "pt.tag_id = ".(int) $id;
        $this->addTitle(sprintf(_w('Tagged “%s”'), $tag['name']));
    }

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

    protected function upsellingPrepare($product_id, $auto_title = false)
    {
        $model = $this->getModel();
        if (isset($this->options['product'])) {
            $product = $this->options['product'];
            $conditions = $this->options['conditions'];
        } else {
            $product = new shopProduct($product_id);
            $type_upselling_model = new shopTypeUpsellingModel();
            $conditions = $type_upselling_model->getByField('type_id', $product['type'], true);
        }

        $this->where[] = 'p.id != '.(int) $product_id;

        $sum = array();

        $feature_join_index = 0;
        foreach ($conditions as $row) {
            if ($row['feature'] == 'tag') {
                $tag_model = new shopTagModel();
                $tag = $tag_model->getByName($row['value']);
                if ($tag) {
                    $this->where[] = 'pt.tag_id = '.(int) $tag['id'];
                    $this->joins[] = array(
                        'table' => 'shop_product_tags',
                        'alias' => 'pt'
                    );
                }
                continue;
            }
            switch ($row['cond']) {
                case 'between':
                    list($min, $max) = explode(',', $row['value']);
                    if ($model->fieldExists($row['feature'])) {
                        $v = $product[$row['feature']];
                    } else {
                        $v = isset($product['features'][$row['feature']]) ? $product['features'][$row['feature']] : null;
                    }
                    if (!$v) {
                        continue;
                    }
                    $min = $v * (float) (100 + $min) / 100;
                    $max = $v * (float) (100 + $max) / 100;
                    $v = str_replace(',', '.', $v);
                    if ($model->fieldExists($row['feature'])) {
                        $this->where[] = 'p.'.$row['feature'].' > '.str_replace(',', '.', $min);
                        $this->where[] = 'p.'.$row['feature'].' < '.str_replace(',', '.', $max);
                        $sum[] = 'ABS(p.'.$row['feature'].' - '.$v.')/'.$v;
                    }
                    break;
                case 'is':
                    if ($model->fieldExists($row['feature'])) {
                        $this->where[] = 'p.'.$row['feature']." = '".$model->escape($row['value'])."'";
                    } else {
                        $feature_join_index++;
                        $this->joins[] = array(
                            'table' => 'shop_product_features',
                            'alias' => 'pf'.$feature_join_index
                        );
                        $this->where[] = 'pf'.$feature_join_index.".feature_value_id = ".(int) $row['value'];
                        $this->group_by = 'p.id';
                    }
                    break;
                case 'any':
                case 'all':
                    if ($model->fieldExists($row['feature'])) {
                        //$this->where[] = 'p.'.$row['feture']." = '".$model->escape($row['value'])."'";
                    } else {
                        if ($row['value']) {
                            $feature_join_index++;
                            $this->joins[] = array(
                                'table' => 'shop_product_features',
                                'alias' => 'pf'.$feature_join_index
                            );
                            $this->where[] = 'pf'.$feature_join_index.".feature_value_id IN (".$row['value'].")";
                            $this->group_by = 'p.id';
                        } else {
                            $this->where[] = '0';
                        }
                    }
                    break;
                case 'same':
                    if ($model->fieldExists($row['feature'])) {
                        $this->where[] = 'p.'.$row['feture']." = '".$model->escape($product->features[$row['feature']])."'";
                    } else {
                        $product_features_model = new shopProductFeaturesModel();
                        $rows = $product_features_model->getByField(array(
                            'product_id' => $product['id'],
                            'sku_id' => null,
                            'feature_id' => $row['feature_id']
                        ), true);
                        $values = array();
                        foreach ($rows as $r) {
                            $values[] = $r['feature_value_id'];
                        }
                        if ($values) {
                            $feature_join_index++;
                            $this->joins[] = array(
                                'table' => 'shop_product_features',
                                'alias' => 'pf'.$feature_join_index
                            );
                            $this->where[] = 'pf'.$feature_join_index.".feature_id = ".$row['feature_id'];
                            $this->where[] = 'pf'.$feature_join_index.".feature_value_id ".
                                (count($values) == 1 ? "= ".$values[0] : "IN (".implode(',', $values).")");
                            $this->group_by = 'p.id';
                        }
                    }
                    break;
            }
        }
        if ($sum) {
            $this->fields[] = '('.implode(' + ', $sum).') AS upselling_deviation';
            $this->order_by = 'upselling_deviation';
        }
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
                if ($parts[0] == 'category_id') {
                    if (!isset($this->joins['shop_category_products'])) {
                        $this->joins['shop_category_products'] = array(
                            'table' => 'shop_category_products',
                            'alias' => 'cp'
                        );
                    }
                    $title[] = "category_id ".$parts[1].$parts[2];
                    $this->where[] = 'cp.category_id'.$this->getExpression($parts[1], $parts[2]);
                } elseif ($parts[0] == 'query') {
                    $word_model = new shopSearchWordModel();
                    $word_ids = $word_model->getByString($parts[2]);
                    if ($word_ids) {
                        $this->joins[] = array(
                            'table' => 'shop_search_index',
                            'alias' => 'si'
                        );
                        $this->where[] = 'si.word_id IN ('.implode(",", $word_ids).')';
                        if (count($word_ids) > 1) {
                            $this->fields[] = "SUM(si.weight) AS weight";
                            $this->order_by = 'weight DESC';
                            $this->group_by = 'p.id';
                        } else {
                            $this->fields[] = "si.weight";
                            $this->order_by = 'si.weight DESC';
                        }
                    } elseif ($parts[2]) {
                        $this->where[] = '0';
                    }
                    $this->prepared = true;
                    // if not found try find by name
                    if (!$this->count()) {
                        $this->count = null;
                        $this->joins = $this->where = $this->having = $this->fields = array();
                        if (wa()->getEnv() == 'frontend') {
                            $this->where[] = 'p.status = 1';
                            if ($this->filtered) {
                                $this->filtered = false;
                                $this->filters(waRequest::get());
                            }
                        }
                        $this->order_by = 'p.create_datetime DESC';
                        $this->group_by = null;
                        $q = $model->escape($parts[2], 'like');
                        $this->where[] = "p.name LIKE '%".$q."%' OR skus.name LIKE '%".$q."%' OR skus.sku LIKE '%".$q."%'";
                        $this->joins[] = array(
                            'table' => 'shop_product_skus',
                            'alias' => 'skus'
                        );
                        $this->group_by = 'p.id';
                        return;
                    } elseif ($word_ids) {
                        $result = $this->getProducts('*', 0, 1);
                        $p = array_shift($result);
                        $w = str_replace(',', '.', 0.3 * $p['weight']);
                        if (count($word_ids) > 1) {
                            $this->having[] = 'SUM(si.weight) > '.$w;
                        } else {
                            $this->where[] = 'weight > '.$w;
                        }
                        $this->count = null;
                    }
                    $title[] = $parts[0].$parts[1].$parts[2];
                } elseif ($parts[0] == 'tag') {
                    $this->joins['tags'] = array(
                        'table' => 'shop_product_tags',
                        'alias' => 'pt',
                    );
                    $tag_model = $this->getModel('tag');
                    if (strpos($parts[2], '||') !== false) {
                        $tags = explode('||', $parts[2]);
                        $tag_ids = $tag_model->getIds($tags);
                    } else {
                        $sql = "SELECT id FROM ".$tag_model->getTableName()." WHERE name".$this->getExpression($parts[1], $parts[2]);
                        $tag_ids = $tag_model->query($sql)->fetchAll(null, true);
                    }
                    if ($tag_ids) {
                        $this->where[] = "pt.tag_id IN ('".implode("', '", $tag_ids)."')";
                    }
                } elseif ($model->fieldExists($parts[0])) {
                    $title[] = $parts[0].$parts[1].$parts[2];
                    $this->where[] = 'p.'.$parts[0].$this->getExpression($parts[1], $parts[2]);
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
            case "==":
            case "=";
        default:
            return " = '".$model->escape($value)."'";
        }
    }

    protected function getFields($fields)
    {
        $model = $this->getModel();
        if ($fields == '*') {
            return 'p.*'.($this->fields ? ",".implode(",", $this->fields) : '');
        }

        $required_fields = array('id' => 'p'); // field => table, to be added later in any case

        if (!is_array($fields)) {
            $fields = explode(",", $fields);
            $fields = array_map('trim', $fields);
        }

        // Add required fields to select and delete fields for getting data after query
        foreach ($fields as $i => $f) {
            if ($f == '*') {
                $fields[$i] = 'p.*';
                continue;
            }
            if (!$model->fieldExists($f)) {
                if ($f == 'images' || $f == 'image' || $f === 'frontend_url') {
                    $this->post_fields['_internal'][] = $f;
                }
                unset($fields[$i]);
                continue;
            } elseif (strpos($f, '.') === false) {
                $fields[$i] = 'p.'.$f;
            }

            if (isset($required_fields[$f])) {
                $fields[$i] = ($required_fields[$f] ? $required_fields[$f]."." : '').$f;
                unset($required_fields[$f]);
            }
        }

        foreach ($required_fields as $field => $table) {
            $fields[] = ($table ? $table."." : '').$field;
        }
        if ($this->fields) {
            foreach ($this->fields as $f) {
                $fields[] = $f;
            }
        }

        return implode(",", $fields);
    }

    /**
     * Returns ORDER BY clause
     * @return string
     */
    protected function _getOrderBy()
    {
        if ($this->order_by) {
            return " ORDER BY ".$this->order_by;
        } else {
            return "";
        }
    }

    /**
     * Returns GROUP BY clause
     * @return string
     */
    protected function _getGroupBy()
    {
        if ($this->group_by) {
            return " GROUP BY ".$this->group_by;
        } else {
            return "";
        }
    }

    /**
     * Set order by clause for select
     *
     * @param string|array $field It is possible pass array with field and order
     * @param string $order
     * @return string
     */
    public function orderBy($field, $order = 'ASC')
    {
        if (is_array($field)) {
            if (count($field) > 1) {
                list($field, $order) = $field;
            } else {
                $field = $field[0];
            }
        }
        if (strtolower(trim($order)) == 'desc') {
            $order = 'DESC';
        } else {
            $order = 'ASC';
        }
        $field = trim($field);
        if ($field) {
            //$this->prepare();
            if (strpos($field, '.') === false) {
                $model = $this->getModel();
                if ($model->fieldExists($field)) {
                    $this->getSQL();
                    return $this->order_by = 'p.'.$field." ".$order;
                } else if ($field == 'sort') {
                    $this->getSQL();
                    if ($this->info['hash'] == 'set') {
                        return $this->order_by = 'sp.'.$field." ASC";
                    } else if ($this->info['hash'] == 'category') {
                        return $this->order_by = 'cp.'.$field." ASC";
                    } else {
                        return '';
                    }
                }
            } else {
                $this->getSQL();
                return $this->order_by = $field.' '.$order;
            }
        }
        return '';
    }

    public function getOrderBy()
    {
        if (!$this->order_by) {
            return array();
        } else {
            $order = explode(' ', $this->order_by);
            if (!isset($order[1])) {
                $order[1] = 'ASC';
            }
            $order[0] = strtolower($order[0]);
            $order[1] = strtolower($order[1]);
            $k = strpos($order[0], '.');
            if ($k !== null) {
                $order[0] = substr($order[0], $k + 1);
            }
            return $order;
        }
    }

    public function getSQL()
    {
        $this->prepare();
        $sql = "FROM shop_product p";

        if ($this->joins) {
            foreach ($this->joins as $join) {
                $alias = isset($join['alias']) ? $join['alias'] : '';
                if (isset($join['on'])) {
                    $on = $join['on'];
                } else {
                    $on = "p.id = ".($alias ? $alias : $join['table']).".product_id";
                }
                $sql .= (isset($join['type']) ? " ".$join['type'] : '')." JOIN ".$join['table']." ".$alias." ON ".$on;
            }
        }

        $where = $this->where;

        if ($where) {
            $sql .= " WHERE ".implode(" AND ", $where);
        }
        return $sql;
    }

    public function count()
    {
        if ($this->count !== null) {
            return $this->count;
        }
        $sql = $this->getSQL();
        $sql = "SELECT COUNT(".($this->joins ? 'DISTINCT ' : '')."p.id) ".$sql;
        if ($this->having) {
            $sql .= $this->_getGroupBy();
            $sql .= " HAVING ".implode(' AND ', $this->having);
        }
        $count = (int) $this->getModel()->query($sql)->fetchField();

        if ($this->hash[0] == 'category' && !empty($this->info['id'])) {
            if ($this->info['count'] != $count && wa()->getEnv() != 'frontend') {
                $this->getModel('category')->updateById($this->hash[1], array('count' => $count));
            }
        } elseif ($this->hash[0] == 'set' && !empty($this->info['id'])) {
            if ($this->info['type'] == shopSetModel::TYPE_DYNAMIC) {
                $count = min($this->info['count'], $count);
            } elseif ($this->info['count'] != $count && wa()->getEnv() != 'frontend') {
                $this->getModel('set')->updateById($this->hash[1], array('count' => $count));
            }
        }
        return $this->count = $count;
    }

    public function getProducts($fields = "*", $offset = 0, $limit = null, $escape = true)
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
        if (wa()->getEnv() == 'frontend' && $fields == '*') {
            $fields .= ',frontend_url';
        }
        $split_fields = array_map('trim', explode(',', $fields));
        if (in_array('frontend_url', $split_fields) && !in_array('*', $split_fields)) {
            if ($dependent_fields = array_diff(array('url', 'category_id', ), $split_fields)) {
                $fields .= ','.implode(',', $dependent_fields);
            }
        }

        $sql = $this->getSQL();

        // for dynamic set
        if ($this->hash[0] == 'set' && !empty($this->info['id']) && $this->info['type'] == shopSetModel::TYPE_DYNAMIC) {
            $this->count();
            if ($offset + $limit > $this->count) {
                $limit = $this->count - $offset;
            }
        }

        $sql = "SELECT ".($this->joins && !$this->group_by ? 'DISTINCT ' : '').$this->getFields($fields)." ".$sql;
        $sql .= $this->_getGroupBy();
        if ($this->having) {
            $sql .= " HAVING ".implode(' AND ', $this->having);
        }
        $sql .= $this->_getOrderBy();
        $sql .= " LIMIT ".($offset ? $offset.',' : '').(int) $limit;
        $data = $this->getModel()->query($sql)->fetchAll('id');
        if (!$data) {
            return array();
        }
        $this->workupProducts($data, $escape);
        return $data;
    }

    private function workupProducts(&$products = array(), $escape)
    {
        foreach ($products as & $p) {
            $p['min_price'] = (float) $p['min_price'];
            $p['max_price'] = (float) $p['max_price'];

            // escape
            if ($escape) {
                $p['name'] = htmlspecialchars($p['name']);
                $p['url'] = htmlspecialchars($p['url']);
            }
        }
        unset($p);

        if (!empty($this->options['params'])) {
            $product_params_model = new shopProductParamsModel();
            $rows = $product_params_model->getByField('product_id', array_keys($products), true);
            foreach ($rows as $row) {
                $products[$row['product_id']]['params'][$row['name']] = $row['value'];
            }
        }

        if ($this->post_fields) {
            $ids = array_keys($products);
            foreach ($this->post_fields as $table => $fields) {
                if ($table == '_internal') {
                    if (wa()->getEnv() == 'frontend' && waRequest::param('url_type') == 2) {
                        $cat_ids = array();
                        foreach ($products as &$p) {
                            if (!empty($p['category_id'])) {
                                $cat_ids[] = $p['category_id'];
                            }
                        }
                        $cat_ids = array_unique($cat_ids);
                        $categories = $this->getModel('category')->getById($cat_ids);
                        foreach ($products as &$p) {
                            if (!empty($p['category_id'])) {
                                $p['category_url'] = $categories[$p['category_id']]['full_url'];
                            }
                        }
                    }
                    foreach ($fields as $i => $f) {
                        if ($f == 'images' || $f == 'image') {
                            if ($f == 'images') {
                                $product_images_model = new shopProductImagesModel();
                                $product_images = $product_images_model->getImages($ids, 'thumb', 'product_id');
                                foreach ($product_images as $product_id => $images) {
                                    $products[$product_id]['images'] = $images;
                                }
                            } elseif ($f == 'image') {
                                $size = wa('shop')->getConfig()->getImageSize('thumb');
                                foreach ($products as & $p) {
                                    if ($p['image_id']) {
                                        $p['image']['thumb_url'] = shopImage::getUrl(array(
                                            'id'         => $p['image_id'],
                                            'product_id' => $p['id'],
                                            'ext'        => $p['ext'],
                                        ), $size);
                                    }
                                }
                            }
                        } elseif ($f == 'frontend_url') {
                            foreach ($products as &$p) {
                                $route_params = array('product_url' => $p['url']);
                                if (isset($p['category_url'])) {
                                    $route_params['category_url'] = $p['category_url'];
                                } elseif (isset($this->info['hash']) && $this->info['hash'] == 'category') {
                                    if (isset($this->info['subcategories']) && $this->info['id'] != $p['category_id']) {
                                        if (isset($this->info['subcategories'][$p['category_id']])) {
                                            $route_params['category_url'] = $this->info['subcategories'][$p['category_id']]['full_url'];
                                        }
                                    } else {
                                        $route_params['category_url'] = $this->info['full_url'];
                                    }
                                }

                                $p['frontend_url'] = wa()->getRouteUrl('shop/frontend/product', $route_params);
                            }
                            unset($p);
                        }

                    }
                }
            }
        }
    }

    /**
     * @return shopProductModel
     */
    protected function getModel($name = 'product')
    {
        if (!isset($this->models[$name])) {
            if ($name == 'product') {
                $this->models[$name] = new shopProductModel();
            } elseif ($name == 'category') {
                $this->models[$name] = new shopCategoryModel();
            } elseif ($name == 'set') {
                $this->models[$name] = new shopSetModel();
            } elseif ($name == 'tag') {
                $this->models[$name] = new shopTagModel();
            }
        }
        return $this->models[$name];
    }

    public function getTitle()
    {
        if ($this->title === null) {
            $this->prepare();
        }
        return $this->title;
    }

    public function getInfo()
    {
        if (empty($this->info)) {
            $this->prepare();
        }
        if (!isset($this->info['hash'])) {
            $this->info['hash'] = $this->hash[0];
        }
        return $this->info;
    }

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

    /** Set GROUP BY clause. Primarily for plugins that extend this collection. */
    public function groupBy($clause)
    {
        $this->group_by = $clause;
        return $this;
    }

    /** Add WHERE condition. Primarily for plugins that extend this collection. */
    public function addWhere($condition)
    {
        $this->where[] = $condition;
        return $this;
    }

    /** Add JOIN clause. Primarily for plugins that extend this collection. */
    public function addJoin($id, $clause=null)
    {
        if ($clause === null) {
            $this->joins[] = $id;
        } else {
            $this->joins[$id] = $clause;
        }
        return $this;
    }

    public function getHash()
    {
        return $this->hash;
    }
}

