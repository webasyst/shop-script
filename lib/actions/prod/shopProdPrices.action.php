<?php
/**
 * /products/<id>/pages/
 */
class shopProdPricesAction extends waViewAction
{
    /**
     * @throws waException
     */
    public function execute()
    {
        $product_id = waRequest::param('id', '', waRequest::TYPE_STRING);
        shopProdGeneralAction::createEmptyProduct($product_id);
        $product = new shopProduct($product_id);
        if (!$product['id']) {
            $this->setTemplate('templates/actions/prod/includes/deleted_product.html');
            $this->setLayout(new shopBackendProductsEditSectionLayout([
                'content_id' => 'prices',
            ]));
            return;
        }

        $product_model = new shopProductModel();
        $can_edit = !!$product_model->checkRights($product["id"]);

        list($frontend_urls, $total_storefronts_count, $url_template) = shopProdGeneralAction::getFrontendUrls($product, false);

        $formatted_product = self::formatProduct($product);
        $formatted_product["can_edit"] = $can_edit;

        $promos = self::getPromos($product);
        $backend_prod_content_event = $this->throwEvent($product);

        $this->view->assign([
            "url_template"  => $url_template,
            "frontend_urls" => $frontend_urls,

            "product"           => $product,
            "formatted_product" => $formatted_product,
            "stocks"            => shopProdSkuAction::getStocks(),

            "prices_model" => self::getPricesModel($product, $promos),
            "promos_model" => self::getPromosModel($product, $promos),
            "filters"      => self::getFilters($promos),

            'backend_prod_content_event' => $backend_prod_content_event
        ]);

        $this->setLayout(new shopBackendProductsEditSectionLayout([
            'product' => $product,
            'content_id' => 'prices',
        ]));
    }

    protected function formatProduct($product)
    {
        // Features (we only need features available for SKUS on this page)
        $feature_model = new shopFeatureModel();
        $features = $feature_model->getByType($product->type_id, 'code');
        $features = array_filter($features, function($feature) {
            return $feature['available_for_sku'];
        });

        // SKU feature values
        $product_features_model = new shopProductFeaturesModel();
        $skus_features_values = $product_features_model->getValuesMultiple($features, $product['id'], array_keys($product['skus']));

        // Характеристики для выбора на витрине.
        // Features used for product variety selection in the storefront.
        $features_selectable_model = new shopProductFeaturesSelectableModel();
        $selected_selectable_feature_ids = $features_selectable_model->getProductFeatureIds($product['id']);

        // Use the same formatter as in SKUS section
        $formatted_features = shopProdSkuAction::formatFeatures($features);
        $formatted_product = shopProdSkuAction::formatProduct($product, array(
            "features"                        => $formatted_features,
            "skus_features_values"            => $skus_features_values,
            "selected_selectable_feature_ids" => $selected_selectable_feature_ids
        ));

        $formatted_product["skus"] = self::formatSkus(array_values($formatted_product["skus"]), $product["currency"]);

        $unit_model = new shopUnitModel();
        $_units = $unit_model->getAll('id');
        foreach ($_units as $_unit) {
            if ($_unit["id"] === $product["stock_unit_id"]) {
                $short_name = (!empty($_unit["storefront_name"]) ? $_unit["storefront_name"] : $_unit["short_name"]);
                $formatted_product["stock_unit"] = [
                    "id" => (string)$_unit["id"],
                    "name" => $_unit["name"],
                    "name_short" => $short_name
                ];
                break;
            }
        }

        // Очищаем ненужное
        unset($formatted_product["badge_id"]);
        unset($formatted_product["badges"]);
        unset($formatted_product["image_id"]);
        unset($formatted_product["photo"]);
        unset($formatted_product["photos"]);
        unset($formatted_product["features"]);
        unset($formatted_product["params"]);

        return $formatted_product;
    }

