<?php
/**
 * /products/<id>/services/
 */
class shopProdServicesAction extends waViewAction
{
    public function execute()
    {
        $product_id = waRequest::param('id', '', 'int');
        $product = new shopProduct($product_id);
        if (!$product['id']) {
            throw new waException(_w("Unknown product"), 404);
        }
        $formatted_product = self::formatProduct($product);
        $frontend_urls = shopProdGeneralAction::getFrontendUrls($product)[0];

        $type_model = new shopTypeModel();
        $product_types = $type_model->getTypes(true);

        $services = self::getServices($product["id"]);
        $product_types = self::formatProductTypes($product_types);
        $product_type = ifset($product_types, $product["type_id"], null);

        $this->view->assign([
            'frontend_urls'     => $frontend_urls,
            'product'           => $product,
            'product_type'      => $product_type,
            'formatted_product' => $formatted_product,
            'services'          => $services
        ]);

        $this->setLayout(new shopBackendProductsEditSectionLayout([
            'product' => $product,
            'content_id' => 'services',
        ]));
    }

    protected function getServices($product_id) {
        $product_services_model = new shopProductServicesModel();
        $services = $product_services_model->getProductServiceFullInfo($product_id);

        $result = [];
        foreach ($services as $service) {
            $result[] = self::formatService($service);
        }

        return $result;
    }

    protected function formatProduct($product)
    {
        return [
            "id"               => $product["id"],
            "name"             => $product["name"],
            "summary"          => $product["summary"],
            "description"      => $product["description"]
        ];
    }

    protected function formatProductTypes($types) {
        $result = [];

        foreach ($types as $type) {
            $icon_html = (!empty($type["icon"]) ? shopHelper::getIcon($type["icon"]) : shopHelper::getIcon("box") );

            $result[$type["id"]] = [
                "id"        => $type["id"],
                "name"      => $type["name"],
                "icon"      => $type["icon"],
                "icon_html" => $icon_html
            ];
        }

        return $result;
    }

    protected function formatService($service) {
        $variants = ifset($service, "variants", []);
        $service["variant_id_default"] = $service["variant_id"];
        $is_changed = false;

        if (!empty($variants)) {
            foreach ($variants as &$variant) {
                if ($variant["status"] === shopProductServicesModel::STATUS_DEFAULT) {
                    $service["variant_id"] = $variant["id"];
                    if ($service["variant_id"] !== $service["variant_id_default"]) {
                        $is_changed = true;
                    }
                }

                if (!empty($variant["price"])) {
                    $is_changed = true;
                }

                if (!empty($variant["skus"])) {
                    foreach ($variant["skus"] as $_sku) {
                        if (!empty($_sku["price"])) {
                            $is_changed = true;
                        }
                    }

                    $variant["skus"] = array_values($variant["skus"]);
                }
            }
            $service["variants"] = array_values($variants);
        }

        $service["is_changed"] = $is_changed;

        return $service;
    }
}
