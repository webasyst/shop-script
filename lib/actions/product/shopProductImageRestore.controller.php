<?php

class shopProductImageRestoreController extends waJsonController
{
    public function execute()
    {
        $id  = waRequest::post('id', null, waRequest::TYPE_INT);
        if (!$id) {
            throw new waException("Can't restore image");
        }

        $product_images_model = new shopProductImagesModel();
        $image = $product_images_model->getById($id);
        if (!$image) {
            throw new waException("Can't restore image");
        }

        // check rights
        $product_model = new shopProductModel();
        if (!$product_model->checkRights($image['product_id'])) {
            throw new waException(_w("Access denied"));
        }

        $original_image_path = shopImage::getOriginalPath($image);
        if (!wa('shop')->getConfig()->getOption('image_save_original') || !file_exists($original_image_path)) {
            throw new waException("Can't restore image. Original image doesn't exist");
        }

        $image_path = shopImage::getPath($image);

        $paths = array();
        try {
            $backup_image_path = preg_replace('/(\.[^\.]+)$/','.backup$1', $image_path);
            if (!waFiles::move($image_path, $backup_image_path)) {
                throw new waException("Error while restore. Operation canceled");
            }

            $paths[] = $backup_image_path;
            if (!waFiles::move($original_image_path, $image_path)) {
                if (!waFiles::move($backup_image_path, $image_path)) {
                    throw new waException("Error while restore. Current file corupted but backuped" );
                }
                throw new waException("Error while restore. Operation canceled");
            }

            $data = $this->getData($image_path);
            $product_images_model->updateById($id, $data);
            $image = array_merge($image, $data);

            $thumb_dir = shopImage::getThumbsPath($image);
            $back_thumb_dir = preg_replace('@(/$|$)@','.back$1', $thumb_dir, 1);
            $paths[] = $back_thumb_dir;
            waFiles::delete($back_thumb_dir); // old backups
            if (!(waFiles::move($thumb_dir, $back_thumb_dir) || waFiles::delete($back_thumb_dir)) && !waFiles::delete($thumb_dir)){
                throw new waException(_w("Error while rebuild thumbnails"));
            }

            /**
             * @var shopConfig $config
             */
            $config = $this->getConfig();
            try {
                shopImage::generateThumbs($image, $config->getImageSizes());
            } catch(Exception $e) {
                waLog::log($e->getMessage());
            }

            $this->response = $image;
            $edit_datetime_ts = strtotime($image['edit_datetime']);
            $this->response['url_big']  = shopImage::getUrl($image, $config->getImageSize('big')). '?'.$edit_datetime_ts;
            $this->response['url_crop'] = shopImage::getUrl($image, $config->getImageSize('crop')).'?'.$edit_datetime_ts;

            foreach($paths as $path) {
                waFiles::delete($path);
            }
        } catch (Exception $e) {
            foreach($paths as $path) {
                waFiles::delete($path);
            }
            throw $e;
        }
    }

    public function getData($path)
    {
        $image = new shopImage($path);
        return array(
            'edit_datetime' => date('Y-m-d H:i:s'),
            'width' => $image->width,
            'height' => $image->height
        );
    }
}