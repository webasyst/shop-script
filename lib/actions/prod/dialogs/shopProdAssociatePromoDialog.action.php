<?php

class shopProdAssociatePromoDialogAction extends waViewAction
{
    protected $count = 0;

    public function execute()
    {
        $promo_model = new shopPromoModel();
        $active_promos = $promo_model->getList(['status' => shopPromoModel::STATUS_ACTIVE]);
        $planned_promos = $promo_model->getList(['status' => shopPromoModel::STATUS_PLANNED]);

        $hash = $this->getProductsHash();

        $this->view->assign([
            'count'          => $this->count,
            'products_hash'  => $hash,
            'active_promos'  => $active_promos,
            'planned_promos' => $planned_promos,
        ]);

        $this->setTemplate('templates/actions/prod/main/dialogs/products.associate_promo.html');
    }

    protected function getProductsHash()
    {
        $product_ids = waRequest::post('product_ids', [], waRequest::TYPE_ARRAY_INT);
        $presentation_id = waRequest::post('presentation_id', null, waRequest::TYPE_INT);

        if (!$presentation_id) {
            $products_hash = 'id/' . join(',', $product_ids);
            $this->count = count($product_ids);

        } else {
            $presentation = new shopPresentation($presentation_id, true);
            $options = [];
            if ($presentation->getFilterId() > 0) {
                $options['exclude_products'] = $product_ids;
                $options['prepare_filter'] = $presentation->getFilterId();
            }
            $collection = new shopProductsCollection('', $options);
            $products = $presentation->getProducts($collection, [
                'fields' => ['id'],
                'offset' => max(0, waRequest::post('offset', 0, waRequest::TYPE_INT)),
            ]);
            $products_hash = 'id/' . join(',', array_keys($products));
            $this->count = count($products);
        }

        return $products_hash;
    }
}