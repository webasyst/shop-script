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
                'purchase_price' => null,
                'compare_price'  => null,
            );
        } elseif (isset($product->skus[$sku_id])) {
            $sku = $product->skus[$sku_id];
        } else {
            throw new waException("SKU not found", 404);
        }

        $this->view->assign('sku', $sku);
        //$this->view->assign('features', $features_model->getByType($product->type_id, 'code', true));
        $this->view->assign('features', $this->getFeatures($product));
        $this->view->assign('sku_features', $product_features_model->getValues($product_id, -$sku_id));

        $event_params = array(
            'product' => $product,
            'sku' => $sku,
            'sku_id' => $sku_id
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
        foreach ($features_model->getByType($product->type_id, 'code', true) as $f) {
            if ($f['multiple'] || $f['code'] == 'weight') {
                $features[$f['code']] = $f;
            }
        }

        // attach values
        $features = $features_model->getValues($features);

        return $features;
    }
}
