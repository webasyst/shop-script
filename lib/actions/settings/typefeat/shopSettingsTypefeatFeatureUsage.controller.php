<?php
/**
 * Before feature is deleted, count how many products have data for the feature.
 * Returns human-readable text as well as feature data as JSON.
 */
class shopSettingsTypefeatFeatureUsageController extends waJsonController
{
    public function execute()
    {
        $id = max(0, waRequest::request('id', 0, waRequest::TYPE_INT));

        $feature_model = new shopFeatureModel();
        $feature = $feature_model->getById($id);
        if (!$feature) {
            throw new waException(_w('Feature not found.'), 404);
        }

        $product_features_model = new shopProductFeaturesModel();
        $feature['product_usage_count'] = $product_features_model->countProductsByFeature($id);

        if ($feature['product_usage_count']) {
            $this->response['notice'] = _w(
                'You have <strong>%d product</strong> with this feature value specified. Deleting this feature will erase it’s value for all these products.',
                'You have <strong>%d products</strong> with this feature value specified. Deleting this feature will erase it’s value for all these products.',
                $feature['product_usage_count']
            );
        } else {
            $this->response['notice'] = _w('This feature is not assigned to any product yet, and thus can be safely deleted.');
        }
        $this->response['feature'] = $feature;
    }
}
