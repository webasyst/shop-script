<?php
class shopProdCategoryDialogAction extends waViewAction
{
    public function execute()
    {
        $category_id = waRequest::request('category_id', null, waRequest::TYPE_INT);
        $_parent_category_id = waRequest::request('parent_id', null, waRequest::TYPE_INT);

        // EDIT
        if ($category_id) {
            $category = $this->getCategory($category_id);

        // CREATE
        } else {
            $category = $this->getNewCategory();
        }

        $stuff = '%category_url%';
        $frontend_url = wa()->getRouteUrl('/frontend/category', array('category_url' => $stuff), true);
        $pos = strrpos($frontend_url, $stuff);
        $frontend_base_url = $pos !== false ? rtrim(substr($frontend_url, 0, $pos), '/').'/' : $frontend_url;

        $category["frontend_base_url"] = $frontend_base_url;
        if (!empty($category["frontend_urls"])) {
            $category["frontend_base_url"] = $category["frontend_urls"][0]["base"];
        }

        if (!empty($_parent_category_id)) {
            $category["parent_id"] = (string)$_parent_category_id;
        }

        $this->view->assign([
            "parent_id" => $_parent_category_id,
            "category" => $category,
            "category_sort_variants" => shopProdCategoriesAction::getCategorySortVariants()
        ]);

        $this->setTemplate("templates/actions/prod/main/dialogs/categories.category.edit.html");
    }

    /**
     * @throws waException
     */
    protected function getCategory($category_id)
    {
        $category_model = new shopCategoryModel();
        $category = $category_model->getById($category_id);
        if (!$category) { return null; }

        // Add routes and other options
        $category = shopProdCategoriesAction::formatCategory($category);
        $category["enable_sorting"] = "0";

        // Params
        $category_params_model = new shopCategoryParamsModel();
        $category_params = $category_params_model->get($category["id"]);
        $_result_params = [];
        foreach ($category_params as $k => $v) {
            if ($k != "order" && $k !== "enable_sorting") { $_result_params[] = $k. "=". $v; }
            if ($k === "enable_sorting") { $category["enable_sorting"] = $v; }
        }
        $category["params"] = implode(PHP_EOL, $_result_params);

        // OG
        $category_og_model = new shopCategoryOgModel();
        $category["og"] = $category_og_model->get($category_id);
        $category["og"] = self::formatOG($category["og"]);

        // Urls
        $category["frontend_urls"] = [];
        $urls = $category_model->getFrontendUrls($category_id, true);
        foreach ($urls as $frontend_url) {
            $pos = strrpos($frontend_url, $category["url"]);
            $category["frontend_urls"][] = array(
                'name' => $frontend_url,
                'url'  => $frontend_url,
                'base' => $pos !== false ? rtrim( substr( $frontend_url, 0, $pos ), '/' ) . '/' : ''
            );
        }

        $category['conditions'] = $this->parseConditions($category['conditions']);

        // Filter
        $category_helper = new shopCategoryHelper();
        $category["explode_feature_ids"] = ($category["filter"] !== null ? explode(',', $category["filter"]) : []);
        $category["allow_filter"] = (count($category["explode_feature_ids"]) > 0 ? "1" : "0");
        //$category["filter"] = [ "price" => $category_helper->getDefaultFilters() ];
        $category["allow_filter_data"] = [];

        if (!empty($category["explode_feature_ids"])) {
            $feature_model = new shopFeatureModel();
            $allow_filter = $feature_model->getById($category["explode_feature_ids"]);
            foreach ($category["explode_feature_ids"] as $feature_id) {
                if (isset($allow_filter[$feature_id])) {
                    $category["allow_filter_data"][$feature_id] = $allow_filter[$feature_id];
                }
                if ($feature_id === "price") {
                    $category["allow_filter_data"][$feature_id] = $category_helper->getDefaultFilters();
                    //remove to avoid duplication
                    //unset($category["filter"]["price"]);
                }
            }
        }

        /*
        if ($category["type"] == shopCategoryModel::TYPE_DYNAMIC) {
            $category = $this->updateDynamicCategoryFilters($category);
        } elseif ($category["type"] == shopCategoryModel::TYPE_STATIC) {
            $category = $this->updateStaticCategoryFilters($category);
        }
        */

        return $category;
    }

