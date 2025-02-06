<?php
/**
 * List of products
 */
class shopProdListAction extends waViewAction
{
    protected $units = null;
    protected $formatted_units = null;
    protected $stocks = null;
    protected $fractional_config = null;
    protected $currencies = [];
    protected $static_categories_tree = null;
    /**
     * @var shopProductFeaturesModel $product_features_model
     */
    protected $product_features_model = [];

    public function __construct($params = null)
    {
        parent::__construct($params);
        $units = shopHelper::getUnits();
        $this->units = $units;
        $this->formatted_units = shopFrontendProductAction::formatUnits($units);
        $this->fractional_config = shopFrac::getFractionalConfig();
        $this->currencies = $this->getProductCurrencies();
    }

    /**
     * @throws waException
     * @throws waDbException
     */
    public function execute()
    {
        $user_id = wa()->getUser()->getId();

        $presentation = shopPresentation::getCurrentTransient();
        $active_filter = $presentation->getFilter();
        $active_presentation = $presentation->getData();

        // получить с сервера список сохранённых презентаций
        $presentation_model = new shopPresentationModel();
        $presentations = array_values($presentation_model->getTemplatesByUser($user_id, ['fields' => ['id', 'name']]));

        $limit  = waRequest::request( "limit", $active_presentation["rows_on_page"], waRequest::TYPE_INT );
        $page   = waRequest::request( "page", 1, waRequest::TYPE_INT );
        $offset = waRequest::request( "offset", null, waRequest::TYPE_INT );

        // Обновляем лимит у представления если он задан через параметр
        if ($limit != $active_presentation["rows_on_page"]) {
            $active_presentation["rows_on_page"] = $limit;
            $presentation_model->updateById($active_presentation['id'], [
                "rows_on_page" => $limit,
            ]);
        }

        $category_model = new shopCategoryModel();
        $categories = $category_model->getFullTree('id, name, parent_id, type');
        $static_categories = array_filter($categories, function($v) {
            return empty($v['type']);
        });
        $this->static_categories_tree = $category_model->buildNestedTree($static_categories);

        $filter = $active_filter->getFilter();
        $filter_options = $active_filter->getFilterOptions();
        $this->clearMissingRules($filter['rules'], $filter_options, $categories);

        $sort_column_type = $presentation->getSortColumnType();
        $has_search = false;
        foreach ($filter['rules'] as $rule) {
            if ($rule['rule_type'] == 'search') {
                $has_search = true;
                break;
            }
        }
        if ($sort_column_type !== null) {
            $sort_column_type = $sort_column_type == 'price' || $sort_column_type == 'base_price' ? 'min_' . $sort_column_type : $sort_column_type;
            $sorting_options = [
                'sort' => array_unique([$sort_column_type, 'name']),
                'order' => strtolower($presentation->getField('sort_order')),
            ];
        } elseif (!$has_search) {
            $sorting_options = [
                'sort' => ['name'],
                'order' => strtolower($presentation->getField('sort_order')),
            ];
        }
        if ($active_filter->getId() > 0 && !empty($filter['rules'])) {
            $sorting_options['prepare_filter'] = $active_filter->getId();
        }
        $collection = new shopProductsCollection('', $sorting_options);
        $products_total_count = $collection->count();
        $pages = ceil($products_total_count / $limit);
        if (!($pages >= 1)) {
            $pages = 1;
        }
        $page = min($pages, max(1, $page));
        if ($offset === null) {
            $offset = ($page - 1) * $limit;
        }

        $columns = $this->mergeColumns($presentation->getColumnsList(), $active_presentation['columns']);
        $columns = $this->formatColumns($columns);

        $_request_view = waRequest::request("view", null, waRequest::TYPE_STRING);
        if (!empty($_request_view)) {
            $view = $active_presentation["view"];
            switch ($_request_view) {
                case "thumbs":
                case "table":
                    $view = $_request_view;
                    break;
                case "skus":
                    $view = "table_extended";
                    break;
            }
            $active_presentation["view"] = $view;
        }

        $filter['rules'] = $this->getFilterGroups($filter['rules']);
        $this->shopProdFiltersEvent($filter, $filter_options, $collection);
        $filter_model = new shopFilterModel();
        $filters = array_values($filter_model->getTemplatesByUser($user_id, ['fields' => ['id', 'name']]));
        $this->getRulesLabels($filter['rules'], $filter_options, $categories);

        foreach($filter_options['features'] as &$f) {
            if (!empty($f['id']) && empty($f['render_type'])) {
                $f = [
                    'id' => $f['id'],
                    'code' => $f['code'],
                    'name' => $f['name'],
                ];
            }
        }
        unset($f);

        $products = $presentation->getProducts($collection, [
            'offset' => $offset,
            'limit' => $limit,
            'format' => true,
        ]);

        /**
         * @event backend_prod_list
         * @since 9.4.1
         */
        $backend_prod_list = wa('shop')->event('backend_prod_list', ref([
            "products"             => $products,
            "products_total_count" => $products_total_count,
            "current_page"         => $page,
            "pages_count"          => $pages,
        ]));

        $this->view->assign([
            "filter"               => $filter,
            "filters"              => $filters,
            "filter_options"       => $filter_options,
            "presentations"        => $presentations,
            "active_presentation"  => $active_presentation,
            "stocks"               => $this->getProductStocks(),
            "columns"              => $columns,
            "products"             => $this->formatProducts($products),
            "products_total_count" => $products_total_count,
            'categories'           => $static_categories,
            'categories_tree'      => $this->static_categories_tree,
            'currencies_data'      => $this->getCurrenciesData(),
            'action_rights'        => $this->getActionRights(),

            "page"                 => $page,
            "pages"                => $pages,

            "mass_actions"         => $this->getMassActions(),
            "backend_prod_list"    => $backend_prod_list,
        ]);
        $this->setTemplate('templates/actions/prod/main/List.html');
        $this->setLayout(new shopBackendProductsListSectionLayout());
    }

