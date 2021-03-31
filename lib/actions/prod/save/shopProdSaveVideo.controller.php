<?php
/**
 * Save video URL to existing product and return extended data about the video (or validation error).
 * Used on Media tab.
 */
class shopProdSaveVideoController extends waJsonController
{
    public function execute()
    {
        $id = waRequest::request('id', '', 'int');
        $url = waRequest::request('url', '', 'string_trim');

        // check rights
        $product_model = new shopProductModel();
        if (!$product_model->checkRights($id)) {
            throw new waException(_w("Access denied"));
        }

        $data = ['video_url' => $url];

        $product = new shopProduct($id);
        $this->throwPreSaveEvent($product, $data);

        $product->save($data);

        if ($url && !$product->video_url) {
            $this->errors[] = [
                'id' => 'product_video_add',
                'text'  => _w('Copy and paste the URL of a product video from the YouTube or Vimeo website.'),
            ];
            return;
        }

        $this->throwSaveEvent($product, $data);

        $this->response = [
            'product' => [
                'video_url' => $product->video_url,
                'video' => $product['video'],
            ],
        ];
    }

    /**
     * @param shopProduct $product
     * @param array &$data - data could be mutated
     * @throws waException
     */
    protected function throwPreSaveEvent($product, array &$data)
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
            'product' => $product,
            'data' => &$data,
            'content_id' => 'media_video',
        ];

        wa('shop')->event('backend_prod_presave', $params);
    }

    protected function throwSaveEvent($product, array $data)
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
            'product' => $product,
            'data' => $data,
            'content_id' => 'media_video',
        ];

        wa('shop')->event('backend_prod_save', $params);
    }
}
