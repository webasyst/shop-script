<?php

class shopProductsCollection
{
    protected $hash;
    protected $info = [];

    protected $options = [];
    protected $prepared = false;
    protected $prepared_event = false;
    protected $filtered = false;
    protected $filtered_by_features = [];
    protected $title;

    protected $fields = [];
    protected $where;
    protected $having = [];
    protected $count;
    protected $order_by = 'p.create_datetime DESC, p.id';
    protected $group_by;
    protected $joins;
    protected $join_index = [];

    protected $promo_skus;
    protected $promo_prices;

    protected $post_fields = [];

    protected $models = [];
    protected $is_frontend;
    protected $storefront_context;
    protected $completed_events = [];

    /**
     * Map of join => alias
     * Need for optimization - skip same JOIN with the same ON and WHERE that've been already processed.
     * @var array
     */
    protected $unique_joins = [];

    /**
     * Creates a new product collection.
     *
     * @param string|array $hash Product selection conditions. Examples:
     *     array(12,23,34) or 'id/12,23,34' — explicitly specified product ids
     *     'related/cross_selling/12' — cross-selling items for product with id = 12
     *     'related/upselling/23' — upselling items for product with id = 23
     *     'category/208' — search by category id
     *     'search/query=style' — search results by query 'style'
     *     'search/tag=style' — search results by tag 'style'
     *     'tag/style' — alternative form of search by tag
     *     'search/type_id=1' — search results by any field of shop_product table; e.g., type_id
     *     'search/sku_id=1,2,42' - search by specified sku_ids, use field 'skus_filtered' to get exact skus only
     *     'search/category_id=1,2,42' - search by product's primary category_ids
     *     'type/2' — search by type_id
     *     'search/name=SuperProduct' — search by 'name' field (exact match)
     *     'search/color.value_id=6' — search by value with id=6 of 'checkboxes'-type feature with code 'color'
     *     'filter/6' - search for all products matching the filter
     *     'presentation/42' - search by saved view's filter settings specified in the backend
     * @param array $options Extra options:
     *     'filters'    => whether products must be filtered according to GET request conditions
     *     'product'    => shopProduct object to select upselling items for; for upselling-type collections only
     *     'conditions' => upselling items selection conditions; for upselling-type collections only
     *     'params'     => whether extra product params must be included in collection products
     *     'absolute'   => whether absolute product image URLs must be returned for collection products
     *     'filter_by_rights' => (bool|string), filter products by rights
     *        - TRUE: leave products with right level to type >= shopRightsConfig::RIGHT_EDIT
     *        - FALSE: not check rights
     *        - int: min right level to product type (see shopRightsConfig::RIGHT_* constants). Compare will be by '>=' logic
     *        - 'delete': if right level to product type is shopRightsConfig::RIGHT_EDIT it also check contact_id of product. User can delete own products if level is shopRightsConfig::RIGHT_EDIT
     * @throws waException
     */
    public function __construct($hash = '', $options = array())
    {
        $env_is_frontend = wa()->getEnv() == 'frontend';

        if (!array_key_exists('storefront_context', $options) && $env_is_frontend) {
            $routing_url = wa()->getRouting()->getRootUrl();
            $storefront = wa()->getConfig()->getDomain().($routing_url ? '/'.$routing_url : '');
            $options['storefront_context'] = $storefront;
        }
        $this->setOptions($options);

        if ($this->is_frontend === null) {
            $this->is_frontend = $env_is_frontend;
        }
        if (!isset($this->options['round_prices'])) {
            $this->options['round_prices'] = $this->is_frontend;
        }
        if ($this->is_frontend && !array_key_exists('defrac_counts', $this->options)) {
            $this->options['defrac_counts'] = true;
        }

        $this->setHash($hash);

        if (empty($this->options['no_plugins'])) {
            /**
             * @event products_collection.filter
             * @param array shopProductsCollection $this
             */
            wa('shop')->event('products_collection.filter', $this);
        }
    }

