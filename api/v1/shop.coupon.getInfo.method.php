<?php

class shopCouponGetInfoMethod extends shopApiMethod
{
    protected $method = 'GET';

    public function execute()
    {
        $id = (int)$this->get('id');
        $code = $this->get('code');
        $coupon_model = new shopCouponModel();
        if ($id) {
            $coupon = $coupon_model->getById($id);
        }
        if (empty($coupon) && $code) {
            $coupon = $coupon_model->getByField('code', $code);
        }
        if (!empty($coupon)) {
            $this->response = $coupon;
        } else {
            throw new waAPIException('invalid_request', _w('Coupon not found.'), 404);
        }
    }
}
