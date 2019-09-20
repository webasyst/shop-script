<?php

class shopMarketingCouponSaveController extends waJsonController
{
    public function execute()
    {
        $coupm = new shopCouponModel();

        $coupon = $coupm->getEmptyRow();

        $post_coupon = waRequest::post('coupon');

        if (!is_array($post_coupon)) {
            $this->addError('id', 'coupon', _w('Invalid data'));
            return;
        }

        $coupon_id = ifempty($post_coupon, 'id', null);

        $hash = shopImportexportHelper::getCollectionHash();
        $post_coupon['products_hash'] = $hash['hash'];
        if ($post_coupon['products_hash'] === '*') {
            $post_coupon['products_hash'] = '';
        }

        $post_coupon = array_intersect_key($post_coupon, $coupon) + array(
                'code' => '',
                'type' => '%',
            );

        if (empty($post_coupon['limit'])) {
            $post_coupon['limit'] = null;
        }

        if (!empty($post_coupon['value'])) {
            $post_coupon['value'] = (float)str_replace(',', '.', $post_coupon['value']);
        }

        if (empty($post_coupon['code'])) {
            $this->addError('name', 'code', _w('This field is required.'));
            return;
        }

        if (!empty($post_coupon['expire_datetime']) && strlen($post_coupon['expire_datetime']) == 10) {
            $post_coupon['expire_datetime'] .= ' 23:59:59';
        }

        if ($post_coupon['type'] == '%') {
            $post_coupon['value'] = min(max($post_coupon['value'], 0), 100);
        }

        try {

            if ($coupon_id) {
                $coupm->updateById($coupon_id, $post_coupon);
            } else {
                $post_coupon['create_contact_id'] = wa()->getUser()->getId();
                $post_coupon['create_datetime'] = date('Y-m-d H:i:s');
                $coupon_id = $coupm->insert($post_coupon);
            }

            $this->response = array('id' => $coupon_id);

        } catch (waDbException $ex) {

            $this->addError('name', 'code', _w('This code already exists.'));

        }
    }

    protected function addError($type, $field, $text = null)
    {
        if ($type == 'name') {
            $field = "coupon[{$field}]";
        }

        $this->errors[] = array(
            $type  => $field,
            'text' => $text,
        );
    }
}