    protected function formatSkus($skus, $currency) {
        $result = [];

        $rules = $this->getRules();

        foreach ($skus as $_sku) {
            $_sku_mods = [];

            foreach ($_sku["modifications"] as $_sku_mod) {
                $promos = null;

                foreach ($rules as $rule) {
                    if (isset($rule['rule_params'][$_sku_mod['product_id']]['skus'][$_sku_mod['id']])) {
                        $promo_data = $rule['rule_params'][$_sku_mod['product_id']]['skus'][$_sku_mod['id']];

                        if (isset($promo_data["price"]) && $promo_data["price"] > 0) {
                            $promo_data["price"] = shop_currency($promo_data["price"], [ 'in_currency' => $currency, 'out_currency' => $currency, 'extended_format' => '%2{h}' ]);
                        } else {
                            $promo_data["price"] = null;
                        }

                        if (isset($promo_data["compare_price"]) && $promo_data["compare_price"] > 0) {
                            $promo_data["compare_price"] = shop_currency($promo_data["compare_price"], [ 'in_currency' => $currency, 'out_currency' => $currency, 'extended_format' => '%2{h}' ]);
                        } else {
                            $promo_data["compare_price"] = null;
                        }

                        $promos[$rule['promo_id']] = $promo_data;
                    }
                }

                $_sku_mods[] = [
                    "id"        => $_sku_mod['id'],
                    "status"    => (bool)$_sku_mod["status"],
                    "available" => (bool)$_sku_mod["available"],

                    "name"  => $_sku_mod["features_name"],
                    "stock" => $_sku_mod["stock"],
                    "count" => $_sku_mod["count"],

                    "price"         => shop_currency($_sku_mod["price"], [ 'in_currency' => $currency, 'out_currency' => $currency, 'extended_format' => '%2{h}' ]),
                    "compare_price" => shop_currency($_sku_mod["compare_price"], [ 'in_currency' => $currency, 'out_currency' => $currency, 'extended_format' => '%2{h}' ]),

                    "promos_values" => $promos,
                ];
            }

            $_sku["modifications"] = $_sku_mods;

            $result[] = $_sku;
        }

        return $result;
    }

    protected function getFilters($promos) {
        $_promos = [
            [
                "id" => "all",
                "name" => _w("Any")
            ],
            [
                "id" => "promos",
                "name" => _w("All promos")
            ],
            [
                "id" => "promos_active",
                "name" => _w("Only valid promos")
            ]
        ];

        $_available = [
            [
                "id" => "all",
                "name" => _w("All")
            ],
            [
                "id" => "available",
                "name" => _w("Only available")
            ],
            [
                "id" => "unavailable",
                "name" => _w("Only unavailable")
            ]
        ];

        $_visibility = [
            [
                "id" => "all",
                "name" => _w("All")
            ],
            [
                "id" => "visible",
                "name" => _w("Only visible")
            ],
            [
                "id" => "hidden",
                "name" => _w("Only hidden")
            ]
        ];

        $_activity = [
            [
                "id" => "all",
                "name" => _w("All")
            ],
            [
                "id" => "active",
                "name" => _w("Only valid promos")
            ]
        ];

        $_stopped_enabled = false;
        $_scheduled_enabled = false;
        foreach ($promos as $_promo) {
            if ($_promo["status"] === "stopped") {
                $_stopped_enabled = true;
            }
            if ($_promo["status"] === "scheduled") {
                $_scheduled_enabled = true;
            }
        }
        if ($_stopped_enabled) {
            $_activity[] = [
                "id" => "stopped",
                "name" => _w("Only paused")
            ];
        }
        if ($_scheduled_enabled) {
            $_activity[] = [
                "id" => "scheduled",
                "name" => _w("Only planned")
            ];
        }

        return [
            "promos" => [
                "label" => _w("Participation in promos:"),
                "options" => $_promos
            ],
            "available" => [
                "label" => _w("Availability for purchase:"),
                "options" => $_available
            ],
            "visibility" => [
                "label" => _w("Visibility:"),
                "options" => $_visibility
            ],
            "promos_activity" => [
                "label" => _w("Show promos:"),
                "options" => $_activity
            ]
        ];
    }

