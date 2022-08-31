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

    public function __construct($params = null) {
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
        $presentations = array_values($presentation_model->getTemplatesByUser($user_id, [ 'columns' => true ]));

        $limit  = waRequest::request( "limit", $active_presentation["rows_on_page"], waRequest::TYPE_INT );
        $page   = waRequest::request( "page", 1, waRequest::TYPE_INT );
        $offset = waRequest::request( "offset", ( ($page - 1) * $limit ), waRequest::TYPE_INT );

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
        $categories_tree = $category_model->buildNestedTree($static_categories);
        $set_model = new shopSetModel();
        $sets = $set_model->select('id, name')->fetchAll('id');

        $filter = $active_filter->getFilter();
        $filter_options = $active_filter->getFilterOptions();
        $this->clearMissingRules($filter['rules'], $filter_options, $categories, $sets);

        $sort_column_type = $presentation->getColumnType();
        $sorting_options = [
            'sort' => $sort_column_type !== null ? array_unique([$sort_column_type, 'name']) : ['name'],
            'order' => strtolower($presentation->getField('sort_order')),
        ];
        if ($active_filter->getId() > 0 && !empty($filter['rules'])) {
            $sorting_options['prepare_filter_id'] = $active_filter->getId();
        }
        $collection = new shopProductsCollection('', $sorting_options);
        $products_total_count = $collection->count();
        $pages = round($products_total_count / $limit);
        $products = $presentation->getProducts($collection, [
            'offset' => $offset,
            'limit' => $limit,
            'format' => true,
        ]);

        $stocks = shopProdSkuAction::getStocks();

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

        shopHelper::setChapter('new_chapter');

        $filter['rules'] = $this->getFilterGroups($filter['rules']);
        $filter_model = new shopFilterModel();
        $filters = array_values($filter_model->getTemplatesByUser($user_id, ['columns' => true]));
        $this->getRulesLabels($filter['rules'], $filter_options, $categories, $sets);

        $this->view->assign([
            "filter"               => $filter,
            "filters"              => $filters,
            "filter_options"       => $filter_options,
            "presentations"        => $presentations,
            "active_presentation"  => $active_presentation,
            "stocks"               => $stocks,
            "columns"              => $columns,
            "products"             => $this->formatProducts($products),
            "products_total_count" => $products_total_count,
            'categories'           => $static_categories,
            'categories_tree'      => $categories_tree,
            'currencies_data'      => $this->getCurrenciesData(),

            "page"                 => $page,
            "pages"                => $pages
        ]);
        $this->setTemplate('templates/actions/prod/main/List.html');
        $this->setLayout(new shopBackendProductsListSectionLayout());
    }

    protected function getFilterGroups($rules)
    {
        $groups = [];

        foreach ($rules as $rule) {
            $group_id = $rule["rule_group"];

            if (empty($groups[$group_id])) {
                $groups[$group_id] = [
                    "id"    => $group_id,
                    "type"  => $rule["rule_type"],
                    "rules" => []
                ];
            }

            $groups[$group_id]["rules"][] = $rule;
        }

        return array_values($groups);
    }

    protected function getRulesLabels(&$rules, $options, $categories, $sets)
    {
        $color_values_model = new shopFeatureValuesColorModel();
        $color_values = $color_values_model->select('id, value')->fetchAll('id');
        $varchar_values_model = new shopFeatureValuesVarcharModel();
        $varchar_values = $varchar_values_model->select('id, value')->fetchAll('id');
        foreach ($rules as &$rule) {
            $label = [];
            $unit_name = '';
            $divider = '';
            $words_count = 0;
            $rule['name'] = '';
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
                        $label[] = $categories[$item['rule_params']]['name'];
                    }
                } elseif ($rule['type'] == 'sets') {
                    if (isset($sets[$item['rule_params']])) {
                        $label[] = $sets[$item['rule_params']]['name'];
                    }
                } else {
                    foreach ($options['features'] as $option) {
                        if (mb_strpos($rule['type'], 'feature_') === 0) {
                            if ($option['rule_type'] == $rule['type']) {
                                if ($option['selectable']) {
                                    $divider = ' | ';
                                }
                                if (($option['type'] == shopFeatureModel::TYPE_DATE || $option['type'] == 'range.date'
                                    || mb_strpos($option['type'], 'range.') === 0 || mb_strpos($option['type'], 'dimension.') === 0)
                                    && !$option['selectable']
                                ) {
                                    $divider = ' ';
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
                                        foreach ($option['options'] as $param) {
                                            if ($param['id'] == $item['rule_params']) {
                                                $label[] = $param['name'];
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
                                    } else {
                                        if ($option['type'] == shopFeatureModel::TYPE_DATE || $option['type'] == 'range.date'
                                                || mb_strpos($option['type'], 'range.') === 0 || mb_strpos($option['type'], 'dimension.') === 0
                                        ) {
                                            $this->addLabelInterval($item, $label, $words_count);
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
                                    $label[] = $sign_left . $item['rule_params'] . $sign_right;
                                }
                                $rule['name'] = $option['name'];
                            }
                        }
                    }
                }
            }
            $rule['label'] = implode($divider, $label) . $unit_name;
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

    public function formatProducts($products) {
        $result = [];

        $selected_selectable_feature_ids = self::getProductsFeatureIds(array_keys($products));
        $features = self::getProductFeatures(array_column($products, 'type_id'), $products);
        $product_features_model = new shopProductFeaturesModel();
        foreach ($products as $_product) {
            // Feature values saved for skus: sku_id => feature code => value
            $skus_features_values = $product_features_model->getValuesMultiple($features[$_product['id']], $_product['id'], array_keys($_product['skus']));
            $result[] = $this->formatProduct($_product, $selected_selectable_feature_ids, $features[$_product['id']], $skus_features_values);
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

        $_normal_mode = (count($product["skus"]) > 1 || ifempty($product, 'params', 'multiple_sku', null));

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
            $modification["price_string"] = shopViewHelper::formatPrice(shop_currency($modification["price"], null, $product["currency"], false), ["currency" => $product["currency"], "unit" => $unit]);

            $names = shopProdSkuAction::explodeSkuName($modification, $skus_features_values, $selectable_features);
            $modification_name = $names['modification_name'];
            $modification["name_values"] = $names['features_name'];

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

    protected function mergeColumns($columns, $presentation_columns)
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
        foreach ($columns as &$column) {
            if (!isset($column['enabled'])) {
                $column['enabled'] = false;
            }
            if (!isset($column['sort'])) {
                $column['sort'] = ++$position;
            }
        }

        usort($columns, function($a, $b) {
            if ($a['sort'] == $b['sort']) {
                return 0;
            }
            return $a['sort'] > $b['sort'] ? 1 : -1;
        });

        return $columns;
    }

    protected function formatColumns($columns) {
        $feature_model = new shopFeatureModel();
        $all_features = $feature_model->getFeatures(true);
        $all_features = shopPresentation::addSelectableValues($all_features);
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
                                    "name" => _w("Published"),
                                    "icon" => "<i class='fas fa-check color-green-dark'></i>"
                                ],
                                [
                                    "value" => "0",
                                    "name" => _w("Hidden"),
                                    "icon" => "<i class='fas fa-times color-yellow'></i>"
                                ],
                                [
                                    "value" => "-1",
                                    "name" => _w("Unpublished"),
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
                            $options[] = [
                                "name" => $badge["name"],
                                "value" => $badge["id"]
                            ];
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

    protected function getProductCategories() {
        $category_model = new shopCategoryModel();
        $categories = $category_model->getFullTree('id, name, parent_id', true);
        return $category_model->buildNestedTree($categories);
    }

    protected function getProductCategoriesOptions() {
        $categories = $this->getProductCategories();

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

        return forEachCategory($categories);
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

    protected function getProductBadges() {
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

    protected function clearMissingRules(&$rules, $data, $categories, $sets)
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
                        if (isset($sets[$rule['rule_params']])) {
                            $is_selected = true;
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
                                $options = $field['options'];
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
}