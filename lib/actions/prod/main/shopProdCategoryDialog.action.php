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
}