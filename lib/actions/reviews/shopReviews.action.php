<?php

class shopReviewsAction extends waViewAction
{
    public function execute()
    {
        $offset = waRequest::get('offset', 0, waRequest::TYPE_INT);
        $total_count = waRequest::get('total_count', null, waRequest::TYPE_INT);
        $lazy = waRequest::get('lazy', false, waRequest::TYPE_INT);

        $product_reivews_model = new shopProductReviewsModel();
        $reviews_per_page = $this->getConfig()->getOption('reviews_per_page_total');

        /*
        $reviews = $product_reivews_model->getList(
            $offset,
            $reviews_per_page,
            array('is_new' => true)
        );
        */
        $reviews = $product_reivews_model->getList('*,is_new,contact,product', array(
                'offset' => $offset,
                'limit' => $reviews_per_page
            )
        );

        $product_reivews_model->unhighlightViewed();

        // TODO: move to model
        $product_ids = array();
        foreach ($reviews as $review) {
            $product_ids[] = $review['product_id'];
        }
        $product_ids = array_unique($product_ids);
        $product_model = new shopProductModel();
        $products = $product_model->getByField('id', $product_ids, 'id');
        $image_size = wa()->getConfig()->getImageSize('crop_small');
        foreach ($reviews as &$review) {
            if (isset($products[$review['product_id']])) {
                $product = $products[$review['product_id']];
                $review['product_name'] = $product['name'];
                if ($product['image_id']) {
                    $review['product_url_crop_small'] = shopImage::getUrl(
                        array(
                            'id' => $product['image_id'],
                            'product_id' => $product['id'],
                            'filename' => $product['image_filename'],
                            'ext' => $product['ext']
                        ),
                        $image_size);
                } else {
                    $review['product_url_crop_small'] = null;
                }
            }
        }

        $this->view->assign(array(
            'total_count' => $total_count ? $total_count : $product_reivews_model->countAll(),
            'count' => count($reviews),
            'offset' => $offset,
            'reviews' => $reviews,
            'current_author' => shopProductReviewsModel::getAuthorInfo(wa()->getUser()->getId()),
            'reply_allowed' => true,
            'lazy' => $lazy,
            'sidebar_counters' => array(
                'new' => $product_reivews_model->countNew(!$offset)
            )
        ));

    }
}