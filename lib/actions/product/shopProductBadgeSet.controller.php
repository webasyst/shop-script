<?php

class shopProductBadgeSetController extends waJsonController
{
    public function execute()
    {
        $product_model = new shopProductModel();

        $id = waRequest::get('id', null, waRequest::TYPE_INT);
        $product = $product_model->getById($id);
        if (!$product) {
            throw new waException(_w("Unknown product"));
        }
        if (!$product_model->checkRights($product)) {
            throw new waException(_w("Access denied"));
        }

        $code = waRequest::post('code', null, waRequest::TYPE_STRING_TRIM);
        if (!$code) {
            throw new waException(_w("Empty code"));
        }

        $product_model->updateById($id, array('badge' => $code));

        $badges = shopProductModel::badges();
        $this->response = isset($badges[$code]) ? $badges[$code]['code'] : $code;
    }
}