    protected function getFilterGroups($rules)
    {
        $groups = [];
        $types = ['categories', 'sets', 'types', 'storefronts', 'tags'];
        foreach ($rules as $rule) {
            $group_id = $rule["rule_group"];

            if (empty($groups[$group_id])) {
                $groups[$group_id] = [
                    "id"    => $group_id,
                    "type"  => $rule["rule_type"],
                    'display_type' => in_array($rule['rule_type'], $types) ? $rule['rule_type'] : 'features',
                    "rules" => []
                ];
            }

            $groups[$group_id]["rules"][] = $rule;
        }

        return array_values($groups);
    }

    protected function shopProdFiltersEvent(&$filter, &$filter_options, $collection)
    {
        shopFilter::shopProdFiltersEvent($filter, $filter_options, $collection);
        $filter_options['features'] = array_values($filter_options['features']);
    }

    protected function getRulesLabels(&$rules, $options, $categories)
    {
        $varchar_ids = $color_ids = [];
        foreach ($rules as $rule) {
            if (mb_strpos($rule['type'], 'feature_') === 0) {
                foreach ($rule['rules'] as $item) {
                    foreach ($options['features'] as $option) {
                        if ($option['rule_type'] == $rule['type'] && !$option['selectable']) {
                            if ($option['type'] == shopFeatureModel::TYPE_VARCHAR) {
                                $varchar_ids[] = $item['rule_params'];
                            } elseif ($option['type'] == shopFeatureModel::TYPE_COLOR) {
                                $color_ids[] = $item['rule_params'];
                            }
                        }
                    }
                }
            }
        }
        $varchar_values = [];
        if ($varchar_ids) {
            $varchar_values_model = new shopFeatureValuesVarcharModel();
            $varchar_values = $varchar_values_model->select('id, value')->where('id IN (?)', [$varchar_ids])->fetchAll('id');
        }
        $color_values = [];
        if ($color_ids) {
            $color_values_model = new shopFeatureValuesColorModel();
            $color_values = $color_values_model->select('id, value')->where('id IN (?)', [$color_ids])->fetchAll('id');
        }

        foreach ($rules as &$rule) {
            $label = [];
            $unit_name = '';
            $divider = '';
            $words_count = 0;
            $rule['name'] = '';
            $rule['hint'] = null;
            foreach ($rule['rules'] as $item) {
                if (in_array($rule['type'], ['types', 'storefronts', 'tags'])) {
                    if ($rule['type'] != 'types') {
                        $divider = ' | ';
                    }
                    foreach ($options[$rule['type']] as $option) {
                        if ($option['id'] == $item['rule_params']) {
                            $label[] = $option['name'];
                        }
                    }
                } elseif ($rule['type'] == 'categories') {
                    if (isset($categories[$item['rule_params']])) {
                        $category = &$categories[$item['rule_params']];
                        $names = $label = [$category['name']];
                        while ($category['parent_id'] > 0) {
                            if (isset($categories[$category['parent_id']])) {
                                $category = &$categories[$category['parent_id']];
                                $names[] = $category['name'];
                            }
                        }
                        unset($category);
                        $rule['hint'] = implode(' ⟶ ', array_reverse($names));
                    }
                } elseif ($rule['type'] == 'sets') {
                    foreach ($options[$rule['type']] as $info) {
                        if (isset($info['sets'])) {
                            foreach ($info['sets'] as $subset) {
                                if ($subset['id'] == $item['rule_params']) {
                                    $label[] = $subset['name'];
                                }
                            }
                        } elseif ($info['id'] == $item['rule_params']) {
                            $label[] = $info['name'];
                        }
                    }
                } else {
                    foreach ($options['features'] as $option) {
                        if (mb_strpos($rule['type'], 'feature_') === 0) {
                            if ($option['rule_type'] == $rule['type']) {
                                if ($option['selectable']) {
                                    $divider = ' | ';
                                }
                                if ($option['type'] != shopFeatureModel::TYPE_DATE && $option['type'] != 'range.date'
                                    && !$unit_name && !is_numeric($item['rule_params']) && isset($option['units'])
                                ) {
                                    foreach ($option['units'] as $unit) {
                                        if ($unit['value'] == $item['rule_params']) {
                                            $unit_name = ' ' . $unit['name'];
                                        }
                                    }
                                } else {
                                    if ($option['selectable'] || $option['type'] == shopFeatureModel::TYPE_BOOLEAN) {
                                        if (!empty($option['options'])) {
                                            foreach ($option['options'] as $param) {
                                                if ($param['value'] == $item['rule_params']) {
                                                    $label[] = $param['name'];
                                                }
                                            }
                                        }
                                    } elseif ($option['type'] == shopFeatureModel::TYPE_VARCHAR || $option['type'] == shopFeatureModel::TYPE_COLOR) {
                                        if ($option['type'] == shopFeatureModel::TYPE_VARCHAR) {
                                            if (isset($varchar_values[$item['rule_params']])) {
                                                $label[] = $varchar_values[$item['rule_params']]['value'];
                                            }
                                        } else {
                                            if (isset($color_values[$item['rule_params']])) {
                                                $label[] = $color_values[$item['rule_params']]['value'];
                                            }
                                        }
                                        $divider = ' | ';
                                    } else {
                                        if ($option['type'] == shopFeatureModel::TYPE_DATE
                                                || $option['type'] == shopFeatureModel::TYPE_DOUBLE
                                                || mb_strpos($option['type'], 'range.') === 0
                                                || mb_strpos($option['type'], 'dimension.') === 0
                                        ) {
                                            $divider = ' ';
                                            $this->addLabelInterval($item, $label, $words_count);
                                        }
                                        if ($option['type'] == shopFeatureModel::TYPE_DATE || $option['type'] == 'range.date') {
                                            $item['rule_params'] = waDateTime::format('humandate', $item['rule_params']);
                                        }
                                        $label[] = $item['rule_params'];
                                    }
                                }
                            }
                        } else {
                            if ($option['rule_type'] == $rule['type']) {
                                if ($option['render_type'] == 'select') {
                                    $divider = ' | ';
                                    foreach ($option['options'] as $param) {
                                        if ($param['value'] == $item['rule_params']) {
                                            $badge = '';
                                            if ($rule['type'] == 'badge') {
                                                $badge = '<span class="s-icon"><i class="' . $param['icon'] . '"></i></span>&nbsp;';
                                            }
                                            $label[] = $badge . $param['name'];
                                        }
                                    }
                                } elseif ($option['render_type'] == 'range') {
                                    $divider = ' ';
                                    $sign_left = $sign_right = '';
                                    if (isset($option['currency'])) {
                                        if ($option['currency']['sign_position']) {
                                            $sign_right = $option['currency']['sign_delim'] . $option['currency']['sign_html'];
                                        } else {
                                            $sign_left = $option['currency']['sign_html'] . $option['currency']['sign_delim'];
                                        }
                                    }
                                    $this->addLabelInterval($item, $label, $words_count);
                                    if ($option['type'] == 'date') {
                                        $item['rule_params'] = waDateTime::format('humandate', strtotime($item['rule_params']));
                                    }
                                    $label[] = $sign_left . $item['rule_params'] . $sign_right;
                                }
                                $rule['name'] = $option['name'];
                            }
                        }
                    }
                }
            }

            $label = implode($divider, $label) . $unit_name;
            if (in_array($rule["type"], ['price', 'purchase_price', 'compare_price', 'badge'])) {
                $rule['label'] = $label;
            } else {
                $rule['label'] = htmlentities($label, ENT_QUOTES, 'utf-8');
            }

        }
    }

