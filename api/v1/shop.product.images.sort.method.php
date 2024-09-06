<?php

class shopProductImagesSortMethod extends shopProductUpdateMethod
{
    protected $method = 'POST';

    public function execute()
    {
        $photo_ids = waRequest::post('photo_ids', array(), waRequest::TYPE_ARRAY);

        if (!$photo_ids) {
            throw new waAPIException('invalid_param');
        }

        $spim = new shopProductImagesModel();
        $spm = new shopProductModel();

        $first_image = $spim->getById($photo_ids[0]);
        if (!$first_image) {
            throw new waAPIException('not_found', 'Product image not found', 404);
        }

        $all_images = $spim->getByField(array('product_id' => $first_image['product_id']), true);

        $image_count = array();

        foreach ($photo_ids as $key => $p_id) {
            $image_count[$p_id] = $key;
        }

        foreach ($all_images as $image) {
            if (!isset($image_count[$image['id']])) {
                $image_count[$image['id']] = count($image_count);
            }
        }

        foreach ($image_count as $key => $value) {
            $spim->updateById($key, array('sort' => $value));
        }

        $spm->updateById($first_image['product_id'], array('image_id' => $first_image['id']));

        $this->response = array('status' => 'ok');
    }
}
