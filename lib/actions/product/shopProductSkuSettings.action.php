<?php

class shopProductSkuSettingsAction extends waViewAction
{
    public function execute()
    {
        $product_features_model = new shopProductFeaturesModel();
        $this->view->assign('sku_id', $sku_id = waRequest::get('sku_id', 0, waRequest::TYPE_INT));
        $this->view->assign('product_id', $product_id = waRequest::get('product_id', 0, waRequest::TYPE_INT));
        $this->view->assign('product', $product = new shopProduct($product_id));
        if ($sku_id < 0) {
            $sku = array(
                'image_id'       => '',
                'available'      => 1,
                'status'         => 1,
                'purchase_price' => null,
                'compare_price'  => null,
            );
        } elseif (isset($product->skus[$sku_id])) {
            $sku = $product->skus[$sku_id];
        } else {
            throw new waException(_w('Product variant not found.'), 404);
        }

        if ($product->id <= 0) {
            $product->type_id = waRequest::get('type_id', 0, waRequest::TYPE_INT);
        }

        $this->view->assign('sku', $sku);

        $this->view->assign('sku_features', $product_features_model->getValues($product_id, -$sku_id));
        $this->view->assign('features', $this->getFeatures($product));

        $event_params = array(
            'product' => $product,
            'sku'     => $sku,
            'sku_id'  => $sku_id,
        );

        /**
         * Plugin hook for handling product entry saving event
         * @event backend_product_sku_settings
         *
         * @param array [string]mixed $params
         * @param array [string][string]mixed $params['product']
         * @param array [string][string]mixed $params['sku']
         * @return void
         */
        $this->view->assign('backend_product_sku_settings', wa()->event('backend_product_sku_settings', $event_params));
    }

    public function getFeatures(shopProduct $product)
    {
        $features_model = new shopFeatureModel();

        $features = array();
        $selectable_features = array();
        foreach ($features_model->getByType($product->type_id, 'code', true) as $f) {
            $f['available_for_sku'] = !empty($f['available_for_sku']);
            $f['internal'] = 1;
            if (($f['code'] == 'weight')
                || !empty($f['available_for_sku'])
            ) {
                if (!empty($f['selectable'])) {
                    $selectable_features[$f['code']] = $f;
                }
                $features[$f['code']] = $f;
            }
        }

        // attach values
        $selectable_features = $features_model->getValues($selectable_features);
        foreach ($features as $code => &$feature) {
            if (isset($selectable_features[$code])) {
                $feature = $selectable_features[$code];
            }
            unset($feature);
        }

        return $features;
    }
}
