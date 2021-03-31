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

        // data for hook
        $data = $product_data;
        unset($data['id']);
        $this->throwPreSaveEvent($product_data['id'], $data, 'media_images_order');

        // data for hook
        $data = [];

        $sort = 0;
        $product_images_model = new shopProductImagesModel();
        foreach(array_keys((array)$product_data['photos']) as $photo_id) {
            $data[] = [
                'id' => $photo_id,
                'sort' => $sort
            ];
            $product_images_model->updateByField([
                'product_id' => $product_data['id'],
                'id' => $photo_id,
            ], [
                'sort' => $sort,
            ]);
            $sort++;
        }

        $this->throwSaveEvent($product_data['id'], $data, 'media_images_order');
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

        // data for hook
        $data = $product_data;
        unset($data['id']);
        $this->throwPreSaveEvent($product_data['id'], $data, 'media_main_image');
        $product_data = ['id' => $product_data['id']] + $data;

        $product_model = new shopProductModel();
        $product_model->updateById($product_data['id'], [
            'image_id'       => $image_data['id'],
            'image_filename' => $image_data['filename'],
            'ext'            => $image_data['ext'],
        ]);

        // data for hook
        $data = [
            'image_id'       => $image_data['id'],
            'image_filename' => $image_data['filename'],
            'ext'            => $image_data['ext'],
        ];
        unset($data['id']);
        $this->throwSaveEvent($product_data['id'], $data, 'media_main_image');
    }

    /**
     * @param int $product_id
     * @param array &$data - data could be mutated
     * @param int $content_id
     * @throws waException
     */
    protected function throwPreSaveEvent($product_id, array &$data, $content_id)
    {
        /**
         * @event backend_prod_presave
         * @since 8.18.0
         *
         * @param shopProduct $product
         * @param array &$data
         *      Raw data from form posted - data could be mutated
         * @param string $content_id
         *       Which page is being saved
         */
        $params = [
            'product' => new shopProduct($product_id),
            'data' => &$data,
            'content_id' => $content_id,
        ];

        wa('shop')->event('backend_prod_presave', $params);
    }

    protected function throwSaveEvent($product_id, array $data, $content_id)
    {
        /**
         * @event backend_prod_save
         * @since 8.18.0
         *
         * @param shopProduct $product
         * @param array $data
         *      Product data that was saved
         * @param string $content_id
         *       Which page is being saved
         */
        $params = [
            'product' => new shopProduct($product_id),
            'data' => $data,
            'content_id' => $content_id,
        ];

        wa('shop')->event('backend_prod_save', $params);
    }
}