    protected function addLabelInterval($item, &$label, &$words_count)
    {
        if ($item['open_interval'] == null) {
            if ($words_count == 0) {
                $label[] = _w('from');
            } elseif ($words_count == 1) {
                $label[] = _w('to');
            }
            $words_count++;
        } else {
            if ($item['open_interval'] == shopFilterRulesModel::OPEN_INTERVAL_LEFT_CLOSED) {
                $label[] = _w('from');
            } elseif ($item['open_interval'] == shopFilterRulesModel::OPEN_INTERVAL_RIGHT_CLOSED) {
                $label[] = _w('to');
            }
        }
    }

    protected function getActionRights()
    {
        $action_rights = [
            'partial_access_types' => [],
            'full_access_types' => [],
        ];
        $type_rights = wa()->getUser()->getRights('shop', 'type.%');
        foreach ($type_rights as $type_id => $value) {
            if ($type_id !== 'all') {
                if ($value == 2) {
                    $action_rights['full_access_types'][] = $type_id;
                } elseif ($value == 1) {
                    $action_rights['partial_access_types'][] = $type_id;
                }
            }
        }
        foreach (['importexport', 'setscategories', 'marketing', 'setup_marketing'] as $rule) {
            $action_rights[$rule] = (bool)wa()->getUser()->getRights('shop', $rule);
        }

        return $action_rights;
    }

    public function formatProducts($products) {
        $result = [];

        $selected_selectable_feature_ids = self::getProductsFeatureIds(array_keys($products));
        $features = self::getProductFeatures(array_column($products, 'type_id'), $products);
        $this->product_features_model = new shopProductFeaturesModel();
        foreach ($products as $_product) {
            // Feature values saved for skus: sku_id => feature code => value
            $product_features = isset($features[$_product['id']]) ? $features[$_product['id']] : [];
            $skus_features_values = $this->product_features_model->getValuesMultiple($product_features, $_product['id'], array_keys($_product['skus']));
            $result[] = $this->formatProduct($_product, $selected_selectable_feature_ids, $product_features, $skus_features_values);
        }

        // !!! TODO: hook that allows plugins to modify $result

        return $result;
    }

