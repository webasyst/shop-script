<?php

class shopMarketingPromoEnabledController extends waJsonController
{
    public function execute()
    {
        $promo_id = waRequest::post('promo_id', null, waRequest::TYPE_INT);
        $enabled = waRequest::post('enabled', null, waRequest::TYPE_INT);

        (new shopPromoModel())->updateById($promo_id, ['enabled' => $enabled]);
    }
}