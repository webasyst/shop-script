<?php
/**
 * Add a new option (value) to feature, useful for features selectable=1
 */
class shopProdAddFeatureValueController extends waJsonController
{
    public function execute()
    {
        $feature_id = waRequest::request('feature_id', null, waRequest::TYPE_INT);
        $value = waRequest::request('value');

        $feature_model = new shopFeatureModel();
        $feature = $feature_model->getById($feature_id);
        if (!$feature) {
            throw new waException(_w('Not found'), 404);
        }

        // Save option to database
        $value_id = $feature_model->getValueId($feature, $value, true);

        // Get actual value, this applies formatting specific to feature type, if needed.
        // This can be an object, but always convertible to string.
        $value_saved = $feature_model->getValuesModel($feature['type'])->getFeatureValue($value_id);

        $this->response = [
            'feature' => $feature,
            'option' => [
                'id' => $value_id,

                // <option value="VALUE">NAME</option>
                'name' => (string) $value_saved,
                'value' => (string) $value_saved,
            ],
        ];

        if ($value_saved instanceof shopColorValue) {
            $this->response['option']['value'] = $value_saved['value'];
            $this->response['option']['code'] = $value_saved['hex'];
        }
        if ($value_saved instanceof shopDimensionValue) {
            $this->response['option']['value_numeric'] = $value_saved['value'];
            $this->response['option']['unit'] = $value_saved['unit'];
        }
    }
}
