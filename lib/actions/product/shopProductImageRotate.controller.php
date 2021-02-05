<?php

class shopProductImageRotateController extends waJsonController
{
    private $angles = array(
        'left' => '-90',
        'right' => '90',
    );

    public function execute()
    {
        $id  = waRequest::get('id', null, waRequest::TYPE_INT);
        if (!$id) {
            throw new waException("Unknown image");
        }

        $direction = waRequest::post('direction', 'left', waRequest::TYPE_STRING_TRIM);
        if (!isset($this->angles[$direction])) {
            throw new waException("Can't rotate image");
        }

        $product_images_model = new shopProductImagesModel();
        $image = $product_images_model->getById($id);
        if (!$image) {
            throw new waException("Unknown image");
        }

        // check rights
        $product_model = new shopProductModel();
        if (!$product_model->checkRights($image['product_id'])) {
            throw new waException(_w("Access denied"));
        }

        $image_path = shopImage::getPath($image);

        $paths = array();
        try
        {
            $result_image_path = preg_replace('/(\.[^\.]+)$/','.result$1', $image_path);
            $backup_image_path = preg_replace('/(\.[^\.]+)$/','.backup$1', $image_path);
            $paths[] = $result_image_path;

            if ($this->rotate($image_path, $result_image_path, $this->angles[$direction]))
            {
                $count = 0;
                while(!file_exists($result_image_path) && ++$count < 5) {
                    sleep(1);
                }

                if(!file_exists($result_image_path)) {
                    throw new waException(_w("Error while rotate. I/O error"));
                }

                if (!waFiles::move($image_path, $backup_image_path)) {
                    throw new waException(_w("Error while rotate. Operation canceled"));
                }

                $paths[] = $backup_image_path;
                if(!waFiles::move($result_image_path, $image_path)) {
                    if(!waFiles::move($backup_image_path, $image_path)) {
                        throw new waException(_w("Error while rotate. The original file is corrupted but backed up." ));
                    }
                    throw new waException(_w("Error while rotate. Operation canceled"));
                }

                $datetime = date('Y-m-d H:i:s');
                $data = array(
                    'edit_datetime' => $datetime,
                    'width' =>  $image['height'],
                    'height' => $image['width']
                );
                $product_images_model->updateById($id, $data);
                $image = array_merge($image, $data);

                $thumb_dir = shopImage::getThumbsPath($image);
                $back_thumb_dir = preg_replace('@(/$|$)@','.back$1', $thumb_dir, 1);
                $paths[] = $back_thumb_dir;
                waFiles::delete($back_thumb_dir);
                if (!(waFiles::move($thumb_dir, $back_thumb_dir) || waFiles::delete($back_thumb_dir)) && !waFiles::delete($thumb_dir)){
                    throw new waException(_w("Error while rebuild thumbnails"));
                }

                $config = $this->getConfig();
                try {
                    shopImage::generateThumbs($image, $config->getImageSizes());
                } catch(Exception $e) {
                    waLog::log($e->getMessage());
                }

                $this->response = $image;
                $edit_datetime_ts = strtotime($image['edit_datetime']);
                $this->response['url_big']  = shopImage::getUrl($image, $config->getImageSize('big')) .'?'.$edit_datetime_ts;
                $this->response['url_crop'] = shopImage::getUrl($image, $config->getImageSize('crop')).'?'.$edit_datetime_ts;
            }
            foreach($paths as $path) {
                waFiles::delete($path);
            }
        } catch(Exception $e) {
            foreach($paths as $path) {
                waFiles::delete($path);
            }
            throw $e;
        }
    }

    public function rotate($src_path, $dst_path, $angle)
    {
        $image = new shopImage($src_path);
        return $image->rotate($angle)->save($dst_path);
    }
}
