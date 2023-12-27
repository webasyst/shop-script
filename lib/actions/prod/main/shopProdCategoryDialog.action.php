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

        $category['preselected'] = false;
        if (!empty($_parent_category_id)) {
            $category["parent_id"] = (string)$_parent_category_id;
            $category_model = new shopCategoryModel();
            $parent_category = $category_model->getById($_parent_category_id);
            if (empty($category['id']) && $parent_category && $parent_category['type'] == $category_model::TYPE_DYNAMIC) {
                $category['type'] = (string)$category_model::TYPE_DYNAMIC;
                $category['preselected'] = true;
            }
        }

        /**
         * @event backend_prod_category_dialog
         * @since 10.1.0
         * @param array $category
         * @return string
         */
        $backend_prod_category_dialog = wa('shop')->event('backend_prod_category_dialog', ref([
            'category' => &$category,
        ]));

        $this->view->assign([
            "parent_id" => $_parent_category_id,
            "category" => $category,
            "backend_prod_category_dialog" => $backend_prod_category_dialog,
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

        $parsed_conditions = self::parseConditions($category['conditions']);
        $category['conditions'] = $this->formatConditions($parsed_conditions);

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
            } else {
                $og["enabled"] = false;
            }
        }

        return $og;
    }

    public static function parseConditions($conditions = '')
    {
        if (!$conditions) {
            return [];
        }

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

        $saved_conditions = [];
        foreach ($conditions as $part) {
            if (!($part = trim($part))) {
                continue;
            }
            $part = str_replace(array($escapedBS, $escapedAmp), array('\\\\', '\\&'), $part);
            $temp = preg_split("/(\\\$=|\^=|\*=|==|!=|>=|<=|=|>|<)/uis", $part, 2, PREG_SPLIT_DELIM_CAPTURE);

            if ($temp) {
                $name = array_shift($temp);
                $is_feature = $is_unit = false;
                $feature_name = null;

                //get feature name
                if (substr($name, -9) === '.value_id') {
                    $is_feature = true;
                    $feature_name = substr($name, 0, -9);
                } elseif (substr($name, -6) === '.value') {
                    $is_feature = true;
                    $feature_name = substr($name, 0, -6);
                } elseif (substr($name, -5) === '.unit') {
                    $is_feature = $is_unit = true;
                    $feature_name = substr($name, 0, -5);
                }

                //Get previous saved values
                if ($is_feature) {
                    $temp_result = ifset($saved_conditions, 'feature', $feature_name, []);
                } else {
                    $temp_result = ifset($saved_conditions, $name, []);
                }

                self::parseTempCondition($name, $temp_result, $temp, $is_unit);

                //Set update/new values
                if ($is_feature) {
                    $saved_conditions['feature'][$feature_name] = $temp_result;
                } else {
                    $saved_conditions[$name] = $temp_result;
                }
            }
        }

        return $saved_conditions;
    }

    protected static function parseTempCondition($name, &$temp_result, $temp, $is_unit)
    {
        if ($name == 'tag' || $name == 'type_id' || $name == 'badge') {
            $temp_result['type'] = 'select';
            $temp_result['values'] = str_replace('\&', '&', $temp[1]); //Remove escape ampersand
            $temp_result['values'] = explode('||', $temp_result['values']);
        } elseif ($name == 'compare_price' && $temp[0] == '>') {
            // backward compatibility
            $temp_result['type'] = 'range';
            $temp_result['begin'] = '0';
            $temp_result['end'] = '';
        } elseif ($temp[0] == '>=') {
            $temp_result['type'] = 'range';
            $temp_result['begin'] = $temp[1];
            $temp_result['end'] = isset($temp_result['end']) ? $temp_result['end'] : '';
            $temp_result['unit'] = isset($temp_result['unit']) ? $temp_result['unit'] : '';
        } elseif ($temp[0] == '<=') {
            $temp_result['type'] = 'range';
            $temp_result['begin'] = isset($temp_result['begin']) ? $temp_result['begin'] : '';
            $temp_result['end'] = $temp[1];
            $temp_result['unit'] = isset($temp_result['unit']) ? $temp_result['unit'] : '';
        } elseif ($is_unit) {
            $temp_result['type'] = 'range';
            $temp_result['begin'] = isset($temp_result['begin']) ? $temp_result['begin'] : '';
            $temp_result['end'] = isset($temp_result['end']) ? $temp_result['end'] : '';
            $temp_result['unit'] = $temp[1];
        } else {
            $temp_result['type'] = 'equal';
            $temp_result['condition'] = $temp[0];
            $temp_result['values'] = preg_split('@[,\s]+@', $temp[1]);
        }
    }

    /**
     * @param array $saved_conditions
     * @return array
     * @throws waException
     */
    protected function formatConditions($saved_conditions)
    {
        $category_helper = new shopCategoryHelper();
        $fields = $category_helper->getProductFields([
            'types' => true,
            'tags' => true,
        ]);

        $result = [];
        foreach ($saved_conditions as $key => $field) {
            if (isset($fields[$key])) {
                $rule = $fields[$key];
                if ($key == 'tag' && !empty($field['values'])) {
                    $tag_model = new shopTagModel();
                    $value_ids = $tag_model->select('id')->where('name IN (?)', [$field['values']])->fetchAll();
                    $field['values'] = array_column($value_ids, 'id');
                }
                $rule['type'] = 'product_param';
                $rule['data']['display_type'] = 'product';
                if ($rule['data']['render_type'] == 'range' && $field['type'] == 'range') {
                    $rule['data']['options'] = [
                        ['name' => '', 'value' => $field['begin']],
                        ['name' => '', 'value' => $field['end']]
                    ];
                } elseif ($rule['data']['render_type'] == 'select' && $field['type'] == 'select') {
                    $rule['data']['values'] = $field['values'];
                }
                $result[] = $rule;
            }
        }

        if (isset($saved_conditions['feature']) && !empty($saved_conditions['feature'])) {
            $feature_model = new shopFeatureModel();
            $options = [
                'code' => array_keys($saved_conditions['feature']),
                'ignore_text' => true,
                'ignore_complex_types' => true,
                'count' => false,
                'ignore' => false,
            ];
            $features = $feature_model->getFilterFeatures($options, shopFeatureModel::GET_ALL, true, 'count DESC');

            $feature_codes = [];
            foreach ($features as $code => $feature) {
                $feature_codes[$feature['id']] = $code;
            }

            $selectable_value_ids = [];
            foreach ($saved_conditions['feature'] as $code => $feature) {
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

            $dimension_list = shopDimension::getInstance()->getList();
            foreach ($features as $feature) {
                $feature['display_type'] = 'feature';
                $saved_rule = $saved_conditions['feature'][$feature_codes[$feature['id']]];
                if (empty($feature['selectable']) && $saved_rule['type'] == 'range') {
                    if ($feature['type'] == shopFeatureModel::TYPE_DATE || $feature['type'] == 'range.date') {
                        $saved_rule['begin'] = shopDateValue::timestampToDate($saved_rule['begin']);
                        $saved_rule['end'] = shopDateValue::timestampToDate($saved_rule['end']);
                    }
                    $feature['options'] = [
                        ['name' => '', 'value' => $saved_rule['begin']],
                        ['name' => '', 'value' => $saved_rule['end']]
                    ];
                    if (isset($saved_rule['unit']) && !empty($feature['units'])) {
                        foreach ($feature['units'] as $unit) {
                            if ($unit['value'] == $saved_rule['unit']) {
                                $feature['active_unit'] = $unit;
                            }
                        }
                    }
                } elseif ((!empty($feature['selectable']) && $saved_rule['type'] == 'equal')
                    || ($feature['type'] == shopFeatureModel::TYPE_VARCHAR
                        || $feature['type'] == shopFeatureModel::TYPE_COLOR
                        || $feature['type'] == shopFeatureModel::TYPE_BOOLEAN)
                ) {
                    $feature['values'] = $saved_rule['values'];
                } elseif ((isset($dimension_list[str_replace('dimension.', '', $feature['type'])])
                        || $feature['type'] == shopFeatureModel::TYPE_DATE || $feature['type'] == shopFeatureModel::TYPE_DOUBLE)
                    && empty($feature['selectable']) && $saved_rule['type'] == 'equal'
                ) {
                    // backward compatibility
                    $selectable_values = [];
                    foreach ($selectable_features[$feature['code']]['values'] as $selectable_value) {
                        if ($feature['type'] == shopFeatureModel::TYPE_DOUBLE) {
                            $selectable_values[] = $selectable_value;
                        } else {
                            $selectable_values[] = $feature['type'] == shopFeatureModel::TYPE_DATE ? $selectable_value->timestamp : $selectable_value->value_base_unit;
                        }
                    }
                    $begin = $end = '';
                    if ($selectable_values) {
                        $begin = min($selectable_values);
                        $end = max($selectable_values);
                        if ($feature['type'] == shopFeatureModel::TYPE_DATE) {
                            $begin = shopDateValue::timestampToDate($begin);
                            $end = shopDateValue::timestampToDate($end);
                        }
                    }
                    $feature['options'] = [
                        ['name' => '', 'value' => $begin],
                        ['name' => '', 'value' => $end],
                    ];
                    $base_unit = shopDimension::getBaseUnit($feature['type']);
                    if (!empty($feature['units'])) {
                        foreach ($feature['units'] as $unit) {
                            if ($unit['value'] == $base_unit['value']) {
                                $feature['active_unit'] = $unit;
                            }
                        }
                    }
                }
                $result[] = [
                    'name' => $feature['name'],
                    'type' => 'feature',
                    'is_negative' => !$feature['selectable'] && ($feature['type'] == shopFeatureModel::TYPE_DOUBLE || mb_strpos($feature['type'], 'range.') === 0 || mb_strpos($feature['type'], 'dimension.') === 0),
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