    /**
     * @throws waException
     * @throws waDbException
     */
    public function formatProduct($product, $selected_selectable_feature_ids, $features, $skus_features_values)
    {
        // They should be in the same order as in $selected_selectable_feature_ids
        $selectable_features = array_fill_keys($selected_selectable_feature_ids[$product['id']], null);
        if (!empty($features)) {
            foreach ($features as $feature) {
                if (!empty($feature["available_for_sku"])) {
                    if (in_array($feature["id"], $selected_selectable_feature_ids[$product['id']])) {
                        $selectable_features[$feature["id"]] = [
                            'code' => $feature['code']
                        ];
                    }
                }
            }
        }

        if (!empty($selectable_features)) {
            foreach($selectable_features as $_feature_id => $_feature) {
                $_feature_column_id = "feature_". $_feature_id;
                if (!empty($product["columns"][$_feature_column_id])) {
                    $product["columns"][$_feature_column_id]["editable"] = false;
                    $product["columns"][$_feature_column_id]["feature_locked"] = true;
                }
            }
        }

        // Make sure there are no NULLs left after array_fill_keys() above
        // also keys must start from 0
        $selectable_features = array_values(array_filter($selectable_features));

        // Параметры продукта
        $getParams = function($product) {
            $params = [];
            // Получение параметров продукта для определения "сложного|простого" продукта
            $product_object = new shopProduct($product);
            if ($product_object->params) {
                foreach ($product_object->params as $k => $v) {
                    $params[$k] = $v;
                }
            }
            return $params;
        };
        $product["params"] = $getParams($product);

        $has_features_values = $this->product_features_model->checkProductFeaturesValues($product['id'], $product['type_id']);
        $_normal_mode = count($product["skus"]) > 1 || $has_features_values || ifempty($product, 'params', 'multiple_sku', null) || $selected_selectable_feature_ids[$product['id']];

        // Фотографии продукта
        $getPhotos = function($product) {
            $result = [];

            $_images = $product["images"];

            foreach ($_images as $_image) {
                // Append file modification time to image URL
                // in order to avoid browser caching issues
                $last_modified = "";
                $path = shopImage::getPath($_image);
                if (file_exists($path)) {
                    $last_modified = "?".filemtime($path);
                }

                $result[$_image["id"]] = [
                    "id" => $_image["id"],
                    "url" => $_image["url_thumb"].$last_modified,
                    "description" => $_image["description"]
                ];
            }

            return $result;
        };
        $photos = $getPhotos($product);

        $skus = [];

        foreach ($product["skus"] as $modification) {
            $unit = null;
            if ($this->fractional_config["stock_units_enabled"] && isset($this->formatted_units[$product["stock_unit_id"]])) {
                $unit = $this->formatted_units[$product["stock_unit_id"]]["name_short"];
            }

            $modification["price_string"] = shopViewHelper::formatPrice($modification["price"], ["currency" => $product["currency"], "unit" => $unit]);

            $names = shopProdSkuAction::explodeSkuName($modification, $skus_features_values, $selectable_features);
            $modification_name = $names['modification_name'];
            $modification["name_values"] = $names['features_name'];

            // Не ко всякому наименованию артикула добавляются в конце значения характеристик.
            // Но в табличном виде надо показывать характеристики всегда, поэтому впишем их сами.
            if ($selectable_features && empty($modification["name_values"])) {
                $mod_feature_values = ifset($skus_features_values, $modification['id'], []);
                if ($mod_feature_values) {
                    $modification["name_values"] = [];
                    foreach($selectable_features as $f) {
                        if (isset($f['code']) && isset($mod_feature_values[$f['code']])) {
                            $modification["name_values"][] = $mod_feature_values[$f['code']];
                        }
                    }
                    $modification["name_values"] = join(', ', $modification["name_values"]);
                }
                if (empty($modification["name_values"])) {
                    $modification['force_empty_name_values'] = true;
                }
            }

            // MODIFICATIONS
            if ($modification["sku"] || $modification_name) {
                $sku_key = $modification["sku"]."###".$modification_name;
                if (empty($skus[$sku_key])) {
                    $skus[$sku_key] = [
                        "sku" => $modification["sku"],
                        "name" => $modification_name,
                        "sku_id" => null,
                        "modifications" => [],
                    ];
                }
                $skus[$sku_key]["modifications"][] = $modification;

                if ($product["sku_id"] === $modification["id"]) {
                    $skus[$sku_key]["sku_id"] = $modification["id"];
                }

            } else {
                $_id = uniqid($modification["id"], true);
                $skus[$_id] = [
                    "sku" => $modification["sku"],
                    "name" => $modification_name,
                    "sku_id" => ($product["sku_id"] === $modification["id"] ? $modification["id"] : null),
                    "modifications" => [$modification],
                ];
            }
        }

        // Корректируем названия модификаций, потому что они отличаются (баг) от названий артикулов.
        foreach ($skus as &$sku) {
            foreach ($sku["modifications"] as &$sku_mod) {
                $sku_mod["sku"] = $sku["sku"];
                $sku_mod["name"] = $sku["name"];
            }
        }

        if (!empty($product["badge"])) {
            switch ($product["badge"]) {
                case "new":
                    $product["badge"] = '<div class="badge"><i class="fas fa-bolt"></i> '._w("New!").'</div>';
                    break;
                case "lowprice":
                    $product["badge"] = '<div class="badge"><i class="fas fa-piggy-bank"></i> '._w("Low price!").'</div>';
                    break;
                case "bestseller":
                    $product["badge"] = '<div class="badge"><i class="fas fa-chart-line"></i> '._w("Bestseller!").'</div>';
                    break;
            }
        }

        return [
            "id"                       => $product["id"],
            "name"                     => $product["name"],
            "status"                   => $product["status"],
            "badge"                    => $product["badge"],
            "sku_id"                   => $product["sku_id"],
            "sku_type"                 => $product["sku_type"],
            "currency"                 => $product["currency"],
            "image_id"                 => $product["image_id"],
            "normal_mode"              => $_normal_mode,
            "skus"                     => array_values( $skus ),
            "photos"                   => array_values( $photos ),
            "create_date_string"       => date( 'd.m.Y', strtotime( $product["create_datetime"] ) ),
            "columns"                  => $product["columns"],
            "product_from_subcategory" => ifset($product, "product_from_subcategory", false)
        ];
    }