    /**
     * @throws waException
     */
    protected function updateDynamicCategoryFilters($category)
    {
        $category_helper = new shopCategoryHelper();

        $conditions = ifset($category, 'conditions', 'feature', []);

        $options_filter_count = [
            'frontend' => true,
        ];
        $options_feature_count = [
            'frontend' => true,
            'status'   => null,
        ];
        $options_filter = [
            'frontend'  => true,
            'ignore_id' => array_keys($category["allow_filter_data"])
        ];
        $options_features = [
            'code'   => array_keys($conditions),
            'status' => null,
        ];

        $category["filter"] += $category_helper->getFilters($options_filter);
        $category["filter_count"] = $category_helper->getCount($options_filter_count);
        $category["features"] = $category_helper->getFilters($options_features);
        $category["feature_count"] = $category_helper->getCount($options_feature_count);

        $all = [];
        foreach ($category["features"] as $feature_code => $condition_data) {
            if (isset($features[$feature_code]) && isset($condition_data["values"])) {
                $all[$feature_code] = (array)$condition_data["values"];
            }
        }

        $category["features"] = $category_helper->getFeaturesValues($category["features"], $all);

        return $category;
    }

    /**
     * @throws waException
     */
    protected function updateStaticCategoryFilters($category)
    {
        $category_helper = new shopCategoryHelper();

        $type_id = $category_helper->getTypesId($category["id"]);

        $options = [
            'status'    => null,
            'frontend'  => true,
            'type_id'   => $type_id,
            'ignore_id' => array_keys($category["allow_filter_data"])
        ];

        $category["filter"] += $category_helper->getFilters($options);
        $category["filter_count"] = $category_helper->getCount($options);

        return $category;
    }

    protected function getNewCategory()
    {
        return [
            "id"                     => null,
            "name"                   => "",
            "description"            => "",
            "parent_id"              => "0",
            "depth"                  => 0,
            "count"                  => 0,
            "type"                   => "0",
            "status"                 => "1",
            "params"                 => "",
            "url"                    => "",

            "conditions"             => [],
            "include_sub_categories" => "0",
            "sort_products"          => "name ASC",
            "enable_sorting"         => "1",
            "explode_feature_ids"    => [],
            "allow_filter"           => "0",
            "allow_filter_data"      => [],

            "storefronts"            => [],
            "frontend_urls"          => [],
            "categories"             => [],

            "meta_title"             => "",
            "meta_keywords"          => "",
            "meta_description"       => "",

            "og"                     => self::formatOG()
        ];
    }

    public static function formatOG($og = [])
    {
        $options = ["title", "description", "image", "type", "video"];
        $og["enabled"] = true;

        foreach ($options as $option) {
            if (empty($og[$option])) {
                $og[$option] = "";
            } else if (empty($og["enabled"])) {
                $og["enabled"] = true;
            }
        }

        return $og;
    }

