<?php

class shopSettingsFeaturesFeatureUsageController extends waJsonController
{
    public function execute()
    {
        if ($id = max(0, waRequest::get('feature_id', 0, waRequest::TYPE_INT))) {
            $feature_model = new shopFeatureModel();
            if ($feature = $feature_model->getById($id)) {
                $product_features_model = new shopProductFeaturesModel();;

                if ($feature['product_usage_count'] = $product_features_model->countProductsByFeature($id)) {
                    $this->response['notice'] = _w('You have <strong>%d product</strong> with this feature value specified. Deleting this feature will erase it’s value for all these products.', 'You have <strong>%d products</strong> with this feature value specified. Deleting this feature will erase it’s value for all these products.', $feature['product_usage_count']);
                } else {
                    $this->response['notice'] = _w('This feature is not assigned to any product yet, and thus can be safely deleted.');
                }
                $this->response['feature'] = $feature;
            } else {
                throw new waException('Feature not found', 404);
            }
        } else {
            throw new waException('Feature id is empty', 404);
        }
    }
}
