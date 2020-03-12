<?php
/**
 * HTML for dialog that confirms deletion of feature.
 */
class shopSettingsTypefeatFeatureDeleteDialogAction extends waViewAction
{
    public function execute()
    {
        $feature_id = waRequest::request('id', '', waRequest::TYPE_STRING);

        $feature_model = new shopFeatureModel();
        $feature = $feature_model->getById($feature_id);
        if (!$feature) {
            throw new waException('Feature not found', 404);
        }

        // If feature is available for SKU, it may not always be deletable
        $can_delete_feature = true;
        if ($feature['available_for_sku']) {
            list($can_disable_sku, $sku_values_count) = shopSettingsTypefeatFeatureEditAction::analyzeSkus($feature);
            $can_delete_feature = $can_disable_sku;
        }

        if (!$can_delete_feature) {
            $notice_html = _w('You cannot delete this feature, because some products in your store have SKUs generated from this feature’s values in “Selectable parameters” mode.');
        } else {
            $product_features_model = new shopProductFeaturesModel();
            $product_usage_count = $product_features_model->countProductsByFeature($feature_id);

            if ($product_usage_count) {
                $notice_html = _w(
                    'You have <strong>%d product</strong> with this feature value specified. Deleting this feature will erase it’s value for all these products.',
                    'You have <strong>%d products</strong> with this feature value specified. Deleting this feature will erase it’s value for all these products.',
                    $product_usage_count
                );
            } else {
                $notice_html = _w('This feature is not assigned to any product yet, and thus can be safely deleted.');
            }
        }

        $this->view->assign([
            'feature' => $feature,
            'notice_html' => $notice_html,
            'can_delete_feature' => $can_delete_feature,
        ]);
    }
}
