<?php

class shopProductVideoSaveController extends waJsonController
{
    public function execute()
    {
        // check rights
        $id = (int) $this->getRequest()->request('id');
        $url = $this->getRequest()->request('url');

        $product = new shopProduct($id);

        // check rights
        $product_model = new shopProductModel();
        if (!$product_model->checkRights($product)) {
            throw new waException(_w("Access denied"));
        }

        if (!$url) {
            $product->save(array('video_url' => null));
            $this->response = array(
                'product' => array(
                    'video_url' => ''
                )
            );
            return;
        }

        if (!$this->saveVideo($product, $url)) {
            $this->errors[] = array(
                'name' => 'url',
                'msg' => _w('Simply copy-and-paste the URL of product video on YouTube or Vimeo.')
            );
            return;
        }

        /**
         * @var shopConfig $config
         */
        $config = $this->getConfig();
        $crop_size = $config->getImageSize('crop');

        shopVideo::generateThumbs($product['id'], array('crop' => $crop_size));

        $this->response = array(
            'product' => array(
                'video_url' => $url
            )
        );

    }

    public static function saveVideo($product, $video_url)
    {
        $product_id = $product['id'];

        $file_path = shopProduct::getPath($product_id, 'video.jpg');

        if (!preg_match('!^(?:https?://)?(?:www.)?(youtube\.com|youtu\.be|vimeo\.com)/(?:watch\?v=)?([a-z0-9\-_]+)!i', $video_url, $m)) {
            return false;
        }

        $video_site = strtolower($m[1]);
        $video_id = $m[2];
        $video_url = 'http://' . $video_site . '/' . $video_id;

        $file_url = null;

        if ($video_site == 'youtube.com' || $video_site == 'youtu.be') {
            $file_url = 'http://img.youtube.com/vi/' . $video_id . '/0.jpg';
            $video_url = 'http://youtu.be/' . $video_id;
        } else {
            $desc = json_decode(@file_get_contents('http://vimeo.com/api/v2/video/' . $video_id . '.json'));
            if (!empty($desc[0]->thumbnail_large)) {
                $file_url = $desc[0]->thumbnail_large;
            }
        }

        if (!$file_url) {
            return false;
        }

        if (!@file_put_contents($file_path, file_get_contents($file_url))) {
            return false;
        }

        $product->save(array('video_url' => $video_url));
        $product['video_url'] = $video_url;

        return true;
    }

}
