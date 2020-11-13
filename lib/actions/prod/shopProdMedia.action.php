<?php
/**
 * /products/<id>/media/
 * Product editor: Photo and Video tab.
 */
class shopProdMediaAction extends waViewAction
{
    public function execute()
    {
        $product_id = waRequest::param('id', '', 'int');
        $product = new shopProduct($product_id);

        $frontend_urls = shopProdGeneralAction::getFrontendUrls($product)[0];

        $this->view->assign([
            'frontend_urls'     => $frontend_urls,
            'product'           => $product,
            'formatted_product' => self::formatProduct($product)
        ]);

        $this->setLayout(new shopBackendProductsEditSectionLayout([
            'product' => $product
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
                // Считаем кол-во использований фотки
                if (!empty($photos[$_sku["image_id"]])) {
                    $photos[$_sku["image_id"]]["uses_count"] += 1;
                }
            }
        }

        // модель главной фотки
        $photo = null;
        if (!empty($product["image_id"]) && !empty($photos) && !empty($photos[$product["image_id"]])) {
            $photo = $photos[$product["image_id"]];
        }

        $_normal_mode = ($product["sku_count"] > 1);

        return [
            "id"          => $product["id"],
            "video"       => ( ! empty( $product["video"] ) ? $product["video"] : null ),
            "image_id"    => $product["image_id"],
            "photo"       => $photo,
            "photos"      => array_values( $photos ),
            "normal_mode" => $_normal_mode
        ];
    }

    protected function getPhotos($product)
    {
        $result = [];

        $_images = $product->getImages('thumb');

        foreach ($_images as $_image) {
            $result[$_image["id"]] = [
                "id" => $_image["id"],
                "url" => $_image["url_thumb"],
                "description" => $_image["description"],
                "uses_count" => 0
            ];
        }

        return $result;
    }
}