    protected static function getProductFeatures($types, $products)
    {
        $feature_model = new shopFeatureModel();

        // Features attached to product type
        $product_ids = array_keys($products);
        $type_features = [];
        if ($types) {
            $sql = "
                SELECT f.*, t.type_id
                FROM `{$feature_model->getTableName()}` `f`
                JOIN `shop_type_features` `t` ON `t`.`feature_id`=`f`.`id`
                WHERE `t`.`type_id` IN (i:type_id) AND `f`.`parent_id` IS NULL
                ORDER BY `t`.`sort`, `t`.`feature_id`";
            $type_features = $feature_model->query($sql, ['type_id' => $types])->fetchAll('code');
            foreach ($type_features as $code => $feature) {
                $type_features[$code]['internal'] = true;
            }
        }

        $product_features = [];
        if ($product_ids) {
            $sql = "SELECT DISTINCT `f`.*, pf.product_id FROM `{$feature_model->getTableName()}` `f`
                JOIN `shop_product_features` `pf` ON `pf`.`feature_id` = `f`.`id`
                WHERE `pf`.`product_id` IN (i:id)";
            $product_features = $feature_model->query($sql, array('id' => $product_ids))->fetchAll('id');
            $sql = "SELECT DISTINCT `f`.*, pf.product_id FROM `{$feature_model->getTableName()}` `f`
                JOIN `shop_product_features_selectable` `pf` ON `pf`.`feature_id` = `f`.`id`
                WHERE `pf`.`product_id` IN (i:id)";
            $product_features += $feature_model->query($sql, array('id' => $product_ids))->fetchAll('id');
        }

        $group_features = [];
        foreach ($products as $product_id => $product) {
            foreach ($type_features as $feature) {
                if ($feature['type_id'] = $product['type_id']) {
                    $group_features[$product_id][$feature['code']] = $feature;
                }
            }
            foreach ($product_features as $feature) {
                if ($feature['product_id'] = $product_id && !isset($group_features[$product_id][$feature['code']])) {
                    $group_features[$product_id][$feature['code']] = $feature;
                }
            }
        }

        return $group_features;
    }

    protected static function getProductsFeatureIds($product_ids)
    {
        $features_selectable_model = new shopProductFeaturesSelectableModel();
        $result = [];
        if (!empty($product_ids) && is_array($product_ids)) {
            $features = $features_selectable_model->select("DISTINCT product_id, feature_id")->where("product_id IN (?)", [$product_ids])->order('sort')->fetchAll();
            $result = array_flip($product_ids);
            array_walk($result, function (&$value) {
                $value = [];
            });
            foreach ($features as $feature) {
                $result[$feature['product_id']][] = $feature['feature_id'];
            }
        }
        return $result;
    }

    public function mergeColumns($columns, $presentation_columns, $remove_disabled=true)
    {
        $position = 0;
        foreach ($presentation_columns as $enabled_column) {
            if (isset($columns[$enabled_column['column_type']])) {
                $columns[$enabled_column['column_type']]['enabled'] = true;
                $columns[$enabled_column['column_type']]['sort'] = $enabled_column['sort'];
                $columns[$enabled_column['column_type']]['settings'] = [];
                if (!empty($enabled_column['data'])) {
                    $columns[$enabled_column['column_type']]['settings'] = ifset($enabled_column, 'data', 'settings', []);
                }
            }
            $position = max($enabled_column['sort'], $position);
        }
        foreach ($columns as $col_id => &$column) {
            if (empty($column['enabled'])) {
                if ($remove_disabled) {
                    unset($columns[$col_id]);
                    continue;
                } else {
                    $column['enabled'] = false;
                }
            }
            if (!isset($column['sort'])) {
                $column['sort'] = ++$position;
            }
        }
        unset($column);

        usort($columns, function($a, $b) {
            if ($a['sort'] == $b['sort']) {
                return 0;
            }
            return $a['sort'] > $b['sort'] ? 1 : -1;
        });

        return $columns;
    }

