<?php
/**
 * Accept POST from feature editor dialog to save new or existing feature.
 */
class shopSettingsTypefeatFeatureSaveController extends waJsonController
{
    public function execute()
    {
        $feature_id = waRequest::post('id', 0, 'int');
        $feature_data = waRequest::post('feature', [], 'array');
        $feature_values_data_raw = waRequest::post('values', [], 'array');
        if (empty($feature_data['all_types_is_checked'])) {
            // Selected product type ids
            $feature_types_data = waRequest::post('types', [], 'array_int');
        } else if ($feature_data['all_types_is_checked'] == 'all') {
            // All product type ids
            $type_model = new shopTypeModel();
            $feature_types_data = [0 => 0];
            foreach(array_keys($type_model->getAll('id')) as $type_id) {
                $feature_types_data[$type_id] = $type_id;
            }
        } else {
            // Single product type id
            $feature_types_data = [$feature_data['all_types_is_checked'] => $feature_data['all_types_is_checked']];
        }

        if (empty($feature_data['name'])) {
            $this->errors[] = [
                'name' => 'feature[name]',
                'text' => _w('This field is required.'),
            ];
        }
        if (empty($feature_data['code'])) {
            $this->errors[] = [
                'name' => 'feature[code]',
                'text' => _w('This field is required.'),
            ];
            return;
        }
        if ($this->errors) {
            return;
        }

        // Existing old data for this feature if exists
        $old_feature = null;
        $model = new shopFeatureModel();
        if ($feature_id) {
            $old_feature = $model->getById($feature_id);
            if (!$old_feature) {
                throw new waException('Not found', 404);
            }
        } else {
            $old_feature = $model->getEmptyRow();
        }
        // Take basic feature data from POST
        $feature_data = $this->parseFeatureData($feature_data, $old_feature);
        if (!$feature_data) {
            throw new waException('This can not happen', 500);
        }

        // Change type of existing feature if asked
        $type_convert_required = $feature_id
            && [$feature_data['type'], $feature_data['selectable'], $feature_data['multiple']]
             != [$old_feature['type'],  $old_feature['selectable'],  $old_feature['multiple']];

        if ($type_convert_required) {
            if (!shopFeatureValuesConverter::isConvertible($old_feature, $feature_data)) {
                $this->errors[] = [
                    'id' => 'unable_to_convert',
                    'text' => _w('Feature type change is not supported for the selected types.'),
                ];
                return;
            }
            $result = shopFeatureValuesConverter::run($feature_id, [
                'type'       => $feature_data['type'],
                'selectable' => $feature_data['selectable'],
                'multiple'   => $feature_data['multiple'],
            ]);
            if (!$result) {
                // This should never happen. Just being paranoid.
                $this->errors[] = [
                    'id' => 'failed_to_convert',
                    'text' => _w('Feature type change has failed because of an error.'),
                ];
                return;
            }
        } else {
            if ($feature_data['selectable']) {
                // Read and validate list of values for this feature
                list($feature_values_data, $feature_value_errors) = $this->parseFeatureValues($feature_values_data_raw, $feature_data);
                if ($feature_value_errors) {
                    $this->errors = $feature_value_errors;
                    return;
                }
            }
        }

        // Save basic feature data like type, name and code
        $saved_feature_id = $model->save($feature_data, $feature_id);
        $feature = $model->getById($saved_feature_id);

        // Read old list of types feature used to belong to
        $type_features_model = new shopTypeFeaturesModel();
        $type_features_model->fillTypes(ref([&$feature]));
        $old_types = $feature['types'];
        unset($feature['types'], $feature['sort']);

        // Save types this feature belongs to
        $feature['types'] = $type_features_model->updateByFeature($feature['id'], $feature_types_data);

        // For types this feature has been just added to, move feature to the top of the list
        foreach ($feature['types'] as $type_id) {
            if ($type_id > 0 && !isset($old_types[$type_id])) {
                $type_features_model->move([
                    'feature_id' => $feature['id'],
                    'type_id' => $type_id,
                ], null, $type_id);
            }
        }

        // Save list of values for this feature, unless type has been just converted
        if (!$type_convert_required && $feature_data['selectable']) {
            $feature['values'] = $model->setValues($feature, $feature_values_data);
        }

        // Delete SKU values if feature is being disabled for SKUs
        if (isset($feature_data['available_for_sku']) && empty($feature_data['available_for_sku']) && !empty($old_feature['available_for_sku'])) {
            $product_features_model = new shopProductFeaturesModel();
            $product_features_model->deleteSkuValuesByFeature($feature['id']);
        }

        // provide data for event below (legacy)
        $features = [$feature['id'] => $feature];
        shopFeatureModel::appendTypeNames($features);

        /**
         * @event features_save
         * @param array $features
         * @return void
         */
        wa('shop')->event('features_save', $features);

        $this->response = $features[$feature['id']];
    }

