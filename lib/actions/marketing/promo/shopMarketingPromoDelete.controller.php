<?php

class shopMarketingPromoDeleteController extends waJsonController
{
    public function execute()
    {
        $promo_id = waRequest::post('promo_id', null, waRequest::TYPE_INT);
        (new shopPromoModel())->delete($promo_id);
    }
}