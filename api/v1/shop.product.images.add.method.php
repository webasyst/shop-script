<?php

class shopProductImagesAddMethod extends shopProductUpdateMethod
{
    public function execute()
    {
        $product_id = $this->get('product_id', true);
        $this->getProduct($product_id);

        $file = waRequest::file('file');
        $image = $file->waImage();
        if ($file->uploaded()) {
            $data = array(
                'product_id'        => $product_id,
                'upload_datetime'   => date('Y-m-d H:i:s'),
                'width'             => $image->width,
                'height'            => $image->height,
                'size'              => $file->size,
                'original_filename' => basename($file->name),
                'ext'               => $file->extension,
                'description'       => waRequest::post('description')
            );

            $product_images_model = new shopProductImagesModel();

            $image_id = $data['id'] = $product_images_model->add($data);
            if (!$image_id) {
                throw new waAPIException('server_error', 500);
            }

            /**
             * @var shopConfig $config
             */
            $config = wa('shop')->getConfig();

            $image_path = shopImage::getPath($data);
            if ((file_exists($image_path) && !is_writable($image_path)) || (!file_exists($image_path) && !waFiles::create($image_path))) {
                $product_images_model->deleteById($image_id);
                throw new waAPIException(
                    sprintf("The insufficient file write permissions for the %s folder.",
                        substr($image_path, strlen($config->getRootPath()))
                    ));
            }
            $file->moveTo($image_path);
            unset($image);
            shopImage::generateThumbs($data, $config->getImageSizes());

            $method = new shopProductImagesGetInfoMethod();
            $_GET['id'] = $image_id;
            $this->response = $method->getResponse(true);
        } else {
            throw new waAPIException('server_error', $file->error);
        }

    }
}