<?php

class shopProdCategoriesAction extends waViewAction
{
    public function execute()
    {
        $category_routes_model = new shopCategoryRoutesModel();
        $category_routes_model->deleteMissingRoutes();

        $categories = $this->getCategories();
        $storefronts = $this->getStorefronts();

        /**
         * @event backend_prod_categories
         * @since 9.4.1
         */
        $backend_prod_categories = wa('shop')->event('backend_prod_categories', ref([
            "storefronts" => &$storefronts,
            "categories"  => &$categories,
        ]));

        $this->view->assign([
            "categories"             => $categories,
            "storefronts"            => $storefronts,
            "category_sort_variants" => self::getCategorySortVariants(),
            "backend_prod_categories" => $backend_prod_categories,
        ]);

        $this->setTemplate("templates/actions/prod/main/Categories.html");
        $this->setLayout(new shopBackendProductsListSectionLayout());
    }

    public static function getCategories() {
        function fixCategoryList($cats) {
            return array_map(function($c) {
                $c["categories"] = array_values(ifempty($c, "categories", []));
                if (!empty($c["categories"])) {
                    $c["categories"] = fixCategoryList($c["categories"]);
                }
                return $c;
            }, $cats);
        }

        $category_model = new shopCategoryModel();
        $categories = $category_model->getFullTree("id, name, parent_id, depth, count, type, status, sort_products, filter, include_sub_categories",  false);
        $categories = shopProdCategoriesAction::formatCategories($categories);
        $categories_tree = $category_model->buildNestedTree($categories);

        return array_values(fixCategoryList($categories_tree));
    }

    /**
     * @param array $categories
     * @return array
     * @throws waException
     */
    public static function formatCategories($categories)
    {
        $result = [];
        foreach ($categories as $category) {
            $result[$category['id']] = shopProdCategoriesAction::formatCategory($category);
        }

        return $result;
    }

    public static function formatCategory($category) {
        //
        $category_routes_model = new shopCategoryRoutesModel();
        $routes = $category_routes_model->getRoutes($category["id"]);

        $category["storefronts"] = $routes;
        $category["sort_products"] = (!empty($category["sort_products"]) ? $category["sort_products"] : "");
        $category["explode_feature_ids"] = $category["filter"] !== null ? explode(',', $category["filter"]) : [];
        $category["allow_filter"] = $category["explode_feature_ids"] ? "1" : "0";

        return $category;
    }

    public function getStorefronts() {
        $result = [];

        foreach (wa()->getRouting()->getByApp('shop') as $domain => $domain_routes) {
            foreach ($domain_routes as $route) {
                $url = $domain.'/'.$route['url'];
                $result[] = [
                    'url' => $url,
                    'name' => waIdna::dec($url),
                ];
            }
        }

        return $result;
    }

    public static function getCategorySortVariants() {
        $result = [
            [
                "name" => _w("Manual"),
                "value" => ""
            ],
            [
                "name" => _w("By name"),
                "value" => "name ASC"
            ],
            [
                "name" => _w("Most expensive"),
                "value" => "price DESC"
            ],
            [
                "name" => _w("Least expensive"),
                "value" => "price ASC"
            ],
            [
                "name" => _w("Highest rated"),
                "value" => "rating DESC"
            ],
            [
                "name" => _w("Lowest rated"),
                "value" => "rating ASC"
            ],
            [
                "name" => _w("Bestsellers by sold amount"),
                "value" => "total_sales DESC"
            ],
            [
                "name" => _w("Worst sellers"),
                "value" => "total_sales ASC"
            ],
            [
                "name" => _w("In stock"),
                "value" => "count DESC"
            ],
            [
                "name" => _w("Date added"),
                "value" => "create_datetime DESC"
            ],
            [
                "name" => _w("Stock net worth"),
                "value" => "stock_worth DESC"
            ]
        ];

        // TODO: добавить другие варианты сортировки товаров, которые добавляют плагины.
        // $result[] = [
        //     "name" => "TODO:plugin variant",
        //     "value" => "todo:plugin_id"
        // ];

        return $result;
    }
}
