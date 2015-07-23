<?php

class shopReviewsWidget extends waWidget
{
    public function defaultAction()
    {
        $prm = new shopProductReviewsModel();
        $size = $this->info['size'];
        $reviews = $prm->getList('*,is_new,contact,product', array(
            'limit' => 6,
            'offset' => 0,
            'where' => array(
                'status' => 'approved',
            )
        ));
        $this->display(array(
            'reviews' => $reviews
        ));
    }
}