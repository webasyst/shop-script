<?php

class shopMarketingCouponDeleteController extends waJsonController
{
    public function execute()
    {
        $coupm = new shopCouponModel();
        $coupon_id = waRequest::post('coupon_id', null, waRequest::TYPE_INT);
        $coupon_name = waRequest::post('coupon_name', '', waRequest::TYPE_STRING_TRIM);

        if (empty($coupon_id)) {
            return $this->errors[] = array(
                'name' => 'coupon[id]',
                'text' => _w('Invalid ID'),
            );
        }
        $page_number = $coupm->getPageNumber($coupon_id, $coupon_name);

        $coupm->delete($coupon_id);

        $this->response = array(
            'page_number' => $page_number,
            'coupon_name' => $coupon_name,
        );
    }
}