<?php

/**
 * Class shopProdReviewsAddController
 *
 * Контроллер для добавления комментариев к отзывам
 */
class shopProdReviewsAddController extends waJsonController
{
    /**
     * @return false
     * @throws waException
     */
    public function execute()
    {
        $product_reviews_model = new shopProductReviewsModel();
        $data = $this->getReqiestData($product_reviews_model);
        if ($this->errors = $product_reviews_model->validate($data)) {
            return false;
        }

        $review_id = $product_reviews_model->add($data, $data['parent_id']);
        if (!$review_id) {
            throw new waException('Error in adding review');
        }

        $reviews = [$product_reviews_model->getReview($review_id)];
        $product_reviews_model->checkForNew($reviews);
        $this->response = shopProdReviewsAction::formatReview(reset($reviews));

        return true;
    }

    /**
     * @param $product_reviews_model
     * @return array
     * @throws waException
     */
    private function getReqiestData($product_reviews_model)
    {
        $product_id = waRequest::post('product_id', null, waRequest::TYPE_INT);
        $parent_id  = waRequest::post('parent_id', 0, waRequest::TYPE_INT);
        $text       = waRequest::post('text',   null, waRequest::TYPE_STRING_TRIM);
        $rate       = null;
        $title      = '';

        if (wa()->getEnv() == 'backend' && !$parent_id) {
            throw new waException(_w('Writing a review to product is available just on frontend'));
        }

        if (!$product_id && !$parent_id) {
            throw new waException('Can\'t add comment: unknown product for review or review for reply');
        }
        if (!$product_id) {
            $parent_comment = $product_reviews_model->getById($parent_id);
            $product_id = $parent_comment['product_id'];
        }

        return [
            'product_id'    => $product_id,
            'parent_id'     => $parent_id,
            'title'         => $title,
            'text'          => $text,
            'rate'          => $rate,
            'contact_id'    => $this->getUser()->getId(),
            'auth_provider' => shopProductReviewsModel::AUTH_USER,
            'datetime'      => date('Y-m-d H:i:s'),
            'status'        => shopProductReviewsModel::STATUS_PUBLISHED
        ];
    }
}