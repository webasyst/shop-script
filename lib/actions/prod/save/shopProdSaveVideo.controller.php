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

        $product = new shopProduct($id);
        $product->save(['video_url' => $url]);

        if ($url && !$product->video_url) {
            $this->errors[] = [
                'id' => 'product_video_add',
                'text'  => _w('Copy and paste the URL of a product video from the YouTube or Vimeo website.'),
            ];
            return;
        }

        $this->response = [
            'product' => [
                'video_url' => $product->video_url,
                'video' => $product['video'],
            ],
        ];
    }
}