    public function formatColumns($columns, $skip_selectable = false) {
        $feature_model = new shopFeatureModel();

        // информацию о характеристиках нужно получить только для колонок-характеристик, т.е. которые содержат поле 'feature_id'
        $feature_ids = [];
        foreach ($columns as $col) {
            if (isset($col['feature_id'])) {
                $feature_ids[] = $col['feature_id'];
            }
        }
        $all_features = $feature_model->getFeatures(['id' => $feature_ids]);
        if (!$skip_selectable) {
            $all_features = shopPresentation::addSelectableValues($all_features);
        }

        $formatted_features = shopProdSkuAction::formatFeatures($all_features);
        $formatted_features_keys = array_flip(array_column($formatted_features, 'id'));

        $result = [];
        foreach ($columns as $key => &$column) {
            // Склады
            if (strpos($column["id"], 'stocks_') === 0) {
                $column["stocks"] = $this->getProductStocks();

            // Характеристики
            } elseif (strpos($column["id"], 'feature_') === 0) {
                $column["options"] = [];
                $column["units"] = [];
                if (isset($formatted_features[$formatted_features_keys[$column['feature_id']]])) {
                    $formatted_feature = $formatted_features[$formatted_features_keys[$column['feature_id']]];
                    if (isset($formatted_feature['options'])) {
                        $column['options'] = $formatted_feature['options'];
                    }
                    if (isset($formatted_feature['units'])) {
                        $column['units'] = $formatted_feature['units'];
                    }
                }
            // Поля продукта и артикула
            } else {
                switch ($column["id"]) {
                    case "status":
                        $column["options"] = [
                            "statuses" => [
                                [
                                    "value" => "1",
                                    "name" => _w("Published, for sale"),
                                    "icon" => "<i class='fas fa-check color-green-dark'></i>"
                                ],
                                [
                                    "value" => "0",
                                    "name" => _w("Hidden, not for sale"),
                                    "icon" => "<i class='fas fa-times color-yellow'></i>"
                                ],
                                [
                                    "value" => "-1",
                                    "name" => _w("Unpublished, not for sale"),
                                    "icon" => "<i class='fas fa-times color-red'></i>"
                                ]
                            ],
                            "types" =>  [
                                [
                                    "value" => "404",
                                    "name" => "404"
                                ],
                                [
                                    "value" => "home",
                                    "name" => _w("Homepage")
                                ],
                                [
                                    "value" => "category",
                                    "name" => _w("Main category")
                                ],
                                [
                                    "value" => "url",
                                    "name" => _w("Specified URL")
                                ]
                            ],
                            "codes" => [
                                [
                                    "name" => _w("302: the product is temporarily hidden"),
                                    "value" => "302"
                                ],
                                [
                                    "name" => _w("301: the product is permanently hidden"),
                                    "value" => "301",
                                ]
                            ]
                        ];
                        break;

                    case "category_id":
                        $column["options"] = $this->getProductCategoriesOptions();
                        break;

                    case "type_id":
                        $column["options"] = [];
                        $types = $this->getProductTypes();
                        foreach ($types as $type) {
                            $column["options"][] = [
                                "name" => $type["name"],
                                "value" => $type["id"]
                            ];
                        }
                        break;

                    case "tax_id":
                        $column["options"] = [
                            [
                                "name" => _w("No"),
                                "value" => "0"
                            ]
                        ];
                        $taxes = $this->getProductTaxes();
                        foreach ($taxes as $tax) {
                            $column["options"][] = [
                                "name" => $tax["name"],
                                "value" => $tax["value"]
                            ];
                        }
                        break;

                    case "currency":
                        $column["options"] = $this->currencies;
                        break;

                    case "count":
                        $column["stocks"] = $this->getProductStocks();
                        break;

                    case "badge":
                        $badges = $this->getProductBadges();
                        $options = [
                            [
                                "name" => _w("No"),
                                "value" => ""
                            ]
                        ];
                        foreach ($badges as $badge) {
                            $badge_data = [
                                "name"  => $badge["name"],
                                "icon"  => null,
                                "value" => $badge["id"]
                            ];
                            switch ($badge["id"]) {
                                case "new":
                                    $badge_data["icon"] = '<i class="fas fa-bolt"></i>';
                                    break;
                                case "lowprice":
                                    $badge_data["icon"] = '<i class="fas fa-piggy-bank"></i>';
                                    break;
                                case "bestseller":
                                    $badge_data["icon"] = '<i class="fas fa-chart-line"></i>';
                                    break;
                                case "custom":
                                    $badge_data["icon"] = '<i class="fas fa-code"></i>';
                                    break;
                            }
                            $options[] = $badge_data;
                        }
                        $column["badges"] = $badges;
                        $column["options"] = $options;
                        break;
                }
            }

            if (!empty($column["name"])) {
                $result[$key] = $column;
            }
        }

        return $result;
    }

    protected function getProductCategoriesOptions() {
        function forEachCategory($_categories, $options = [], $deep = 0) {
            foreach ($_categories as $_category) {
                $name = $_category["name"];
                if ($deep > 0) {
                    for ($i = 1; $i <= $deep; $i++) {
                        $name_tail = "  ";
                        if ($i === 1) {
                            $name_tail = "– ";
                        }
                        $name = $name_tail . $name;
                    }
                }

                $option = [
                    "name" => $name,
                    "value" => $_category["id"]
                ];
                $options[] = $option;

                if (!empty($_category["categories"])) {
                    $options += forEachCategory($_category["categories"], $options, $deep + 1);
                }
            }
            return $options;
        }

        if ($this->static_categories_tree === null) {
            $category_model = new shopCategoryModel();
            $static_categories_tree = $category_model->buildNestedTree(array_filter($category_model->getFullTree('id, name, parent_id, type'), function($v) {
                return empty($v['type']);
            }));
        } else {
            $static_categories_tree = $this->static_categories_tree;
        }

        return forEachCategory($static_categories_tree);
    }

    protected function getProductTypes() {
        $type_model = new shopTypeModel();
        return $type_model->getAll('id');
    }

    protected function getProductTaxes() {
        $tax_model = new shopTaxModel();
        return $tax_model->select('`id` AS `value`, name')->fetchAll('value');
    }

