<?php

class shopMarketingPromoSortController extends waJsonController
{
    public function execute()
    {
        $storefront = waRequest::request('storefront', '', 'string');
        $ids = waRequest::post('ids', [], 'array_int');

        if (!$storefront || !$ids) {
            return;
        }

        $promo_routes_model = new shopPromoRoutesModel();
        $promo_routes_model->reorderPromos($storefront, $ids);
    }
}