<?php
/**
 * Get feature data pre-formatted, by feature_id.
 * Used on SKU page.
 */
class shopProdFormatFeatureController extends waJsonController
{
    public function execute()
    {
        $feature_id = waRequest::request('feature_id', null, 'int');
        $product_id = waRequest::request('product_id', null, 'int');

        $feature_model = new shopFeatureModel();
        $feature = $feature_model->getById($feature_id);
        if (!$feature) {
            throw new waException(_w('Feature not found.'), 404);
        }

        $feature = $feature_model->getById($feature_id);
        $feature['internal'] = $this->featureBelongsToType($product_id, $feature_id);

        $features = [$feature['code'] => $feature];
        if (!empty($feature['selectable'])) {
            $features = $feature_model->getValues($features);
        }
        $formatted_features = shopProdSkuAction::formatFeatures($features);
        $this->response = reset($formatted_features);
    }

    /** Whether given feature belongs to product type of given product */
    protected function featureBelongsToType($product_id, $feature_id)
    {
        $product_model = new shopProductModel();
        $product = $product_model->getById($product_id);
        if (empty($product['type_id'])) {
            return false;
        }

        $type_features_model = new shopTypeFeaturesModel();
        $result = $type_features_model->getByField([
            'type_id' => $product['type_id'],
            'feature_id' => $feature_id,
        ]);

        return (bool) $result;
    }
}
