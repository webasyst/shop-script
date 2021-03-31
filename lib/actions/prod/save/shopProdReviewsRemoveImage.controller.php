<?php

/**
 * Class shopProdReviewsRemoveImageController
 *
 * Контроллер для удаления изображений добавленных к отзывам (комментариям)
 */
class shopProdReviewsRemoveImageController extends waJsonController
{
    public function execute()
    {
        $image_id = waRequest::post('image_id', null, waRequest::TYPE_STRING);
        $review_images_model = new shopProductReviewsImagesModel();
        $result = $review_images_model->remove($image_id);

        if (!$result) {
            $this->errors[] = _w('Image deletion error');
        }
        $this->response['result'] = $result;
    }
}