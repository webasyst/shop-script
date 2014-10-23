<?php

class shopProductFeaturesSelectableAction extends shopProductAction
{
    public function execute()
    {
        $config = $this->getConfig();
        /**
         * @var shopConfig $config
         */
        $product = new shopProduct(waRequest::get('id', 0, waRequest::TYPE_INT));
        $type_id = waRequest::request('type_id', null, waRequest::TYPE_INT);
        if ($type_id != null) {
            $product->type_id = $type_id;
        }

        $sku_type = waRequest::request('sku_type', null, waRequest::TYPE_INT);
        if ($sku_type != null) {
            $product->sku_type = $sku_type;
        }
        // Selectable features
        $features_selectable = $product->features_selectable;

        $counts = array();
        foreach ($features_selectable as $f) {
            if ($f['selected']) {
                $counts[] = $f['selected'];
            }
        }
        $this->view->assign('product', $product);
        $this->view->assign('features', $features_selectable);
        $this->view->assign('features_counts', $counts);

        $this->view->assign(array(
            'use_product_currency' => wa()->getSetting('use_product_currency'),
            'currencies'           => $this->getCurrencies(),
            'primary_currency'     => $config->getCurrency(),
        ));
    }
}
