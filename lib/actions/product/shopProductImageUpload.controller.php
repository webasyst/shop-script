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

        if (!$this->model) {
            $this->model = new shopProductImagesModel();
        }

        $data = $this->model->addImage($file, $product_id);

        /** @var shopConfig $config */
        $config = $this->getConfig();

        shopImage::generateThumbs($data, $config->getImageSizes());

        $product_model->updateById($product_id, [
            'edit_datetime' => date('Y-m-d H:i:s')
        ]);

        return array(
            'id'             => $data['id'],
            'name'           => $data['filename'],
            'type'           => $file->type,
            'size'           => $data['size'],
            'url_thumb'      => shopImage::getUrl($data, $config->getImageSize('thumb')),
            'url_crop'       => shopImage::getUrl($data, $config->getImageSize('crop')),
            'url_crop_small' => shopImage::getUrl($data, $config->getImageSize('crop_small')),
            'description'    => '',
        );
    }
}