    // $feature_values_data_raw = ['id' => [10, 20, 30, 40], 'value' => ['a','b','c','d'], 'code' => ['q','w','e','r']]
    protected function parseFeatureValues($feature_values_data_raw, $feature)
    {
        if ($feature['type'] == 'color') {
            $value_format = 'color';
        } else if ('dimension.' == substr($feature['type'], 0, 10)) {
            list($value_format, $value_dimension) = explode('.', $feature['type'], 2);
        } else {
            // varchar, double
            $value_format = 'string';
        }

        $new_id = -1;
        $unique_values = [];
        $feature_values_data = [];
        $feature_value_errors = [];
        if ($feature_values_data_raw) {
            foreach(array_keys(reset($feature_values_data_raw)) as $i) {
                $value = [];
                foreach(array_keys($feature_values_data_raw) as $key) {
                    $value[$key] = ifset($feature_values_data_raw, $key, $i, '');
                }

                if (empty($value['id'])) {
                    $value['id'] = $new_id;
                    $new_id--;
                }

                // Different feature types want different format for their values
                $unique_value = ifset($value, 'value', '');
                switch ($value_format) {
                    case 'string':
                        $feature_values_data[$value['id']] = $value['value'];
                        break;
                    case 'color':
                        $feature_values_data[$value['id']] = [
                            'value' => $value['value'],
                            'code' => $value['code'],
                        ];
                        break;
                    case 'dimension':
                        $unique_value = $value['value'].'%%%'.$value['unit'];
                        $feature_values_data[$value['id']] = [
                            'value' => $value['value'],
                            'unit' => $value['unit'],
                        ];
                        break;
                }

                // Make sure values are unique
                if (is_scalar($unique_value) && strlen($unique_value) > 0) {
                    if (!isset($unique_values[$unique_value])) {
                        $unique_values[$unique_value] = 1;
                    } else {
                        $feature_value_errors[] = [
                            'id' => 'kind_value_error',
                            'text' => _w('Values must be unique'),
                            'data' => [
                                'index' => $i,
                            ],
                        ];
                    }
                }
            }
        }
        return [$feature_values_data, $feature_value_errors];
    }

    protected function parseFeatureData($feature_data, $old_feature)
    {
        //
        // Feature name and code
        //
        $feature_data['name'] = ifset($feature_data, 'name', $old_feature['name']);
        $feature_data['code'] = ifset($feature_data, 'code', $old_feature['code']);
        if (empty($feature_data['name'])) {
            $feature_data['name'] = $feature_data['code'];
            if (empty($feature_data['name'])) {
                $model = new shopFeatureModel();
                $feature_data['name'] = $model->getUniqueCode('f');
            }
        }
        if (empty($feature_data['code'])) {
            $feature_data['code'] = strtolower(waLocale::transliterate($feature_data['name']));
        }

        //
        // Feature status (visibility in frontend)
        //
        if (!empty($feature_data['visible_in_frontend'])) {
            $feature_data['status'] = 'public';
        } else if (isset($feature_data['visible_in_frontend'])) {
            $feature_data['status'] = 'private';
        }
        $feature_data['status'] = ifset($feature_data, 'status', $old_feature['status']);
        if (!in_array($feature_data['status'], ['public', 'private'])) {
            $feature_data['status'] = 'public';
        }

        //
        // Feature type, selectable and multiple
        //
        if (!empty($feature_data['type'])) {
            // Allow to provide type and selectable/multiple directly. Used for dividers.
            $feature_data['selectable'] = ifempty($feature_data, 'selectable', 0);
            $feature_data['multiple'] = ifempty($feature_data, 'multiple', 0);
        } else if (!empty($feature_data['kind'])) {
            // Allow to select via kind and format selectors
            list(
                $feature_data['selectable'],
                $feature_data['multiple'],
                $feature_data['type']
            ) = $this->parseTypeByKindAndFormat($feature_data);
            unset($feature_data['format'], $feature_data['kind']);
        } else {
            // Keep old type if not provided in POST
            $feature_data['type'] = ifempty($old_feature, 'type', 'varchar');
            $feature_data['selectable'] = ifempty($old_feature, 'selectable', 0);
            $feature_data['multiple'] = ifempty($old_feature, 'multiple', 0);
        }

        $feature_data = array_intersect_key($feature_data, [
          'name' => '',
          'code' => '',
          'type' => '',
          'status' => '',
          'multiple' => '',
          'selectable' => '',
          'available_for_sku' => '',
          'default_unit' => '',
        ]);

        return $feature_data;
    }

    public static function parseTypeByKindAndFormat($feature_data, $old_feature=null)
    {
        $feature_data['format'] = ifset($feature_data, 'format', ''); // because may not be set for booleans

        list(
            $feature_data['selectable'],
            $feature_data['multiple'],
            $feature_data['type']
        ) = shopFeatureModel::getTypeByKindAndFormat($feature_data['kind'], $feature_data['format']);

        if ($feature_data['format'] == '2d' || $feature_data['format'] == '3d') {
            // Special cases. Reasoning behind this is legacy.
            // It was possible to create 2d.volume and 3d.volume features in old editor,
            // as well as 2d.* and 3d.* for any dimension type.
            // Then it was decided that 2d and 3d should only be supported for 'number' and 'length',
            // notably 2d/3d.length being under 'area' and 'volume' type selectors respectively.
            // Yet, to be nice to legacy features, we still want to save them without breaking them.
            // Hence this code checks for that.
            $legacy_type = $feature_data['format'].'.dimension.'.$feature_data['kind'];
            if ($legacy_type === ifempty($old_feature, 'type', '')) {
                $feature_data['type'] = $legacy_type;
            }
        }

        return [
            $feature_data['selectable'],
            $feature_data['multiple'],
            $feature_data['type'],
        ];
    }
}
