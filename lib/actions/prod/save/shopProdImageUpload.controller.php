<?php
/**
 * Upload new image and save into product.
 */
class shopProdImageUploadController extends shopUploadController
{
    /**
     * Изменение порядка сортировки изображений товара
     * Последнее добавленное изображение идет первым
     *
     * @param $images_model
     * @param $image
     */
    protected function unshiftImage($images_model, $image)
    {
        if ($images_model && !empty($image['product_id'])) {
            $first_image_id = (int) $image['id'];
            $images = $images_model->getImages($image['product_id']);
            foreach ($images as $image_id => $img) {
                if ($first_image_id === $image_id) {
                    $images_model->updateById($image_id, ['sort' => 0]);
                } else {
                    $images_model->updateById($image_id, ['sort' => ++$img['sort']]);
                }
            }
        }
    }

    protected function save(waRequestFile $file)
    {
        $product_id = waRequest::request('product_id', null, waRequest::TYPE_INT);
        $product_model = new shopProductModel();
        if (!$product_id || !$product_model->checkRights($product_id)) {
            throw new waException(_w("Access denied"), 403);
        }

        $product_images_model = new shopProductImagesModel();
        $image = $product_images_model->addImage($file, $product_id);
        $this->unshiftImage($product_images_model, $image);
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
