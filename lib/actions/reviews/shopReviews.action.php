<?php

class shopReviewsAction extends waViewAction
{
    public function execute()
    {
        $this->setProductReviewTemplate();
        $offset = waRequest::get('offset', 0, waRequest::TYPE_INT);
        $lazy = waRequest::get('lazy', false, waRequest::TYPE_INT);

        $product_reviews_model = new shopProductReviewsModel();
        $reviews_per_page = $this->getConfig()->getOption('reviews_per_page_total');
        $reviews = $product_reviews_model->getList('*,is_new,contact,product,images', array(
                'offset' => $offset,
                'limit'  => $reviews_per_page,
                'sort'   => $this->getSort(),
                'order'  => $this->getOrder(),
                'where'  => $this->getWhere(),
                'escape' => false
            )
        );

        $product_reviews_model->unhighlightViewed();

        /**
         * Show reviews
         * @param array &$reviews
         * @event products_reviews
         */
        $params = array(
            'reviews' => &$reviews,
        );
        $products_reviews_hook = wa('shop')->event('products_reviews', $params);
        $this->view->assign('products_reviews_hook', $products_reviews_hook);

        $this->view->assign(array(
            'total_count'      => $this->getTotalCount(),
            'count'            => count($reviews),
            'offset'           => $offset,
            'reviews'          => $reviews,
            'sort'             => $this->getSort(),
            'order'            => $this->getOrder(),
            'filters'          => $this->getRawFilters(),
            'product_id'       => $this->getProductId(),
            'current_author'   => shopProductReviewsModel::getAuthorInfo(wa()->getUser()->getId()),
            'current_product'  => $this->getCurrentProductInfo(),
            'reply_allowed'    => true,
            'lazy'             => $lazy,
            'sidebar_counters' => array(
                'new'        => $product_reviews_model->countNew(!$offset),
                'moderation' => $product_reviews_model->getModerationReviewsCount(),
            )
        ));
    }

    /**
     * If betrayed the flag, use another template.
     */
    protected function setProductReviewTemplate()
    {
        $review_template = $this->getReviewTemplate();

        if ($review_template) {
            $this->setTemplate('product/ProductReviews.html', true);
        }
    }

    /**
     * @return array
     */
    protected function getCurrentProductInfo()
    {
        $product_id = $this->getProductId();
        $product_data = [];
        if ($product_id) {
            $product = new shopProduct($product_id);
            $product_data = $product->getData();
        }

        return $product_data;
    }

    /**
     * @return int
     */
    protected function getTotalCount()
    {
        $total_count = $this->getRawTotalCount();

        if (!$total_count) {
            $product_reviews_model = new shopProductReviewsModel();
            $total_count = $product_reviews_model->count($this->getProductId(), false, ['where' => $this->getWhere()]);
        }
        return (int)$total_count;
    }

    /**
     * @return array
     */
    protected function getWhere()
    {
        $product_id = $this->getProductId();
        $result = [
            'filters' => $this->getRawFilters(),
        ];
        if ($product_id) {
            $result['product_id'] = $product_id;
        }

        return $result;
    }

    protected function getRawFilters()
    {
        return waRequest::get('filters', [], waRequest::TYPE_ARRAY);
    }

    protected function getProductId()
    {
        return waRequest::get('product_id', null, waRequest::TYPE_STRING);
    }

    protected function getRawTotalCount()
    {
        return waRequest::get('total_count', null, waRequest::TYPE_INT);
    }

    protected function getSort()
    {
        return waRequest::get('sort', null, waRequest::TYPE_STRING);
    }

    protected function getOrder()
    {
        return waRequest::get('order', 'DESC', waRequest::TYPE_STRING);
    }

    protected function getReviewTemplate()
    {
        return waRequest::get('template', null, waRequest::TYPE_STRING);
    }
}
