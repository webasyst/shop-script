<?php

class shopProductImagesGetInfoMethod extends shopApiMethod
{
    public function execute()
    {
        $id = $this->get('id', true);

        $images_model = new shopProductImagesModel();
        $image = $images_model->getById($id);

        if (!$image) {
            throw new waAPIException('invalid_param', _w('Product image not found.'), 404);
        }

        $this->response = $image;
        $size = waRequest::get('size', wa('shop')->getConfig()->getImageSize('thumb'));
        $this->response['url_thumb'] = shopImage::getUrl($image, $size, true);
        $this->response['url_big'] = shopImage::getUrl($image, null, true);
    }
}
