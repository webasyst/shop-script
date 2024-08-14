<?php
/**
 * Duplicate existing feature and attach it to the same product types.
 */
class shopSettingsTypefeatFeatureDuplicateController extends waJsonController
{
    public function execute()
    {
        $old_feature_id = waRequest::post('id', 0, 'int');
        $feature_model = new shopFeatureModel();
        $old_feature = $feature_model->getById($old_feature_id);
        if (!$old_feature) {
            throw new waException(_w('Feature not found.'), 404);
        }

        // Load existing feature product types
        $type_features_model = new shopTypeFeaturesModel();
        $type_features_model->fillTypes(ref([$old_feature['id'] => &$old_feature]));

        // Load existing feature values
        $feature_model->getValues(ref([$old_feature['id'] => &$old_feature]));

        // Prepare basic data for new feature
        $feature_data = array_intersect_key($old_feature, [
          'name' => '',
          'code' => '',
          'type' => '',
          'status' => '',
          'multiple' => '',
          'selectable' => '',
          'sort' => '',
        ]);

        // Modify name and code
        $old_name = $feature_data['name'];
        if (preg_match('/^(.*\D)(\d+)$/', $old_name, $matches)) {
            $old_name = $matches[1];
            $number = $matches[2] + 1;
        } else {
            $old_name .= ' ';
            $number = 1;
        }
        $feature_data['name'] = $old_name.$number;
        $feature_data['code'] = rtrim($feature_data['code'], '0123456789').$number;
        $feature_data['code'] = $feature_model->getUniqueCode($feature_data['code']);

        // Duplicate feature and read it back from DB
        $saved_feature_id = $feature_model->save($feature_data);
        $feature = $feature_model->getById($saved_feature_id);

        // Duplicate feature values
        $feature_model->setValues($feature, $old_feature['values'], false, true);

        // Save product types this feature belongs to
        $type_features_model->updateByFeature($feature['id'], array_keys($old_feature['types']));

        // Fix ordering in all types so that copy appears to be where original is
        if (!empty($old_feature['sort'])) {
            foreach($old_feature['sort'] as $type_id => $sort) {
                $type_features_model->updateByField([
                    'feature_id' => $saved_feature_id,
                    'type_id' => $type_id,
                ], [
                    'sort' => $sort,
                ]);
            }
        }

        $this->response = [
            'id' => $saved_feature_id,
        ];
    }
}
