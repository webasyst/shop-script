<?php
/**
 * Saves data from Media tab when user clicks Save button.
 * Responsible for image ordering and main product image.
 */
class shopProdSaveMediaController extends waJsonController
{
    public function execute()
    {
        $this->saveImageOrder();
        $this->saveMainProductImage();
    }

    protected function saveImageOrder()
    {
        $product_data = waRequest::post('product', [], 'array');
        if (empty($product_data['id']) || empty($product_data['photos'])) {
            return;
        }

        $sort = 0;
        $product_images_model = new shopProductImagesModel();
        foreach(array_keys((array)$product_data['photos']) as $photo_id) {
            $product_images_model->updateByField([
                'product_id' => $product_data['id'],
                'id' => $photo_id,
            ], [
                'sort' => $sort,
            ]);
            $sort++;
        }
    }

    protected function saveMainProductImage()
    {
        $product_data = waRequest::post('product', [], 'array');
        if (empty($product_data['id']) || empty($product_data['image_id'])) {
            return;
        }

        $product_images_model = new shopProductImagesModel();
        $image_data = $product_images_model->getByField([
            'id' => $product_data['image_id'],
            'product_id' => $product_data['id'],
        ]);
        if (!$image_data) {
            return;
        }

        $product_model = new shopProductModel();
        $product_model->updateById($product_data['id'], [
            'image_id'       => $image_data['id'],
            'image_filename' => $image_data['filename'],
            'ext'            => $image_data['ext'],
        ]);
    }
}
