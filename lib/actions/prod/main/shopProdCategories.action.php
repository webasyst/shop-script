<?php

class shopProdCategoriesAction extends waViewAction
{
    public function execute()
    {
        $categories = $this->getCategories();
        $storefronts = $this->getStorefronts();

        shopHelper::setChapter('new_chapter');

        $this->view->assign([
            "categories"             => $categories,
            "storefronts"            => $storefronts,
            "category_sort_variants" => self::getCategorySortVariants()
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
        $categories = $category_model->getFullTree("id, name, parent_id, depth, count, type, status, sort_products",  false);
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
        $category_model = new shopCategoryModel();
        $category_model->recount();
        foreach ($categories as $category) {
            $result[$category['id']] = shopProdCategoriesAction::formatCategory($category);
        }

        return $result;
    }

    public static function formatCategory($category) {
        //
        $category_routes_model = new shopCategoryRoutesModel();
        $routes = $category_routes_model->getRoutes($category["id"]);

        if (!empty($routes)) {
            $routes = array_map(function($r) {
                $r = rtrim($r, '/*');
                // когда магазин поселен не в корне, нужно оставить финальный слеш
                if (strpos($r, '/') !== false) { $r .= '/'; }
                return $r;
            }, $routes);
        }

        $category["storefronts"] = $routes;
        $category["sort_products"] = (!empty($category["sort_products"]) ? $category["sort_products"] : "");

        return $category;
    }

    public function getStorefronts() {
        $result = [];

        $storefront_list = new shopStorefrontList();
        $all_routes = $storefront_list->getAll();

        foreach ($all_routes as $_route) {
            $result[] = [
                "url" => $_route,
                "name" => waIdna::dec($_route)
            ];
        }

        return $result;
    }

    public static function getCategorySortVariants() {
        $result = [
            [
                "name" => _w("Вручную (как задано в панели управления)"),
                "value" => ""
            ],
            [
                "name" => _w("По наименованию"),
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
        $result[] = [
            "name" => "TODO:plugin variant",
            "value" => "todo:plugin_id"
        ];

        return $result;
    }
}