    protected function getProductCurrencies() {
        $result = [];

        $model = new shopCurrencyModel();
        $currencies = $model->getCurrencies();

        foreach ($currencies as $_currency) {
            $result[] = [
                "name" => $_currency["title"],
                "value" => $_currency["code"]
            ];
        }

        return $result;
    }

    protected function getProductStocks() {
        if ($this->stocks === null) {
            $result = [];
            $stocks = shopHelper::getStocks();
            foreach ($stocks as $stock_id => $stock) {
                $result[$stock_id] = [
                    'id'             => $stock_id,
                    'name'           => $stock['name'],
                    'low_count'      => $stock['low_count'],
                    'critical_count' => $stock['critical_count'],

                    'is_virtual'     => isset($stock['substocks']),
                    'substocks'      => (!empty($stock['substocks']) ? $stock['substocks'] : null)
                ];
            }
            $this->stocks = $result;
        }

        return $this->stocks;
    }

    public static function getProductBadges() {
        // BADGES
        $badges = shopProductModel::badges();

        foreach($badges as $_badge_id => &$badge) {
            $badge["id"] = $_badge_id;
        }

        $badges["custom"] = [
            "id" => "custom",
            "name" => _w("Custom badge"),
            "code" => "<div class=\"badge\" style=\"background-color: #a1fcff;\"><span>" . _w("YOUR TEXT") . "</span></div>"
        ];

        return $badges;
    }

    /**
     * @throws waException
     */
    protected function getCurrenciesData() {
        $result = [];

        foreach ($this->currencies as $_currency) {
            $code = $_currency["value"];
            $result[$code] = shopViewHelper::getCurrencyData($code);
        }

        return $result;
    }

    protected function clearMissingRules(&$rules, $data, $categories)
    {
        $is_selected = false;
        $rule_types = ['categories', 'sets', 'types'];
        $obsolete_rules_ids = [];
        foreach ($rules as $key => $rule) {
            if (in_array($rule['rule_type'], $rule_types)) {
                if (!$is_selected) {
                    if ($rule['rule_type'] == 'categories') {
                        if (isset($categories[$rule['rule_params']])) {
                            $is_selected = true;
                        }
                    } elseif ($rule['rule_type'] == 'sets') {
                        foreach ($data[$rule['rule_type']] as $info) {
                            if (isset($info['sets'])) {
                                foreach ($info['sets'] as $subset) {
                                    if ($subset['id'] == $rule['rule_params']) {
                                        $is_selected = true;
                                        break;
                                    }
                                }
                            } elseif ($info['id'] == $rule['rule_params']) {
                                $is_selected = true;
                                break;
                            }
                        }
                    } elseif ($rule['rule_type'] == 'types') {
                        foreach ($data[$rule['rule_type']] as $info) {
                            if ($info['id'] == $rule['rule_params']) {
                                $is_selected = true;
                                break;
                            }
                        }
                    }
                    if (!$is_selected) {
                        $obsolete_rules_ids[] = $rule['id'];
                        unset($rules[$key]);
                    }
                } else {
                    $obsolete_rules_ids[] = $rule['id'];
                    unset($rules[$key]);
                }
            } else {
                $rule_exist = false;
                if ($rule['rule_type'] == 'storefronts' || $rule['rule_type'] == 'tags'
                    || $rule['rule_type'] == 'currency' || $rule['rule_type'] == 'tax_id'
                ) {
                    $options = [];
                    $options_field = 'value';
                    if ($rule['rule_type'] == 'storefronts' || $rule['rule_type'] == 'tags') {
                        $options = $data[$rule['rule_type']];
                        $options_field = 'id';
                    } else {
                        foreach ($data['features'] as $field) {
                            if ($field['rule_type'] == $rule['rule_type']) {
                                $options = ifset($field, 'options', []);
                                break;
                            }
                        }
                    }
                    foreach ($options as $info) {
                        if ($info[$options_field] == $rule['rule_params']) {
                            $rule_exist = true;
                            break;
                        }
                    }
                    if (!$rule_exist) {
                        $obsolete_rules_ids[] = $rule['id'];
                        unset($rules[$key]);
                    }
                } elseif (mb_strpos($rule['rule_type'], 'feature_') === 0) {
                    foreach ($data['features'] as $feature) {
                        if ($feature['rule_type'] == $rule['rule_type']) {
                            $rule_exist = true;
                            break;
                        }
                    }
                    if (!$rule_exist) {
                        $obsolete_rules_ids[] = $rule['id'];
                        unset($rules[$key]);
                    }
                }
            }
        }

        if ($obsolete_rules_ids) {
            $filter_rules_model = new shopFilterRulesModel();
            $filter_rules_model->deleteById($obsolete_rules_ids);

            $is_first = true;
            $last_index = 0;
            $index = 0;
            foreach ($rules as &$rule) {
                if ($is_first) {
                    $is_first = false;
                    $last_index = $rule['rule_group'];
                    $rule['rule_group'] = 0;
                } elseif ($rule['rule_group'] == $last_index) {
                    $rule['rule_group'] = $index;
                } else {
                    $index++;
                    $last_index = $rule['rule_group'];
                    $rule['rule_group'] = $index;
                }
            }
            unset($rule);

        }
    }