    protected function getPricesModel($product, $promos) {
        $filters = [
            "available"  => "all",
            "visibility" => "all",
        ];

        if (!empty($promos)) {
            $filters = array_merge(["promos" => "all"], $filters);
        }

        return [
            "filters" => $filters
        ];
    }

    protected function getPromosModel($product, $promos) {
        $filters = [];

        $_can_play_marketing = wa()->getUser()->getRights('shop', 'marketing');
        if ($_can_play_marketing) {
            $filters = array_merge(["promos_activity"  => "all"], $filters);
        }

        return [
            "promos" => $promos,
            "filters" => $filters
        ];
    }

    protected function getPromos($product) {
        $promo_ids = array();
        $rules = $this->getRules();
        foreach ($rules as $rule) {
            if (isset($rule['rule_params'][$product['id']])) {
                $promo_ids[] = $rule['promo_id'];
            }
        }
        $promo_model = new shopPromoModel();
        $promos = $promo_model->getById($promo_ids);

        $promo_routes_model = new shopPromoRoutesModel();
        $promo_routes = $promo_routes_model->getByField('promo_id', $promo_ids, true);

        $_can_play_marketing = wa()->getUser()->getRights('shop', 'marketing');

        $_promos_active = [];
        $_promos_paused = [];
        $_promos_scheduled = [];

        foreach ($promos as $promo) {
            $formatted_promo = self::formatPromo($promo, $promo_routes, $product);
            if ($formatted_promo["status"] === "active" || $_can_play_marketing) {
                switch ($formatted_promo["status"]) {
                    case "active":
                        $_promos_active[] = $formatted_promo;
                        break;
                    case "stopped":
                        $_promos_paused[] = $formatted_promo;
                        break;
                    case "scheduled":
                        $_promos_scheduled[] = $formatted_promo;
                        break;
                }
            }
        }

        $_promos_active = self::sortPromos($_promos_active, "finish");
        $_promos_paused = self::sortPromos($_promos_paused, "finish");
        $_promos_scheduled = self::sortPromos($_promos_scheduled, "start");

        return array_merge($_promos_active, $_promos_paused, $_promos_scheduled);
    }

    /**
     * @param $promo
     * @param $promo_routes
     * @param shopProduct $product
     * @return array
     * @throws waException
     */
    protected function formatPromo($promo, $promo_routes, $product) {
        $storefronts = shopStorefrontList::getAllStorefronts();
        $product_storefronts = $this->getProductStorefronts($product);

        $now = time();
        $start_time = (!empty($promo["start_datetime"]) ? strtotime($promo["start_datetime"]) : null);
        $finish_time = (!empty($promo["finish_datetime"]) ? strtotime($promo["finish_datetime"]) : null);

        $status = "active";
        if ($finish_time && $finish_time < $now) {
            $status = "finished";
        } else if ($promo["enabled"] === "0") {
            $status = "stopped";
        } else if ($start_time && $start_time > $now) {
            $status = "scheduled";
        }

        $date_string = _w("Without time limitation.");
        if (!empty($promo["start_datetime"]) && !empty($promo["finish_datetime"]) ) {
            $date_string = sprintf(_w("from %s to %s"),
                '<span class="s-date">'.waDateTime::format( 'humandate', $promo["start_datetime"]).'</span>',
                '<span class="s-date">'.waDateTime::format( 'humandate', $promo["finish_datetime"]).'</span>');
        } else if (!empty($promo["start_datetime"])) {
            $date_string = sprintf(_w("from %s, %s"),
                '<span class="s-date">'.waDateTime::format( 'humandate', $promo["start_datetime"]).'</span>',
                '<span class="s-date">'._w("without an end date").'</span>');
        } else if (!empty($promo["finish_datetime"])) {
            $date_string = sprintf(_w("%s, until %s"),
                '<span class="s-date">'._w("without a start date").'</span>',
                '<span class="s-date">'.waDateTime::format( 'humandate', $promo["finish_datetime"]).'</span>');
        }

        $_storefronts = array();
        foreach ($promo_routes as $route) {
            if ($promo["id"] === $route["promo_id"]) {
                if (stripos($route["storefront"], "%all%") !== false) {
                    $_storefronts = null;
                    break;
                } else {
                    $found_domain = false;
                    $full_route_data = [];
                    foreach ($product_storefronts as $domain => $routes) {
                        foreach ($routes as $r) {
                            if ($route['storefront'] == $r['prepared_route']) {
                                $full_route_data = $r;
                                $found_domain = $domain;
                            }
                        }
                    }
                    if (in_array($route["storefront"], $storefronts) && $found_domain !== false) {
                        $category_url = $product->getCategoryUrl($full_route_data);
                        $_storefronts[] = wa()->getRouteUrl('shop/frontend/product', array(
                                'product_url' => $product['url'],
                                'category_url' => $category_url
                            ), true, $found_domain, $full_route_data['url']);
                    }
                }
            }
        }

        return [
            "id"            => $promo["id"],
            "name"          => $promo["name"],
            "status"        => $status,
            "start_time"    => $start_time,
            "finish_time"   => $finish_time,
            "date_string"   => $date_string,
            "storefronts"   => $_storefronts,
            "filters"       => [
                "available"  => "all",
                "visibility" => "all",
            ]
        ];
    }

