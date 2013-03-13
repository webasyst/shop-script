<?php

class shopReviewsChangeStatusController extends waJsonController
{
    public function execute()
    {
        $review_id = waRequest::post('review_id', null, waRequest::TYPE_INT);
        if (!$review_id) {
            throw new waException("Unknown review id");
        }

        $status = waRequest::post('status', '', waRequest::TYPE_STRING_TRIM);
        if (
            $status == shopProductReviewsModel::STATUS_DELETED ||
            $status == shopProductReviewsModel::STATUS_PUBLISHED
        ) {
            $product_reviews_model = new shopProductReviewsModel();
            $product_reviews_model->changeStatus($review_id, $status);
        }
    }
}