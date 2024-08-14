<?php
/**
 * Upload new image and save into product.
 */
class shopProdImageUploadController extends shopUploadController
{
    protected function save(waRequestFile $file)
    {
        $product_id = waRequest::request('product_id', null, waRequest::TYPE_INT);
        $product_model = new shopProductModel();
        $product = $product_model->getById($product_id);
        if (!$product) {
            throw new waException(_w('Product not found.'), 404);
        }
        if (!$product_model->checkRights($product_id)) {
            throw new waException(_w("Access denied"), 403);
        }

        $product_images_model = new shopProductImagesModel();
        $image = $product_images_model->addImage($file, $product_id);
        $config = wa('shop')->getConfig();
        shopImage::generateThumbs($image, $config->getImageSizes("default"));

        // same data as shopProdSaveImageDetailsController
        return [
            "id" => $image["id"],
            "url" => shopImage::getUrl($image, $config->getImageSize('default')),
            "url_original" => wa()->getAppUrl(null, true) . "?module=prod&action=origImage&id=" . $image["id"],
            "description" => $image["description"],
            "size" => shopProdMediaAction::formatFileSize($image["size"]),
            "name" => $image["original_filename"],
            "width" => $image["width"],
            "height" => $image["height"],
            "uses_count" => 0
        ];
    }
}
