<?php

class shopReviewsRemoveImageController extends waJsonController
{
    public function execute()
    {
        $review_images_model = new shopProductReviewsImagesModel();
        $result = $review_images_model->remove($this->getImageId());

        if (!$result) {
            $this->errors[] = _w('Image deletion error');
        }
        $this->response['result'] = $result;
    }

    protected function getImageId()
    {
        return waRequest::post('image_id', null, waRequest::TYPE_STRING);
    }

    protected function getReviewId()
    {
        return waRequest::post('review_id', null, waRequest::TYPE_STRING);
    }
}