    /**
     * Extra collection options
     * @param array $options
     * @see __construct()
     *
     */
    public function setOptions($options)
    {
        foreach ($options as $k => $v) {
            $this->options[$k] = $v;
        }
        if (isset($this->options['frontend'])) {
            $this->is_frontend = $this->options['frontend'];
        }
        if (array_key_exists('storefront_context', $options) && (is_null($options['storefront_context']) || is_scalar($options['storefront_context']))) {
            $this->storefront_context = $options['storefront_context'];
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
        if ($this->prepared && !$add) {
            return;
        }

        $type = $this->hash[0];
        if ($this->is_frontend) {
            $this->frontendConditions();
        }

        if (isset($this->options['filter_by_rights']) && $this->options['filter_by_rights'] !== false) {
            $this->addWhereByRights($this->options['filter_by_rights']);
        }
        if (isset($this->options['exclude_products']) && is_array($this->options['exclude_products'])) {
            $this->excludeProducts($this->options['exclude_products']);
        }

        $sort = waRequest::get('sort');
        if (!$sort && isset($this->options['sort'])) {
            $sort = $this->options['sort'];
        }
        if ($sort) {
            if ($sort == 'stock') {
                $sort = 'count';
            }
            $order = waRequest::get('order');
            if (!$order && isset($this->options['order'])) {
                $order = $this->options['order'];
            }
            if ($order == 'desc') {
                $order = 'DESC';
            } else {
                $order = 'ASC';
            }
            // special fields for sorting in the backend
            $virtual_fields = array_intersect(['minimum_price', 'minimum_base_price'], (array)$sort);
            $additional_tables = array_intersect(['type_id', 'tax_id', 'category_id'], (array)$sort);
            $stock_field = $this->getStockField($sort);
            $feature_field = $this->getFeatureField($sort);

            $model = $this->getModel();

            if (count($virtual_fields) === 1) {
                $sort_field = reset($virtual_fields);
                $actual_joins = $this->getJoinsByTableName('shop_product_skus');
                if ($actual_joins) {
                    $skus_alias = $actual_joins[0]['alias'];
                } else {
                    $skus_alias = $this->addJoin('shop_product_skus', ':table.product_id = p.id');
                }
                // Build the condition for regular prices:
                $additional_query = $sort_field == 'minimum_base_price' ? "/ IFNULL($skus_alias.stock_base_ratio, p.stock_base_ratio)" : '';
                $order_by = ["MIN($skus_alias.primary_price $additional_query) $order"];
                $this->group_by = $this->addRemainingSortFields($sort, $sort_field);
                $this->order_by = $this->addRemainingSortFields($sort, $sort_field, $order_by, $order);
            } elseif ($sort == 'price' || $sort == 'base_price') {
                if (!isset($this->join_index['ps'])) {
                    $this->order_by = "p.$sort $order, p.id";
                    $this->addPromoPriceSort($sort, $order);
                } else {
                    // Build the condition for regular prices:
                    $additional_query = $sort == 'base_price' ? '/ IFNULL(ps1.stock_base_ratio, p.stock_base_ratio)' : '';
                    $main_sku_price = "MAX(IF(ps1.id = p.sku_id, ps1.primary_price $additional_query, -1))";
                    $main_sku_exists = '-1 < '.$main_sku_price;
                    $sku_price = "MIN(ps1.primary_price $additional_query)";
                    // We use main SKU price if it's not filtered out;
                    // otherwise min of all remaining SKU prices.
                    $price_sort = "IF($main_sku_exists, $main_sku_price, $sku_price)";

                    $this->order_by = "$price_sort $order, p.id";

                    // Check promo prices
                    $this->loadPromoPrices();
                    if (!empty($this->storefront_context) && !empty($this->promo_prices[$this->storefront_context])) {
                        // If we have promo-prices, and the filter is on the product_skus - this is a disaster :((((
                        $promo_prices_tmp_alias = $this->getPromoPricesTmpAlias();
                        if (!$promo_prices_tmp_alias) {
                            // To get started, add join to the temporary table with promo-prices.
                            $promo_prices_tmp_alias = $this->addJoin([
                                'table' => 'shop_product_promo_price_tmp',
                                'type'  => 'LEFT',
                                'on'    => "p.id = :table.product_id AND ps1.id = :table.sku_id AND :table.storefront = '".$this->getModel()->escape($this->storefront_context)."'",
                            ]);
                        }

                        // Build a sorting condition for promo-prices
                        if ($sort == 'price') {
                            $promo_main_sku_price = "MAX(IF({$promo_prices_tmp_alias}.sku_id = p.sku_id, {$promo_prices_tmp_alias}.primary_price, -1))";
                            $promo_sku_price = "MIN({$promo_prices_tmp_alias}.primary_price)";
                        } else {
                            $promo_main_sku_price = "MAX(IF({$promo_prices_tmp_alias}.sku_id = p.sku_id, {$promo_prices_tmp_alias}.primary_price / IFNULL({$promo_prices_tmp_alias}.stock_base_ratio, p.stock_base_ratio), -1))";
                            $promo_sku_price = "MIN({$promo_prices_tmp_alias}.primary_price / IFNULL({$promo_prices_tmp_alias}.stock_base_ratio, p.stock_base_ratio))";
                        }
                        $promo_main_sku_exists = '-1 < '.$promo_main_sku_price;
                        $promo_price = "IF($promo_main_sku_exists, $promo_main_sku_price, $promo_sku_price)";
                        $promo_price_exists = '-1 < '.$promo_price; // In SQL, we first make sure that we have promo-prices

                        // Build one big sort condition:
                        $this->order_by = "IF($promo_price_exists, $promo_price, $price_sort) $order, p.id";
                    }
                }
            } elseif ($sort == 'stock_worth') {

                $this->fields['order_by'] = 'IFNULL(p.count, 0)*p.price AS stock_worth';
                $this->order_by = 'stock_worth '.$order.', p.id';
            } elseif ($sort === 'sku' || $sort === 'purchase_price') {
                $actual_joins = $this->getJoinsByTableName('shop_product_skus');
                if ($actual_joins) {
                    $skus_alias = $actual_joins[0]['alias'];
                } else {
                    $skus_alias = $this->addJoin('shop_product_skus', ':table.product_id = p.id');
                }
                $this->order_by = "{$skus_alias}.{$sort} {$order}, p.id";
            } elseif (count($additional_tables) === 1) {
                $sort_field = reset($additional_tables);
                $table_name = str_replace('_id', '', $sort_field);
                $table_join = $this->getJoinsByTableName('shop_'.$table_name);
                if ($table_join) {
                    $table_alias = $table_join[0]['alias'];
                } else {
                    $table_alias = $this->addJoin([
                        'table' => 'shop_'.$table_name,
                        'type' => 'LEFT',
                        'on' => ':table.id = p.'.$sort_field
                    ]);
                }
                $order_by = ["{$table_alias}.name {$order}"];
                $this->order_by = $this->addRemainingSortFields($sort, $sort_field, $order_by, $order);
            } elseif (in_array('image_count', (array)$sort)) {
                $actual_joins = $this->getJoinsByTableName('shop_product_images');
                if ($actual_joins) {
                    $images_alias = $actual_joins[0]['alias'];
                } else {
                    $images_alias = $this->addJoin('shop_product_images', ':table.product_id = p.id');
                }
                $order_by = ["COUNT($images_alias.product_id) $order"];
                $this->group_by = $this->addRemainingSortFields($sort, 'image_count');
                $this->order_by = $this->addRemainingSortFields($sort, 'image_count', $order_by, $order);
            } elseif (in_array('sales_30days', (array)$sort)) {
                $actual_items_join = $this->getJoinsByTableName('shop_order_items');
                if ($actual_items_join) {
                    $items_alias = $actual_items_join[0]['alias'];
                } else {
                    $items_alias = $this->addJoin([
                        'table'=> 'shop_order_items',
                        'on' => ":table.product_id = p.id AND :table.type = 'product'",
                        'type' => 'LEFT'
                    ]);
                }
                $actual_order_join = $this->getJoinsByTableName('shop_order');
                if ($actual_order_join) {
                    $order_alias = $actual_order_join[0]['alias'];
                } else {
                    $order_alias = $this->addJoin([
                        'table' => 'shop_order',
                        'on' => ":table.id = $items_alias.order_id AND :table.paid_date >= '" . date('Y-m-d', time() - 3600 * 24 * 30) . "'",
                        'type' => 'LEFT'
                    ]);
                }
                $order_by = ["SUM($items_alias.price * $items_alias.quantity * $order_alias.rate) $order"];
                $this->group_by = $this->addRemainingSortFields($sort, 'sales_30days');
                $this->order_by = $this->addRemainingSortFields($sort, 'sales_30days', $order_by, $order);
            } elseif ($stock_field) {
                $actual_stocks_join = $this->getJoinsByTableName('shop_product_stocks');
                if ($actual_stocks_join) {
                    $stocks_alias = $actual_stocks_join[0]['alias'];
                } else {
                    $stocks_alias = $this->addJoin([
                        'table'=> 'shop_product_stocks',
                        'on' => ':table.product_id = p.id AND :table.stock_id = ' . $stock_field['stock_id'],
                        'type' => 'LEFT'
                    ]);
                }
                $order_by = ["$stocks_alias.count $order"];
                $this->order_by = $this->addRemainingSortFields($sort, $stock_field['field'], $order_by, $order);
            } elseif ($feature_field) {
                $product_features_joins = $this->getJoinsByTableName('shop_product_features');
                $product_features_aliases = [];
                if ($product_features_joins) {
                    foreach ($product_features_joins as $product_feature_join) {
                        $product_features_aliases[] = $product_feature_join['alias'];
                    }
                    if (count($product_features_aliases) != count($feature_field['feature_ids'])) {
                        return;
                    }
                } else {
                    foreach ($feature_field['feature_ids'] as $feature_id) {
                        $product_features_aliases[] = $this->addJoin([
                            'table'=> 'shop_product_features',
                            'on' => ":table.product_id = p.id AND :table.feature_id = $feature_id AND :table.sku_id IS NULL",
                            'type' => 'LEFT'
                        ]);
                    }
                }
                $order_fields = [];
                if ($feature_field['table_name'] == 'shop_product_features') {
                    $order_by = ["$product_features_aliases[0].{$feature_field['sort_field']} $order"];
                } else {
                    $actual_feature_joins = $this->getJoinsByTableName($feature_field['table_name']);
                    $feature_aliases = [];
                    if ($actual_feature_joins) {
                        foreach ($actual_feature_joins as $actual_feature_join) {
                            $feature_aliases[] = $actual_feature_join['alias'];
                        }
                        if (count($feature_aliases) != count($product_features_aliases)) {
                            return;
                        }
                    } else {
                        foreach ($product_features_aliases as $product_features_alias) {
                            $feature_aliases[] = $this->addJoin([
                                'table' => $feature_field['table_name'],
                                'on' => ":table.id = $product_features_alias.feature_value_id",
                                'type' => 'LEFT'
                            ]);
                        }
                    }
                    if (count($feature_aliases) > 1) {
                        foreach ($feature_aliases as $feature_alias) {
                            $order_fields[] = "$feature_alias.{$feature_field['sort_field']}";
                        }
                        $order_by = ["(" . implode(' * ', $order_fields) . ") $order"];
                    } else {
                        $order_by = ["$feature_aliases[0].{$feature_field['sort_field']} $order"];
                    }
                }
                $this->group_by = $this->addRemainingSortFields($sort, $feature_field['field'], $order_fields);
                $this->order_by = $this->addRemainingSortFields($sort, $feature_field['field'], $order_by, $order);
            } else {
                $order_by = array();
                $fields = array();
                foreach ((array)$sort as $_id => $_sort) {
                    $_sort = trim((string)$_sort);
                    if ($model->fieldExists($_sort)) {
                        $order_by[$_id] = 'p.'.$_sort;
                        $order_by[$_id] .= ' '.$order;
                        if ($_sort == 'count') {
                            $fields[$_id] = 'IF(p.count IS NULL, 1, 0) count_null';
                            $order_by[$_id] = 'count_null '.$order.', '.$order_by[$_id];
                        }
                    }
                }
                if ($order_by) {
                    if (!in_array('id', (array)$sort)) {
                        $order_by[] = 'p.id';
                    }
                    $this->order_by = implode(', ', $order_by);
                }
                if ($fields) {
                    $this->fields['order_by'] = implode(', ', $fields);
                }
            }
        }
        if (isset($this->options['prepare_filter']) && is_numeric($this->options['prepare_filter'])) {
            $this->filterPrepare($this->options['prepare_filter']);
        }
        if ($type) {
            $method = strtolower($type).'Prepare';
            if (method_exists($this, $method)) {
                $this->$method(isset($this->hash[1]) ? $this->hash[1] : '', $auto_title);
            } else {
                if (empty($this->options['no_plugins']) && empty($this->options['no_plugins_products_collection'])) {
                    /**
                     * @event products_collection
                     * @param array [string]mixed $params
                     * @param array [string]shopProductsCollection $params['collection']
                     * @param array [string]boolean $params['auto_title']
                     * @param array [string]boolean $params['add']
                     * @return bool null if ignored, true when something changed in the collection
                     */
                    $this->prepared = true;
                    $processed = wa('shop')->event('products_collection', ref(array(
                        'collection' => $this,
                        'auto_title' => $auto_title,
                        'add'        => $add,
                    )));
                }
                if (empty($processed)) {
                    throw new waException(sprintf_wp('Unknown collection hash type: “%s”.', htmlspecialchars($type)));
                } else {
                    $this->completed_events['products_collection'] = $processed;
                }
            }
        } else {
            if ($auto_title) {
                $this->addTitle(_w('All products'));
            }
        }

        $this->prepared = true;
    }

    /**
     * @param string|array $sort
     * @return array
     */
    protected function getStockField($sort)
    {
        $stock = [];
        foreach ((array)$sort as $item) {
            if (strpos($item, 'stocks_') === 0) {
                $stock_id = substr($item, 7);
                if (is_numeric($stock_id)) {
                    $stock = [
                        'field' => $item,
                        'stock_id' => (int)$stock_id,
                    ];
                }
            }
        }

        return $stock;
    }


    /**
     * @param string|array $sort
     * @return array
     */
    protected function getFeatureField($sort)
    {
        $field = [];
        foreach ((array)$sort as $item) {
            if (strpos($item, 'feature_') === 0) {
                $feature_id = substr($item, 8);
                if (is_numeric($feature_id)) {
                    $feature_model = new shopFeatureModel();
                    $features = $feature_model->where("`multiple` = 0 AND `type` NOT IN ('color', 'divider') AND (`id` = i:main_id OR `parent_id` = i:main_id)", [
                        'main_id' => $feature_id,
                    ])->order('parent_id, id')->fetchAll('id');
                    if ($features) {
                        $feature = array_shift($features);
                        $feature_type = preg_replace('/^(2d\.|3d\.)?(\w*)(\..*)?/', '$2', $feature['type']);
                        if ($feature_type) {
                            $model = 'shopFeatureValues' . ucfirst($feature_type) . 'Model';
                            if (class_exists($model)) {
                                if ($model == 'shopFeatureValuesBooleanModel') {
                                    $table_name = 'shop_product_features';
                                    $sort_field = 'feature_value_id';
                                } else {
                                    $feature_values_model = new $model();
                                    $table_name = $feature_values_model->getTableName();
                                    $sort_field = 'value';
                                    if ($table_name == 'shop_feature_values_dimension') {
                                        $sort_field = 'value_base_unit';
                                    } elseif ($table_name == 'shop_feature_values_range') {
                                        $sort_field = 'begin_base_unit';
                                    }
                                }
                                $field = [
                                    'field' => $item,
                                    'feature_ids' => $features ? array_column($features, 'id') : [(int)$feature['id']],
                                    'table_name' => $table_name,
                                    'sort_field' => $sort_field
                                ];
                            }

                        }
                    }
                }
            }
        }

        return $field;
    }

    /**
     * @param string|array $sort
     * @param string $except_field
     * @param array $added_fields
     * @param string $order
     * @return string
     */
    protected function addRemainingSortFields($sort, $except_field = '', $added_fields = [], $order = '')
    {
        $has_product_id = false;
        foreach ((array)$sort as $sort_field) {
            $sort_field = trim((string)$sort_field);
            if ($sort_field != $except_field && $this->getModel()->fieldExists($sort_field)) {
                if ($sort_field == 'id') {
                    $has_product_id = true;
                }
                $added_fields[] = 'p.'.$sort_field . ' '.$order;
            }
        }
        if (!$has_product_id) {
            $added_fields[] = 'p.id';
        }
        return implode(', ', $added_fields);
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

        if (waRequest::param('drop_out_of_stock') == 2) {
            $this->where[] = '(p.count > 0 || p.count IS NULL)';
        }

        $this->where[] = 'p.status = 1';
    }

    protected function alsoboughtPrepare($id)
    {
        $alias = 'alsobought';
        $this->joins[] = array(
            'table' => '(SELECT oi1.product_id, COUNT(*) orders_count FROM shop_order_items oi1
                JOIN (
                  SELECT order_id FROM shop_order_items WHERE product_id = '.(int)$id.' ORDER BY id DESC LIMIT 2000
                ) t ON oi1.order_id = t.order_id
                WHERE oi1.product_id != '.(int)$id.' AND oi1.type = "product"
                GROUP BY oi1.product_id
                ORDER BY orders_count DESC
                LIMIT 50)',
            'alias' => $alias
        );
        if (!isset($this->join_index[$alias])) {
            $this->join_index[$alias] = 1;
        } else {
            $this->join_index[$alias]++;
        }

        $this->fields['order_by'] = $alias.'.orders_count';
        $this->order_by = $alias.'.orders_count DESC, p.id';
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
     * @throws waException
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
        /**
         * @var shopConfig $config
         */

        if (!empty($data['in_stock_only'])) {
            $this->where[] = '(p.count > 0 OR p.count IS NULL)';
        } elseif (!empty($data['out_of_stock_only'])) {
            $this->where[] = 'p.count <= 0';
        }

        // Filter SKUs by price
        $price_filter = array();
        if (isset($data['price_min']) && $data['price_min'] !== '') {
            $price_min = str_replace(',', '.', $data['price_min']);
            $price_filter['price_min'] = '>= '.$this->toFloat(shop_currency($price_min, true, $config->getCurrency(true), false));
        }
        if (isset($data['price_max']) && $data['price_max'] !== '') {
            $price_max = str_replace(',', '.', $data['price_max']);
            $price_filter['price_max'] = '<= '.$this->toFloat(shop_currency($price_max, true, $config->getCurrency(true), false));
        }
        $unit_id = null;
        if (isset($data['unit']) && is_numeric($data['unit'])) {
            $unit_id = (int)$data['unit'];
            $this->addWhere("(p.stock_unit_id = {$unit_id} OR p.base_unit_id = {$unit_id})");
        }
        if (isset($data['base_unit_id']) && wa_is_int($data['base_unit_id'])) {
            $unit_id = (int) $data['base_unit_id'];
            $this->addWhere("p.base_unit_id = {$unit_id}");
        }
        if (isset($data['stock_unit_id']) && wa_is_int($data['stock_unit_id'])) {
            $unit_id = (int) $data['stock_unit_id'];
            $this->addWhere("p.stock_unit_id = {$unit_id}");
        }
        if (!empty($price_filter)) {
            $skus_alias = $this->addJoin([
                'table' => 'shop_product_skus',
                'on'    => ':table.product_id = p.id',
                'where' => ':table.available > 0 AND :table.status > 0',
            ]);

            $where_conditional = "({$skus_alias}.primary_price)";

            // Check promo prices
            $this->loadPromoPrices();
            if (!empty($this->storefront_context) && !empty($this->promo_prices[$this->storefront_context])) {
                $promo_prices_tmp_alias = $this->getPromoPricesTmpAlias();
                if (!$promo_prices_tmp_alias) {
                    $promo_prices_tmp_alias = $this->addJoin([
                        'table' => 'shop_product_promo_price_tmp',
                        'type'  => 'LEFT',
                        'on'    => "p.id = :table.product_id AND ps1.id = :table.sku_id AND :table.storefront = '".$this->getModel()->escape($this->storefront_context)."'",
                    ]);
                }
                $where_conditional = "(IFNULL({$promo_prices_tmp_alias}.primary_price, {$skus_alias}.primary_price))";
            }

            foreach ($price_filter as $price_filter_item) {
                if ($unit_id !== null) {
                    $this->addWhere("IF(p.stock_unit_id = {$unit_id}, {$where_conditional} {$price_filter_item}, {$where_conditional} / IFNULL({$skus_alias}.stock_base_ratio, p.stock_base_ratio) {$price_filter_item})");
                } else {
                    $this->addWhere($where_conditional.' '.$price_filter_item);
                }
            }
        }

        unset(
            $data['in_stock_only'],
            $data['out_of_stock_only'],
            $data['price_min'],
            $data['price_max']
        );

        $feature_model = new shopFeatureModel();
        $features = $feature_model->getByField('code', array_keys($data), 'code');

        if ($features && $this->getModel('product')->existsSelectableProducts()) {
            if (empty($skus_alias)) {
                $skus_alias = $this->addJoin('shop_product_skus', ':table.product_id = p.id', ':table.available > 0 AND :table.status');
            }
        }
        if (!empty($skus_alias) && waRequest::param('drop_out_of_stock') == 2) {
            $this->addWhere('('.$skus_alias.'.count IS NULL OR '.$skus_alias.'.count > 0)');
        }

        $alias_index = 1;
        //$this->filtered_by_features = array(); // should not reset conditions added by search (that is, by dynamic category built-in filter)
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
                            $min = str_replace(',', '.', $values['min']);
                            if ($unit) {
                                $min = shopDimension::getInstance()->convert($min, $features[$feature_code]['type'], null, $unit);
                            } elseif ($features[$feature_code]['type'] == 'range.date') {
                                $min = shopDateValue::dateToTimestamp($min);
                            }
                        }
                        if (isset($values['max']) && $values['max'] !== '') {
                            $max = str_replace(',', '.', $values['max']);
                            if ($unit) {
                                $max = shopDimension::getInstance()->convert($max, $features[$feature_code]['type'], null, $unit);
                            } elseif ($features[$feature_code]['type'] == 'range.date') {
                                $max = shopDateValue::dateToTimestamp($max);
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
                    $imploded_values = implode(',', $values);
                    $feature_id = $features[$feature_code]['id'];
                    if (wa('shop')->getConfig()->getOption('filters_features') == 'exists') {
                        $t = 'tpf'.($alias_index++);
                        $join_where = "{$t}.product_id = p.id";
                        if (!empty($skus_alias)) {
                            $sku_where = " AND ({$t}.sku_id IS NULL OR {$t}.sku_id = {$skus_alias}.id)";
                        } else {
                            $sku_where = '';
                        }
                        $this->where[] = <<<SQL
EXISTS (
  SELECT
    1
  FROM shop_product_features {$t}
  WHERE
    {$join_where}
    AND
    {$t}.feature_id = {$feature_id}
    AND
    {$t}.feature_value_id IN ({$imploded_values})
    {$sku_where}
)
SQL;

                    } else {
                        $this->filtered_by_features[$feature_id] = $values;
                        $where = ":table.feature_id = $feature_id AND :table.feature_value_id IN ($imploded_values)";
                        $on = ":table.product_id = p.id";
                        if (!empty($skus_alias)) {
                            $on .= " AND (:table.sku_id IS NULL OR :table.sku_id = $skus_alias.id)";
                        }
                        $this->addJoin('shop_product_features', "($on)", $where);
                    }
                    $this->group_by = 'p.id';
                } else {
                    $this->where[] = '0';
                }
            }
        }
        $this->filtered = true;
    }

    protected function presentationPrepare($params, $auto_title = false)
    {
        $product_ids = array_map('intval', explode(',', $params));
        $presentation_id = array_shift($product_ids);

        if ($presentation_id <= 0) {
            $this->where[] = '0';
            return;
        }
        $presentation = new shopPresentation($presentation_id);
        $filter = $presentation->getFilter();
        if ($filter->getId()) {
            $this->filterPrepare($filter->getId());
        }
        if ($product_ids) {
            $product_ids = array_keys(array_flip($product_ids)); // unique
            $this->excludeProducts($product_ids);
        }
    }

    /**
     * @param int $id - ID of the filter
     * @param bool $auto_title
     * @throws waException
     */
    protected function filterPrepare($id, $auto_title = false)
    {
        $this->options['prepare_filter'] = null;
        $this->options['filter_id_prepared'] = $id;
        /**
         * @var shopFilterRulesModel $filter_rules_model
         */
        $filter_rules_model = $this->getModel('filterRules');
        $rules = $filter_rules_model->getByFilterId($id);

        // rules not found
        if (!$rules) {
            return;
        }
        if ($this->hash[0] == 'filter') {
            $this->info['hash'] = 'filter';
            if ($auto_title) {
                // TODO название заголовка
            }
        }

        $groups = [];
        foreach ($rules as $rule) {
            $group_id = $rule['rule_group'];
            if (empty($groups[$group_id])) {
                $groups[$group_id] = [
                    'id' => $group_id,
                    'type' => $rule['rule_type'],
                    'rule_params' => [],
                    'open_interval' => $rule['open_interval'],
                ];
            }
            $groups[$group_id]['rule_params'][] = $rule['rule_params'];
        }
        $this->info['rules'] = $groups;

        $storefronts = [];
        $all_storefronts = shopStorefrontList::getAllStorefronts(true);
        foreach ($all_storefronts as $storefront) {
            if (!empty($storefront['route']['checkout_storefront_id'])) {
                $storefront_id = $storefront['route']['checkout_storefront_id'];
            } else {
                $storefront_id = 'sfid'.md5(var_export($storefront['route'], true));
            }
            $storefronts[$storefront_id] = ifset($storefront, 'route', 'type_id', []);
        }
        $all_types = shopFilter::getAllTypes();
        $dimension = null;
        $dimension_list = [];
        $feature_types = ['double', 'date'];
        $search_prepared = false;
        foreach ($groups as $key => $group) {
            if ($group['type'] == 'search') {
                $search_prepared = true;
                $this->searchPrepare('query=' . $group['rule_params'][0], false);
                unset($groups[$key]);
            }
        }
        foreach ($groups as $group) {
            switch ($group['type']) {
                case 'categories':
                    /**
                     * @var shopCategoryModel $category_model
                     */
                    $category_model = $this->getModel('category');
                    $category = $category_model->getById($group['rule_params'][0]);
                    if (!$category) {
                        break;
                    }
                    if (!$search_prepared) {
                        $this->info['main_filter_type'] = 'category';
                        $this->info['main_filter_data'] = $category;
                    }
                    if ($category['type'] == shopCategoryModel::TYPE_STATIC) {
                        $category_alias = $this->addJoin('shop_category_products');
                        if ($category['include_sub_categories']) {
                            $category['subcategories'] = $category_model->descendants($category, true)->where('type = '.shopCategoryModel::TYPE_STATIC)->fetchAll('id');
                            $descendant_ids = array_keys($category['subcategories']);
                            if ($descendant_ids) {
                                $this->where[] = $category_alias . '.category_id IN(' . implode(',', $descendant_ids) . ')';
                            }
                        } else {
                            $this->where[] = $category_alias . '.category_id = ' . (int)$category['id'];
                        }
                    } else {
                        $this->setHash('/search/'.$category['conditions']);
                        $this->prepare($search_prepared, false);
                        while (!empty($category['parent_id'])) {
                            $category = $category_model->getById($category['parent_id']);
                            if ($category['type'] == shopCategoryModel::TYPE_DYNAMIC) {
                                $this->setHash('/search/'.$category['conditions']);
                                $this->prepare(true, false);
                            } else {
                                $category_alias = $this->addJoin('shop_category_products');
                                if ($category['include_sub_categories']) {
                                    $subcategories = $category_model->descendants($category, true)->where('type = '.shopCategoryModel::TYPE_STATIC)->fetchAll('id');
                                    $descendant_ids = array_keys($subcategories);
                                    if ($descendant_ids) {
                                        $this->where[] = $category_alias . '.category_id IN(' . implode(',', $descendant_ids) . ')';
                                    }
                                } else {
                                    $this->where[] = $category_alias . '.category_id = ' . (int)$category['id'];
                                }
                                break;
                            }
                        }
                    }
                    break;
                case 'sets':
                    /**
                     * @var shopSetModel $set_model
                     */
                    $set_model = $this->getModel('set');
                    $set = $set_model->getById($group['rule_params'][0]);
                    if (!$set) {
                        break;
                    }
                    if (!$search_prepared) {
                        $this->info['main_filter_type'] = 'set';
                        $this->info['main_filter_data'] = $set;
                    }
                    if ($set['type'] == shopSetModel::TYPE_STATIC) {
                        $set_alias = $this->addJoin('shop_set_products');
                        $this->where[] = $set_alias . ".set_id = '" . $set_model->escape($set['id']) . "'";
                    } else {
                        $inner_select = 'DISTINCT p.id';
                        $inner_from = 'shop_product p';
                        $rule = ifset($set, 'rule', false);
                        $json_params = ifset($set, 'json_params', '');
                        $params = json_decode($json_params, true);
                        $alias_items = $alias_order = '';
                        if (
                            $rule === shopSetModel::BESTSELLERS_RULE ||
                            $rule === shopSetModel::TOTAL_COUNT_RULE ||
                            !empty($params['date_start']) ||
                            !empty($params['date_end'])
                        ) {
                            $alias_items = 'oi';
                            $inner_from .= "\nJOIN shop_order_items $alias_items ON $alias_items.product_id=p.id AND $alias_items.type='product'";

                            $alias_order = 'o';
                            $inner_from .= "\nJOIN shop_order $alias_order ON $alias_items.order_id=$alias_order.id";
                        }

                        $group_by = '';
                        $inner_where = [];
                        if ($rule === shopSetModel::BESTSELLERS_RULE) {
                            $inner_select .= ", $alias_items.price * $alias_order.rate * $alias_items.quantity AS sales";
                            $order_by = 'sales DESC';
                            $group_by = 'p.id';
                        } elseif ($rule === shopSetModel::TOTAL_COUNT_RULE) {
                            $inner_select .= ", SUM($alias_items.quantity) AS sum_quantity";
                            $order_by = 'sum_quantity DESC';
                            $group_by = 'p.id';
                        } elseif ($rule == 'compare_price DESC') {
                            $set_alias = 'p';
                            $inner_where[] = "$set_alias.compare_price > $set_alias.price";
                            $order_by = "$set_alias.compare_price DESC";
                        } else {
                            $order_by = !empty($set['rule']) ? 'p.'.$set['rule'] : 'p.create_datetime DESC';
                        }

                        if (!empty($params['date_start'])) {
                            $inner_where[] = "$alias_order.paid_date >= '{$params['date_start']}'";
                        }

                        if (!empty($params['date_end'])) {
                            $inner_where[] = "$alias_order.paid_date <= '{$params['date_end']}'";
                        }

                        $inner_sql = 'SELECT ' . $inner_select . "\nFROM " . $inner_from;
                        if ($inner_where) {
                            $inner_sql .= "\nWHERE " . implode(' AND ', $inner_where);
                        }
                        if ($group_by) {
                            $inner_sql .= "\nGROUP BY $group_by";
                        }
                        $inner_sql .= "\nORDER BY $order_by, p.id";
                        if (!empty($set['count']) && wa_is_int($set['count'])) {
                            $inner_sql .= "\nLIMIT ".((int)$set['count']);
                        }
                        $this->addJoin([
                            'table' => '(' . $inner_sql . ')',
                            'alias' => 'iq',
                            'on'    => 'p.id = :table.id',
                        ]);
                    }
                    break;
                case 'types':
                    $this->addJoin([
                        'table' => 'shop_type',
                        'on' => ':table.id = p.type_id AND :table.id = ' . (int)$group['rule_params'][0],
                    ]);
                    break;
                case 'storefronts':
                    if (count($group['rule_params']) > 0) {
                        $type_ids = [];
                        foreach ($group['rule_params'] as $storefront) {
                            if (!empty($storefronts[$storefront]) && is_array($storefronts[$storefront])) {
                                $type_ids += $storefronts[$storefront];
                            }
                        }
                        $type_ids = array_unique($type_ids);
                        if ($type_ids) {
                            $this->where[] = 'p.type_id IN ('. implode(',', array_map('intval', $type_ids)) . ')';
                        }
                    }
                    break;
                case 'tags':
                    if (count($group['rule_params']) > 0) {
                        $tags_alias = $this->addJoin('shop_product_tags');
                        $this->where[] = $tags_alias . '.tag_id IN ('. implode(',', array_map('intval', $group['rule_params'])) . ')';
                    }
                    break;
                default:
                    if (isset($all_types['product_fields'][$group['type']])) {
                        $type = $all_types['product_fields'][$group['type']];
                        if ($group['type'] == 'badge') {
                            $has_custom = false;
                            foreach ($group['rule_params'] as $key => $param) {
                                if ($param == 'custom') {
                                    $has_custom = true;
                                    unset($group['rule_params'][$key]);
                                }
                            }
                            if ($has_custom) {
                                $all_badges = array_keys(shopProductModel::badges());
                                if (count($group['rule_params']) != count($all_badges)) {
                                    if (!empty($group['rule_params'])) {
                                        $not_included = array_diff($all_badges, $group['rule_params']);
                                        $in = "('" . implode("','", $not_included) . "')";
                                    } else {
                                        $in = "('" . implode("','", $all_badges) . "')";
                                    }
                                    $this->where[] = "p.{$group['type']} NOT IN $in";
                                }
                                $this->where[] = "p.{$group['type']} IS NOT NULL AND p.{$group['type']} != ''";
                            } elseif (!empty($group['rule_params'])) {
                                $this->where[] = "p.{$group['type']} IN ('" . implode("','", $group['rule_params']) . "')";
                            }
                        } else {
                            $this->addFilterRule('p', $type, $group);
                        }
                    } elseif (isset($all_types['sku_fields'][$group['type']])) {
                        $skus_join = $this->addJoin('shop_product_skus', ':table.product_id = p.id');
                        $type = $all_types['sku_fields'][$group['type']];
                        $this->addFilterRule($skus_join, $type, $group);
                    } elseif (isset($all_types['dynamic_fields'][$group['type']])) {
                        if ($group['type'] == 'sales_30days') {
                            $alias_items = $this->addJoin([
                                'table' => 'shop_order_items',
                                'type' => 'LEFT',
                                'on' => ":table.product_id = p.id AND :table.type = 'product'",
                            ]);
                            $alias_order = $this->addJoin([
                                'table' => 'shop_order',
                                'type' => 'LEFT',
                                'on' => ":table.id = $alias_items.order_id AND :table.paid_date >= '" . date('Y-m-d', time() - 3600 * 24 * 30) . "'",
                            ]);
                            $this->fields['sales_30days'] = "SUM($alias_items.price * $alias_order.rate * $alias_items.quantity) `sales_30days`";
                            $this->group_by = 'p.id';
                            $sales_having = $this->parseRules($group, '`sales_30days`');
                            $this->having[] = "(($sales_having) OR `sales_30days` IS NULL)";
                        } elseif ($group['type'] == 'stock_worth') {
                            $alias_skus = $this->addJoin('shop_product_skus', ":table.product_id = p.id");
                            $this->fields['stock_worth'] = "SUM($alias_skus.primary_price * $alias_skus.count) AS `stock_worth`";
                            $this->group_by = 'p.id';
                            $this->having[] = $this->parseRules($group, '`stock_worth`');
                        }
                    } elseif (isset($all_types['features'][$group['type']])) {
                        $type = $all_types['features'][$group['type']];
                        if ($type['type'] == 'boolean' || $type['type'] == 'varchar' || $type['type'] == 'color' || $type['selectable']) {
                            $this->addJoin([
                                'table' => 'shop_product_features',
                                'on' => ':table.product_id = p.id',
                                'where' => ':table.feature_value_id IN (' . implode(',', $group['rule_params']) . ') AND :table.feature_id = ' . $type['id']
                            ]);
                        } else {
                            $feature_table_name = shopFeatureModel::getValuesModel($type['type'])->getTableName();
                            if ($feature_table_name) {
                                if (empty($dimension)) {
                                    $dimension = shopDimension::getInstance();
                                    $dimension_list = array_keys($dimension->getList());
                                }
                                $where = '';
                                if (in_array($type['type'], $feature_types)) {
                                    $where = $this->parseRules($group, ':table.value');
                                } elseif (mb_strpos($type['type'], 'dimension.') === 0
                                    && in_array(mb_substr($type['type'], 10), $dimension_list)
                                ) {
                                    $where = $this->parseRules($group, ':table.value_base_unit', $dimension);
                                } elseif (mb_strpos($type['type'], 'range.') === 0) {
                                    $dimension_type = mb_substr($type['type'], 6);
                                    if (in_array($dimension_type, $feature_types)) {
                                        $where = $this->parseRules($group);
                                    } elseif (in_array($dimension_type, $dimension_list)) {
                                        $where = $this->parseRules($group, '', $dimension);
                                    }
                                }
                                if ($where) {
                                    $product_features_alias = $this->addJoin([
                                        'table' => 'shop_product_features',
                                        'on' => ':table.product_id = p.id',
                                        'where' => ':table.feature_id = ' . $type['id']
                                    ]);
                                    $this->addJoin([
                                        'table' => $feature_table_name,
                                        'on' => ":table.id = $product_features_alias.feature_value_id",
                                        'where' => $where
                                    ]);
                                }
                            }
                        }
                    }
                    break;
            }
        }
    }

    /**
     * @param $table_alias
     * @param $type
     * @param $group
     * @return void
     */
    protected function addFilterRule($table_alias, $type, $group)
    {
        if (isset($type['table']) && $type['render_type'] == 'select') {
            if ($group['type'] == 'currency') {
                $in = '("' . implode('","', $group['rule_params']) . '")';
                $product_field = 'code';
            } else {
                $in = '(' . implode(',', $group['rule_params']) . ')';
                $product_field = 'id';
            }
            $this->addJoin([
                'table' => "shop_{$type['table']}",
                'on' => ":table.$product_field = $table_alias.{$group['type']}",
                'where' => ":table.$product_field IN $in"
            ]);
        } elseif ($type['render_type'] == 'range') {
            $string_type = $group['type'] == 'create_datetime' || $group['type'] == 'edit_datetime' ? "'" : '';
            if (!is_null($group['open_interval'])
                && ($group['open_interval'] == shopFilterRulesModel::OPEN_INTERVAL_LEFT_CLOSED
                    || $group['open_interval'] == shopFilterRulesModel::OPEN_INTERVAL_RIGHT_CLOSED)
            ) {
                $compare_sign = $group['open_interval'] == shopFilterRulesModel::OPEN_INTERVAL_LEFT_CLOSED ? '>=' : '<=';
                $this->where[] = "$table_alias.{$group['type']} $compare_sign $string_type{$group['rule_params'][0]}$string_type";
            } elseif (count($group['rule_params']) > 1) {
                $group_type = $group['type'] == 'price' ? 'primary_price' : $group['type'];
                $this->where[] = "$table_alias.$group_type >= $string_type{$group['rule_params'][0]}$string_type";
                $this->where[] = "$table_alias.$group_type <= $string_type{$group['rule_params'][1]}$string_type";
            }
        } elseif ($type['render_type'] == 'select') {
            $in = "(" . implode(",", $group['rule_params']) . ")";
            $this->where[] = "$table_alias.{$group['type']} IN $in";
        }
    }

    /**
     * @param array $group
     * @param string $field_name
     * @param shopDimension $dimension
     * @return string|null
     */
    protected function parseRules($group, $field_name = '', $dimension = [])
    {
        $result = null;
        if (!is_null($group['open_interval'])
            && ($group['open_interval'] == shopFilterRulesModel::OPEN_INTERVAL_LEFT_CLOSED
                || $group['open_interval'] == shopFilterRulesModel::OPEN_INTERVAL_RIGHT_CLOSED)
        ) {
            $compare_sign = $group['open_interval'] == shopFilterRulesModel::OPEN_INTERVAL_LEFT_CLOSED ? '>=' : '<=';
            if (empty($field_name)) {
                $field_name = $group['open_interval'] == shopFilterRulesModel::OPEN_INTERVAL_LEFT_CLOSED ? ':table.begin_base_unit' : ':table.end_base_unit';
            }
            if ($dimension) {
                $type = $this->getTypeByUnit($group['rule_params'][1], $dimension);
                $group['rule_params'][0] = $dimension->convert($group['rule_params'][0], $type, '', $group['rule_params'][1]);
            }
            $result = "$field_name $compare_sign {$group['rule_params'][0]}";
        } elseif (count($group['rule_params']) > 1) {
            $begin = $end = $field_name;
            if (empty($field_name)) {
                $begin = ':table.begin_base_unit';
                $end = ':table.end_base_unit';
            }
            if ($dimension) {
                $type = $this->getTypeByUnit($group['rule_params'][2], $dimension);
                $group['rule_params'][0] = $dimension->convert($group['rule_params'][0], $type, '', $group['rule_params'][2]);
                $group['rule_params'][1] = $dimension->convert($group['rule_params'][1], $type, '', $group['rule_params'][2]);
            }
            $result = "$begin >= {$group['rule_params'][0]} AND $end <= {$group['rule_params'][1]}";
        }

        return $result;
    }

    /**
     * @param string $unit
     * @param shopDimension $dimension
     * @return int|string
     */
    protected function getTypeByUnit($unit, $dimension)
    {
        $list = $dimension->getList();
        foreach ($list as $type => $data) {
            if (isset($data['units'][$unit])) {
                return $type;
            }
        }
        return '';
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
     * @throws waException
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
            $frontend_urls = $category_model->getFrontendUrls($id, true);
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

                    $this->fields['order_by'] = 'IF(p.count IS NULL, 1, 0) count_null';
                    $this->order_by = 'count_null '.$tmp[1].', p.count '.$tmp[1].', p.id';
                } elseif ($tmp[0] == 'stock_worth') {

                    $this->fields['order_by'] = 'IFNULL(p.count, 0)*p.price AS stock_worth';
                    $this->order_by = 'stock_worth '.$tmp[1].', p.id';
                } else {
                    $this->order_by = 'p.'.$this->info['sort_products'].', p.id';
                }
            }
        }


        if ($this->info['type'] == shopCategoryModel::TYPE_STATIC) {
            $alias = $this->addJoin('shop_category_products');
            if (true
                /* && wa()->getEnv() == 'frontend'*/
                && $this->info['include_sub_categories']
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
                $this->group_by = $alias.'.sort, p.id';
                $this->order_by = $alias.'.sort ASC, p.id';
            }
        } else {
            $hash = $this->hash;
            $this->setHash('/search/'.$this->info['conditions']);
            $this->prepare(false, false);
            $info = $this->info;
            while ($info['parent_id'] /* && $this->info['conditions']*/) {
                $info = $category_model->getById($info['parent_id']);
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

        //Group by faster than DISTINCT
        if (empty($this->group_by)) {
            $this->group_by = 'p.id';
        }
    }

    /**
     * @param int $id - ID of the set
     * @param bool $auto_title
     * @throws waException
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
                $this->fields[] = $alias.'.sort';
                $this->order_by = $alias.'.sort ASC, p.id';
            }
        } else {
            $init_data = [];
            foreach (['fields', 'order_by', 'where', 'join_index', 'joins', 'group_by', 'prepared'] as $field) {
                $init_data[$field] = $this->{$field};
            }

            $rule = ifset($set, 'rule', false);

            $json_params = ifset($set, 'json_params', '');
            $params = json_decode($json_params, true);

            if (
                $rule === shopSetModel::BESTSELLERS_RULE ||
                $rule === shopSetModel::TOTAL_COUNT_RULE ||
                !empty($params['date_start']) ||
                !empty($params['date_end'])
            ) {
                $alias_items = $this->addJoin([
                    'table' => 'shop_order_items',
                    'on'    => ":table.product_id=p.id AND :table.type='product'",
                ]);

                $alias_order = $this->addJoin([
                    'table' => 'shop_order',
                    'alias' => 'o',
                    'on'    => "{$alias_items}.order_id=:table.id",
                ]);
            }

            if ($rule === shopSetModel::BESTSELLERS_RULE) {
                $this->fields['sales'] = "{$alias_items}.price * {$alias_order}.rate * {$alias_items}.quantity AS sales";
                $this->groupBy('p.id');
                $this->order_by = 'sales DESC, p.id';
            } elseif ($rule === shopSetModel::TOTAL_COUNT_RULE) {
                $this->groupBy('p.id');
                // If you call the function - there will be infinite recursion :[
                $this->order_by = "sum($alias_items.quantity) DESC, p.id";
            } elseif ($rule == 'compare_price DESC') {
                $this->setByComparePrice();
            } else {
                $this->order_by = !empty($set['rule']) ? 'p.'.$set['rule'] : 'p.create_datetime DESC';
                $this->order_by .= ', p.id';

                if (!isset($this->join_index['ps']) && preg_match('~^(price)\s(asc|desc)$~ui', $set['rule'], $matches)) {
                    $order = $matches[2];
                    $this->addPromoPriceSort('price', $order);
                }
            }

            if (!empty($params['date_start'])) {
                $this->where[] = "{$alias_order}.paid_date >= '{$params['date_start']}'";
            }

            if (!empty($params['date_end'])) {
                $this->where[] = "{$alias_order}.paid_date <= '{$params['date_end']}'";
            }

            if (!empty($set['sort_products'])) {
                $this->prepared = true;

                $distinct = $this->joins && !$this->group_by ? 'DISTINCT ' : '';
                $from_and_where = $this->getSQL();
                $group_by = $this->_getGroupBy();

                $inner_sql = 'SELECT ' . $distinct . $this->getFields('') . "\n" . $from_and_where;
                if ($group_by) {
                    $inner_sql .= $group_by;
                }
                $inner_sql .= $this->_getOrderBy();

                foreach (['fields', 'order_by', 'where', 'join_index', 'joins', 'group_by', 'prepared'] as $field) {
                    $this->{$field} = $init_data[$field];
                }

                if ($set['sort_products'] == 'compare_price ASC' || $set['sort_products'] == 'purchase_price ASC') {
                    $skus_alias = $this->addUniqueJoin('shop_product_skus', 'p.id = :table.product_id', '', 'LEFT');
                    $this->order_by = $skus_alias. '.' . $set['sort_products'];
                } else {
                    $this->order_by = 'p.' . $set['sort_products'];
                }
                $this->order_by .= ', p.id';

                $this->addJoin([
                    'table' => '(' . $inner_sql . ')',
                    'alias' => 'iq',
                    'on'    => 'p.id = :table.id',
                ]);
            }
        }
    }

    protected function setByComparePrice()
    {
        $set_alias = 'p';

        if (isset($this->join_index['ps'])) {
            $set_alias = 'ps1';
        }

        $where = "{$set_alias}.compare_price > {$set_alias}.price";
        $order_by = "{$set_alias}.compare_price DESC, p.id";

        // Check promo prices
        $this->loadPromoPrices();
        if (!empty($this->storefront_context) && !empty($this->promo_prices[$this->storefront_context])) {
            $promo_prices_tmp_alias = $this->getPromoPricesTmpAlias();
            if (!$promo_prices_tmp_alias) {

                $on = "{$set_alias}.id = :table.product_id AND {$set_alias}.sku_id = :table.sku_id";
                if ($set_alias == 'ps1') {
                    // Join on skus
                    $on = "{$set_alias}.product_id = :table.product_id AND {$set_alias}.id = :table.sku_id";
                }

                $promo_prices_tmp_alias = $this->addJoin([
                    'table' => 'shop_product_promo_price_tmp',
                    'type'  => 'LEFT',
                    'on'    => "{$on} AND :table.storefront = '".$this->getModel()->escape($this->storefront_context)."'",
                ]);
            }

            $where = "IFNULL({$promo_prices_tmp_alias}.compare_price, {$set_alias}.compare_price) > IFNULL({$promo_prices_tmp_alias}.price, {$set_alias}.price)";
            $order_by = "IFNULL({$promo_prices_tmp_alias}.compare_price, {$set_alias}.compare_price) DESC, p.id";
        }

        $this->where[] = $where;
        $this->order_by = $order_by;
    }

    protected function idPrepare($ids_str)
    {
        $this->info = array(
            'hash' => 'id'
        );
        $ids = array();
        foreach (explode(',', $ids_str) as $id) {
            if (strlen($id)) {
                $ids[] = (int)$id;
            }
        }
        if ($ids) {
            $this->where[] = "p.id IN (".implode(',', $ids).")";
        } else {
            $this->where[] = '0';
        }
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
        $tag = $tag_model->getByName($id);
        if ($tag) {
            $id = $tag['id'];
        } elseif (is_numeric($id)) {
            $tag = $tag_model->getById($id);
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


    /**
     * @param $query
     * @return array
     * @see how it work in waContactsCollection::searchPrepare
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
                if ($name != 'price' && $temp[0] != '>=' && $temp[0] != '<=') {
                    $result[$name] = $temp;
                } else {
                    if ($temp[0] == '>=') {
                        $result[$name][0] = $temp;
                    } elseif ($temp[0] == '<=') {
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
                        continue 2;
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
                        $where = ":table.feature_id = ".(int)$row['feature_id'];
                        $where .= " AND :table.feature_value_id = ".(int)$row['value'];
                        $this->addJoin('shop_product_features', null, $where);
                        $this->group_by = 'p.id';
                    }
                    break;
                case 'any':
                case 'all':
                    if ($model->fieldExists($row['feature'])) {
                        //$this->where[] = 'p.'.$row['feature']." = '".$model->escape($row['value'])."'";
                    } else {
                        if ($row['value']) {
                            $where = ":table.feature_id = ".(int)$row['feature_id'];
                            $where .= " AND :table.feature_value_id IN (".$row['value'].")";
                            $this->addJoin('shop_product_features', null, $where);
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
                                        'sku_id'     => null,
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
                                $this->where[] = $alias.".feature_id = ".$f_id;
                                if (count($f_values) == 1) {
                                    $this->where[] = $alias.".feature_value_id ".($row['cond'] == 'notsame' ? '!' : '')."= ".$f_values[0];
                                } else {
                                    $this->where[] = $alias.".feature_value_id ".($row['cond'] == 'notsame' ? 'NOT ' : '')."IN (".implode(',', $f_values).")";
                                }
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
            $this->fields['order_by'] = '('.implode(' + ', $sum).') AS upselling_deviation';
            $this->order_by = 'upselling_deviation, p.id';
        }
    }

    protected function bestsellersPrepare($query, $auto_title = true)
    {
        $this->bestsellersJoin();
        $shop_order_alias = ifset(ref($this->getJoinsByTableName('shop_order')), 0, 'alias', 'o');
        if ($query && wa_is_int($query)) {
            $date_start = date('Y-m-d H:i:s', time() - $query);
            $this->where[] = $shop_order_alias.".paid_date >= '{$date_start}'";
        } else {
            $this->where[] = $shop_order_alias.".paid_date IS NOT NULL";
        }
    }

    protected function bestsellersJoin()
    {
        $alias_items = $this->addJoin([
            'table' => 'shop_order_items',
            'on'    => ":table.product_id=p.id AND :table.type='product'",
        ]);

        $alias_order = $this->addJoin([
            'table' => 'shop_order',
            'alias' => 'o',
            'on'    => "{$alias_items}.order_id=:table.id",
        ]);

        $this->fields['sales'] = "{$alias_items}.price * {$alias_order}.rate * {$alias_items}.quantity AS sales";
        $this->groupBy('p.id');

        $this->order_by = 'sales DESC, p.id';
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
        $title = [];
        $range_collection = $range_units = [];

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
                        $this->addJoin(
                            'shop_category_products',
                            null,
                            $this->getArrayExpression(':table.category_id', $parts[1], $parts[2])
                        );
                        $title[] = "category_id ".$parts[1].$parts[2];
                    }
                } elseif ($parts[0] == 'sku_id') {
                    $this->addJoin(
                        'shop_product_skus',
                        ':table.product_id = p.id',
                        $this->getArrayExpression(':table.id', $parts[1], $parts[2])
                    );
                    $title[] = "sku_id ".$parts[1].$parts[2];
                } elseif ($parts[0] == 'query') {

                    if (!wa('shop')->getConfig()->getOption('search_smart')) {
                        // simple search

                        $words = explode(' ', $parts[2]);
                        $alias = $this->addJoin('shop_product_skus');
                        foreach ($words as $w) {
                            $w = trim($w);
                            $w = $model->escape($w, 'like');
                            $this->where[] = "(p.name LIKE '%".$w."%' OR ".$alias.".sku LIKE '%".$w."%' OR ".$alias.".name LIKE '%".$w."%')";
                        }
                    } else {
                        //smart search

                        // Following block of code might change the ordering
                        // as part of searching logic.
                        // Remember order-by in case we want to restore it later.
                        $auto_order_by = $this->order_by;
                        $auto_fields = $this->fields; // save fields

                        $search = new shopIndexSearch();
                        $word_ids = $search->getWordIds($parts[2], true);
                        if ($word_ids) {
                            $this->joins[] = array(
                                'table' => 'shop_search_index',
                                'alias' => 'si'
                            );
                            $this->where[] = 'si.word_id IN ('.implode(",", $word_ids).')';

                            if (empty($this->fields['order_by'])) {
                                if (count($word_ids) > 1) {
                                    $this->fields['order_by'] = "SUM(si.weight) AS weight";
                                    $this->fields['order_by_2'] = "COUNT(*) AS weight_count";
                                    $this->order_by = 'weight_count DESC, weight DESC, p.id';
                                    $this->group_by = 'p.id';
                                } else {
                                    $this->fields['order_by'] = "si.weight";
                                    $this->order_by = 'si.weight DESC, p.id';
                                }
                            } elseif (count($word_ids) > 1) {
                                $this->group_by = 'p.id';
                            }
                        } elseif ($parts[2]) {
                            $this->where[] = '0';
                        }
                        $this->prepared = true;
                        // if not found try find by name
                        if (!$this->count()) {
                            $this->count = null;
                            $auto_joins = $this->joins;
                            $this->joins = $this->where = $this->having = array();
                            $this->fields = $auto_fields; //restore fields;
                            if ($this->is_frontend) {
                                if ($this->filtered) {
                                    $this->filtered = false;
                                }
                                $this->frontendConditions();
                            }
                            $join_aliases = is_array($auto_joins) ? array_column($auto_joins, 'alias') : [];
                            if (waRequest::request('sort', 'weight', 'string') == 'weight'
                                || ($join_aliases && preg_match('/(' . join('|', $join_aliases) . ')\./m', $auto_order_by, $matches) && $matches)
                            ) {
                                $this->order_by = 'p.create_datetime DESC, p.id';
                            } else {
                                $this->order_by = $auto_order_by;
                            }
                            $q = $model->escape($parts[2], 'like');
                            $this->addJoin('shop_product_skus', null, "(p.name LIKE '%".$q."%' OR :table.name LIKE '%".$q."%' OR :table.sku LIKE '%".$q."%')");
                            $this->group_by = 'p.id';
                            return;
                        }

                        // Restore original order-by if were specified.
                        if (waRequest::request('sort', 'weight', 'string') != 'weight' || isset($this->options['sort'])) {
                            $this->order_by = $auto_order_by;
                        }
                    }
                    $title[] = $parts[0].$parts[1].$parts[2];
                } elseif ($parts[0] == 'tag') {
                    $title[] = $parts[0].$parts[1].$parts[2];
                    $tag_model = $this->getModel('tag');
                    /**
                     * @var shopTagModel $tag_model
                     */
                    if (strpos($parts[2], '||') !== false) {
                        $tags = explode('||', $parts[2]);
                        $tag_ids = $tag_model->getIds($tags);
                    } else {
                        $sql = "SELECT `id` FROM ".$tag_model->getTableName();
                        $sql .= " WHERE `name`".$this->getExpression($parts[1], $parts[2]);
                        $tag_ids = $tag_model->query($sql)->fetchAll(null, true);
                    }
                    if ($tag_ids) {
                        $this->addJoin('shop_product_tags', null, ":table.tag_id IN (".implode(", ", $tag_ids).")");
                    } else {
                        $this->where[] = "0";
                    }
                } else if ($parts[0] == 'by_code') {
                    $code_value = $parts[2];

                    // chestnyznak code contains GTIN
                    if (preg_match('~^01(\d{14})21~', $code_value, $m)) {
                        $code_value = $m[1];
                    }

                    $feature_model = $this->getModel('feature');
                    $feature = $feature_model->getByCode('gtin');
                    $values_id = null;
                    if ($feature) {
                        $values_model = $feature_model->getValuesModel($feature['type']);
                        $short_value = ltrim($code_value, '0');
                        $values = [$code_value, $short_value];
                        foreach ([14, 13, 12, 8] as $length) {
                            if (strlen($short_value) < $length) {
                                $values[] = str_pad($short_value, $length, '0', STR_PAD_LEFT);
                            }
                        }
                        $values_id = $values_model->getId($feature['id'], $values);
                    }
                    if (!empty($values_id)) {
                        $skus_join = $this->addUniqueJoin('shop_product_skus', ':table.product_id = p.id', null);
                        $join_where = ":table.feature_id = {$feature['id']} AND :table.feature_value_id IN (".join(',', $values_id).")";
                        $this->addUniqueJoin('shop_product_features', "((:table.sku_id = $skus_join.id OR :table.sku_id IS NULL) AND :table.product_id = p.id)", $join_where);
                        $this->filtered_by_features[$feature['id']] = $values_id;
                    } else {
                        $this->addWhere("0='no gtin'");
                    }
                } elseif ($model->fieldExists($parts[0])) {
                    $title[] = $parts[0].$parts[1].$parts[2];
                    if ($parts[0] === 'count' && in_array($parts[1], array('>', '>='))) {
                        $this->where[] = '(p.'.$parts[0].$this->getExpression($parts[1], $parts[2]).' OR p.count IS NULL)';
                    } else {
                        $this->where[] = 'p.'.$parts[0].$this->getExpression($parts[1], $parts[2]);
                    }
                } elseif ($parts[1] == '=') {
                    $code = $parts[0];
                    $is_value_id = false;
                    if (substr($code, -5) == '.unit') {
                        $feature_code = substr($code, 0, -5);
                        $range_units[$feature_code] = $parts[2];
                        continue;
                    } elseif (substr($code, -9) == '.value_id') {
                        $code = substr($code, 0, -9);
                        $is_value_id = true;
                    }
                    $feature_model = $this->getModel('feature');
                    /**
                     * @var shopFeatureModel $feature_model
                     */
                    $feature = $feature_model->getByCode($code);
                    if ($feature) {
                        if ($is_value_id) {
                            $value_id = array_map('intval', preg_split('@[,\s]+@', $parts[2]));
                            $values_id = $value_id;

                            // need sort list of value ids in light of optimization
                            // in case if hash looks like 'feature.value_id=1,2,3&feature=3,2,1'
                            sort($values_id, SORT_NUMERIC);

                            $value_id = implode(', ', $values_id);
                        } else {
                            $values_model = $feature_model->getValuesModel($feature['type']);
                            $value_id = (int)$values_model->getValueId($feature['id'], $parts[2]);
                            $values_id = [$value_id];
                        }

                        $skus_join = $this->addUniqueJoin('shop_product_skus', ':table.product_id = p.id', null);
                        $join_where = ":table.feature_id = {$feature['id']} AND :table.feature_value_id IN ($value_id)";
                        $this->addUniqueJoin('shop_product_features', "((:table.sku_id = $skus_join.id OR :table.sku_id IS NULL) AND :table.product_id = p.id)", $join_where);

                        $this->filtered_by_features[$feature['id']] = $values_id;
                        $this->group_by = 'p.id';
                    }
                } elseif ($parts[1] == '<=' || $parts[1] == '>=') {
                    $range_collection[$parts[0]][] = $parts;
                }
            }
        }

        if ($range_collection) {
            foreach ($range_collection as $code => $ranges) {
                $begin = $end = null;

                if (substr($code, -6) == '.value') {
                    $code = substr($code, 0, -6);
                }
                $unit = isset($range_units[$code]) && mb_strlen($range_units[$code]) ? $range_units[$code] : null;

                /**
                 * @var shopFeatureModel $feature_model
                 */
                $feature_model = $this->getModel('feature');
                $feature = $feature_model->getByCode($code);

                if ($feature) {
                    foreach ($ranges as $range) {
                        $range_value = $range[2];
                        if ($unit) {
                            $range_value = shopDimension::getInstance()->convert($range_value, $feature['type'], null, $unit);
                        }
                        if ($range[1] == '<=') {
                            $end[] = $range_value;
                        } elseif ($range[1] == '>=') {
                            $begin[] = $range_value;
                        }
                    }

                    $where_placeholder = ['feature_id' => $feature['id']];
                    if ($feature['type'] == shopFeatureModel::TYPE_DATE
                        || $feature['type'] == shopFeatureModel::TYPE_DOUBLE
                        || mb_strpos($feature['type'], 'dimension.') === 0
                    ) {
                        if (mb_strpos($feature['type'], 'dimension.') === 0) {
                            $field_name = 'value_base_unit';
                            $model = new shopFeatureValuesDimensionModel();
                        } else {
                            $field_name = 'value';
                            $model = new shopFeatureValuesDoubleModel();
                        }

                        $where = ['feature_id = i:feature_id'];
                        if ($end) {
                            $where[] = "$field_name <= f:begin_value";
                            $where_placeholder['begin_value'] = min($end);
                        }
                        if ($begin) {
                            $where[] = "$field_name >= f:end_value";
                            $where_placeholder['end_value'] = max($begin);
                        }

                        $where = join(' AND ', $where);
                        $range_ids = $model->select('id')
                                           ->where($where, $where_placeholder)
                                           ->fetchAll('id');
                    } else {
                        // 1=1 need to connect conditions
                        $where = [
                            'feature' => 'feature_id = i:feature_id',
                            'end'     => '1=1',
                            'begin'   => '1=1',
                        ];

                        if ($end) {
                            $where['end'] .= ' AND end_base_unit <= f:end';
                            $where['begin'] .= ' AND begin_base_unit <= f:end'; //The initial value should not be greater than the maximum
                            $where_placeholder['end'] = min($end); //if many conditions take the minimum range
                        }
                        if ($begin) {
                            $where['end'] .= ' AND end_base_unit >= f:begin'; //The final value should not be less than the starting value.
                            $where['begin'] .= ' AND begin_base_unit >= f:begin';
                            $where_placeholder['begin'] = max($begin); //see above
                        }

                        //If the final or initial value is not specified, use the "end" or "begin" column.
                        //Because in the column "*** _ base_unit" never is the value NULL
                        $where['end'] = '(('.$where['end'].') OR end IS NULL)';
                        $where['begin'] = '(('.$where['begin'].') OR begin IS NULL)';

                        $where = join(' AND ', $where);

                        $feature_range_model = new shopFeatureValuesRangeModel();
                        $range_ids = $feature_range_model->select('id')
                                                         ->where($where, $where_placeholder)
                                                         ->fetchAll('id');
                    }
                    if ($range_ids) {
                        $value_ids = join(', ', array_keys($range_ids));

                        $skus_join = $this->addUniqueJoin('shop_product_skus', ':table.product_id = p.id', null);
                        $join_where = ":table.feature_id = {$feature['id']} AND :table.feature_value_id IN ($value_ids)";
                        $this->addJoin('shop_product_features', "((:table.sku_id = $skus_join.id OR :table.sku_id IS NULL) AND :table.product_id = p.id)", $join_where);

                        $this->filtered_by_features[$feature['id']] = array_keys($range_ids);
                        $this->group_by = 'p.id';
                    } else {
                        $this->addJoin('shop_product_features', null, '1=0');
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
            case "=":
            default:
                if (strpos($value, '||') !== false) {
                    $values = explode('||', $value);
                    return " IN ('".implode("','", $model->escape($values))."')";
                } else {
                    return " = '".$model->escape($value)."'";
                }
        }
    }

    /**
     * Returns expression for SQL
     *
     * @param string $op - operand ==, >=, etc
     * @param string $value - value comma separated integer values
     * @return string
     */
    protected function getArrayExpression($field, $op, $value)
    {
        $values = preg_split('@[,\s]+@', trim($value, ', '));
        $values = array_unique(array_filter(array_map('intval', $values)));
        if (!$values) {
            switch ($op) {
                case '>':
                case '>=':
                case '<':
                case '<=':
                case "==":
                case "=":
                    return '1=0';
                case '!=':
                    return '1=1';
            }
        }

        switch ($op) {
            case "^=":
            case "$=":
            case "*=":
                // backward compatibility for strange use case
                $model = $this->getModel();
                $value = $model->escape($value, 'like');
                break;
            case '>':
            case '>=':
                $value = max($values);
                break;
            case '<':
            case '<=':
                $value = min($values);
                break;
            case '!=':
            case "==":
            case "=":
            default:
                $value = implode(',', $values);
                break;
        }

        switch ($op) {
            case "^=":
                return $field." LIKE '".$value."%'";
            case "$=":
                return $field." LIKE '%".$value."'";
            case "*=":
                return $field." LIKE '%".$value."%'";
            case '>':
            case '>=':
            case '<':
            case '<=':
                return $field." ".$op." '".$value."'";
            case '!=':
                return $field." NOT IN (".$value.")";
            case "==":
            case "=":
            default:
                return $field." IN (".$value.")";
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
        if ($this->is_frontend) {
            $fields .= ',frontend_url';
        }
        $split_fields = array_map('trim', explode(',', $fields));
        if (in_array('frontend_url', $split_fields) && !in_array('*', $split_fields)) {
            if ($dependent_fields = array_diff(array('url', 'category_id',), $split_fields)) {
                $fields .= ','.implode(',', $dependent_fields);
            }
        }

        if (waRequest::param('drop_out_of_stock') == 1) {
            $this->fields['drop_out_of_stock'] = '(p.count > 0 || p.count IS NULL) AS in_stock';
        }

        // 'skus_filtered' require additional data if joined with product_skus table
        if (isset($this->join_index['ps']) && (in_array('skus_filtered', $split_fields) || in_array('sku_filtered', $split_fields))) {
            $this->fields['skus_filtered'] = "GROUP_CONCAT(DISTINCT ps" . $this->join_index['ps'] . ".id) AS sku_ids";
            if (empty($this->group_by)) {
                $this->group_by = 'p.id';
            }
        }

        $model = $this->getModel();
        if ($fields == '*') {
            return 'p.*'.($this->fields ? ",".implode(",", $this->fields) : '');
        }

        $required_fields = array('id' => 'p'); // field => table, to be added later in any case

        if (!is_array($fields)) {
            $fields = explode(",", $fields);
            $fields = array_map('trim', $fields);
        }

        if (false !== strpos(join('', $fields), 'price')) {
            $fields[] = 'currency'; // required for proper rounding and conversion
            $fields[] = 'sku_id'; // be sure to get the main sku to find it in the promo
        }

        $virtual_fields = array(
            'images',
            'images2x',
            'image',
            'image_crop_small',
            'image_count',
            'frontend_url',
            'sales_30days',
            'stock_worth',
            'stock_counts',
            'reviews_count',
            'params',
            'sku',
            'sku_filtered',
            'skus_filtered',
            'skus',
            'skus_image',
        );
        // Add required fields to select and delete fields for getting data after query
        foreach ($fields as $i => $f) {
            if ($f == '*') {
                $fields[$i] = 'p.*';
                continue;
            }
            if (!$model->fieldExists($f)) {
                unset($fields[$i]);
                if (in_array($f, $virtual_fields)) {
                    $this->post_fields['_internal'][] = $f;
                    $required_fields['sku_id'] = 'p';
                    $required_fields['image_id'] = 'p';
                    $required_fields['image_filename'] = 'p';
                    $required_fields['ext'] = 'p';
                } elseif (substr($f, 0, 8) == 'feature_') {
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
            foreach (array_unique($this->fields) as $f) {
                $fields[] = $f;
            }
        }

        $fields = implode(",", $fields);

        return $fields;
    }

    /**
     * Returns ORDER BY clause.
     *
     * @return string
     */
    protected function _getOrderBy()
    {
        if ($this->order_by) {
            if (waRequest::param('drop_out_of_stock') == 1) {
                return "\nORDER BY in_stock DESC,".$this->order_by;
            } else {
                return "\nORDER BY ".$this->order_by;
            }
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
            return "\nGROUP BY ".$this->group_by;
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
     * @throws waException
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
                        $this->fields['order_by'] = 'IF(p.count IS NULL, 1, 0) count_null';
                        return $this->order_by = 'count_null '.$order.', p.count '.$order.', p.id';
                    } elseif ($field == 'stock_worth') {
                        $this->fields['order_by'] = 'IFNULL(p.count, 0)*p.price AS stock_worth';
                        $this->order_by = 'stock_worth '.$order.', p.id';
                    } else {
                        return $this->order_by = 'p.'.$field." ".$order.', p.id';
                    }
                } elseif ($field == 'sort') {
                    $this->getSQL();
                    return '';
                } elseif (strtolower($field) == 'rand()') {
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
            return [
                0 => null,
                1 => null,
            ];
        } else {
            $order_all = explode(',', $this->order_by);
            $order = explode(' ', trim(end($order_all)));
            if ($order[0] == 'p.id' && count($order_all) > 1) {
                // Ignore last part of order by id
                // It is simply a technical way to make the ordering stable.
                array_pop($order_all);
                $order = explode(' ', trim(end($order_all)));
            }
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
     * @throws waException
     */
    public function getSQL()
    {
        $this->prepare();

        if (!$this->prepared_event) {
            /**
             * Products collection after prepare
             *
             * @event products_collection.prepared
             * @param shopProductsCollection $this
             */
            $this->prepared_event = true;
            if (empty($this->options['no_plugins'])) {
                wa('shop')->event('products_collection.prepared', $this);
            }
        }

        $sql = "FROM shop_product p";

        if ($this->joins) {
            foreach ($this->joins as $join) {
                $alias = isset($join['alias']) ? $join['alias'] : '';
                if (isset($join['on'])) {
                    $on = $join['on'];
                } else {
                    $on = "p.id = ".($alias ? $alias : $join['table']).".product_id";
                }
                $sql .= "\n\t".(isset($join['type']) ? $join['type'].' ' : '')."JOIN ".$join['table']." ".$alias."\n\t\tON ".$on;
            }
        }

        $where = $this->where;

        if ($where) {
            $sql .= "\nWHERE ".implode("\n\tAND ", $where);
        }
        return $sql;
    }

    /**
     * Returns number of products included in collection.
     *
     * @return int
     * @throws waException
     */
    public function count()
    {
        if ($this->count !== null) {
            return $this->count;
        }

        $from_and_where = $this->getSQL();
        if ($this->having) {
            $distinct = $this->joins && !$this->group_by ? 'DISTINCT ' : '';
            $sql = "SELECT ".$distinct.$this->getFields('*')."\n";
            $sql .= $from_and_where;
            $sql .= $this->_getGroupBy();
            $sql .= " HAVING ".implode(' AND ', $this->having);
            $sql = "SELECT COUNT(*) FROM (".$sql.") AS t";
        } else {
            $sql = "SELECT COUNT(DISTINCT p.id) ".$from_and_where;
        }
        $count = (int)$this->getModel()->query($sql)->fetchField();

        if (($this->hash[0] == 'category' && !empty($this->info['id']) && $this->info['type'] == shopCategoryModel::TYPE_DYNAMIC)
            || (!empty($this->info['main_filter_type']) && $this->info['main_filter_type'] == 'category'
                && $this->info['main_filter_data']['type'] == shopCategoryModel::TYPE_DYNAMIC)
        ) {
            if (!$this->is_frontend && ((isset($this->info['count']) && $this->info['count'] != $count)
                || (isset($this->info['main_filter_data']['count']) && $this->info['main_filter_data']['count'] != $count))
            ) {
                $this->getModel('category')->updateById($this->hash[1], array('count' => $count));
            }
        } elseif (($this->hash[0] == 'set' && !empty($this->info['id']))
            || (!empty($this->info['main_filter_type']) && $this->info['main_filter_type'] == 'set')
        ) {
            if ($this->hash[0] == 'set') {
                if ($this->info['type'] == shopSetModel::TYPE_DYNAMIC) {
                    $count = min($this->info['count'], $count);
                } elseif ($this->info['count'] != $count && !$this->is_frontend) {
                    $this->getModel('set')->updateById($this->hash[1], array('count' => $count));
                }
            } else {
                if ($this->info['main_filter_data']['type'] == shopSetModel::TYPE_DYNAMIC) {
                    $count = min($this->info['main_filter_data']['count'], $count);
                } elseif ($this->info['main_filter_data']['count'] != $count && !$this->is_frontend) {
                    $this->getModel('set')->updateById($this->info['main_filter_data']['id'], array('count' => $count));
                }
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
     * @throws waException
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

        // This builds body of the query and also
        // prepare()s $this instance if not prepared yet.
        $from_and_where = $this->getSQL();

        // for dynamic set
        if ($this->hash[0] == 'set' && !empty($this->info['id']) && $this->info['type'] == shopSetModel::TYPE_DYNAMIC) {
            $this->count();
            if ($offset + $limit > $this->count) {
                $limit = $this->count - $offset;
            }
        }

        $distinct = $this->joins && !$this->group_by ? 'DISTINCT ' : '';

        $sql = "SELECT ".$distinct.$this->getFields($fields)."\n";
        $sql .= $from_and_where;
        $sql .= $this->_getGroupBy();
        if ($this->having) {
            $sql .= "\nHAVING ".implode(' AND ', $this->having);
        }
        $sql .= $this->_getOrderBy();
        $sql .= "\nLIMIT ".($offset ? $offset.',' : '').(int)$limit;

        $data = $this->getModel()->query($sql)->fetchAll('id');
        if (!$data) {
            return array();
        }
        $this->workupProducts($data, $escape);
        return $data;
    }

    public function getAllUnitIds()
    {
        $sql = "SELECT DISTINCT base_unit_id, stock_unit_id\n".$this->getSQL();

        $stock_units_ids = [];
        $all_base_unit_ids = [];
        $base_unit_ids = [];
        foreach($this->getModel()->query($sql) as $row) {
            $stock_units_ids[$row['stock_unit_id']] = $row['stock_unit_id'];
            $all_base_unit_ids[$row['base_unit_id']] = $row['base_unit_id'];
            if ($row['base_unit_id'] != $row['stock_unit_id']) {
                $base_unit_ids[$row['base_unit_id']] = $row['base_unit_id'];
            }
        }

        // $stock_units_ids   - all stock units in this collection
        // $all_base_unit_ids - all base units in this collection
        // $base_unit_ids     - base units when they don't match product's stock unit
        // (When base unit matches product's stock unit it means base unit for this product is not set explicitly by user.)
        return [$stock_units_ids, $base_unit_ids, $all_base_unit_ids];
    }

    /**
     * Sets $p['category_url'] for each product in list,
     * based on its $p['category_id']
     * @param mixed[] $products
     * @throws waException
     */
    protected function updateCategoryUrls(&$products)
    {
        /*
         * We need to build a full frontend URL for each product,
         * which for url_type=2 includes the category breadcrumbs.
         * Each product has the main category_id that is used to build the URL.
         *
         * Sometimes the main category of a product is set up not to be visible
         * in current frontend settlement. For such products we will build the URL
         * as if the product has no main category set.
         */

        $cat_ids = array();
        foreach ($products as &$p) {
            if (!empty($p['category_id'])) {
                $cat_ids[$p['category_id']] = $p['category_id'];
            }
        }
        unset($p);
        if (!$cat_ids) {
            return;
        }

        // Figure out which categories are disabled for current settlement
        $category_disabled = array();
        $route = wa()->getRouting()->getDomain(null, true).'/'.wa()->getRouting()->getRoute('url');
        $category_routes = $this->getModel('categoryRoutes')->getRoutes(array_values($cat_ids), false);
        foreach ($category_routes as $category_id => $routes) {
            if ($routes && !in_array($route, $routes)) {
                $category_disabled[$category_id] = true;
            }
        }
        unset($category_routes);

        // Set empty main category for products when it's disabled.
        // This generates a short URL for such products (i.e. no category breadcrumbs).
        foreach ($products as &$p) {
            if (!empty($p['category_id']) && !empty($category_disabled[$p['category_id']])) {
                $p['category_id'] = null;
            }
        }
        unset($p);

        // For products with no main category (or main category disabled in current settlement),
        // there's an experimental option to use URL from some other category the product is in.
        // This can be DB-heavy, so it's disabled by default.
        if (waRequest::param('correct_category_urls') || !empty($this->options['correct_category_urls'])) {
            // Products that need replacement of main category
            $product_ids = array();
            foreach ($products as $p) {
                if (empty($p['category_id'])) {
                    $product_ids[] = $p['id'];
                }
            }

            // Fetch info about which products are in which categories
            $more_cat_ids = array();
            $product_categories = array();
            if ($product_ids) {
                $rows = $this->getModel('categoryProducts')->getByField('product_id', $product_ids, true);
                foreach ($rows as $row) {
                    $product_categories[$row['product_id']][] = $row['category_id'];
                    $more_cat_ids[$row['category_id']] = $row['category_id'];
                }
                unset($rows, $row, $product_ids);
            }

            // Fetch info about which categories are disabled in current storefront
            $more_cat_ids = array_values(array_diff_key($more_cat_ids, $cat_ids));
            if ($more_cat_ids) {
                $category_routes = $this->getModel('categoryRoutes')->getRoutes($more_cat_ids, false);
                foreach ($category_routes as $category_id => $routes) {
                    if ($routes && !in_array($route, $routes)) {
                        $category_disabled[$category_id] = true;
                    }
                }
                unset($category_routes, $more_cat_ids);
            }

            // Update main categories of products, selecting any category
            // that is enabled for current frontend settlement.
            if ($product_categories) {
                foreach ($product_categories as $p_id => $pcats) {
                    foreach (ifempty($pcats, array()) as $category_id) {
                        if (empty($category_disabled[$category_id])) {
                            $cat_ids[$category_id] = $category_id;
                            $products[$p_id]['category_id'] = $category_id;
                            break;
                        }
                    }
                }
                unset($p);
            }
        }

        // Update products data. For products with valid frontend category, set the full URL.
        $cat_ids = array_values(array_diff_key($cat_ids, $category_disabled));
        $categories = $this->getModel('category')->getById($cat_ids);
        foreach ($products as &$p) {
            if (!empty($p['category_id']) && !empty($categories[$p['category_id']]['full_url'])) {
                $p['category_url'] = $categories[$p['category_id']]['full_url'];
            } else {
                $p['category_id'] = null;
            }
        }
        unset($p);
    }

    /**
     * @param array $products
     * @param bool $escape
     * @throws waDbException
     * @throws waException
     */
    private function workupProducts(&$products = array(), $escape = true)
    {
        if (empty($products)) {
            return;
        }
        $skus     = [];
        $rounding = [
            'price',
            'min_price',
            'max_price',
            'compare_price'
        ];

        /** Names of fields that must be converted to float values */
        $float = [
            'min_price',
            'max_price',
            'total_sales',
            'base_price_selectable',
            'rating',
            'price',
            'compare_price',
        ];

        $fetch_params = !empty($this->options['params']) || (!empty($this->post_fields['_internal']) && in_array('params', $this->post_fields['_internal']));

        // Workup promo prices
        if (!empty($this->storefront_context)) {
            $this->promoProductPrices()->workupPromoProducts($products);
        }

        // Round prices for products
        $config = wa('shop')->getConfig();
        /** @var shopConfig $config */
        $default_currency = $config->getCurrency(true);
        $frontend_currency = null;
        if ($this->is_frontend) {
            $frontend_currency = $config->getCurrency(false);
            if (!empty($this->options['round_prices'])) {
                shopRounding::roundProducts($products);
            }
        }

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
                // Striked-out price cannot be lower than actual price
                if (!empty($p['compare_price']) && $p['compare_price'] <= ifset($p['price'])) {
                    $p['compare_price'] = 0;
                }

                if (empty($this->options['round_prices'])) {
                    // Add the 'frontend_*' and 'unconverted_*' keys anyway
                    foreach ($rounding as $k) {
                        if (isset($p[$k])) {
                            $p['unconverted_'.$k] = $p[$k];
                            $p['frontend_'.$k] = shop_currency($p[$k], $default_currency, $frontend_currency, false);
                        }
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
            $this->updateCategoryUrls($products);
        }

        if ($this->post_fields) {

            $unprocessed = $this->post_fields;

            if (!empty($unprocessed['_internal'])) {
                $fields = array_fill_keys($unprocessed['_internal'], true);
                unset($unprocessed['_internal']);

                if (isset($fields['images']) || isset($fields['images2x'])) {
                    $fields['images'] = 1;
                    foreach ($products as &$p) {
                        $p['images'] = array();
                    }
                    unset($p);

                    $sizes = array();
                    $enabled_2x = isset($fields['images2x']) && wa('shop')->getConfig()->getOption('enable_2x');
                    foreach (array('thumb', 'crop', 'big') as $size) {
                        $sizes[$size] = $config->getImageSize($size);
                        if ($enabled_2x) {
                            $sizes[$size] .= '@2x';
                        }
                    }
                    $product_images_model = new shopProductImagesModel();
                    $product_images = $product_images_model->getImages(array_keys($products), $sizes, 'product_id');
                    foreach ($product_images as $product_id => $images) {
                        $products[$product_id]['images'] = $images;
                    }
                }
                if (isset($fields['image'])) {
                    $sizes = array();
                    foreach (array('thumb', 'crop', 'big') as $size) {
                        $sizes[$size] = $config->getImageSize($size);
                    }

                    $absolute_image_url = !empty($this->options['absolute_image_url']);

                    foreach ($products as &$p) {
                        if ($p['image_id']) {
                            $tmp = array(
                                'id'         => $p['image_id'],
                                'product_id' => $p['id'],
                                'filename'   => $p['image_filename'],
                                'ext'        => $p['ext']
                            );
                            foreach ($sizes as $size_id => $size) {
                                $p['image'][$size_id.'_url'] = shopImage::getUrl($tmp, $size, ifset($this->options['absolute'], $absolute_image_url));
                            }
                        } else {
                            foreach ($sizes as $size_id => $size) {
                                $p['image'] = null;
                            }
                        }
                    }
                    unset($p);
                }
                if (isset($fields['image_crop_small'])) {
                    $size = $config->getImageSize('crop_small');
                    foreach ($products as &$p) {
                        if ($p['image_id']) {
                            $tmp = array(
                                'id'         => $p['image_id'],
                                'product_id' => $p['id'],
                                'filename'   => $p['image_filename'],
                                'ext'        => $p['ext']
                            );
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
                        foreach ($product_images_model->countImages(array_keys($products)) as $product_id => $count) {
                            isset($products[$product_id]) && ($products[$product_id]['image_count'] = $count);
                        }
                    }
                }

                if (isset($fields['skus']) || isset($fields['sku_filtered']) || isset($fields['skus_filtered'])) {

                    // `sku_filtered` means: fetch exactly one SKU for each product that passed filtering;
                    // `skus_filtered` will fetch all SKUs that passed filtering;
                    // `skus` will fetch all SKUs
                    if (isset($fields['sku_filtered']) || isset($fields['skus_filtered'])) {

                        // sku_ids in products data (if present) are skus that passed filtering
                        // see $this->getFields(). If there are no such field, it means there's no filtering,
                        // and we fetch all SKUs below (same as $fields['skus'])
                        $skus = array();
                        foreach ($products as $product) {
                            if (!isset($product['sku_ids'])) {
                                $skus = [];
                                break; // no filtering; will fetch all SKUs
                            }
                            foreach (explode(',', $product['sku_ids']) as $sku_id) {
                                if ($sku_id) {
                                    $skus[$sku_id] = [
                                        'id' => $sku_id,
                                        'product_id' => $product['id']
                                    ];
                                }
                            }
                        }

                        // If list is filtered by features, we must not include SKUs
                        // that don't match all the feature conditions we're filtering against.
                        // SKU may match either because it has a needed feature
                        // or because a product has the feature and therefore all its SKUs have it, too.
                        if ($skus && $this->filtered_by_features) {

                            // Build a list of conditions like:
                            // feature_id=? AND feature_value_id IN (?)
                            $feature_conditions = array();
                            foreach ($this->filtered_by_features as $feature_id => $values) {
                                if (!$feature_id || !$values) {
                                    $feature_conditions = null;
                                    break;
                                }
                                $values = array_filter($values, 'wa_is_int');
                                $feature_conditions[] = "(feature_id = ".((int)$feature_id)." AND feature_value_id IN (".join(',', $values)."))";
                            }

                            if ($feature_conditions) {

                                // Find out which SKUs and products match which filters
                                $sql = "SELECT *
                                        FROM shop_product_features
                                        WHERE product_id IN (".join(',', array_keys($products)).")
                                            AND (".join("\n\tOR ", $feature_conditions).")";
                                foreach ($this->getModel()->query($sql) as $row) {
                                    if (!empty($row['sku_id'])) {
                                        if (!empty($skus[$row['sku_id']])) {
                                            // A single sku matches the filter by a single feature
                                            $skus[$row['sku_id']]['matches_feature'][$row['feature_id']] = true;
                                            // Remember that this product has a SKU that matches this feature
                                            $products[$row['product_id']]['feature_has_sku_match'][$row['feature_id']] = true;
                                        }
                                    } else {
                                        // The whole product matches filter by a feature
                                        $products[$row['product_id']]['matches_feature'][$row['feature_id']] = true;
                                    }
                                }

                                // Remove SKUs that do not match all the conditions
                                $match_count_needed = count($feature_conditions);
                                foreach ($skus as $sku_id => $sku) {
                                    // Take into account SKU matches of a feature
                                    $sku_matches = ifset($sku, 'matches_feature', []);

                                    // Take into account match of a product (in case SKU does not match)
                                    $product_matches = ifset($products, $sku['product_id'], 'matches_feature', []);
                                    // Ignore match of a product if another SKU of this product matches a feature
                                    $ignore_product_matches = ifset($products, $sku['product_id'], 'feature_has_sku_match', []);
                                    $product_matches = array_diff_key($product_matches, $ignore_product_matches);

                                    // Remove SKU if it matches less features than we filtered for
                                    if (count($sku_matches + $product_matches) < $match_count_needed) {
                                        unset($skus[$sku_id]);
                                    }
                                }
                            }
                        }

                        if (!isset($fields['skus_filtered'])) {
                            // sku_filtered: select one SKU to fetch for each product.
                            if ($skus) {
                                // Filtering is enabled; for each product select one SKU from several candidates
                                $product_ids = [];
                                $selected_skus = [];
                                foreach ($skus as $sku_id => $sku) {
                                    if (!isset($product_ids[$sku['product_id']])) {
                                        $selected_skus[$sku_id] = $sku;
                                        $product_ids[$sku['product_id']] = $sku_id;
                                    }
                                    if (isset($products[$sku['product_id']]) && $products[$sku['product_id']]['sku_id'] == $sku_id) {
                                        if (isset($product_ids[$sku['product_id']])) {
                                            unset($selected_skus[$product_ids[$sku['product_id']]]);
                                        }
                                        $selected_skus[$sku_id] = $sku;
                                        $product_ids[$sku['product_id']] = $sku_id;
                                    }
                                }
                                $skus = $selected_skus;
                            } else {
                                // no filtering is enabled: fetch main SKUs
                                $skus = [];
                                foreach ($products as $product) {
                                    $skus[$product['sku_id']] = [];
                                }
                            }
                        }
                    }

                    // Fetch SKU data.
                    // `$skus` array is made above if 'sku_filtered' or 'skus_filtered' is requested.
                    // $skus will be empty either if 'skus' is requested OR if 'skus_filtered' is requested but no filtering happened.
                    $skus_model = new shopProductSkusModel();
                    if (!empty($skus)) {
                        // 'sku_filtered' or 'skus_filtered' field: only fetch SKUs that passed filtering
                        $skus = $skus_model->getByField('id', array_keys($skus), 'id');
                    } else {
                        // 'skus' field: fetch all SKUs of selected products
                        $skus = $skus_model->getByField('product_id', array_keys($products), 'id');
                    }

                    if (!empty($this->storefront_context)) {
                        $this->promoProductPrices()->workupPromoSkus($skus, $products);
                    }

                    // Sort SKUs by `sort` field
                    uasort($skus, array($this, 'sortSkus'));

                    // Apply rounding to prices
                    if ($this->is_frontend && !empty($this->options['round_prices'])) {
                        shopRounding::roundSkus($skus, $products);
                    }

                    // Prepare products so that even products with no SKUs
                    // have certain keys
                    foreach ($products as &$p) {
                        $p['skus'] = array();
                        if (isset($fields['stock_counts'])) {
                            $p['has_stock_counts'] = false;
                        }
                    }
                    unset($p);
                    $empty_stocks = array();

                    // Fetch SKU stock counts
                    if (isset($fields['stock_counts'])) {
                        $stock_model = new shopStockModel();
                        $stocks = $stock_model->getAll('id');
                        $empty_stocks = array_fill_keys(array_keys($stocks), null);

                        $product_stocks_model = new shopProductStocksModel();
                        $rows = $product_stocks_model->getByField('product_id', array_keys($products), true);
                        foreach ($rows as $row) {
                            if (!empty($skus[$row['sku_id']])) {
                                $skus[$row['sku_id']]['stock'][$row['stock_id']] = $row['count'];
                            }
                            if (!empty($products[$row['product_id']])) {
                                $products[$row['product_id']]['has_stock_counts'] = true;
                            }
                        }
                        unset($rows, $row);
                    }

                    // Gather SKU image ids and fetch them from DB
                    if (isset($fields['skus_image'])) {
                        $images = $image_ids = array();
                        foreach ($skus as $s) {
                            if (!empty($s['image_id'])) {
                                $image_ids[$s['image_id']] = $s['image_id'];
                            }
                        }
                        if ($image_ids) {
                            $image_model = new shopProductImagesModel();
                            $images = $image_model->getById(array_values($image_ids));
                        }
                    }

                    // Write everything to product
                    foreach ($skus as &$s) {
                        if (empty($products[$s['product_id']])) {
                            continue;
                        }

                        // Prepare SKU prices
                        if (isset($s['price'])) {
                            $s['price_float'] = (float)$s['price'];
                        }
                        if (isset($s['purchase_price'])) {
                            $s['purchase_price_float'] = (float)$s['purchase_price'];
                        }
                        if (isset($s['compare_price'])) {
                            $s['compare_price_float'] = (float)$s['compare_price'];
                        }
                        if (isset($s['primary_price'])) {
                            $s['primary_price_float'] = (float)$s['primary_price'];
                        }

                        // Write stock counts to SKU
                        if (isset($fields['stock_counts'])) {
                            if (empty($products[$s['product_id']]['has_stock_counts'])) {
                                $s['stock'] = null;
                            } elseif (isset($s['stock'])) {
                                $s['stock'] = ifempty($s['stock'], array()) + $empty_stocks;
                            } else {
                                $s['stock'] = null;
                            }
                        }

                        // Write image to SKU
                        if (isset($fields['skus_image'])) {
                            if ($s['image_id'] && isset($images[$s['image_id']])) {
                                $s['ext'] = $images[$s['image_id']]['ext'];
                                $s['image_filename'] = $images[$s['image_id']]['filename'];
                                $s['image_description'] = $images[$s['image_id']]['description'];
                            } else {
                                $s['ext'] = $s['image_filename'] = $s['image_description'] = $s['image_id'] = null;
                            }
                        }

                        // Write SKU to product
                        $products[$s['product_id']]['skus'][$s['id']] = $s;
                    }
                    unset($s);
                    $products = $this->updateMainProductImage($products);
                }
                if (isset($fields['sku'])) {
                    $sku_ids = array();
                    foreach ($products as $p) {
                        $sku_ids[] = $p['sku_id'];
                    }
                    $skus_model = new shopProductSkusModel();
                    $skus = $skus_model->getByField('id', $sku_ids, 'id');
                    foreach ($products as &$p) {
                        $p['sku'] = ifset($skus[$p['sku_id']]['sku'], '');
                    }
                    unset($p);
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
                    $default_currency = $config->getCurrency(true);
                    $sql = "SELECT product_id, SUM(oi.price*oi.quantity*o.rate)
                            FROM shop_order_items AS oi
                                JOIN shop_order AS o
                                    ON oi.order_id=o.id
                            WHERE oi.product_id IN (?)
                                AND oi.type='product'
                                AND o.paid_date >= ?
                            GROUP BY product_id";
                    $sales = $this->getModel()->query($sql, array(array_keys($products), date('Y-m-d', time() - 3600 * 24 * 30)))->fetchAll('product_id', true);
                    foreach ($products as &$p) {
                        $p['sales_30days'] = (float)ifset($sales[$p['id']], 0.0);
                        $p['sales_30days_html'] = empty($p['sales_30days']) ? '' : '<span class="nowrap">'.
                            shop_currency_html($p['sales_30days'], $default_currency, $default_currency).
                            '</span>';
                    }
                    unset($p);
                }
                if (isset($fields['stock_worth'])) {
                    $default_currency = $config->getCurrency(true);
                    $sql = "SELECT s.product_id, SUM(s.primary_price*s.count) AS net_worth
                            FROM shop_product_skus AS s
                            WHERE s.product_id IN (?)
                            GROUP BY s.product_id";
                    $stock_worth = $this->getModel()->query($sql, array(array_keys($products)))->fetchAll('product_id', true);
                    foreach ($products as &$p) {
                        $p['stock_worth'] = (float)ifset($stock_worth[$p['id']], 0.0);
                        $p['stock_worth_html'] = empty($p['stock_worth']) ? '' : '<span class="nowrap">'.
                            shop_currency_html($p['stock_worth'], $default_currency, $default_currency).
                            '</span>';
                    }
                    unset($p);
                }
            }

            // features
            if (!empty($unprocessed['_features'])) {
                $feature_ids = [];
                $feature_codes = array_keys($unprocessed['_features']);
                foreach($feature_codes as $f) {
                    if (is_numeric($f)) {
                        $feature_ids[$f] = $f;
                    }
                }
                unset($unprocessed['_features']);

                // product_id => feature_id => array(value => ..., value_html => ...)
                $feature_values = array();

                // sku_id => feature_id => array(value => ..., value_html => ...)
                $feature_values_skus = array();

                $feature_model = new shopFeatureModel();
                $features = $feature_model->getByField('code', $feature_codes, 'id');
                if ($feature_ids) {
                    $features += $feature_model->getByField('id', $feature_ids, 'id');
                }
                if ($features) {

                    // Get feature_value_ids for all products
                    $sql = "SELECT pf.*
                            FROM shop_product_features AS pf
                            WHERE pf.product_id IN (?)
                                AND pf.feature_id IN (?)";
                    $product_features = $this->getModel()->query($sql, array(
                        array_keys($products),
                        array_keys($features),
                    ));

                    // Prepare list of value_ids to fetch later, and places to fetch them from
                    $storages = array(); // feature type => feature_value_id => product_id => list of sku ids
                    foreach ($product_features as $row) {
                        $f = $features[$row['feature_id']];
                        $type = preg_replace('/\..*$/', '', $f['type']);
                        if ($type == shopFeatureModel::TYPE_BOOLEAN) {
                            /** @var shopFeatureValuesBooleanModel $model */
                            $model = shopFeatureModel::getValuesModel($type);
                            $values = $model->getValues('id', $row['feature_value_id']);
                            $feature_values[$row['product_id']][$row['feature_id']]['value'] = reset($values);
                            if (!empty($row['sku_id'])) {
                                $feature_values_skus[$row['sku_id']][$row['feature_id']]['value'] = reset($values);
                            }
                        } elseif ($type == shopFeatureModel::TYPE_DIVIDER) {
                            // ignore dividers
                        } else {
                            if (!isset($storages[$type][$row['feature_value_id']][$row['product_id']])) {
                                $storages[$type][$row['feature_value_id']][$row['product_id']] = [];
                            }
                            if (!empty($row['sku_id'])) {
                                $storages[$type][$row['feature_value_id']][$row['product_id']][$row['sku_id']] = $row['sku_id'];
                            }
                        }
                    }

                    // Fetch actual values from shop_feature_values_* tables
                    foreach ($storages as $type => $value_products) {
                        $model = shopFeatureModel::getValuesModel($type);
                        foreach ($model->getValues('id', array_keys($value_products)) as $feature_id => $values) {
                            if (isset($features[$feature_id])) {
                                $f = $features[$feature_id];
                                foreach ($values as $value_id => $value) {
                                    foreach ($value_products[$value_id] as $product_id => $sku_ids) {
                                        if (!empty($f['multiple'])) {
                                            $feature_values[$product_id][$feature_id]['value'][] = $value;
                                            foreach ($sku_ids as $sku_id) {
                                                $feature_values_skus[$sku_id][$feature_id]['value'][] = $value;
                                            }
                                        } else {
                                            $feature_values[$product_id][$feature_id]['value'] = $value;
                                            foreach ($sku_ids as $sku_id) {
                                                $feature_values_skus[$sku_id][$feature_id]['value'] = $value;
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }

                    // Prepare value_html for each feature value
                    foreach ($feature_values as &$fv) {
                        foreach ($fv as $feature_id => &$arr) {
                            if (is_array($arr['value'])) {
                                $arr['value_html'] = join(', ', $arr['value']);
                            } else {
                                $arr['value_html'] = (string)$arr['value'];
                            }
                        }
                    }
                    unset($fv, $arr);
                    foreach ($feature_values_skus as &$fv) {
                        foreach ($fv as $feature_id => &$arr) {
                            if (is_array($arr['value'])) {
                                $arr['value_html'] = join(', ', $arr['value']);
                            } else {
                                $arr['value_html'] = (string)$arr['value'];
                            }
                        }
                    }
                    unset($fv, $arr);
                }

                // Finally, assign feature data to actual products and skus
                foreach ($products as &$p) {
                    foreach ($feature_ids as $fid) {
                        $p['feature_'.$fid] = ifset($feature_values[$p['id']][$fid]['value']);
                        $p['feature_'.$fid.'_html'] = ifset($feature_values[$p['id']][$fid]['value_html'], ifempty($p, 'feature_'.$fid, ''));
                        if (!empty($p['skus'])) {
                            foreach ($p['skus'] as $sku_id => &$sku) {
                                $sku['feature_'.$fid] = ifset($feature_values_skus[$sku['id']][$fid]['value']);
                                $sku['feature_'.$fid.'_html'] = ifset($feature_values_skus[$sku['id']][$fid]['value_html'], ifempty($sku, 'feature_'.$fid, ''));
                            }
                        }
                    }
                    foreach ($features as $fid => $f) {
                        $p['feature_'.$f['code']] = ifset($feature_values[$p['id']][$fid]['value']);
                        $p['feature_'.$f['code'].'_html'] = ifset($feature_values[$p['id']][$fid]['value_html'], ifempty($p, 'feature_'.$f['code'], ''));
                        if (!empty($p['skus'])) {
                            foreach ($p['skus'] as $sku_id => &$sku) {
                                $sku['feature_'.$f['code']] = ifset($feature_values_skus[$sku['id']][$fid]['value']);
                                $sku['feature_'.$f['code'].'_html'] = ifset($feature_values_skus[$sku['id']][$fid]['value_html'], ifempty($sku, 'feature_'.$f['code'], ''));
                            }
                        }
                    }
                }
                unset($p);
            }

            // Throw event if there's something left to fetch
            if ($unprocessed) {
                // !!!
            }
        }

        if ($this->is_frontend) {

            if (wa('shop')->getSetting('limit_main_stock')) {
                $public_stocks = array(waRequest::param('stock_id'));
            } else {
                // When storefront is only limited to certain stock, recalculate product counts
                $public_stocks = waRequest::param('public_stocks');
                if (!is_array($public_stocks)) {
                    $public_stocks = $this->getVisibleStocks();
                }
            }

            if (!empty($public_stocks)) {

                $public_stocks = $this->checkIsVirtualStock($public_stocks);

                // List of all stocks
                $stock_model = new shopStockModel();
                $stocks = $stock_model->getAll('id');

                // For each product make a list of stock counts
                // product_id => sku_id => stock_id => count
                $stock_counts = array();
                $spsm = new shopProductStocksModel();
                $rows = $spsm->getByField(array(
                    'product_id' => array_keys($products),
                ), true);
                foreach ($rows as $row) {
                    $stock_counts[$row['product_id']][$row['sku_id']][$row['stock_id']] = $row['count'];
                }

                // When there is no record in shopProductStocksModel for a sku_id+stock_id combination,
                // but there is at least one record for this product_id it means infinite supply
                // of this sku_id on this stock.
                // We take these infinite counts into account by adding NULLs to $stock_counts.
                $infinite_stocks = array_fill_keys(array_keys($stocks), null);
                $zero_stocks = array_fill_keys(array_keys($stocks), 0);
                foreach ($stock_counts as &$p) {
                    // Add stock_id => null (infinity) records to all existing skus
                    foreach ($p as &$s) {
                        $s += $infinite_stocks;
                    }
                }
                unset($p, $s);

                $ignore_stock_count = wa('shop')->getSetting('ignore_stock_count');
                foreach ($products as $product_id => $product) {
                    foreach (ifset($product, 'skus', array()) as $sku_id => $sku) {
                        if (!is_array($sku)) {
                            continue; // being paranoid
                        }
                        // Add [ stock_id => null ] (infinity) for SKUs that do not use by-stock accounting
                        // but have a positive stock set in SKU itself
                        if (empty($stock_counts[$product_id][$sku_id])) {
                            if (array_key_exists('count', $sku)) {
                                if ($sku['count'] === null || $sku['count'] > 0) {
                                    $stock_counts[$product_id][$sku_id] = $infinite_stocks;
                                } else {
                                    $stock_counts[$product_id][$sku_id] = $zero_stocks;
                                }
                            }
                        }
                        // Ignore SKUs that are not available for order
                        if (!$ignore_stock_count && !empty($stock_counts[$product_id][$sku_id])
                            && (array_key_exists('available', $sku) && empty($sku['available'])
                                || array_key_exists('status', $sku) && empty($sku['status'])
                            )
                        ) {
                            $stock_counts[$product_id][$sku_id] = $zero_stocks;
                        }
                    }
                }
            }

            $reviews_count = [];
            if (isset($fields['reviews_count'])) {
                $reviews_count = $this->getCountProductReviews($products);
            }
            foreach ($products as $p_id => $p) {
                if (isset($p['price'])) {
                    $products[$p_id]['original_price'] = $p['price'];
                }
                if (isset($p['compare_price'])) {
                    $products[$p_id]['original_compare_price'] = $p['compare_price'];
                }

                // For each product calculate counts with respect to $public_stocks visible in current storefront.
                // When product does not use stocks, and actual counts are stored in sku.count,
                // this logic returns null (infinity). This is not currently considered a bug.
                // Storefront limited by main stock should not list products that does not use stocks.
                // See also: shopCartItemsModel->checkAvailability()
                if (!empty($public_stocks)) {
                    $products[$p_id]['count'] = $this->countOfSelectedStocks($public_stocks, ifempty($stock_counts, $p_id, null));
                }

                if (isset($reviews_count[$p_id])) {
                    $products[$p_id]['reviews_count'] = $reviews_count[$p_id]['reviews_count'];
                }
            }
        }

        // Replace product prices with those from SKU. This may be used when
        // some SKUs are filtered out by collection conditions.
        if (!empty($this->options['overwrite_product_prices'])) {
            foreach ($products as &$product) {
                if (empty($product['skus'])) {
                    continue;
                }

                $sku = null;

                // Use primary SKU if it exists in selection
                // (it might not be there if filtered out)
                if (!empty($product['sku_id'])) {
                    $sku = ifset($product, 'skus', $product['sku_id'], null);
                }

                //
                // In case main SKU is filtered out, we look whether any SKU
                // is available for order. If some are, we select the cheapest from them.
                // If no SKU are available, we select cheapest from all.
                //

                // sku_id => sku price in primary currency
                $prices = array();
                // sku_id => sku price in primary currency (only for SKUs that are available for order)
                foreach ($product['skus'] as $id => $s) {
                    $prices[$id] = $s['primary_price'];
                }

                // When main SKU is filtered out, use the cheapest.
                if ($prices && !$sku) {
                    $sku_id = array_search(min($prices), $prices);
                    $sku = ifset($product, 'skus', $sku_id, null);
                }

                // Replace product prices with selected SKU price
                if ($sku && (empty($product['sku_id']) || $sku['id'] != $product['sku_id'])) {
                    // Replace sku_id
                    $product['default_sku_id'] = $product['sku_id'];
                    $product['sku_id'] = $sku['id'];

                    // Replace price
                    $product['price'] = $sku['primary_price'];

                    // Replace compare_price
                    if (!empty($product['currency'])) {
                        $sku = ifset($product, 'skus', $sku['id'], null);
                        if ($sku && array_key_exists('compare_price', $sku)) {
                            $product['compare_price'] = shop_currency($sku['compare_price'], $product['currency'], $default_currency, false);
                        }
                    }

                    // Replace image
                    $this->replaceMainProductImage($product, $sku);

                    // Show full url for product (product+sku)
                    // if set up in settlement parameters
                    if ($this->is_frontend && !empty($product['frontend_url'])) {
                        $product['frontend_url'] .= '?sku='.$sku['id'];
                    }
                } elseif ($sku && $this->filtered_by_features && count($product['skus']) != $product['sku_count']) {
                    $this->replaceMainProductImage($product, $sku);
                }
            }
            unset($product);
        }

        if (!empty($this->options['defrac_counts'])) {
            foreach ($products as &$p) {
                if (array_key_exists('count', $p)) {
                    $p['count'] = shopFrac::discardZeros($p['count']);
                }
                if (!empty($p['skus'])) {
                    foreach ($p['skus'] as &$s) {
                        $s['count'] = shopFrac::discardZeros($s['count']);
                        if (isset($skus[$s['id']]) && array_key_exists('count', $s)) {
                            $skus[$s['id']]['count'] = $s['count'];
                        }
                    }
                }
            }
            unset($p, $s);
        }

        if ($this->is_frontend && empty($this->options['no_plugins']) && empty($this->options['no_plugins_frontend_products'])) {
            wa('shop')->event('frontend_products', ref([
                'products' => &$products,
                'skus'     => &$skus,
            ]));
        }

        if (is_array($skus)) {
            foreach ($skus as $sku_id => $sku_par) {
                if (isset($products[$sku_par['product_id']], $products[$sku_par['product_id']]['skus'][$sku_id])) {
                    $products[$sku_par['product_id']]['skus'][$sku_id] = array_merge($products[$sku_par['product_id']]['skus'][$sku_id], $sku_par);
                }
            }
        }
    }

    /**
     * Gets the number of reviews for each product
     * @param array $products
     * @return array
     */
    protected function getCountProductReviews($products)
    {
        $product_ids = array_map('intval', array_keys($products));
        $reviews_count = [];
        if ($product_ids) {
            $reviews_model = new shopProductReviewsModel();
            $review_where = "`review_id` = 0 AND `status` = '" . $reviews_model::STATUS_PUBLISHED . "' AND `product_id` IN (?)";
            $sql = "SELECT `product_id`, COUNT(`id`) AS `reviews_count`
                    FROM {$reviews_model->getTableName()}
                    WHERE {$review_where}
                    GROUP BY `product_id`";
            $reviews_count = $reviews_model->query($sql, [$product_ids])->fetchAll('product_id');
        }

        return $reviews_count;
    }

    /**
     * Updates the main product image, if used filtering by SKU
     *
     * @param array $products
     * @return array
     */
    protected function updateMainProductImage($products = array())
    {
        if ($this->filtered_by_features) {
            foreach ($products as &$product) {
                // Updates only if 1 sku is found.
                if (isset($product['skus']) && count($product['skus']) == 1) {
                    $main_sku = reset($product['skus']);
                    $this->replaceMainProductImage($product, $main_sku);
                }
            }
            unset($product);
        }

        return $products;
    }

    /**
     * @param array $product
     * @param array $sku
     */
    protected function replaceMainProductImage(&$product, $sku)
    {
        if (!empty($sku['image_id']) && !empty($product['image_id']) && $product['image_id'] !== $sku['image_id']) {
            $product['image_id'] = $sku['image_id'];
            $product['ext'] = ifset($sku, 'ext', '');
            $product['image_filename'] = ifset($sku, 'image_filename', '');
            $product['image_description'] = ifset($sku, 'image_description', '');
        }
    }

    protected function sortSkus($a, $b)
    {
        if ($a['sort'] == $b['sort']) {
            return 0;
        }
        return ($a['sort'] < $b['sort']) ? -1 : 1;
    }

    /**
     * Gets all the stocks visible to the storefront.
     * If all stocks are visible, then the recalculation is not needed
     * @return array|null
     */
    protected function getVisibleStocks()
    {
        $all_stocks = shopHelper::getStocks();
        $visible_stocks = array();
        $is_all_visible = true;

        if ($all_stocks) {
            foreach ($all_stocks as $id => $stock) {
                if ($stock['public'] == 0) {
                    $is_all_visible = false;
                    continue;
                }

                //if it virtual stock
                if (!wa_is_int($id) && is_array($stock['substocks'])) {
                    $visible_stocks = array_merge($visible_stocks, $stock['substocks']);
                    continue;
                }
                $visible_stocks[] = (string)$id; //(string) - For the same formatting (To not surprise why int :)
            }

        }

        if ($is_all_visible) {
            return null;
        }

        return array_unique($visible_stocks);
    }

    /**
     * If there is a virtual warehouse in the array, you can get its physical stocks.
     * @param $public_stocks
     * @return array
     */
    protected function checkIsVirtualStock($public_stocks)
    {
        $v_stock = null;
        foreach ($public_stocks as $key => $stock) {
            if (wa_is_int($stock)) {
                continue;
            }

            //get id virtual stock
            $v_stock[] = substr($stock, 1);
            unset($public_stocks[$key]);
        }

        if ($v_stock) {
            $svsm = new shopVirtualstockStocksModel();
            $stocks = $svsm
                ->select('DISTINCT stock_id')
                ->where('virtualstock_id IN (i:v_stock)', compact('v_stock'))
                ->fetchAll('stock_id');

            $v_stock = array_keys($stocks);
            $public_stocks = array_unique(array_merge($public_stocks, $v_stock));
        }
        return $public_stocks;
    }

    /**
     * Count product supply taking into account stocks visible in current storefront.
     * @param $public_stocks
     * @param $product_stock_counts
     * @return int|null
     */
    protected function countOfSelectedStocks($public_stocks, $product_stock_counts)
    {
        $count = null;
        if ($product_stock_counts) {
            foreach ($product_stock_counts as $sku_id => $stocks) {
                foreach ($stocks as $stock_id => $c) {
                    if (in_array($stock_id, $public_stocks)) {
                        if ($c === null) {
                            return null;
                        }
                        $count += $c;
                    }
                }
            }
        }
        return $count;
    }

    /**
     * @param string $name
     * @return shopProductModel|shopCategoryModel|shopTagModel|shopSetModel|shopFeatureModel|shopCategoryRoutesModel|shopCategoryProductsModel|shopProductPromoPriceTmpModel|shopFilterRulesModel
     */
    protected function getModel($name = 'product')
    {
        if (!isset($this->models[$name])) {
            $available_models = [
                'product',
                'category',
                'tag',
                'set',
                'feature',
                'categoryRoutes',
                'categoryProducts',
                'productPromoPriceTmp',
                'filterRules',
            ];
            if (in_array($name, $available_models)) {
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
     * @throws waException
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
     * @throws waException
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
     * Add a condition for filter by rights
     *
     * Take into account:
     *   - admin accesses (webasyst, shop)
     *   - not access to shop at all
     *   - meta-right 'type.all'
     *   - default cases when in DB no yet 'type.%d+' records
     *   - cases when there are somehow 'type.%d+' records of deleted types in DB (some inconsistent that could be in real world)
     *   - 'delete' right - can delete when right level to product type >= shopRightConfig::RIGHT_FULL OR if == shopRightConfig::RIGHT_EDIT that can delete own products
     *
     * @param mixed $right_level
     * @throws waDbException
     * @throws waException
     */
    protected function addWhereByRights($right_level)
    {
        $user = wa()->getUser();

        $contact_id = $user->getId();

        $min_right_level = null;
        $check_delete = false;

        if ($right_level === true) {
            $min_right_level = shopRightConfig::RIGHT_EDIT;
        } elseif (wa_is_int($right_level)) {
            $min_right_level = (int)$right_level;
        } elseif ($right_level === 'delete') {
            $min_right_level = shopRightConfig::RIGHT_FULL;
            $check_delete = true;
        }

        // this is backend super admin - full access to products
        if ($user->isAdmin('webasyst')) {
            return;
        }

        // this is backend shop admin - full access to products
        if ($user->isAdmin('shop')) {
            return;
        }

        // shop rights
        $rights = $user->getRights('shop');

        // type.all meta-right rule exists
        if (isset($rights['type.all'])) {

            // If check delete right, then check contact_id in shopRightConfig::RIGHT_EDIT level
            // Otherwise if right less min_right_level 'close access' (by where 0)
            // Otherwise not mix-in any condition (all products are available)
            if ($check_delete && $rights['type.all'] == shopRightConfig::RIGHT_EDIT) {
                $this->addWhere("p.contact_id = {$contact_id}");
            } elseif ($rights['type.all'] < $min_right_level) {
                $this->addWhere(0);
            }

            // type.all check stops here - no go further
            return;
        }

        // get type ids now in DB
        $type_model = new shopTypeModel();
        $all_type_ids = $type_model->getAll(null, true);

        // and mix-in type ids extracted from 'type.%d+' records of deleted types in DB (some inconsistent that could be in real world)
        foreach ($rights as $right_name => $level) {
            if (substr($level, 0, 5) === 'type.') {
                $type_id = substr($level, 5);
                if (is_numeric($type_id)) {
                    $all_type_ids[] = intval($type_id);
                }
            }
        }

        $all_type_ids = array_unique($all_type_ids);

        // collect allowed types
        $allowed_types = [];
        $allowed_own_types = [];

        foreach ($all_type_ids as $type_id) {
            $right_name = "type.{$type_id}";

            $level = shopRightConfig::RIGHT_READ;   // this is default access right ( default cases when in DB no yet 'type.%d+' records )
            if (isset($rights[$right_name]) && wa_is_int($rights[$right_name])) {
                $level = $rights[$right_name];
            }

            // user can delete own products only in shopRightConfig::RIGHT_EDIT
            if ($check_delete && $level == shopRightConfig::RIGHT_EDIT) {
                $allowed_own_types[] = $type_id;
            } elseif ($level >= $min_right_level) {
                $allowed_types[] = $type_id;
            }

        }

        $where = array();
        if ($allowed_types) {
            $types_string = implode(',', $allowed_types);
            // has access to products of that types
            $where[] = "(p.type_id IN ({$types_string}))";
        }

        if ($allowed_own_types) {
            $types_string = implode(',', $allowed_own_types);
            // has access to products of that types
            $where[] = " (p.type_id IN ({$types_string}) AND p.contact_id = {$contact_id})";
        }

        $where = implode(' OR ', $where);
        if ($where) {
            $this->addWhere($where);
        } else {
            $this->addWhere(0);
        }

    }

    protected function excludeProducts($product_ids)
    {
        $product_ids = array_filter(array_map('intval', $product_ids), function ($value) {
            return ($value > 0);
        });
        if (!empty($product_ids)) {
            $this->addWhere('(p.id NOT IN (' . implode(',', $product_ids) . '))');
        }
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
        $alias = $this->getAlias($table);
        if (is_array($table)) {
            if (isset($table['on'])) {
                $on = $table['on'];
            }
            if (isset($table['where'])) {
                $where = $table['where'];
            }
            if (isset($table['type'])) {
                $join_type = $table['type'];
            }
            $table = $table['table'];
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
        if (isset($join_type)) {
            $join['type'] = $join_type;
        }
        $this->joins[] = $join;
        if ($where) {
            $this->where[] = str_replace(':table', $alias, $where);
        }
        return $alias;
    }

    /**
     * Returns alias for current table
     *
     * @param $table
     * @return mixed|string
     */
    protected function getAlias($table)
    {
        $alias = '';

        if (is_array($table)) {
            if (isset($table['alias'])) {
                $alias = $table['alias'];
            }
            $table = $table['table'];
        }

        if (!$alias) {
            $t = explode('_', $table);
            foreach ($t as $tp) {
                if ($tp == 'shop') {
                    continue;
                }
                $alias .= substr($tp, 0, 1);
            }
        }

        if (!$alias) {
            $alias = $table;
        }

        return $alias;
    }


    /**
     *
     * @param $table_name
     * @return array
     */
    public function getJoinsByTableName($table_name)
    {
        $result = [];

        if ($this->joins) {
            foreach ($this->joins as $join) {
                if ($join['table'] === $table_name) {
                    $result[] = $join;
                }
            }
        }
        return $result;
    }

    /**
     * Add join with checking for uniqueness
     *
     * Optimization reason - prevent adding the same join twice
     * For example - in hierarchy of dynamic categories if conditions overlaps (see categoryPrepare)
     *
     * Almost all arguments required - because they will use for build lookup key for map
     *
     * Return alias of table
     *
     * @param string $table
     * @param string $on
     * @param string $where
     * @param string|null $type - can be skipped. By default join is inner
     *
     * @return string
     * @since 8.2.0
     */
    protected function addUniqueJoin($table, $on, $where, $type = null)
    {
        $join_key = join('+', func_get_args());
        if (empty($this->unique_joins[$join_key])) {
            $this->unique_joins[$join_key] = $this->addJoin(array(
                'table' => $table,
                'type'  => $type,
                'on'    => $on,
                'where' => $where
            ));
        }
        return $this->unique_joins[$join_key];
    }

    protected function addPromoPriceSort($field, $order)
    {
        $field = strtolower($field);
        if ($field !== 'compare_price' && $field !== 'base_price') {
            $field = 'price';
        }
        $order = strtoupper($order);
        if ($order !== 'DESC') {
            $order = 'ASC';
        }

        // Check promo prices
        $this->loadPromoPrices();
        if (!empty($this->storefront_context) && !empty($this->promo_prices[$this->storefront_context])) {
            $promo_prices_tmp_alias = $this->getPromoPricesTmpAlias();
            if (!$promo_prices_tmp_alias) {
                $promo_prices_tmp_alias = $this->addJoin([
                    'table' => 'shop_product_promo_price_tmp',
                    'type'  => 'LEFT',
                    'on'    => "p.id = :table.product_id AND p.sku_id = :table.sku_id AND :table.storefront = '".$this->getModel()->escape($this->storefront_context)."'",
                ]);
            }

            $this->order_by = "IFNULL({$promo_prices_tmp_alias}.primary_{$field}, p.{$field}) $order, p.id";
        }
    }

    protected function loadPromoPrices()
    {
        if (empty($this->storefront_context)) {
            return;
        }

        if (!empty($this->promo_prices[$this->storefront_context]) && is_array($this->promo_prices[$this->storefront_context])) {
            return;
        }
        // This has a side effect of populating tmp table shopProductPromoPriceTmpModel
        $this->promo_prices[$this->storefront_context] = $this->promoProductPrices()->getPromoPrices();
        $this->promo_skus[$this->storefront_context] = $this->promoProductPrices()->getPromoSkus();
    }

    /**
     * @return shopPromoProductPrices
     * @throws waException
     */
    protected function promoProductPrices()
    {
        static $promo_product_prices;

        // If there is no storefront - it makes no sense to create a temporary table (expensive).
        if (empty($this->storefront_context)) {
            throw new waException('Storefront required');
        }

        if (empty($promo_product_prices[$this->storefront_context])) {
            $promo_prices_model = $this->getModel('productPromoPriceTmp');
            $options = [
                'model'      => $promo_prices_model,
                'storefront' => $this->storefront_context,
            ];
            $promo_product_prices[$this->storefront_context] = new shopPromoProductPrices($options);
        }

        return $promo_product_prices[$this->storefront_context];
    }

    /**
     * If there is a join to the temporary table with promo-prices, the method will return the alias of the table.
     * @return null|string
     */
    protected function getPromoPricesTmpAlias()
    {
        $promo_price_joins = $this->getJoinsByTableName('shop_product_promo_price_tmp');
        return ifempty($promo_price_joins, 0, 'alias', null);
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
     * @param boolean $filtered
     * @return array
     * array(
     *     feature1_id => array(feature1_value1_id, feature1_value2_id, ...),
     *     feature2_id => array(feature2_value1_id, feature2_value2_id, ...),
     *     ...
     * )
     * @throws waException
     */
    public function getFeatureValueIds($filtered = true)
    {
        if (!$filtered) {
            unset($this->options['filters']);
            if ($this->filtered) {
                $this->fields = array();
                $this->where = null;
                $this->having = array();
                $this->count = null;
                $this->order_by = 'p.create_datetime DESC, p.id';
                $this->group_by = null;
                $this->joins = null;
                $this->join_index = array();
                $this->unique_joins = array();

                $this->post_fields = array();
                $this->prepared = false;
            }
        }

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
     * @throws waException
     */
    public function getPriceRange($unit_id = null)
    {
        // Check promo prices
        $promo_prices_tmp_alias = '';
        $this->loadPromoPrices();
        if (!empty($this->storefront_context) && !empty($this->promo_prices[$this->storefront_context])) {
            $promo_prices_tmp_alias = $this->getPromoPricesTmpAlias();
            if (!$promo_prices_tmp_alias) {
                $promo_prices_tmp_alias = $this->addJoin([
                    'table' => 'shop_product_promo_price_tmp',
                    'type'  => 'LEFT',
                    'on'    => "p.id = :table.product_id AND :table.storefront = '".$this->getModel()->escape($this->storefront_context)."'",
                ]);
            }
        }

        static $skus_alias = '';
        if ($unit_id && is_numeric($unit_id)) {
            $unit_id = (int)$unit_id;
            foreach ($this->where as $where_key => $condition) {
                if (strpos($condition, '(p.stock_unit_id = ') === 0
                    || strpos($condition, 'p.base_unit_id = ') === 0
                    || strpos($condition, 'p.stock_unit_id = ') === 0
                ) {
                    unset($this->where[$where_key]);
                }
            }
            $this->addWhere("(p.stock_unit_id = $unit_id OR p.base_unit_id = $unit_id)");

            if (!$skus_alias) {
                $skus_alias = $this->addJoin([
                    'table' => 'shop_product_skus',
                    'on' => ':table.product_id = p.id',
                    'where' => ':table.available > 0 AND :table.status > 0',
                ]);
            }
            if (!empty($promo_prices_tmp_alias)) {
                $price_field = "(IFNULL($promo_prices_tmp_alias.primary_price, $skus_alias.primary_price))";
            } else {
                $price_field = "$skus_alias.primary_price";
            }
            $values = "IF(p.stock_unit_id = $unit_id, $price_field, $price_field / IFNULL($skus_alias.stock_base_ratio, p.stock_base_ratio))";
            $select = "SELECT MIN($values) min, MAX($values) max ";
        } elseif (!empty($promo_prices_tmp_alias)) {
            $select = "SELECT MIN(IFNULL({$promo_prices_tmp_alias}.primary_price, p.min_price)) min, MAX(IFNULL({$promo_prices_tmp_alias}.primary_price, p.max_price)) max ";
        } else {
            $select = "SELECT MIN(p.min_price) min, MAX(p.max_price) max ";
        }

        $full_sql = $select.$this->getSQL();
        $data = $this->getModel()->query($full_sql)->fetch();
        return array(
            'min' => (double)(isset($data['min']) ? $data['min'] : 0),
            'max' => (double)(isset($data['max']) ? $data['max'] : 0)
        );
    }

    /**
     * @return bool
     * @throws waException
     */
    public function isPluginHash()
    {
        $this->prepare();
        if (!empty($this->completed_events['products_collection']) && is_array($this->completed_events['products_collection'])) {
            foreach ($this->completed_events['products_collection'] as $plugin_id => $event_processed) {
                if ($event_processed && strpos($plugin_id, '-plugin') !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return array
     */
    public function getPluginNames()
    {
        $names = [];
        $this->prepare();
        if (!empty($this->completed_events['products_collection']) && is_array($this->completed_events['products_collection'])) {
            foreach ($this->completed_events['products_collection'] as $plugin_id => $event_processed) {
                if ($event_processed) {
                    $name = $plugin_id;
                    if (strpos($plugin_id, '-plugin') !== false) {
                        $name = $plugin_id = str_replace('-plugin', '', $plugin_id);
                        try {
                            $name = _wp(wa('shop')->getPlugin($plugin_id, true)->getName());
                            waSystem::popActivePlugin();
                        } catch (waException $e) {
                        }
                    }
                    $names[] = $name;
                }
            }
        }

        return $names;
    }

    /**
     * Used to find previous and next row in this collection relative to given $product_id.
     *
     * $result['count'] is total number of products in this collection.
     *
     * $result['position'] is index of $product_id in this collection. First product in list
     * has position equal to 1. Last product in list has position equal to $result['count'].
     * When $result['position'] is NULL it means $product_id is not found in this collection.
     *
     * When $result['position'] is set but information about next product is missing,
     * i.e. $result['next']['id'] === null, it means that $product_id is the last in list.
     * Similarly, if $result['prev']['id'] === null, it means that $product_id is the first in list.
     *
     * @param int $product_id
     * @param bool $with_name if true, prev and next product names are returned along with ids
     * @return array @result (see above)
     * @since 9.3.0
     */
    public function getPrevNextProductId($product_id, $with_name = false)
    {
        $from_and_where = $this->getSQL();
        $group_by = $this->_getGroupBy();

        // This query is basically identical to main collection query
        $distinct = $group_by ? '' : 'DISTINCT ';
        $sql = "SELECT " . $distinct . 'p.id' . ($with_name ? ',p.name' : '') . ($this->fields ? ',' . implode(',', $this->fields) : '') . "\n";
        $sql .= $from_and_where;
        $sql .= $group_by;
        if ($this->having) {
            $sql .= "\nHAVING ".implode(' AND ', $this->having);
        }
        $sql .= $this->_getOrderBy();

        // This query wraps main collection query, adding information about previous row as well as index of current row
        $inner_sql = "SELECT @rowid AS prev_id, @rowid := q.id AS id, @rownum := @rownum + 1 AS rownum".($with_name ? ', @prev_name AS prev_name, @prev_name := name AS name' : '')."
                      FROM ({$sql}) AS q
                          JOIN (SELECT @rownum := 0, @rowid := 0, @prev_name := NULL) AS r";

        // This query filters previous one. It only keeps two rows related to $product_id we're interested in.
        $sql = "SELECT qq.prev_id, id, rownum, @rownum as total_rows".($with_name ? ', prev_name, name' : '')."
                FROM (
                    {$inner_sql}
                ) AS qq
                WHERE id=? OR prev_id=?
                LIMIT 2";
        $rows = $this->getModel()->query($sql, [$product_id, $product_id])->fetchAll();

        $count = $prev_id = $next_id = $position = $prev_name = $next_name = null;
        foreach($rows as $row) {
            $count = $row['total_rows'];
            if ($row['id'] == $product_id) {
                $prev_id = ifempty($row['prev_id'], null);
                $position = $row['rownum'];
                if ($with_name) {
                    $prev_name = ifempty($row['prev_name'], null);
                }
            } elseif ($row['prev_id'] == $product_id) {
                $next_id = $row['id'];
                if ($with_name) {
                    $next_name = $row['name'];
                }
            }
        }
        if ($count === null) {
            $count = $this->count();
        }

        $result = [
            'prev' => [
                'id' => $prev_id,
            ],
            'next' => [
                'id' => $next_id,
            ],
            'position' => $position,
            'count' => $count,
        ];
        if ($with_name) {
            $result['prev']['name'] = $prev_name;
            $result['next']['name'] = $next_name;
        }
        return $result;
    }
}
