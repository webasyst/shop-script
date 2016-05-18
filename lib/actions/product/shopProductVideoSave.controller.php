<?php

class shopProductVideoSaveController extends waJsonController
{
    public function execute()
    {
        $id = (int)$this->getRequest()->request('id');
        $url = $this->getRequest()->request('url');

        // check rights
        $product_model = new shopProductModel();
        if (!$product_model->checkRights($id)) {
            throw new waException(_w("Access denied"));
        }

        $product = new shopProduct($id);
        $product->save(array('video_url' => $url));

        if ($url && !$product->video_url) {
            $this->errors[] = array(
                'name' => 'url',
                'msg'  => _w('Simply copy-and-paste the URL of product video on YouTube or Vimeo.')
            );
        } else {
            $product->video;
        }
    }
}
