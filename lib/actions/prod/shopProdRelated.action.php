<?php
/**
 * /products/<id>/related/
 */
class shopProdRelatedAction extends waViewAction
{
    public function execute()
    {
        $product_id = waRequest::param('id', '', waRequest::TYPE_INT);
        $product = new shopProduct($product_id);

        if (!$product['id']) {
            throw new waException(_w("Unknown product"), 404);
        }

        $frontend_urls = shopProdGeneralAction::getFrontendUrls($product)[0];

        $type_model = new shopTypeModel();
        $type = $type_model->getById($product['type_id']);
        $cross_selling = self::getCrossSelling($product, $type);
        $upselling = self::getUpselling($product, $type);

        $this->view->assign([
            'product'       => self::formatProduct($product),
            'upselling'     => $upselling,
            'cross_selling' => $cross_selling,
            'frontend_urls' => $frontend_urls,
        ]);

        $this->setLayout(new shopBackendProductsEditSectionLayout([
            'product' => $product,
            'content_id' => 'related',
        ]));
    }

    protected function formatProduct($product)
    {
        $_normal_mode = ($product["sku_count"] > 1);

        $type = null;
        if ($product["type_id"]) {
            $type_model = new shopTypeModel();
            $types = $type_model->getTypes(true);
            if (!empty($types[$product["type_id"]])) {
                $type = $types[$product["type_id"]];
            }
        }

        return [
            "id"          => $product["id"],
            "type"        => $type,
            "name"        => $product["name"],
            "summary"     => $product["summary"],
            "description" => $product["description"],
            "normal_mode" => $_normal_mode
        ];
    }

    protected function getCrossSelling($product, $type)
    {
        $products = [];
        $active_option_value = $product['cross_selling'] == null ? '1' : $product['cross_selling'];
        $view_type = $product["cross_selling"] == null || $product["cross_selling"] == 1 ? "auto" : "manual";

        if ($product["cross_selling"] == null) {
            $product["cross_selling"] = $type["cross_selling"] ? 1 : 0;
        }

        if ($product["cross_selling"] == 2) {
            $related_model = new shopProductRelatedModel();
            $related = $related_model->getAllRelated($product["id"]);
            if (!empty($related["cross_selling"])) {
                $wa_app_url = wa()->getAppUrl(null, true);
                foreach ($related["cross_selling"] as $_product) {
                    $products[] = [
                        "id" => $_product["id"],
                        "name" => $_product["name"],
                        "price" => shop_currency_html($_product["price"], true),
                        'url' => $wa_app_url . "products/{$_product['id']}/",
                    ];
                }
            }
        }

        $auto_enabled = !!$type["cross_selling"];
        $description = ($type["cross_selling"] ? _w("Auto (based on what other customers purchased with a particular product)") : null);;

        return [
            "view_type" => $view_type,
            "show_form" => false,
            "value" => $active_option_value,
            "auto_enabled" => $auto_enabled,
            "description" => $description,
            "options" => [
                [
                    "id"          => "off",
                    "name"        => _w( "Disable" ),
                    "value"       => "0",
                    "description" => "",
                    "disabled"    => false
                ],
                [
                    "id"          => "manual",
                    "name"        => _w( "Manually select recommended products" ),
                    "value"       => "2",
                    "description" => "",
                    "disabled"    => false
                ]
            ],
            "products" => $products
        ];
    }

    protected function getUpselling($product, $type)
    {
        $products = [];
        $active_option_value = $product['upselling'] == null ? '0' : $product['upselling'];
        $view_type = $product["upselling"] == null || $product["upselling"] != 1 ? "manual" : "auto";

        if ($product["upselling"] == null) {
            $product["upselling"] = $type["upselling"] ? 1 : 0;
        }

        if ($product["upselling"] == 2) {
            $related_model = new shopProductRelatedModel();
            $related = $related_model->getAllRelated($product["id"]);
            if (!empty($related["upselling"])) {
                $wa_app_url = wa()->getAppUrl(null, true);
                foreach ($related["upselling"] as $_product) {
                    $products[] = [
                        "id" => $_product["id"],
                        "name" => $_product["name"],
                        "price" => shop_currency_html($_product["price"], true),
                        'url' => $wa_app_url . "products/{$_product['id']}/",
                    ];
                }
            }
        }

        $auto_enabled = !!$type["upselling"];
        $description = null;

        if ($type["upselling"]) {
            $type_upselling_model = new shopTypeUpsellingModel();
            $data = $type_upselling_model->getByType($type['id']);
            $description = shopMarketingRecommendationsAction::getConditionHTML($data);

        }

        return [
            "view_type" => $view_type,
            "show_form" => false,
            "value" => $active_option_value,
            "auto_enabled" => $auto_enabled,
            "description" => $description,
            "options" => [
                [
                    "id"          => "off",
                    "name"        => _w( "Disable" ),
                    "value"       => "0",
                    "description" => "",
                    "disabled"    => false
                ],
                [
                    "id"          => "manual",
                    "name"        => _w( "Manually select recommended products" ),
                    "value"       => "2",
                    "description" => "",
                    "disabled"    => false
                ]
            ],
            "products" => $products
        ];
    }
}
