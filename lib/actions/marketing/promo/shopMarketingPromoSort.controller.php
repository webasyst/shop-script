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

        $sort = 0;
        foreach($ids as $promo_id) {

            $promo_routes_model->updateByField([
                'promo_id' => $promo_id,
                'storefront' => $storefront,
            ], [
                'sort' => $sort,
            ]);

            $sort++;
        }
    }
}