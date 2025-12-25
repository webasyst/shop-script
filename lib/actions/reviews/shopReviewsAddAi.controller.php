<?php

class shopReviewsAddAiController extends waJsonController {
    /**
     * @throws \waException
     */
    public function execute() {

        if (wa()->getEnv() != 'backend') {
            return;
        }

        $parent_id = waRequest::post('parent_id', 0, waRequest::TYPE_INT);
        if (!$parent_id) {
            throw new waException("Can't add comment: unknown product for review or review for reply");
        }

        $parent_comment = (new shopProductReviewsModel)->getById($parent_id);

        if (empty($parent_comment['product_id']) || empty($parent_comment['text'])) {
            throw new waException("Can't add comment: unknown product for review or review for reply");
        }

        try {
            $result = (new shopAiApiRequest())
                ->loadFieldsFromApi('store_product_review_answer')
                ->loadFieldValuesFromSettings()
                ->loadFieldValuesFromProduct(new shopProduct($parent_comment['product_id']))
                ->setFieldValues([
                    'review' => $parent_comment['text'],
                ])
                ->generate();

            $this->response = trim(strip_tags($result['text'] ?? ''));
        } catch (Exception $e) {
            $this->setError($e->getMessage());
        }
    }
}
