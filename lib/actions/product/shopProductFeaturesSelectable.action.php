<?php

class shopProductFeaturesSelectableAction extends waViewAction
{
    public function execute()
    {
        $product = new shopProduct(waRequest::get('id', 0, waRequest::TYPE_INT));

        $type_id = waRequest::get('type_id', null, waRequest::TYPE_INT);
        if ($type_id != null) {
            $product->type_id = $type_id;
        }

        $sku_type = waRequest::get('sku_type', null, waRequest::TYPE_INT);
        if ($sku_type != null) {
            $product->sku_type = $sku_type;
        }

        // Selectable features
        $selectable_features = $this->getSelectableFeatures($product);

        $counts = array();
        foreach ($selectable_features as $f) {
            if ($f['count']) {
                $counts[] = $f['count'];
            }
        }
        $this->view->assign('product', $product);
        $this->view->assign('features', $selectable_features);
        $this->view->assign('features_counts', $counts);
    }

    /**
     * Get only multiple type features
     * @param shopProduct $product
     */
    protected function getSelectableFeatures(shopProduct $product)
    {
        $features_model = new shopFeatureModel();
        $features = $features_model->getMultipleSelectableFeaturesByType($product->type_id);

        // attach values
        $features = $features_model->getValues($features);

        $features_selectable_model = new shopProductFeaturesSelectableModel();
        $selected = array();
        foreach ($features_selectable_model->getByField('product_id', $product->id, true) as $item) {
            $selected[$item['feature_id']][$item['value_id']] = true;
        }
        foreach ($features as $code => $f) {
            $count = 0;
            foreach ($f['values'] as $v_id => $v) {
                $is_selected = isset($selected[$f['id']][$v_id]);
                $features[$code]['values'][$v_id] = array(
                    'name' => (string)$v,
                    'selected' => $is_selected
                );
                if ($is_selected) {
                    $count += 1;
                }
            }
            $features[$code]['count'] = $count;
        }
        return $features;
    }
}