<?php

class shopProductImageDownloadController extends waViewController
{
    public function execute()
    {
        $id = waRequest::get('id', 0, waRequest::TYPE_INT);
        if (!$id) {
            throw new waException(_w("Image not found"), 404);
        }

        $product_images_model = new shopProductImagesModel();
        $image = $product_images_model->getById($id);
        if (!$image) {
            throw new waException(_w("Image not found"), 404);
        }
        
        if (waRequest::get('original')) {
            $path = shopImage::getOriginalPath($image);
        } else {
            $path = shopImage::getPath($image);
        }

        if (!$path || !file_exists($path)) {
            throw new waException(_w("Image not found"), 404);
        }
        waFiles::readFile($path, "Image_{$image['product_id']}_{$image['id']}.{$image['ext']}", true, true);
    }
}