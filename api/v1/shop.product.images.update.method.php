<?php
/**
 * @since 10.0.0
 */
class shopProductImagesUpdateMethod extends shopProductUpdateMethod
{
    public function execute()
    {
        $product_id = $this->get('product_id', true);
        $this->getProduct($product_id);

        $new_images = array_values(waRequest::post('images', [], 'array'));

        $product_images_model = new shopProductImagesModel();
        $old_images = $product_images_model->getImages($product_id);

        // Update images
        foreach($new_images as $sort => $new_image) {
            $image_id = ifset($new_image, 'id', null);
            if (!$image_id || !is_scalar($image_id) || !isset($old_images[$image_id])) {
                continue;
            }
            $old_image = $old_images[$image_id];
            unset($old_images[$image_id]);

            $update = [];
            if (array_key_exists('description', $new_image) && $new_image['description'] !== $old_image['description']) {
                $update['description'] = $new_image['description'];
            }
            if ($sort != $old_image['sort']) {
                $update['sort'] = $sort;
            }

            if ($update) {
                $product_images_model->updateById($image_id, $update);
            }
        }

        // Delete images that are not in list
        foreach($old_images as $image_id => $old_image) {
            $product_images_model->delete($image_id);
        }

        $this->response = true;
    }
}