    protected function getProductStorefronts($product)
    {
        $routing = wa()->getRouting();
        $domain_routes = $routing->getByApp('shop');
        $storefronts = [];
        foreach ($domain_routes as $domain => $routes) {
            foreach ($routes as $route) {
                if (!empty($route['type_id']) && !in_array($product->type_id, (array)$route['type_id'])) {
                    continue;
                }
                if ($route['url'] !== '*') {
                    $route['prepared_route'] = rtrim($domain, '/') . '/' . rtrim($route['url'], '/*') . '/';
                } else {
                    $route['prepared_route'] = $domain;
                }
                $storefronts[$domain][] = $route;
            }
        }
        return $storefronts;
    }

    protected function getRules()
    {
        static $rules;
        if (!isset($rules)) {
            $promo_rules_model = new shopPromoRulesModel();
            $rules = $promo_rules_model->getByField(['rule_type' => 'custom_price'], true);
        }

        return $rules;
    }

    /**
     * Throw 'backend_prod_content' event
     * @param shopProduct $product
     * @return array
     * @throws waException
     */
    protected function throwEvent($product)
    {
        /**
         * @event backend_prod_content
         * @since 8.19.0
         *
         * @param shopProduct $product
         * @param string $content_id
         *       Which page (tab) is shown
         */
        $params = [
            'product' => $product,
            'content_id' => 'prices',
        ];

        return wa('shop')->event('backend_prod_content', $params);
    }

    protected static function sortPromos($promos, $sort)
    {
        $sort_function_start = function($a, $b) {
            if ($a["start_time"] < $b["start_time"]) { return -1; }
            else if ($a["start_time"] > $b["start_time"]) { return 1; }
            else return 0;
        };

        $sort_function_finish = function($a, $b) {
            if ($a["finish_time"] < $b["finish_time"]) { return -1; }
            else if ($a["finish_time"] > $b["finish_time"]) { return 1; }
            else return 0;
        };

        $_has_start = [];
        $_has_finish = [];
        $_others = [];

        foreach ($promos as $_promo) {
            if ($sort === "finish") {
                if (!empty($_promo["finish_time"])) {
                    $_has_finish[] = $_promo;
                } else if (! empty( $_promo["start_time"])) {
                    $_has_start[] = $_promo;
                } else {
                    $_others[] = $_promo;
                }
            } else {
                if (! empty( $_promo["start_time"])) {
                    $_has_start[] = $_promo;
                } else if (! empty( $_promo["finish_time"])) {
                    $_has_finish[] = $_promo;
                } else {
                    $_others[] = $_promo;
                }
            }
        }

        usort($_has_start, $sort_function_start);
        usort($_has_finish, $sort_function_finish);

        if ($sort === "finish") {
            $promos = array_merge($_has_finish, $_has_start, $_others);
        } else {
            $promos = array_merge($_has_start, $_has_finish, $_others);
        }

        return $promos;
    }
}
