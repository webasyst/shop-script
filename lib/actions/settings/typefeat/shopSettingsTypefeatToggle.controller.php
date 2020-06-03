<?php
/**
 * Change feature's frontend visibility or other single boolean parameter.
 */
class shopSettingsTypefeatToggleController extends waJsonController
{
    public function execute()
    {
        $id = waRequest::post('feature_id', 0, 'int');
        $param = waRequest::post('param', '', 'string');
        $value = waRequest::post('value');

        $feature_model = new shopFeatureModel();

        // Make sure to update both feature itself, as well as its child sub-features (for 2d, 3d)
        $ids = array_keys($feature_model->select('id')->where('parent_id='.intval($id))->fetchAll('id'));
        $ids[] = $id;

        switch ($param) {
            case 'visibility':
                $feature_model->updateById($ids, [
                    'status' => $value ? 'public' : 'private',
                ]);
                break;
            case 'sku':

                // No feature no problem
                $feature = $feature_model->getById($id);
                if (!$feature) {
                    break;
                }

                // Turning SKU mode off is complicated
                if (!$value && $feature['available_for_sku']) {

                    list($can_disable_sku, $sku_values_count) = shopSettingsTypefeatFeatureEditAction::analyzeSkus($feature);

                    // User is not allowed to disable SKU mode if feature is used as selectable for any products
                    if (!$can_disable_sku) {
                        $this->errors[] = [
                            'id' => 'not_allowed_disable_sku',
                            'type' => 'fatal',
                            'text' => _w('You cannot disable the editing of this feature’s values in product SKUs’ properties, because some products in your store have SKUs generated from this feature’s values in “Selectable parameters” mode.'),
                        ];
                        return;
                    }

                    // Warn user if disabling SKU mode will result in data loss
                    if ($sku_values_count > 0 && !waRequest::post('force')) {
                        $this->errors[] = [
                            'id' => 'disable_sku_data_loss',
                            'type' => 'warning',
                            'text' => _w(
                                    'If you disable the availability of this feature in product SKUs’ properties, this will delete all its values from %d SKU.',
                                    'If you disable the availability of this feature in product SKUs’ properties, this will delete all its values from %d SKUs.',
                                    $sku_values_count
                                ).' '._w('Are you sure?')
                        ];
                        return;
                    }

                    // Okay, they seem to know what they are doing. A man's gotta do what a man's gotta do
                    $product_features_model = new shopProductFeaturesModel();
                    $product_features_model->deleteSkuValuesByFeature($id);
                }

                $feature_model->updateById($ids, [
                    'available_for_sku' => $value ? 1 : 0,
                ]);

                break;
        }

        $this->response = 'ok';
    }
}
