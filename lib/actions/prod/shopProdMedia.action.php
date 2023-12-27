<?php
/**
 * /products/<id>/media/
 * Product editor: Photo and Video tab.
 */
class shopProdMediaAction extends waViewAction
{
    public function execute()
    {
        $product_id = waRequest::param('id', '', waRequest::TYPE_STRING);
        shopProdGeneralAction::createEmptyProduct($product_id);
        $product = new shopProduct($product_id);
        if (!$product['id']) {
            $this->setTemplate('templates/actions/prod/includes/deleted_product.html');
            $this->setLayout(new shopBackendProductsEditSectionLayout([
                'content_id' => 'media',
            ]));
            return;
        }
        $product_model = new shopProductModel();
        if (!$product_model->checkRights($product_id)) {
            throw new waException(_w('Access denied'));
        }

        $frontend_urls = shopProdGeneralAction::getFrontendUrls($product)[0];

        $backend_prod_content_event = $this->throwEvent($product);

        $this->view->assign([
            'frontend_urls'     => $frontend_urls,
            'product'           => $product,
            'formatted_product' => self::formatProduct($product),
            'backend_prod_content_event' => $backend_prod_content_event,
            'show_sku_warning' => shopProdSkuAction::isSkuCorrect($product['id'], $product['sku_type']),
        ]);

        $this->setLayout(new shopBackendProductsEditSectionLayout([
            'product' => $product,
            'content_id' => 'media',
        ]));
    }

    /**
     * @param shopProduct $product
     * @return array
     * @throws waDbException
     * @throws waException
     */
    protected function formatProduct($product)
    {
        $photos = self::getPhotos($product);

        $first_photo = [];
        if ($photos) {
            $first_photo = reset($photos);
        }

        foreach ($product["skus"] as $_sku) {
            if (!empty($_sku["image_id"])) {
                // совместимость с товарами у которых главное фото не на первом месте
                if ($_sku["id"] === $product["sku_id"] && $first_photo && $product['image_id'] != $first_photo['id']) {
                    $product["image_id"] = $_sku["image_id"];
                    $fake_main_photo = $photos[$_sku['image_id']];
                    unset($photos[$_sku['image_id']]);
                    array_unshift($photos, $fake_main_photo);
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
        $product_features_model = new shopProductFeaturesModel();
        $has_features_values = $product_features_model->checkProductFeaturesValues($product['id'], $product['type_id']);
        $_normal_mode = $product['sku_count'] > 1 || $has_features_values
            || ifempty($product, 'params', 'multiple_sku', null) || !empty($product->getSkuFeatures());
        foreach ($product['skus'] as $modification) {
            foreach (['stock_base_ratio', 'order_count_min', 'order_count_step'] as $field) {
                if (!empty($modification[$field])) {
                    $_normal_mode = true;
                    break 2;
                }
            }
        }

        return [
            "id"          => $product["id"],
            "video"       => ( ! empty( $product["video"] ) ? $product["video"] : null ),
            "image_id"    => $product["image_id"],
            "photo"       => $photo,
            "photos"      => array_values( $photos ),
            "normal_mode" => $_normal_mode
        ];
    }

    /**
     * @param shopProduct $product
     * @return array
     * @throws waException
     */
    protected function getPhotos($product)
    {
        $result = [];

        $config = wa('shop')->getConfig();
        $wa_app_url = wa()->getAppUrl(null, true);

        $_images = $product->getImages([
            'default' => $config->getImageSize('default')
        ]);

        foreach ($_images as $_image) {

            // Append file notification time to image URL
            // in order to avoid browser caching issues
            $last_modified = '';
            $path = shopImage::getPath($_image);
            if (file_exists($path)) {
                $last_modified = '?'.filemtime($path);
            }

            // Large image that is used to make smaller previews.
            // Can be larger than 960px, does not contain watermarks.
            $_url_original = $wa_app_url . "?module=prod&action=origImage&id=" . $_image["id"];

            // Backup of the original uploaded image.
            // This may differ from $_url_original in case user cropped or rotated image
            // with web editor tools. "Restore from original" will use this backup.
            $_url_backup = $wa_app_url . "?module=prod&action=origImage&backup=1&id=" . $_image["id"];

            $result[$_image["id"]] = [
                "id" => $_image["id"],
                "url" => $_image["url_default"].$last_modified,
                "url_backup" => $_url_backup,
                "url_original" => $_url_original,
                "description" => $_image["description"],
                "size" => shopProdMediaAction::formatFileSize($_image["size"]),
                "name" => $_image["original_filename"],
                "width" => $_image["width"],
                "height" => $_image["height"],
                'sort' => $_image['sort'],
                "uses_count" => 0
            ];
        }

        return $result;
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
         * @since 8.18.0
         *
         * @param shopProduct $product
         * @param string $content_id
         *       Which page (tab) is shown
         */
        $params = [
            'product' => $product,
            'content_id' => 'media',
        ];
        return wa('shop')->event('backend_prod_content', $params);
    }

    public static function formatFileSize($file_size, $decimals=2) {
        $dimension = _ws("b");
        $dimensions = [_ws("kB"), _ws("MB"), _ws("GB")];
        while ($file_size > 768 && $dimensions) {
            $dimension = array_shift($dimensions);
            $file_size = $file_size/1024;
        }

        if ($file_size != (int) $file_size) {
            $file_size = waLocale::format($file_size, max(0, $decimals));
        }

        return $file_size.' '.$dimension;
    }
}
