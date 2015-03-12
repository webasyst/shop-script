<?php

class shopReviewsAddController extends waJsonController
{
    /**
     * @var shopProductReviewsModel
     */
    protected $product_reviews_model;

    public function __construct()
    {
        $this->product_reviews_model = new shopProductReviewsModel();
        $this->author = $this->getUser();
        $this->view = wa()->getView();
    }

    public function execute()
    {
        $data = $this->getReqiestData();
        if ($this->errors = $this->product_reviews_model->validate($data)) {
            return false;
        }

        $id = $this->product_reviews_model->add($data, $data['parent_id']);
        if (!$id) {
            throw new waException("Error in adding review");
        }

        $data['id'] = $id;
        $data['author'] = $this->getResponseAuthorData();

        $this->view->assign('review', $data);
        $this->view->assign('reply_allowed', true);
        $this->response['id'] = $data['id'];
        $this->response['parent_id'] = $data['parent_id'];
        $this->response['html'] = $this->view->fetch('templates/actions/product/include.review.html');
    }

    protected function getReqiestData()
    {
        $product_id = waRequest::post('product_id', null, waRequest::TYPE_INT);
        $parent_id  = waRequest::post('parent_id', 0, waRequest::TYPE_INT);
        $rate = 0;

        if (wa()->getEnv() == 'backend' && !$parent_id) {
            throw new waException(_w("Writing a review to product is available just on frontend"));
        }

        if (!$product_id && !$parent_id) {
            throw new waException("Can't add comment: unknown product for review or review for reply");
        }
        if (!$product_id) {
            $parent_comment = $this->product_reviews_model->getById($parent_id);
            $product_id = $parent_comment['product_id'];
            $rate = waRequest::post('rate', 0, waRequest::TYPE_INT);
        }
        $text = waRequest::post('text',   null, waRequest::TYPE_STRING_TRIM);
        $title = waRequest::post('title', null, waRequest::TYPE_STRING_TRIM);
        return array(
            'product_id'  => $product_id,
            'parent_id' => $parent_id,
            'title' => $title,
            'text' => $text,
            'rate' => $rate,
            'contact_id' => $this->author->getId(),
            'auth_provider' => shopProductReviewsModel::AUTH_USER,
            'datetime' => date('Y-m-d H:i:s'),
            'status' => shopProductReviewsModel::STATUS_PUBLISHED
        );
    }

    protected function getResponseAuthorData()
    {
        return array(
            'id' => $this->author->getId(),
            'name' => $this->author->getName(),
            'photo' => $this->author->getPhoto(50)
        );
    }
}