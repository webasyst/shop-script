<?php

class shopReviewsWidget extends waWidget
{
    public function defaultAction()
    {
        $prm = new shopProductReviewsModel();
        $reviews = $prm->getList('*,is_new,contact,product,parent_id', array(
            'limit' => 6,
            'offset' => 0,
            'where' => array(
                'status' => 'approved',
            )
        ));

        if (class_exists('\\shopHelper')) {
            foreach ($reviews as $key => $review) {
                if (!$review["parent_id"]) {
                    $reviews[$key]['rating_html'] = shopHelper::getRatingHtml($review['rate'], 10, true);
                }

                unset($review["parent_id"]);
            }
        }

        $this->display(array(
            'reviews' => $reviews
        ));
    }
}
