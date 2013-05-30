<?php

class shopProductReviewsAction extends waViewAction
{
    protected $orders = array(
        'default' => 'datetime DESC',
        'datetime' => 'datetime',
        'best_rated' => 'rate DESC',
        'worse_rated' => 'rate'
    );
    public function execute()
    {
        $id = waRequest::get('id', null, waRequest::TYPE_INT);
        $product_model = new shopProductModel();
        $product = $product_model->getById($id);
        if (!$product) {
            throw new waException(_w("Unkown product"));
        }

        $offset = waRequest::get('offset', 0, waRequest::TYPE_INT);
        $total_count = waRequest::get('total_count', null, waRequest::TYPE_INT);
        $lazy = waRequest::get('lazy');
        $order = waRequest::get('order', 'default', waRequest::TYPE_STRING_TRIM);
        $order = isset($this->orders[$order]) ? $order : 'default';

        $product_reviews_model = new shopProductReviewsModel();
        $reviews = $product_reviews_model->getFullTree(
            $id, $offset, $this->getConfig()->getOption('reviews_per_page_product'),
            $this->orders[$order],
            array('is_new' => true)
        );

        $this->view->assign(array(
            'product' => $product,
            'reviews' => $reviews,
            'offset' => $offset,
            'total_count' => $total_count ? $total_count : $product_reviews_model->count($id),
            'reply_allowed' => true,
            'lazy' => $lazy,
            'current_author' => shopProductReviewsModel::getAuthorInfo(wa()->getUser()->getId()),
            'count' => count($reviews),
            'id' => $id,
            'order' => $order,
            'sidebar_counters' => array(
                'new' => $product_reviews_model->countNew()
            )
        ));
    }
}