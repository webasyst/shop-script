<?php

class shopMarketingCouponDeleteController extends waJsonController
{
    public function execute()
    {
        $coupm = new shopCouponModel();
        $coupon_id = waRequest::post('coupon_id', null, waRequest::TYPE_INT);

        if (empty($coupon_id)) {
            return $this->errors[] = array(
                'name' => 'coupon[id]',
                'text' => _w('Invalid ID'),
            );
        }

        $coupm->delete($coupon_id);
    }
}