<?php

class shopProductsAssociatePromoDialogAction extends waViewAction
{
    public function execute()
    {
        $promo_model = new shopPromoModel();
        $active_promos = $promo_model->getList(['status' => shopPromoModel::STATUS_ACTIVE]);
        $planned_promos = $promo_model->getList(['status' => shopPromoModel::STATUS_PLANNED]);

        $products_hash = waRequest::post('products_hash', null, waRequest::TYPE_STRING_TRIM);
        $collection = new shopProductsCollection($products_hash);
        $products = $collection->getProducts();

        $this->view->assign([
            'products_hash'  => $products_hash,
            'products'       => $products,
            'active_promos'  => $active_promos,
            'planned_promos' => $planned_promos,
        ]);
    }
}