    protected function getMassActions()
    {
        $wa_app_url = wa()->getAppUrl(null, true);
        $_sprite_url = wa()->getRootUrl() . "wa-apps/shop/img/backend/products/product/icons.svg?v=" . wa('shop')->getVersion();

        $result = array();
        if ($this->getUser()->getRights('shop', 'importexport')) {
            $result['export'] = [
                "id" => "export",
                "name" => _w("Export"),
                "actions" => [
                    [
                        "id" => "export_csv",
                        "name" => _w("Export to CSV"),
                        "icon" => '<i class="fas fa-file-csv" style="color:var(--alert-info-border-color)"></i>',
                        "redirect_url" => $wa_app_url."?action=importexport#/csv:product:export/hash/id/"
                    ]
                ]
            ];
        }

        $result = $result + [
            "organize" => [
                "id" => "organize",
                "name" => _w("Organize"),
                "actions" => [
                    [
                        "id" => "assign_tags",
                        "name" => _w("Assign tags"),
                        "icon" => '<i class="fas fa-tag" style="color: var(--menu-tags-link-color)"></i>',
                        "action_url" => $wa_app_url."?module=prod&action=assignTagsDialog"
                    ],
                    [
                        "id" => "remove_tags",
                        "name" => _w("Remove tags"),
                        "icon" => '<svg class="text-gray"><use xlink:href="'.$_sprite_url.'#tag-minus"></use></svg>',
                        "action_url" => $wa_app_url."?module=prod&action=removeTagsDialog"
                    ]
                ]
            ],
            "edit" => [
                "id" => "edit",
                "name" => _w("Editing"),
                "actions" => [
                    [
                        "id" => "set_badge",
                        "name" => _w("Badge"),
                        "icon" => '<i class="fas fa-certificate text-yellow"></i>',
                        "action_url" => $wa_app_url."?module=prod&action=setBadgeDialog",
                        "pinned" => true
                    ],
                    [
                        "id" => "set_publication",
                        "name" => _w("Availability in the storefront"),
                        "icon" => '<i class="fas fa-share text-dark-gray" style="color:var(--text-color)"></i>',
                        "action_url" => $wa_app_url."?module=prod&action=setPublicationDialog",
                        "pinned" => true
                    ],
                    [
                        "id" => "set_type",
                        "name" => _w("Change product type"),
                        "icon" => '<i class="fas fa-cube text-brown"></i>',
                        "action_url" => $wa_app_url."?module=prod&action=setTypeDialog"
                    ],
                    [
                        "id" => "duplicate",
                        "name" => _w("Duplicate"),
                        "icon" => '<i class="fas fa-clone"></i>',
                        "action_url" => $wa_app_url."?module=prod&action=productDuplicate",
                        "pinned" => true
                    ],
                    [
                        "id" => "delete",
                        "name" => _w("Delete"),
                        "icon" => '<i class="fas fa-trash-alt text-red"></i>',
                        "action_url" => $wa_app_url."?module=prod&action=deleteProductsDialog",
                        "pinned" => true
                    ]
                ]
            ],
        ];

        if ($this->getUser()->getRights('shop', 'setscategories')) {
            $result['organize']['actions'] = array_merge([
                [
                    "id" => "add_to_categories",
                    "name" => _w("Add to category"),
                    "icon" => '<i class="fas fa-folder-plus text-blue"></i>',
                    "action_url" => $wa_app_url."?module=prod&action=addToCategoriesDialog",
                    "pinned" => true
                ],
                [
                    "id" => "exclude_from_categories",
                    "name" => _w("Remove from category"),
                    "icon" => '<i class="fas fa-folder-minus text-gray"></i>',
                    "action_url" => $wa_app_url."?module=prod&action=excludeFromCategoriesDialog"
                ],
                [
                    "id" => "add_to_sets",
                    "name" => _w("Add to set"),
                    "icon" => '<svg class="text-red"><use xlink:href="'.$_sprite_url.'#list-plus"></use></svg>',
                    "action_url" => $wa_app_url."?module=prod&action=addToSetsDialog",
                    "pinned" => true
                ],
                [
                    "id" => "exclude_from_sets",
                    "name" => _w("Remove from set"),
                    "icon" => '<svg class="text-gray"><use xlink:href="'.$_sprite_url.'#list-minus"></use></svg>',
                    "action_url" => $wa_app_url."?module=prod&action=excludeFromSetsDialog"
                ],
            ], $result['organize']['actions']);
        }

        if ($this->getUser()->getRights('shop', 'marketing')) {
            $result['marketing'] = [
                "id" => "marketing",
                "name" => _w("Marketing"),
                "actions" => [
                    [
                        "id" => "discount_coupons",
                        "name" => _w("Discount coupons"),
                        "icon" => '<i class="fas fa-percentage text-pink"></i>',
                        "redirect_url" => $wa_app_url."marketing/coupons/create/?products_hash=id/"
                    ],
                    [
                        "id" => "associate_promo",
                        "name" => _w("Add to promo"),
                        "icon" => '<i class="fas fa-bullhorn text-purple"></i>',
                        "action_url" => $wa_app_url."?module=prod&action=associatePromoDialog"
                    ]
                ]
            ];
        }

        /**
         * @event backend_prod_mass_actions
         * @since 9.4.1
         */
        wa('shop')->event('backend_prod_mass_actions', ref([
            'actions' => &$result,
        ]));

        foreach($result as $group_id => &$group) {
            if (!isset($group['id'])) {
                $group['id'] = $group_id;
            }
        }
        unset($group);

        return array_values($result);
    }
}
