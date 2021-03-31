<?php
/**
 * /products/<id>/seo/
 */
class shopProdSeoAction extends waViewAction
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

        $this->view->assign([
            'frontend_urls'     => $frontend_urls,
            'product'           => $product,
            'formatted_product' => $formatted_product,
            'search'            => self::getSearchData($formatted_product),
            'social'            => self::getSocialData($formatted_product)
        ]);

        $this->setLayout(new shopBackendProductsEditSectionLayout([
            'product' => $product,
            'content_id' => 'seo',
        ]));
    }

    protected function formatProduct($product)
    {
        $photos = self::getPhotos($product);

        foreach ($product["skus"] as $_sku) {
            if (!empty($_sku["image_id"])) {
                // Добавляем информацию о главной фотке
                if (($_sku["id"] === $product["sku_id"])) {
                    $product["image_id"] = $_sku["image_id"];
                }
            }
        }

        // модель главной фотки
        $photo = null;
        if (!empty($product["image_id"]) && !empty($photos) && !empty($photos[$product["image_id"]])) {
            $photo = $photos[$product["image_id"]];
        }

        $_normal_mode = ($product["sku_count"] > 1);

        $storefronts_data = self::getProductStorefrontsData( $product );
        $storefronts = $storefronts_data["list"];
        $root_front = $storefronts_data["root"];

        return [
            "id"               => $product["id"],
            "name"             => $product["name"],
            "summary"          => $product["summary"],
            "description"      => $product["description"],
            "image_id"         => $product["image_id"],
            "meta_title"       => $product["meta_title"],
            "meta_keywords"    => $product["meta_keywords"],
            "meta_description" => $product["meta_description"],
            "og"               => $product["og"],
            "price"            => shop_currency_html($product["price"]),
            "photo"            => $photo,
            "photos"           => array_values( $photos ),
            "normal_mode"      => $_normal_mode,
            "storefronts"      => $storefronts,
            "root_front"       => $root_front
        ];
    }

    protected function getPhotos($product)
    {
        $result = [];

        $config = wa('shop')->getConfig();
        $wa_app_url = wa()->getAppUrl(null, true);

        $_images = $product->getImages([
            'default' => $config->getImageSize('default')
        ]);

        foreach ($_images as $_image) {
            $_url_original = $wa_app_url . "?module=prod&action=origImage&id=" . $_image["id"];
            $result[$_image["id"]] = [
                "id" => $_image["id"],
                "url" => $_image["url_default"],
                "url_original" => $_url_original,
                "description" => $_image["description"]
            ];
        }

        return $result;
    }

    protected function getProductStorefrontsData($product)
    {
        $result = [
            "list" => [],
            "root" => null
        ];

        $storefronts = shopProdGeneralAction::getFrontendUrls($product)[0];
        if (!empty($storefronts)) {
            $root_front = null;

            foreach ($storefronts as $_front) {
                $is_root = (!$root_front && !empty($_front["proper_url"]));

                $front = [
                    "url" => $_front["url"],
                    "is_root" => $is_root
                ];

                $result["list"][] = $front;

                if ($is_root) { $root_front = $front; }
            }

            if (!$root_front) {
                $root_front = $result[0];
                $root_front["is_root"] = true;
            }

            $result["root"] = $root_front;
        }

        return $result;
    }

    protected function getSearchData($product)
    {
        return [
            "url"         => (!empty($product["root_front"]) ? $product["root_front"]["url"] : ""),
            "title"       => $product["meta_title"],
            "keywords"    => $product["meta_keywords"],
            "description" => $product["meta_description"]
        ];
    }

    protected function getSocialData($product)
    {
        $description = "";
        if (!empty($product["summary"])) {
            $description = $product["summary"];
        }
        elseif (!empty($product["description"])) {
            $description = $product["description"];
        }

        $result = [
            "url"         => (!empty($product["root_front"]) ? $product["root_front"]["url"] : ""),
            "title"       => $product["name"],
            "description" => $description,
            "image_id"    => ($product["image_id"] ? $product["image_id"] : ""),
            "image_url"   => "",
            "video_url"   => "",
            "keywords"    => "",
            "type"        => "product",
            "is_auto"     => true
        ];

        $og = $product["og"];
        if (!empty($og)) {
            $result["is_auto"]     = false;
            $result["use_url"]     = !empty($og["image"]);
            $result["title"]       = ifset( $og, "title", $result["title"] );
            $result["image_id"]    = ifset( $og, "image_id", $result["image_id"] );
            $result["image_url"]   = ifset( $og, "image", $result["image_url"] );
            $result["video_url"]   = ifset( $og, "video", $result["video_url"] );
            $result["description"] = ifset( $og, "description", $result["description"] );
        }

        return $result;
    }
}
