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
        if (!$product_id || !$product_model->checkRights($product_id)) {
            throw new waException(_w("Access denied"), 403);
        }

        $product_images_model = new shopProductImagesModel();
        $data = $product_images_model->addImage($file, $product_id);

        $config = wa('shop')->getConfig();
        shopImage::generateThumbs($data, $config->getImageSizes());

        return [
            "id" => $data["id"],
            "description" => $data["description"],
            "url" => shopImage::getUrl($data, $config->getImageSize('thumb'))
        ];
    }
}
