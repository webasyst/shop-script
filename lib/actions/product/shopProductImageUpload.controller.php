<?php

class shopProductImageUploadController extends shopUploadController
{
    /**
     * @var shopProductImagesModel
     */
    private $model;

    protected function save(waRequestFile $file)
    {

        $product_id = waRequest::post('product_id', null, waRequest::TYPE_INT);
        $product_model = new shopProductModel();
        if (!$product_model->checkRights($product_id)) {
            throw new waException(_w("Access denied"));
        }

        // check image
        if (!($image = $file->waImage())) {
            throw new waException('Incorrect image');
        }

        $image_changed = false;

        /**
         * Extend upload proccess
         * Make extra workup
         * @event image_upload
         */
        $event = wa()->event('image_upload', $image);
        if ($event) {
            foreach ($event as $plugin_id => $result) {
                if ($result) {
                    $image_changed = true;
                }
            }
        }

        if (!$this->model) {
            $this->model = new shopProductImagesModel();
        }

        if ($this->getConfig()->getOption('image_filename')) {
            $filename = basename($file->name, '.' . $file->extension);
            if (!preg_match('//u', $filename)) {
                $tmp_name = @iconv('windows-1251', 'utf-8//ignore', $filename);
                if ($tmp_name) {
                    $filename = $tmp_name;
                }
            }
            $filename = preg_replace('/\s+/u', '_', $filename);
            if ($filename) {
                foreach (waLocale::getAll() as $l) {
                    $filename = waLocale::transliterate($filename, $l);
                }
            }
            $filename = preg_replace('/[^a-zA-Z0-9_\.-]+/', '', $filename);
            if (!strlen(str_replace('_', '', $filename))) {
                $filename = '';
            }
        } else {
            $filename = '';
        }

        $data = array(
            'product_id'        => $product_id,
            'upload_datetime'   => date('Y-m-d H:i:s'),
            'width'             => $image->width,
            'height'            => $image->height,
            'size'              => $file->size,
            'filename'          => $filename,
            'original_filename' => basename($file->name),
            'ext'               => $file->extension,
        );
        $image_id = $data['id'] = $this->model->add($data);

        if (!$image_id) {
            throw new waException("Database error");
        }

        /**
         * @var shopConfig $config
         */
        $config = $this->getConfig();

        $image_path = shopImage::getPath($data);
        if ((file_exists($image_path) && !is_writable($image_path)) || (!file_exists($image_path) && !waFiles::create($image_path))) {
            $this->model->deleteById($image_id);
            throw new waException(
                sprintf("The insufficient file write permissions for the %s folder.",
                    substr($image_path, strlen($config->getRootPath()))
            ));
        }

        if ($image_changed) {
            $image->save($image_path);

            // save original
            $original_file = shopImage::getOriginalPath($data);
            if ($config->getOption('image_save_original') && $original_file) {
                $file->moveTo($original_file);
            }

        } else {
            $file->moveTo($image_path);
        }
        unset($image);        // free variable

        shopImage::generateThumbs($data, $config->getImageSizes());

        return array(
            'id'             => $image_id,
            'name'           => $file->name,
            'type'           => $file->type,
            'size'           => $file->size,
            'url_thumb'      => shopImage::getUrl($data, $config->getImageSize('thumb')),
            'url_crop'       => shopImage::getUrl($data, $config->getImageSize('crop')),
            'url_crop_small' => shopImage::getUrl($data, $config->getImageSize('crop_small')),
            'description'    => ''
        );
    }
}
