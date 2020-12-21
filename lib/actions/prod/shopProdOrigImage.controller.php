<?php
/**
 * Original version of an image. Used in editor on Media tab.
 */
class shopProdOrigImageController extends waViewController
{
    public function execute()
    {
        $id = waRequest::get('id', 0, waRequest::TYPE_INT);
        if ($id) {
            $product_images_model = new shopProductImagesModel();
            $image = $product_images_model->getById($id);
        }
        if (empty($image)) {
            throw new waException(_w("Image not found"), 404);
        }

        $path = null;
        if (waRequest::request('backup')) {
            $path = shopImage::getOriginalPath($image);
        }
        if (!$path || !file_exists($path)) {
            $path = shopImage::getPath($image);
        }
        if (!$path || !file_exists($path)) {
            throw new waException(_w("Image not found"), 404);
        }
        waFiles::readFile($path, null, true, true);
    }
}