    protected function parseConditions($conditions = '')
    {
        if (!$conditions) {
            return $conditions;
        }

        $tag_model = new shopTagModel();
        $tags = [];

        $escapedBS = 'ESCAPED_BACKSLASH';
        while (false !== strpos($conditions, $escapedBS)) {
            $escapedBS .= rand(0, 9);
        }
        $escapedAmp = 'ESCAPED_AMPERSAND';
        while (false !== strpos($conditions, $escapedAmp)) {
            $escapedAmp .= rand(0, 9);
        }

        $conditions = str_replace('\\&', $escapedAmp, str_replace('\\\\', $escapedBS, $conditions));
        $conditions = explode('&', $conditions);

        $saved_product_fields = [];
        $saved_features = [];
        foreach ($conditions as $part) {
            if (!($part = trim($part))) {
                continue;
            }
            $part = str_replace(array($escapedBS, $escapedAmp), array('\\\\', '\\&'), $part);
            $temp = preg_split("/(\\\$=|\^=|\*=|==|!=|>=|<=|=|>|<)/uis", $part, 2, PREG_SPLIT_DELIM_CAPTURE);

            if ($temp) {
                $name = array_shift($temp);
                $is_feature = false;
                $feature_name = null;

                //get feature name
                if (substr($name, -9) === '.value_id') {
                    $is_feature = true;
                    $feature_name = substr($name, 0, -9);
                } elseif (substr($name, -6) === '.value') {
                    $is_feature = true;
                    $feature_name = substr($name, 0, -6);
                }

                //Get previous saved values
                if ($is_feature) {
                    $tmp_result = ifset($saved_features, $feature_name, []);
                } else {
                    $tmp_result = ifset($saved_product_fields, $name, []);
                }

                if ($name == 'tag' || $name == 'type' || $name == 'badge') {
                    $tmp_result['type'] = 'select';
                    $tmp_result['values'] = str_replace('\&', '&', $temp[1]); //Remove escape ampersand
                    if ($name == 'tag') {
                        // backward compatibility
                        $values = explode('||', $tmp_result['values']);
                        $value_ids = [];
                        $tags = $tag_model->getByField('name', $values, true);
                        foreach ($values as $tag_name) {
                            foreach ($tags as $tag) {
                                if ($tag['name'] == $tag_name) {
                                    $value_ids[] = $tag['id'];
                                }
                            }
                        }
                        $tmp_result['values'] = $value_ids;
                    } else {
                        $tmp_result['values'] = explode('||', $tmp_result['values']);
                    }
                } elseif ($name == 'compare_price' && $temp[0] == '>') {
                    // backward compatibility
                    $tmp_result['type'] = 'range';
                    $tmp_result['begin'] = '0';
                    $tmp_result['end'] = '';
                } elseif ($temp[0] == '>=') {
                    $tmp_result['type'] = 'range';
                    $tmp_result['begin'] = $temp[1];
                    $tmp_result['end'] = isset($tmp_result['end']) ? $tmp_result['end'] : null;
                } elseif ($temp[0] == '<=') {
                    $tmp_result['type'] = 'range';
                    $tmp_result['begin'] = isset($tmp_result['begin']) ? $tmp_result['begin'] : null;
                    $tmp_result['end'] = $temp[1];
                } else {
                    $tmp_result['type'] = 'equal';
                    $tmp_result['condition'] = $temp[0];
                    $tmp_result['values'] = preg_split('@[,\s]+@', $temp[1]);
                }

                //Set update/new values
                if ($is_feature) {
                    $saved_features[$feature_name] = $tmp_result;
                } else {
                    $saved_product_fields[$name] = $tmp_result;
                }
            }
        }

        $currency_model = new shopCurrencyModel();
        $currencies = $currency_model->getCurrencies();
        $currency_id = wa('shop')->getConfig()->getCurrency(true);
        $currency = $currencies[$currency_id];

        $fields = [
            'create_datetime' => [
                'name' => _w('Date added'),
                'data' => [
                    'type' => 'date',
                    'render_type' => 'range',
                ]
            ],
            'edit_datetime' => [
                'name' => _w('Дата последнего изменения'),
                'data' => [
                    'type' => 'date',
                    'render_type' => 'range',
                ],
            ],
            'type' => [
                'name' => _w('Type'),
                'data' => [
                    'type' => 'select',
                    'render_type' => 'select',
                    'options' => [],
                ],
            ],
            'tag' => [
                'name' => _w('Tag'),
                'data' => [
                    'type' => 'select',
                    'render_type' => 'select',
                    'options' => $tags,
                ],
            ],
            'rating' => [
                'name' => _w('Rating'),
                'data' => [
                    'type' => 'double',
                    'render_type' => 'range',
                ],
            ],
            'price' => [
                'name' => _w('Price'),
                'data' => [
                    'type' => 'double',
                    'render_type' => 'range',
                    'currency' => $currency,
                ],
            ],
            'compare_price' => [
                'name' => _w('Compare at price'),
                'data' => [
                    'type' => 'double',
                    'render_type' => 'range',
                    'currency' => $currency,
                ],
            ],
            'purchase_price' => [
                'name' => _w('Purchase price'),
                'data' => [
                    'type' => 'double',
                    'render_type' => 'range',
                    'currency' => $currency,
                ],
            ],
            'count' => [
                'name' => _w('In stock'),
                'data' => [
                    'type' => 'double',
                    'render_type' => 'range',
                ],
            ],
            'badge' => [
                'name' => _w('Badge'),
                'data' => [
                    'type' => 'varchar',
                    'render_type' => 'select',
                    'options' => [
                        [
                            'name' => _w('New!'),
                            'value' => 'new',
                            'icon' => 'fas fa-bolt'
                        ],
                        [
                            'name' => _w('Low price!'),
                            'value' => 'lowprice',
                            'icon' => 'fas fa-piggy-bank',
                        ],
                        [
                            'name' => _w('Bestseller!'),
                            'value' => 'bestseller',
                            'icon' => 'fas fa-chart-line',
                        ],
                        [
                            'name' => _w('Custom badge'),
                            'value' => 'custom',
                            'icon' => 'fas fa-code',
                        ]
                    ]
                ],
            ],
        ];

        $result = [];
        foreach ($saved_product_fields as $key => $field) {
            if (isset($fields[$key])) {
                $rule = $fields[$key];
                $rule['type'] = 'product_param';
                $rule['data'] += [
                    'id' => $key,
                    'name' => $rule['name'],
                    'display_type' => 'product'
                ];
                if ($rule['data']['render_type'] == 'range' && $field['type'] == 'range') {
                    $rule['data']['options'] = [
                        ['name' => '', 'value' => $field['begin']],
                        ['name' => '', 'value' => $field['end']]
                    ];
                } elseif ($rule['data']['render_type'] == 'select' && $field['type'] == 'select') {
                    $rule['data']['values'] = $field['values'];
                    if ($key == 'type' && $field['values']) {
                        $type_model = new shopTypeModel();
                        $types = $type_model->select('`id`, `name`')->where('id IN (?)', $field['values'])->fetchAll();
                        $rule['data']['options'] = $types;
                    }
                }
                $result[] = $rule;
            }
        }

        if ($saved_features) {
            $feature_model = new shopFeatureModel();
            $where = "`type` != 'text' AND `type` != 'divider' AND `type` NOT LIKE '2d.%' AND `type` NOT LIKE '3d.%' 
                AND `parent_id` IS NULL AND `code` IN (?)";
            $features = $feature_model->where($where, array_keys($saved_features))->order('`count` DESC')->fetchAll('code', true);
            $feature_codes = [];
            foreach ($features as $code => $feature) {
                $feature_codes[$feature['id']] = $code;
            }

            $selectable_value_ids = [];
            foreach ($saved_features as $code => $feature) {
                if ($feature['type'] == 'equal' && !empty($feature['values'])) {
                    $selectable_value_ids[$code] = array_map('intval', $feature['values']);
                }
            }
            if (empty($selectable_value_ids)) {
                $selectable_value_ids = null;
            }
            $selectable_features = $feature_model->getValues($features, $selectable_value_ids);
            foreach ($selectable_features as $code => $feature) {
                if (isset($features[$code]) && isset($feature['values'])) {
                    $features[$code]['values'] = $feature['values'];
                }
            }
            $features = shopProdSkuAction::formatFeatures($features, true);

            foreach ($features as $feature) {
                $feature['display_type'] = 'feature';
                $saved_rule = $saved_features[$feature_codes[$feature['id']]];
                if (empty($feature['selectable']) && $saved_rule['type'] == 'range') {
                    $feature['options'] = [
                        ['name' => '', 'value' => $saved_rule['begin']],
                        ['name' => '', 'value' => $saved_rule['end']]
                    ];
                } elseif ((!empty($feature['selectable']) && $saved_rule['type'] == 'equal')
                    || (mb_strpos($feature['type'], shopFeatureModel::TYPE_VARCHAR) !== false
                        || mb_strpos($feature['type'], shopFeatureModel::TYPE_COLOR) !== false
                        || $feature['type'] == shopFeatureModel::TYPE_BOOLEAN)
                ) {
                    $feature['values'] = $saved_rule['values'];
                }
                $result[] = [
                    'name' => $feature['name'],
                    'type' => 'feature',
                    'data' => $feature,
                ];
            }
        }

        usort($result, function($f1, $f2) {
            return strnatcasecmp(mb_strtolower(trim($f1['name'])), mb_strtolower(trim($f2['name'])));
        });

        return $result;
    }
}