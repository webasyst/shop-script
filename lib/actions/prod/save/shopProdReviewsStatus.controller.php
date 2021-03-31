<?php

/**
 * Class shopProdReviewsStatusController
 *
 * Контроллер для удаления/восстановления отзыва (комментария) к товару
 */
class shopProdReviewsStatusController extends waJsonController
{
    public function execute()
    {
        $review_id = waRequest::post('review_id', null, waRequest::TYPE_INT);
        if (!$review_id) {
            throw new waException('Unknown review id');
        }

        $status = waRequest::post('status', '', waRequest::TYPE_STRING_TRIM);
        $product_reviews_model = new shopProductReviewsModel();
        if (
            $status == shopProductReviewsModel::STATUS_DELETED
            || $status == shopProductReviewsModel::STATUS_PUBLISHED
            || $status == shopProductReviewsModel::STATUS_MODERATION
        ) {
            $product_reviews_model->changeStatus($review_id, $status);
        }
        $reviews = [$product_reviews_model->getReview($review_id)];
        $product_reviews_model->checkForNew($reviews);

        $this->response = shopProdReviewsAction::formatReview(reset($reviews));
    }
}