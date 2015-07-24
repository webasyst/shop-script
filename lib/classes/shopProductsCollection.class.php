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
    protected $join_index = array();

    protected $post_fields = array();

    protected $models = array();
    protected $is_frontend;

    /**
     * Creates a new product collection.
     *
     * @param string|array $hash Product selection conditions. Examples:
     *     array(12,23,34) or 'id/12,23,34' — explicitely specified product ids
     *     'related/cross_selling/12' — cross-selling items for product with id = 12
     *     'related/upselling/23' — upselling items for product with id = 23
     *     'category/208' — search by category id
     *     'search/query=style' — search results by query 'style'
     *     'search/tag=style' — search results by tag 'style'
     *     'tag/style' — alternative form of search by tag
     *     'search/type_id=1' — search results by any field of shop_product table; e.g., type_id
     *     'type/2' — search by type_id
     *     'search/name=SuperProduct' — search by 'name' field (exact match)
     *     'search/color.value_id=6' — search by value with id=6 of 'checkboxes'-type feature with code 'color'
     * @param array $options Extra options:
     *     'filters'    => whether products must be filtered according to GET request conditions
     *     'product'    => shopProduct object to select upselling items for; for upselling-type collections only
     *     'conditions' => upselling items selection conditions; for upselling-type collections only
     *     'params'     => whether extra product params must be included in collection products
     *     'absolute'   => whether absolute product image URLs must be returned for collection products
     */
    public function __construct($hash = '', $options = array())
    {
        $this->setOptions($options);
        if ($this->is_frontend === null) {
            $this->is_frontend = wa()->getEnv() == 'frontend';
            if (!isset($this->options['round_prices'])) {
                $this->options['round_prices'] = true;
            }
        }
        $this->setHash($hash);
    }

    /**
     * Extra collection options
     * @see __construct()
     *
     * @param array $options
     */
    public function setOptions($options)
    {
        foreach ($options as $k => $v) {
            $this->options[$k] = $v;
        }
        if (isset($this->options['frontend'])) {
            $this->is_frontend = $this->options['frontend'];
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
            if ($this->is_frontend) {
                $this->frontendConditions();
            }
            if ($sort = waRequest::get('sort')) {
                if ($sort == 'stock') {
                    $sort = 'count';
                }
                if (waRequest::get('order') == 'desc') {
                    $order = 'DESC';
                } else {
                    $order = 'ASC';
                }
                $model = $this->getModel();
                if ($sort == 'stock_worth') {
                    $this->fields[] = 'IFNULL(p.count, 0)*p.price AS stock_worth';
                    $this->order_by = 'stock_worth '.$order;
                } else {
                    $order_by = array();
                    foreach ((array)$sort as $_id=> $_sort) {
                        $_sort = trim((string)$_sort);
                        if ($model->fieldExists($_sort)) {
                            $order_by[$_id] = 'p.'.$_sort;
                            $order_by[$_id] .= ' '.$order;
                            if ($_sort == 'count') {
                                $this->fields[] = 'IF(p.count IS NULL, 1, 0) count_null';
                                $order_by[$_id] = 'count_null '.$order.', '.$order_by[$_id];
                            }
                        }
                    }
                    if($order_by){
                        $this->order_by = implode(', ',$order_by);
                    }
                }
            }
            if ($type) {
                $method = strtolower($type).'Prepare';
                if (method_exists($this, $method)) {
                    $this->$method(isset($this->hash[1]) ? $this->hash[1] : '', $auto_title);
                } else {
                    $params = array(
                        'collection' => $this,
                        'auto_title' => $auto_title,
                        'add'        => $add,
                    );
                    /**
                     * @event products_collection
                     * @param array [string]mixed $params
                     * @param array [string]shopProductsCollection $params['collection']
                     * @param array [string]boolean $params['auto_title']
                     * @param array [string]boolean $params['add']
                     * @return bool null if ignored, true when something changed in the collection
                     */
                    $processed = wa()->event('products_collection', $params);
                    if (!$processed) {
                        throw new waException('Unknown collection hash type: '.htmlspecialchars($type));
                    }
                }
            } else {
                if ($auto_title) {
                    $this->addTitle(_w('All products'));
                }
            }

            if ($this->prepared) {
                return;
            }
            $this->prepared = true;
        }
    }

    protected function frontendConditions()
    {
        if (!empty($this->options['filters'])) {
            $this->filters(waRequest::get());
        }

        if (($type_id = waRequest::param('type_id')) && is_array($type_id)) {
            foreach ($type_id as & $t) {
                $t = (int)$t;
            }
            if ($type_id) {
                $this->where[] = 'p.type_id IN ('.implode(',', $type_id).')';
            } else {
                $this->where[] = '0';
            }
        }

        $this->where[] = 'p.status = 1';
    }

    protected function alsoboughtPrepare($id)
    {
        $alias = $this->addJoin('shop_order_items', null, ':table.product_id != '.(int)$id." AND :table.type = 'product'");
        $this->addJoin('shop_order_items', $alias.".order_id = :table.order_id", ':table.product_id = '.(int)$id);
        $this->fields[] = "COUNT(*) orders_count";
        $this->group_by = 'p.id';
        $this->order_by = 'orders_count DESC';
    }

    protected function toFloat($value)
    {
        if (strpos($value, ',') !== false) {
            $value = str_replace(',', '.', $value);
        }
        return str_replace(',', '.', (double)$value);
    }

    /**
     * Filters collection products by specified conditions.
     *
     * @param array $data Product filtering conditions:
     *     'in_stock_only'     => whether only products with positive or unlimited stock count must be returned
     *     'price_min'         => minimum price limit
     *     'price_max'         => maximum price limit
     *     '%feature_code%'    => feature value
     */
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

        $config = wa('shop')->getConfig();

        if (isset($data['in_stock_only'])) {
            $this->where[] = '(p.count > 0 OR p.count IS NULL)';
        }

        if (isset($data['price_min']) && $data['price_min'] !== '') {
            $this->where[] = 'p.max_price >= '.$this->toFloat(shop_currency($data['price_min'], true, $config->getCurrency(true), false));
            unset($data['price_min']);
        }
        if (isset($data['price_max']) && $data['price_max'] !== '') {
            $this->where[] = 'p.min_price <= '.$this->toFloat(shop_currency($data['price_max'], true, $config->getCurrency(true), false));
            unset($data['price_max']);
        }
        $feature_model = new shopFeatureModel();
        $features = $feature_model->getByField('code', array_keys($data), 'code');
        foreach ($data as $feature_code => $values) {
            if (!is_array($values)) {
                if ($values === '') {
                    continue;
                }
                $values = array($values);
            }
            if (isset($features[$feature_code])) {
                if (isset($values['min']) || isset($values['max']) || isset($values['unit'])) {
                    if (ifset($values['min'], '') === '' && ifset($values['max'], '') === '') {
                        continue;
                    } else {
                        $unit = ifset($values['unit']);
                        $min = $max = null;
                        if (isset($values['min']) && $values['min'] !== '') {
                            $min = $values['min'];
                            if ($unit) {
                                $min = shopDimension::getInstance()->convert($min, $features[$feature_code]['type'], null,
                                    $unit);
                            }
                        }
                        if (isset($values['max']) && $values['max'] !== '') {
                            $max = $values['max'];
                            if ($unit) {
                                $max = shopDimension::getInstance()->convert($max, $features[$feature_code]['type'],
                                    null, $unit);
                            }
                        }
                        $fm = $feature_model->getValuesModel($features[$feature_code]['type']);
                        $values = $fm->getValueIdsByRange($features[$feature_code]['id'], $min, $max);
                    }
                } else {
                    foreach ($values as & $v) {
                        $v = (int)$v;
                    }
                }
                if ($values) {
                    $this->addJoin('shop_product_features',
                        'p.id = :table.product_id AND :table.feature_id = '.(int)$features[$feature_code]['id'],
                        ':table.feature_value_id IN ('.implode(',', $values).')');
                    $this->group_by = 'p.id';
                } else {
                    $this->where[] = '0';
                }
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
        $this->where[] = 'pr.product_id = '.(int)$product_id;
    }

    /**
     * @param int $id - ID of the category
     * @param bool $auto_title
     */
    protected function categoryPrepare($id, $auto_title = true)
    {
        /**
         * @var shopCategoryModel $category_model
         */
        $category_model = $this->getModel('category');
        $category = $category_model->getById($id);

        $this->info = $category;

        // category not found
        if (!$this->info) {
            $this->where[] = '0';
            return;
        }

        $this->info['hash'] = 'category';
        if ($this->is_frontend) {
            $this->info['frontend_url'] = wa()->getRouteUrl('shop/frontend/category', array(
                'category_url' => waRequest::param('url_type') == 1 ? $category['url'] : $category['full_url']
            ), true);
        } else {
            $frontend_urls = $category_model->getFrontendUrls($id);
            if ($frontend_urls) {
                $this->info['frontend_url'] = $frontend_urls[0];
                $this->info['frontend_urls'] = $frontend_urls;
            }
        }

        if ($auto_title) {
            $this->addTitle($this->info['name']);
        }

        if (!waRequest::get('sort')) {
            if (!empty($this->info['sort_products'])) {
                $tmp = explode(' ', $this->info['sort_products']);
                if (!isset($tmp[1])) {
                    $tmp[1] = 'DESC';
                }
                if ($tmp[0] == 'count') {
                    $this->fields[] = 'IF(p.count IS NULL, 1, 0) count_null';
                    $this->order_by = 'count_null '.$tmp[1].', p.count '.$tmp[1];
                } else if ($tmp[0] == 'stock_worth') {
                    $this->fields[] = 'IFNULL(p.count, 0)*p.price AS stock_worth';
                    $this->order_by = 'stock_worth '.$tmp[1];
                } else {
                    $this->order_by = 'p.'.$this->info['sort_products'];
                }
            }
        }


        if ($this->info['type'] == shopCategoryModel::TYPE_STATIC) {
            $alias = $this->addJoin('shop_category_products');
            if ( /*wa()->getEnv() == 'frontend' && */
            $this->info['include_sub_categories']
            ) {
                $this->info['subcategories'] = $category_model->descendants($this->info, true)->where('type = '.shopCategoryModel::TYPE_STATIC)->fetchAll('id');
                $descendant_ids = array_keys($this->info['subcategories']);
                if ($descendant_ids) {
                    $this->where[] = $alias.".category_id IN(".implode(',', $descendant_ids).")";
                }
            } else {
                $this->where[] = $alias.".category_id = ".(int)$id;
            }
            if ((empty($this->info['sort_products']) && !waRequest::get('sort')) || waRequest::get('sort') == 'sort') {
                $this->order_by = $alias.'.sort ASC';
            }
        } else {
            $hash = $this->hash;
            $this->setHash('/search/'.$this->info['conditions']);
            $this->prepare(false, false);
            $info = $this->info;
            while ($info['parent_id'] /* && $this->info['conditions']*/) {
                $info = $category_model->getByid($info['parent_id']);
                if ($info['type'] == shopCategoryModel::TYPE_DYNAMIC) {
                    $this->setHash('/search/'.$info['conditions']);
                    $this->prepare(true, false);
                } else {
                    $alias = $this->addJoin('shop_category_products');
                    if ($info['include_sub_categories']) {
                        $subcategories = $category_model->descendants($info, true)->where('type = '.shopCategoryModel::TYPE_STATIC)->fetchAll('id');
                        $descendant_ids = array_keys($subcategories);
                        if ($descendant_ids) {
                            $this->where[] = $alias.".category_id IN(".implode(',', $descendant_ids).")";
                        }
                    } else {
                        $this->where[] = $alias.".category_id = ".(int)$info['id'];
                    }

                    break;
                }
            }
            $this->setHash(implode('/', $hash));
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
            $alias = $this->addJoin('shop_set_products', null, ":table.set_id = '".$set_model->escape($id)."'");
            if (wa()->getEnv() == 'frontend' || !waRequest::get('sort') || waRequest::get('sort') == 'sort') {
                $this->order_by = $alias.'.sort ASC';
            }
        } else {
            if (!waRequest::get('sort') && !empty($set['rule'])) {
                $this->order_by = $set['rule'];
            }
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
        $this->where[] = "p.id IN (".implode(',', $ids).")";
    }

    /**
     * Collections /type/1
     *
     * @param int $id - type_id
     * @param bool $auto_title
     */
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

        $this->where[] = "p.type_id = ".(int)$id;
    }

    /**
     * Collections /tag/iphone or /tag/1
     *
     * @param int|string $id - tag_id or tag
     * @param bool $auto_title
     */
    protected function tagPrepare($id, $auto_title = true)
    {
        $tag_model = new shopTagModel();
        $tag = false;
        if (is_numeric($id)) {
            $tag = $tag_model->getById($id);
        }
        if (!$tag) {
            $tag = $tag_model->getByName($id);
            if ($tag) {
                $id = $tag['id'];
            }
        }
        if ($tag) {
            $this->addJoin('shop_product_tags', null, ':table.tag_id = '.(int)$id);
            if ($auto_title) {
                $this->addTitle(sprintf(_w('Tagged “%s”'), $tag['name']));
            }
        } else {
            $this->where[] = "0";
        }
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

        $this->where[] = 'p.id != '.(int)$product_id;
        $prev_where_count = count($this->where);

        $sum = array();

        foreach ($conditions as $row) {
            if ($row['feature'] == 'tag') {
                $tag_model = new shopTagModel();
                $tag = $tag_model->getByName($row['value']);
                if ($tag) {
                    $this->where[] = 'pt.tag_id = '.(int)$tag['id'];
                    $this->joins[] = array(
                        'table' => 'shop_product_tags',
                        'alias' => 'pt'
                    );
                }
                continue;
            } elseif ($row['feature'] == 'type_id') {
                if ($row['cond'] == 'same') {
                    $this->where[] = 'p.type_id = '.(int)$product['type_id'];
                } elseif ($row['cond'] == 'notsame') {
                    $this->where[] = 'p.type_id != '.(int)$product['type_id'];
                } elseif ($row['cond'] == 'is') {
                    $this->where[] = 'p.type_id = '.(int)$row['value'];
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
                    $min = $v * (float)(100 + $min) / 100;
                    $max = $v * (float)(100 + $max) / 100;
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
                        $this->addJoin('shop_product_features', null,
                            ":table.feature_id = ".(int)$row['feature_id']." AND :table.feature_value_id = ".(int)$row['value']);
                        $this->group_by = 'p.id';
                    }
                    break;
                case 'any':
                case 'all':
                    if ($model->fieldExists($row['feature'])) {
                        //$this->where[] = 'p.'.$row['feture']." = '".$model->escape($row['value'])."'";
                    } else {
                        if ($row['value']) {
                            $this->addJoin('shop_product_features', null,
                                ":table.feature_id = ".(int)$row['feature_id']." AND :table.feature_value_id IN (".$row['value'].")");
                            $this->group_by = 'p.id';
                        } else {
                            $this->where[] = '0';
                        }
                    }
                    break;
                case 'notsame':
                case 'same':
                    if ($model->fieldExists($row['feature'])) {
                        $this->where[] = 'p.'.$row['feature']." ".($row['cond'] == 'notsame' ? '!' : '')."= '".$model->escape($product->features[$row['feature']])."'";
                    } else {
                        $product_features_model = new shopProductFeaturesModel();
                        $rows = $product_features_model->getByField(array(
                            'product_id' => $product['id'],
                            'sku_id'     => null,
                            'feature_id' => $row['feature_id']
                        ), true);
                        if (!$rows) {
                            $feature_model = new shopFeatureModel();
                            $f = $feature_model->getById($row['feature_id']);
                            if ($f && preg_match('/^(2|3)d\./', $f['type'])) {
                                $sub_features = $feature_model->getByField('parent_id', $row['feature_id'], 'id');
                                if ($sub_features) {
                                    $rows = $product_features_model->getByField(array(
                                        'product_id' => $product['id'],
                                        'sku_id' => null,
                                        'feature_id' => array_keys($sub_features)
                                    ), true);
                                }
                            }
                        }
                        $values = array();
                        foreach ($rows as $r) {
                            $values[$r['feature_id']][] = $r['feature_value_id'];
                        }
                        if ($values) {
                            foreach ($values as $f_id => $f_values) {
                                $alias = $this->addJoin('shop_product_features');
                                $this->where[] = $alias . ".feature_id = " . $f_id;
                                $this->where[] = $alias . ".feature_value_id " .
                                    (count($f_values) == 1 ? ($row['cond'] == 'notsame' ? '!' : '') . "= " . $f_values[0] : ($row['cond'] == 'notsame' ? 'NOT ' : '') . "IN (" . implode(',', $f_values) . ")");
                            }
                            $this->group_by = 'p.id';
                        }
                    }
                    break;
            }
        }

        // When no filtering conditions were added, show no upselling for this product
        // instead of all products promiscuously
        if ($prev_where_count == count($this->where)) {
            $this->where[] = '0';
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
                $query_parts[] = str_replace('\&', '&', substr($query, $i, $j - $i));
                $i = $j + 1;
            }
            $offset = $j + 1;
        }
        $query_parts[] = str_replace('\&', '&', substr($query, $i));

        $model = $this->getModel();
        $title = array();
        foreach ($query_parts as $part) {
            if (!($part = trim($part))) {
                continue;
            }
            $parts = preg_split("/(\\\$=|\^=|\*=|==|!=|>=|<=|=|>|<)/uis", $part, 2, PREG_SPLIT_DELIM_CAPTURE);
            if ($parts) {
                if ($parts[0] == 'category_id') {
                    if ($parts[1] == '==' && $parts[2] == 'null') {
                        $this->where[] = 'p.category_id IS NULL';
                        $title[] = 'without category';
                    } else {
                        $this->addJoin('shop_category_products', null, ':table.category_id'.$this->getExpression($parts[1], $parts[2]));
                        $title[] = "category_id ".$parts[1].$parts[2];
                    }
                } elseif ($parts[0] == 'query') {
                    // simple search
                    if (!wa('shop')->getConfig()->getOption('search_smart')) {
                        $words = explode(' ', $parts[2]);
                        $alias = $this->addJoin('shop_product_skus');
                        foreach ($words as $w) {
                            $w = trim($w);
                            $w = $model->escape($w, 'like');
                            $this->where[] = "(p.name LIKE '%".$w."%' OR ".$alias.".sku LIKE '%".$w."%' OR ".$alias.".name LIKE '%".$w."%')";
                        }
                    }
                    // smart search
                    else {

                        // Following block of code might change the ordering
                        // as part of searching logic.
                        // Remember order-by in case we want to restore it later.
                        $auto_order_by = $this->order_by;

                        $search = new shopIndexSearch();
                        $word_ids = $search->getWordIds($parts[2], true);
                        if ($word_ids) {
                            $this->joins[] = array(
                                'table' => 'shop_search_index',
                                'alias' => 'si'
                            );
                            $this->where[] = 'si.word_id IN (' . implode(",", $word_ids) . ')';
                            if (count($word_ids) > 1) {
                                $this->fields[] = "SUM(si.weight) AS weight";
                                $this->fields[] = "COUNT(*) AS weight_count";
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
                            if ($this->is_frontend) {
                                if ($this->filtered) {
                                    $this->filtered = false;
                                }
                                $this->frontendConditions();
                            }
                            if (waRequest::request('sort', 'weight', 'string') == 'weight') {
                                $this->order_by = 'p.create_datetime DESC';
                            } else {
                                $this->order_by = $auto_order_by;
                            }
                            $q = $model->escape($parts[2], 'like');
                            $this->addJoin('shop_product_skus', null, "(p.name LIKE '%" . $q . "%' OR :table.name LIKE '%" . $q . "%' OR :table.sku LIKE '%" . $q . "%')");
                            $this->group_by = 'p.id';
                            return;
                        } elseif ($word_ids) {
                            $result = $this->getProducts('*', 0, 1);
                            $p = array_shift($result);
                            $w = str_replace(',', '.', 0.3 * $p['weight']);
                            if (count($word_ids) > 1) {
                                $this->having[] = 'SUM(si.weight) >= ' . $w;
                                if ($p['weight_count'] == count($word_ids)) {
                                    $this->having[] = 'COUNT(*) >= ' . count($word_ids);
                                }
                            } else {
                                $this->where[] = 'weight >= ' . $w;
                            }
                            $this->count = null;

                            // Restore original order-by if were specified
                            if (waRequest::request('sort', 'weight', 'string') != 'weight') {
                                $this->order_by = $auto_order_by;
                            }
                        }
                    }
                    $title[] = $parts[0].$parts[1].$parts[2];
                } elseif ($parts[0] == 'tag') {
                    $tag_model = $this->getModel('tag');
                    if (strpos($parts[2], '||') !== false) {
                        $tags = explode('||', $parts[2]);
                        $tag_ids = $tag_model->getIds($tags);
                    } else {
                        $sql = "SELECT id FROM ".$tag_model->getTableName()." WHERE name".$this->getExpression($parts[1], $parts[2]);
                        $tag_ids = $tag_model->query($sql)->fetchAll(null, true);
                    }
                    if ($tag_ids) {
                        $this->addJoin('shop_product_tags', null, ":table.tag_id IN ('".implode("', '", $tag_ids)."')");
                    } else {
                        $this->where[] = "0";
                    }
                } elseif ($model->fieldExists($parts[0])) {
                    $title[] = $parts[0].$parts[1].$parts[2];
                    $this->where[] = 'p.'.$parts[0].$this->getExpression($parts[1], $parts[2]);
                } elseif ($parts[1] == '=') {
                    $code = $parts[0];
                    $is_value_id = false;
                    if (substr($code, -9) == '.value_id') {
                        $code = substr($code, 0, -9);
                        $is_value_id = true;
                    }
                    $feature_model = $this->getModel('feature');
                    $f = $feature_model->getByCode($code);
                    if ($f) {
                        if ($is_value_id) {
                            $value_id = $parts[2];
                        } else {
                            $values_model = $feature_model->getValuesModel($f['type']);
                            $value_id = $values_model->getValueId($f['id'], $parts[2]);
                        }
                        $this->addJoin('shop_product_features', null, ':table.feature_id = '.$f['id'].' AND :table.feature_value_id = '.(int)$value_id);
                        $this->group_by = 'p.id';
                    }
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

    /**
     * Returns fields for SELECT clause
     *
     * @param string|array $fields
     * @return string
     */
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
                unset($fields[$i]);
                if (in_array($f, array('images', 'image', 'frontend_url', 'image_count', 'sales_30days', 'image_crop_small', 'stock_worth'))) {
                    $this->post_fields['_internal'][] = $f;
                } else if (substr($f, 0, 8) == 'feature_') {
                    $this->post_fields['_features'][substr($f, 8)] = $f;
                }
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

        if (waRequest::param('drop_out_of_stock')) {
            $fields[] = '(p.count > 0 || p.count IS NULL) AS in_stock';
        }

        return implode(",", $fields);
    }

    /**
     * Returns ORDER BY clause.
     *
     * @return string
     */
    protected function _getOrderBy()
    {
        if ($this->order_by) {
            return " ORDER BY ".(waRequest::param('drop_out_of_stock') ? 'in_stock DESC,' : '').$this->order_by;
        } else {
            return "";
        }
    }

    /**
     * Returns GROUP BY clause.
     *
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
     * Changes ORDER BY clause of product selection query.
     *
     * @param string|array $field Name of field in 'shop_product' table. Alternative value options:
     *     'sort' (ignored)
     *     'rand()' or 'RAND()' — sets 'RAND()' condition for ORDER BY clause
     *     array($field, $order) — in this case $order parameter is ignored
     * @param string $order 'ASC' or 'DESC' modifier
     * @return string New ORDER BY clause or empty string (for 'rand()' $field value)
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
                    if ($field == 'count') {
                        $this->fields[] = 'IF(p.count IS NULL, 1, 0) count_null';
                        return $this->order_by = 'count_null '.$order.', p.count '.$order;
                    } else if ($field == 'stock_worth') {
                        $this->fields[] = 'IFNULL(p.count, 0)*p.price AS stock_worth';
                        $this->order_by = 'stock_worth '.$order;
                    } else {
                        return $this->order_by = 'p.'.$field." ".$order;
                    }
                } else if ($field == 'sort') {
                    $this->getSQL();
                    return '';
                } else if (strtolower($field) == 'rand()') {
                    $this->getSQL();
                    $this->order_by = 'RAND()';
                    return '';
                }
            } else {
                $this->getSQL();
                return $this->order_by = $field.' '.$order;
            }
        }
        return '';
    }

    /**
     * Returns array of conditions used in ORDER BY clause.
     *
     * @return array
     */
    public function getOrderBy()
    {
        if (!$this->order_by) {
            return array();
        } else {
            $order = explode(',', $this->order_by);
            $order = explode(' ', trim(end($order)));
            if (!isset($order[1])) {
                $order[1] = 'ASC';
            }
            $order[0] = strtolower($order[0]);
            $order[1] = strtolower($order[1]);
            $k = strpos($order[0], '.');
            if ($k !== false) {
                $order[0] = substr($order[0], $k + 1);
            }
            return $order;
        }
    }

    /**
     * Returns product selection SQL query
     *
     * @return string
     */
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

    /**
     * Returns number of products included in collection.
     *
     * @return int
     */
    public function count()
    {
        if ($this->count !== null) {
            return $this->count;
        }
        $sql = $this->getSQL();

        if ($this->having) {
            $sql .= $this->_getGroupBy();
            $sql .= " HAVING ".implode(' AND ', $this->having);
            $sql = "SELECT COUNT(*) FROM (SELECT * ".$sql.") AS t";
        } else {
            $sql = "SELECT COUNT(".($this->joins ? 'DISTINCT ' : '')."p.id) ".$sql;
        }
        $count = (int)$this->getModel()->query($sql)->fetchField();

        if ($this->hash[0] == 'category' && !empty($this->info['id']) && $this->info['type'] == shopCategoryModel::TYPE_DYNAMIC) {
            if ($this->info['count'] != $count && !$this->is_frontend) {
                $this->getModel('category')->updateById($this->hash[1], array('count' => $count));
            }
        } elseif ($this->hash[0] == 'set' && !empty($this->info['id'])) {
            if ($this->info['type'] == shopSetModel::TYPE_DYNAMIC) {
                $count = min($this->info['count'], $count);
            } elseif ($this->info['count'] != $count && !$this->is_frontend) {
                $this->getModel('set')->updateById($this->hash[1], array('count' => $count));
            }
        }
        return $this->count = $count;
    }

    /**
     * Returns array of products included in collection.
     *
     * @param string $fields List of product properties, comma-separated, to be included in returned array
     * @param int $offset Initial position in returned product array, 0 means first product in collection
     * @param int|bool $limit Maximum product limit.
     *     If a Boolean value is specified, then $escape = $limit and $limit = null
     *     If no value is specified, then $limit = 0.
     *     If no value is specified and $offset is non-zero, then $limit = $offset and $offset = 50
     * @param bool $escape Whether product names and urls must be escaped using htmlspecialchars() function, defaults to true
     *
     * @return array Array of collection products' sub-arrays
     */
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
        if ($this->is_frontend && $fields == '*') {
            $fields .= ',frontend_url';
        }
        $split_fields = array_map('trim', explode(',', $fields));
        if (in_array('frontend_url', $split_fields) && !in_array('*', $split_fields)) {
            if ($dependent_fields = array_diff(array('url', 'category_id',), $split_fields)) {
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
        $sql .= " LIMIT ".($offset ? $offset.',' : '').(int)$limit;

        $data = $this->getModel()->query($sql)->fetchAll('id');
        if (!$data) {
            return array();
        }
        $this->workupProducts($data, $escape);
        return $data;
    }

    private function workupProducts(&$products = array(), $escape)
    {
        if (empty($products)) {
            return;
        }

        // Round prices for products
        $default_currency = wa('shop')->getConfig()->getCurrency(true);
        if ($this->is_frontend) {
            $frontend_currency = wa('shop')->getConfig()->getCurrency(false);
            !empty($this->options['round_prices']) && shopRounding::roundProducts($products);
        }
        $rounding = array(
            'price', 'min_price', 'max_price', 'compare_price'
        );

        // Names of fields that must be converted to float values
        $float = array(
            'min_price',
            'max_price',
            'total_sales',
            'base_price_selectable',
            'rating',
            'price',
            'compare_price',
        );

        $fetch_params = !empty($this->options['params']) || (!empty($this->post_fields['_internal']) && in_array('params', $this->post_fields['_internal']));

        foreach ($products as &$p) {
            foreach ($float as $field) {
                if (isset($p[$field])) {
                    $p[$field] = (float)$p[$field];
                }
            }

            if (isset($p['total_sales'])) {
                $p['total_sales_html'] = '';
                if (!empty($p['total_sales'])) {
                    $p['total_sales_html'] = '<span class="nowrap">'.
                        shop_currency_html($p['total_sales'], $default_currency, $default_currency).
                    '</span>';
                }
            }

            if (isset($p['rating'])) {
                $p['rating_html'] = '<span class="rate nowrap" title="'.htmlspecialchars(sprintf_wp('Average user rating: %s / 5', $p['rating'])).'">'.
                    shopHelper::getRatingHtml($p['rating'], 10, true).
                '</span>';
            }

            // escape
            if ($escape) {
                if (isset($p['name'])) {
                    $p['name'] = htmlspecialchars($p['name']);
                }
                if (isset($p['url'])) {
                    $p['url'] = htmlspecialchars($p['url']);
                }
            }

            // Make sure array exists for all products
            if ($fetch_params) {
                $p['params'] = array();
            }

            if ($this->is_frontend) {
                // Striked-out price can not be lower than actual price
                if ($p['compare_price'] && $p['compare_price'] <= $p['price']) {
                    $p['compare_price'] = 0;
                }

                if (empty($this->options['round_prices'])) {
                    // Add the 'frontend_*' and 'unconverted_*' keys anyway
                    foreach($rounding as $k) {
                        $p['unconverted_'.$k] = $p[$k];
                        $p['frontend_'.$k] = shop_currency($p[$k], $default_currency, $frontend_currency, false);
                    }
                }
            }
        }
        unset($p);

        // Fetch params
        if ($fetch_params) {
            $product_params_model = new shopProductParamsModel();
            $rows = $product_params_model->getByField('product_id', array_keys($products), true);
            foreach ($rows as $row) {
                $products[$row['product_id']]['params'][$row['name']] = $row['value'];
            }
        }

        // Get 'category_url' for each product
        if ($this->is_frontend && waRequest::param('url_type') == 2) {
            $cat_ids = array();
            foreach ($products as &$p) {
                if (!empty($p['category_id'])) {
                    $cat_ids[] = $p['category_id'];
                }
            }
            $cat_ids = array_unique($cat_ids);
            if ($cat_ids) {
                $categories = $this->getModel('category')->getById($cat_ids);
                foreach ($products as &$p) {
                    if (!empty($p['category_id'])) {
                        $p['category_url'] = $categories[$p['category_id']]['full_url'];
                    }
                }
                unset($p);
            }
        }

        if ($this->post_fields) {

            $unprocessed = $this->post_fields;

            if (!empty($unprocessed['_internal'])) {
                $fields = array_fill_keys($unprocessed['_internal'], true);
                unset($unprocessed['_internal']);

                if (isset($fields['images'])) {
                    foreach ($products as &$p) {
                        $p['images'] = array();
                    }
                    unset($p);
                    $product_images_model = new shopProductImagesModel();
                    $product_images = $product_images_model->getImages(array_keys($products), 'thumb', 'product_id');
                    foreach ($product_images as $product_id => $images) {
                        $products[$product_id]['images'] = $images;
                    }
                }
                if (isset($fields['image'])) {
                    $thumb_size = wa('shop')->getConfig()->getImageSize('thumb');
                    $big_size = wa('shop')->getConfig()->getImageSize('big');
                    foreach ($products as &$p) {
                        if ($p['image_id']) {
                            $tmp = array('id' => $p['image_id'], 'product_id' => $p['id'],
                                'filename' => $p['image_filename'], 'ext' => $p['ext']);
                            $p['image']['thumb_url'] = shopImage::getUrl($tmp, $thumb_size, isset($this->options['absolute']) ? $this->options['absolute'] : false);
                            $p['image']['big_url'] = shopImage::getUrl($tmp, $big_size, isset($this->options['absolute']) ? $this->options['absolute'] : false);
                        }
                    }
                    unset($p);
                }
                if (isset($fields['image_crop_small'])) {
                    $size = wa('shop')->getConfig()->getImageSize('crop_small');
                    foreach ($products as &$p) {
                        if ($p['image_id']) {
                            $tmp = array('id' => $p['image_id'], 'product_id' => $p['id'],
                                'filename' => $p['image_filename'], 'ext' => $p['ext']);
                            $p['image_crop_small'] = shopImage::getUrl($tmp, $size, isset($this->options['absolute']) ? $this->options['absolute'] : false);
                        }
                    }
                    unset($p);
                }
                if (isset($fields['image_count'])) {
                    if (isset($fields['images'])) {
                        foreach ($products as &$p) {
                            $p['image_count'] = count($p['images']);
                        }
                    } else {
                        $product_images_model = new shopProductImagesModel();
                        foreach($product_images_model->countImages(array_keys($products)) as $product_id => $count) {
                            isset($products[$product_id]) && ($products[$product_id]['image_count'] = $count);
                        }
                    }
                }
                if (isset($fields['frontend_url'])) {
                    foreach ($products as &$p) {
                        $route_params = array('product_url' => $p['url']);
                        if (isset($p['category_url'])) {
                            $route_params['category_url'] = $p['category_url'];
                        } elseif (isset($this->info['hash']) && $this->info['hash'] == 'category' && !$this->info['type']) {
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
                if (isset($fields['sales_30days'])) {
                    $default_currency = wa('shop')->getConfig()->getCurrency(true);
                    $sql = "SELECT product_id, SUM(oi.price*oi.quantity*o.rate)
                            FROM shop_order_items AS oi
                                JOIN shop_order AS o
                                    ON oi.order_id=o.id
                            WHERE oi.product_id IN (?)
                                AND oi.type='product'
                                AND o.paid_date >= ?
                            GROUP BY product_id";
                    $sales = $this->getModel()->query($sql, array(array_keys($products), date('Y-m-d', time() - 3600*24*30)))->fetchAll('product_id', true);
                    foreach ($products as &$p) {
                        $p['sales_30days'] = (float) ifset($sales[$p['id']], 0.0);
                        $p['sales_30days_html'] = empty($p['sales_30days']) ? '' : '<span class="nowrap">'.
                            shop_currency_html($p['sales_30days'], $default_currency, $default_currency).
                        '</span>';
                    }
                    unset($p);
                }
                if (isset($fields['stock_worth'])) {
                    $default_currency = wa('shop')->getConfig()->getCurrency(true);
                    $sql = "SELECT s.product_id, SUM(s.primary_price*s.count) AS net_worth
                            FROM shop_product_skus AS s
                            WHERE s.product_id IN (?)
                            GROUP BY s.product_id";
                    $stock_worth = $this->getModel()->query($sql, array(array_keys($products)))->fetchAll('product_id', true);
                    foreach ($products as &$p) {
                        $p['stock_worth'] = (float) ifset($stock_worth[$p['id']], 0.0);
                        $p['stock_worth_html'] = empty($p['stock_worth']) ? '' : '<span class="nowrap">'.
                            shop_currency_html($p['stock_worth'], $default_currency, $default_currency).
                        '</span>';
                    }
                    unset($p);
                }
            }

            // features
            if (!empty($unprocessed['_features'])) {
                $feature_ids = array_keys($unprocessed['_features']);
                unset($unprocessed['_features']);

                // product_id => feature_id => array(value => ..., value_html => ...)
                $feature_values = array();

                $feature_model = new shopFeatureModel();
                $features = $feature_model->getByField('id', $feature_ids, 'id');
                if ($features) {

                    // Get feature_value_ids for all products
                    $sql = "SELECT pf.*
                            FROM shop_product_features AS pf
                            WHERE pf.product_id IN (?)
                                AND pf.feature_id IN (?)";
                    $product_features = $this->getModel()->query($sql, array(
                        array_keys($products),
                        $feature_ids,
                    ));

                    // Prepare list of value_ids to fetch later, and places to fetch them from
                    $storages = array(); // feature type => feature_value_id => list of product_ids
                    foreach ($product_features as $row) {
                        $f = $features[$row['feature_id']];
                        $type = preg_replace('/\..*$/', '', $f['type']);
                        if ($type == shopFeatureModel::TYPE_BOOLEAN) {
                            /** @var shopFeatureValuesBooleanModel $model */
                            $model = shopFeatureModel::getValuesModel($type);
                            $values = $model->getValues('id', $row['feature_value_id']);
                            $feature_values[$row['product_id']][$row['feature_id']]['value'] = reset($values);
                        } elseif ($type == shopFeatureModel::TYPE_DIVIDER) {
                            // ignore dividers
                        } else {
                            $storages[$type][$row['feature_value_id']][$row['product_id']] = true;
                        }
                    }

                    // Fetch actual values from shop_feature_values_* tables
                    foreach ($storages as $type => $value_products) {
                        $model = shopFeatureModel::getValuesModel($type);
                        foreach ($model->getValues('id', array_keys($value_products)) as $feature_id => $values) {
                            if (isset($features[$feature_id])) {
                                $f = $features[$feature_id];
                                foreach($values as $value_id => $value) {
                                    foreach(array_keys($value_products[$value_id]) as $product_id) {
                                        if (!empty($f['multiple'])) {
                                            $feature_values[$product_id][$feature_id]['value'][] = $value;
                                        } else {
                                            $feature_values[$product_id][$feature_id]['value'] = $value;
                                        }
                                    }
                                }
                            }
                        }
                    }

                    // Prepare value_html for each feature value
                    foreach($feature_values as &$fv) {
                        foreach($fv as $feature_id => &$arr) {
                            if (is_array($arr['value'])) {
                                $arr['value_html'] = join(', ', $arr['value']);
                            } else {
                                $arr['value_html'] = (string) $arr['value'];
                            }
                        }
                    }
                    unset($fv, $arr);
                }

                // Finally, assign feature data to actual products
                foreach ($products as &$p) {
                    foreach($feature_ids as $fid) {
                        $p['feature_'.$fid] = ifset($feature_values[$p['id']][$fid]['value']);
                        $p['feature_'.$fid.'_html'] = ifset($feature_values[$p['id']][$fid]['value_html'], ifempty($p['feature_'.$fid], ''));
                    }
                }
                unset($p);
            }

            // Throw event if there's something left to fetch
            if ($unprocessed) {
                // !!!
            }
        }
    }

    /**
     * @return shopProductModel
     */
    protected function getModel($name = 'product')
    {
        if (!isset($this->models[$name])) {
            if (in_array($name, array('product', 'category', 'tag', 'set', 'feature'))) {
                $class_name = 'shop'.ucfirst($name).'Model';
                $this->models[$name] = new $class_name();
            }
        }
        return $this->models[$name];
    }

    /**
     * Returns collection title.
     *
     * @return string
     */
    public function getTitle()
    {
        if ($this->title === null) {
            $this->prepare();
        }
        return $this->title;
    }

    /**
     * Returns various useful information about collection.
     *
     * @return array
     */
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
     * Adds a GROUP BY clause to product selection query.
     *
     * @param string $clause
     * @return self
     */
    public function groupBy($clause)
    {
        $this->group_by = $clause;
        return $this;
    }

    /**
     * Adds a WHERE condition to product selection query.
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
     * Adds a simple JOIN clause to product selection query.
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
        if (is_array($table)) {
            if (isset($table['on'])) {
                $on = $table['on'];
            }
            if (isset($table['where'])) {
                $where = $table['where'];
            }
            $table = $table['table'];
        }
        $t = explode('_', $table);
        $alias = '';
        foreach ($t as $tp) {
            if ($tp == 'shop') {
                continue;
            }
            $alias .= substr($tp, 0, 1);
        }

        if (!$alias) {
            $alias = $table;
        }

        if (!isset($this->join_index[$alias])) {
            $this->join_index[$alias] = 1;
        } else {
            $this->join_index[$alias]++;
        }
        $alias .= $this->join_index[$alias];

        $join = array(
            'table' => $table,
            'alias' => $alias,
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
     * Returns collection hash.
     *
     * @return string
     */
    public function getHash()
    {
        return $this->hash;
    }

    /**
     * Returns value ids of product's features
     *
     * @return array
     * array(
     *     feature1_id => array(feature1_value1_id, feature1_value2_id, ...),
     *     feature2_id => array(feature2_value1_id, feature2_value2_id, ...),
     *     ...
     * )
     */
    public function getFeatureValueIds()
    {
        $this->prepare();
        // add join
        $alias = $this->addJoin('shop_product_features');
        $sql = $this->getSQL();
        // remove join
        unset($this->joins[count($this->joins) - 1]);
        $sql = 'SELECT DISTINCT '.$alias.'.feature_id, '.$alias.'.feature_value_id '.$sql;
        $rows = $this->getModel()->query($sql);
        if (!$rows) {
            return array();
        }
        $result = array();
        foreach ($rows as $row) {
            $result[$row['feature_id']][] = $row['feature_value_id'];
        }
        return $result;
    }

    /**
     * Returns collection's minimum and maximum product prices
     *
     * @return array
     * array(
     *    'min' => MIN PRICE,
     *    'max' => MAX PRICE
     * )
     */
    public function getPriceRange()
    {
        $sql = $this->getSQL();
        $sql = "SELECT MIN(p.min_price) min, MAX(p.max_price) max ".$sql;
        $data = $this->getModel()->query($sql)->fetch();
        return array(
            'min' => (double)(isset($data['min']) ? $data['min'] : 0),
            'max' => (double)(isset($data['max']) ? $data['max'] : 0)
        );
    }
}

