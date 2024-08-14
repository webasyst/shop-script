<?php
/**
 * Accepts POST data from Sku tab in product editor.
 */
class shopProdSaveSkuController extends waJsonController
{
    public function execute()
    {
        $product_raw_data = waRequest::post('product', null, 'array');
        $product = new shopProduct(ifempty($product_raw_data, 'id', null));
        if (!$product->getId()) {
            $this->errors[] = [
                'id' => 'not_found',
                'text' => _w('Product not found.'),
            ];
            return;
        }
        $this->checkRights($product['id']);

        $product_data = $this->prepareProductData($product, $product_raw_data);

        $backend_prod_pre_save = $this->throwPreSaveEvent($product, $product_data);
        foreach ($backend_prod_pre_save as $plugin_id => $result) {
            if ($result['errors']) {
                $this->errors = array_merge($this->errors, $result['errors']);
            }
        }

        if ($product_data && !$this->errors) {
            $product = $this->saveProduct($product, $product_data);
        }

        if (!$this->errors) {
            $this->response = $this->prepareResponse($product);
        }
    }

    /**
     * Takes data from POST and prepares format suitable as input for for shopProduct class.
     * If something gets wrong, writes errors into $this->errors.
     *
     * @param shopProduct
     * @param array
     * @return array
     */
    protected function prepareProductData(shopProduct $product, array $product_raw_data)
    {
        $params_string = ifset($product_raw_data, 'params_string', '');

        $product_data = array_intersect_key($product_raw_data, [
            'badge' => true,
            'sku_id' => true,
            'currency' => true,
            'features' => true,
            'skus' => true,
            'params' => true,
        ]);

        // When no values come for a `checklist` feature type, it means we need to remove all its values.
        // Add empty array as data for the checklist if product already contains value for it.
        // Do that when user attempts to save at least one feature.
        if (isset($product_data['features'])) {

            $codes = [];
            foreach($product->features as $code => $values) {
                // checklist values is an array, ignore everything else
                if (is_array($values)) {
                    // Only bother if data did not come from POST
                    if (!isset($product_data['features'][$code])) {
                        $codes[] = $code;
                    }
                }
            }

            if ($codes) {
                $feature_model = new shopFeatureModel();
                $features = $feature_model->getByField('code', $codes, 'code');
                foreach ($features as $code => $feature) {
                    // make sure feature is indeed a checklist
                    if ($feature['selectable'] && $feature['multiple']) {
                        $product_data['features'][$code] = [];
                    }
                }
            }
            unset($features, $feature, $codes, $code);
        }

        $main_sku_id = ifset($product_data['sku_id'], null);
        if ($main_sku_id && empty($product_data['skus'][$main_sku_id]['status'])) {
            $this->errors[] = [
                'id' => 'main_sku_visibility',
                'text' => _w('The main SKU cannot be hidden. Either select another main SKU or make the main SKU visible.'),
            ];
        }

        if (empty($this->errors) && isset($product_data['skus']) && is_array($product_data['skus'])) {

            // Validate SKU prices. They must not exceet certain length
            $count_status = 0;
            foreach ($product_data['skus'] as $sku_id => &$sku) {
                if (!is_array($sku)) {
                    unset($product_data['skus'][$sku_id]);
                    continue;
                }

                if (!empty($sku['status'])) {
                    $count_status++;
                }

                foreach (['price', 'purchase_price', 'compare_price'] as $field) {
                    if (isset($sku[$field])) {
                        $sku[$field] = str_replace(',', '.', $sku[$field]);
                        if (strlen($sku[$field]) > 0 && (!is_numeric($sku[$field]) || strlen($sku[$field]) > 16
                            || $sku[$field] > floatval('1'.str_repeat('0', 11)))
                        ) {
                            $this->errors[] = [
                                'id' => 'price_error',
                                'name' => "product[skus][{$sku_id}][{$field}]",
                                'text' => _w('Invalid value'),
                            ];
                        }
                    }
                }
                foreach (['stock_base_ratio' => 16, 'order_count_min' => 15, 'order_count_step' => 15] as $sku_field => $length) {
                    if (isset($sku[$sku_field])) {
                        $max_number = $sku_field == 'stock_base_ratio' ? 8 : 12;
                        $min_number = $sku_field == 'stock_base_ratio' ? 7 : 2;
                        if (is_string($sku[$sku_field]) && strlen($sku[$sku_field]) === 0) {
                            $sku[$sku_field] = null;
                        } elseif ($sku[$sku_field] <= 0 || !is_numeric($sku[$sku_field]) || strlen($sku[$sku_field]) > $length + 1
                            || $sku[$sku_field] > floatval('1'.str_repeat('0', $max_number))
                            || $sku[$sku_field] < floatval('0.'.str_repeat('0', $min_number).'1')
                        ) {
                            $this->errors[] = [
                                'id' => $sku_field . '_error',
                                'name' => "product[skus][$sku_id][$sku_field]",
                                'text' => _w('Invalid value'),
                            ];
                        }
                    }
                }
            }
            unset($sku);

            if (!empty($product_data['skus']) && empty($count_status)) {
                $this->errors[] = [
                    'id' => 'general',
                    'text' => _w('At least one SKU must be visible in the storefront.'),
                ];
            }

            // Mark SKUs for deletion if data for sku_id is missing
            // shopProduct does not touch SKUs when data is not provided
            // to delete a SKU, its existing sku_id must point to any scalar value
            $product_data['skus'] += array_fill_keys(array_keys($product['skus']), '');

        } else {
            unset($product_data['skus']);
        }

        // SKU type is whether customer selects SKU from a flat list (SKU_TYPE_FLAT)
        // or selects based on features (SKU_TYPE_SELECTABLE).
        $new_sku_type = ifset($product_raw_data, 'sku_type', $product['sku_type']);
        // Selectable features are those shown to customers in frontend when they select SKU
        $new_features_selectable_ids = ifset($product_raw_data, 'features_selectable', null);
        if (isset($new_features_selectable_ids) && !is_array($new_features_selectable_ids)) {
            // empty string means empty array
            $new_features_selectable_ids = [];
        }
        if ($new_sku_type == shopProductModel::SKU_TYPE_FLAT || !empty($new_features_selectable_ids)) {
            $product_data['features_selectable_ids'] = $new_features_selectable_ids;
            $product_data['sku_type'] = $new_sku_type;
        }

        $divided_params = explode("\n", $params_string);
        if (is_array($divided_params)) {
            foreach ($divided_params as $param) {
                $param_key_value = explode('=', $param, 2);
                if (is_array($param_key_value) && count($param_key_value) == 2) {
                    $param_key = $param_key_value[0];
                    $param_value = trim($param_key_value[1]);
                    if (mb_strlen($param_key) && !isset($product_data['params'][$param_key])) {
                        $product_data['params'][$param_key] = $param_value;
                    }
                }
            }
        }

        if (!$this->errors) {
            shopProdSaveGeneralController::updateMainImage($product_data, $product_raw_data['id'], $product['type_id']);
        }

        return $product_data;
    }

    /**
     * @throws waRightsException
     */
    protected function checkRights($id)
    {
        $product_model = new shopProductModel();
        if (!$id || !$product_model->checkRights($id)) {
            throw new waRightsException(_w("Access denied"));
        }
    }

    /**
     * Helper for saveProduct().
     * Count sibling modifications in the same SKU
     * @param array   $product_data['skus']
     * @return array  [(int) Modification index => (int) count modifications in the same SKU]
     */
    protected function countModsBySku($product_data_skus)
    {
        // Group modifications by sku
        $mods_by_sku = [];
        foreach($product_data_skus as $sku_index => $sku_data) {
            if (!is_array($sku_data)) {
                // ignore SKUs marked for deletion
                continue;
            }

            if (isset($sku_data['sku']) && isset($sku_data['name']) && (strlen($sku_data['sku']) || strlen($sku_data['name']))) {
                // mods with code or name are grouped into SKUs by code and name
                $sku_code_and_name = $sku_data['sku'] . '###' . $sku_data['name'];
                if (!isset($mods_by_sku[$sku_code_and_name])) {
                    $mods_by_sku[$sku_code_and_name] = [];
                }
                $mods_by_sku[$sku_code_and_name][] = ['index' => $sku_index] + $sku_data;
            } else {
                // mods with no code or name are their own SKUs
                $mods_by_sku[] = [['index' => $sku_index] + $sku_data];
            }
        }

        //
        $count_mods_by_sku = [];
        foreach($mods_by_sku as $sku_mods) {
            foreach($sku_mods as $mod) {
                $count_mods_by_sku[$mod['index']] = count($sku_mods);
            }
        }

        return $count_mods_by_sku;
    }

    /**
     * Takes product and data prepared by $this->prepareProductData()
     * Saves to DB and returns shopProduct just saved.
     * If something goes wrong, writes errors into $this->errors and returns null.
     * @param shopProduct $product
     * @param array $product_data
     * @return shopProduct or null
     */
    protected function saveProduct(shopProduct $product, array $product_data)
    {
        $errors = null;
        try {
            // Save selectable features separately, not via shopProduct class
            // because 'features_selectable' key of shopProduct generates SKUs on the fly,
            // which is not what we want in this new editor.
            $features_selectable_ids = ifset($product_data, 'features_selectable_ids', null);
            unset($product_data['features_selectable_ids']);

            // Append selectable feature values to SKU names in certain cases
            if (isset($features_selectable_ids) && isset($product_data['skus'])) {

                $count_mods_by_sku = $this->countModsBySku($product_data['skus']);

                $feature_model = new shopFeatureModel();
                $features = $feature_model->getById($features_selectable_ids);

                foreach($product_data['skus'] as $sku_index => &$sku_data) {
                    if (!is_array($sku_data)) {
                        // ignore SKUs marked for deletion
                        continue;
                    }

                    $sku_name_parts = [];
                    if (isset($sku_data['name']) && strlen($sku_data['name']) > 0) {
                        // When SKU has only one modification, and name specified by hand,
                        // we do not need to append feature values to SKU name.
                        if ($count_mods_by_sku[$sku_index] <= 1) {
                            if (isset($product_data['params']['multiple_sku']) && empty($product_data['params']['multiple_sku'])) {
                                $sku_data['name'] = '';
                            }
                            continue;
                        }

                        $sku_name_parts[] = $sku_data['name'];
                    }
                    foreach($features_selectable_ids as $feature_id) {
                        if (!isset($features[$feature_id])) {
                            continue; // unknown feature
                        }
                        $feature = $features[$feature_id];
                        if (!isset($sku_data['features'][$feature['code']])) {
                            continue; // no data for this feature for this SKU
                        }
                        $value = $sku_data['features'][$feature['code']];
                        if (is_scalar($value)) {
                            $is_empty_value = strlen($value) <= 0;
                        } else {
                            $is_empty_value = strlen(ifset($value, 'value', '')) <= 0;
                        }
                        if ($is_empty_value) {
                            continue; // feature value for this sku is empty, nothing to append to name
                        }

                        // For features of some types we need full formatting:
                        // Dimensions, dates and booleans.
                        $full_formatting_needed = is_array($value) && isset($value['unit']);
                        $full_formatting_needed = $full_formatting_needed || $feature['type'] == 'date' || $feature['type'] == 'boolean';

                        if ($full_formatting_needed) {
                            // $value_formatted is an object convertible to string.
                            $value_id = $feature_model->getValueId($feature, $value, true);
                            $value_formatted = $feature_model->getValuesModel($feature['type'])->getFeatureValue($value_id);
                            $sku_name_parts[] = (string) $value_formatted;
                        } else {
                            // For everything else we already have the sting in the data
                            if (is_array($value)) {
                                $sku_name_parts[] = (string) $value['value'];
                            } else {
                                $sku_name_parts[] = (string) $value;
                            }
                        }
                    }

                    $sku_data['name'] = join(', ', $sku_name_parts);
                }
                unset($sku_data);
            }

            /** для сохранения фич, избавляемся от лишней вложенности */
            foreach ($product_data['features'] as $feature => $val) {
                if (is_array($val) && 1 === count($val) && isset($val['value']) && '0' === $val['value']) {
                    $product_data['features'][$feature] = $val['value'];
                }
            }

            // Save product
            $errors = null;
            if ($product->save($product_data, true, $errors)) {
                $this->logAction('product_edit', $product['id']);

                // Separately save selectable features
                if (isset($features_selectable_ids)) {
                    $product_features_selectable_model = new shopProductFeaturesSelectableModel();
                    $product_features_selectable_model->setFeatureIds($product, $features_selectable_ids);
                }
            }

        } catch (Exception $ex) {
            if (get_class($ex) === 'waDbException' && $ex->getCode() === 1366) {
                $this->errors[] = [
                    'id'   => 'general',
                    'text' => _w('Enable the emoji support in system settings.')
                ];
            } else {
                $message = $ex->getMessage();
                if (SystemConfig::isDebug()) {
                    if ($ex instanceof waException) {
                        $message .= "\n".$ex->getFullTraceAsString();
                    } else {
                        $message .= "\n".$ex->getTraceAsString();
                    }
                }
                $this->errors[] = [
                    'id' => "general",
                    'text' => _w('Unable to save product.').' '.$message,
                ];
            }
        }

        if ($errors) {
            $this->errors[] = [
                'id' => "general",
                'text' => _w('Unable to save product.').' '.wa_dump_helper($errors),
            ];
        }

        if (!$this->errors) {
            $this->throwSaveEvent($product, $product_data);
        }

        return $product;
    }

    protected function prepareResponse(shopProduct $product)
    {
        return [
            'id' => $product['id'],
        ];
    }

    /**
     * @param int $product_id
     * @param array &$data - data could be mutated
     * @return array
     * @throws waException
     */
    protected function throwPreSaveEvent($product, &$data)
    {
        /**
         * @event backend_prod_presave
         * @since 8.18.0
         *
         * @param shopProduct $product
         * @param array &$data
         *      Raw data from form posted - data could be mutated
         * @param string $content_id
         *       Which page is being saved
         * @return array
         *      array[string]array $return[%plugin_id%]['errors'] - validation errors
         */
        $params = [
            'product' => $product,
            'data' => &$data,
            'content_id' => 'sku',
        ];

        $backend_prod_pre_save = wa('shop')->event('backend_prod_presave', $params);

        // typecast plugin responses a little bit
        foreach ($backend_prod_pre_save as $plugin_id => &$result) {
            if (!$result || !is_array($result)) {
                unset($backend_prod_pre_save[$plugin_id]);
                continue;
            }
            if (!isset($result['errors']) || !is_array($result['errors'])) {
                $result['errors'] = [];
            }
        }
        unset($data);

        return $backend_prod_pre_save;
    }

    protected function throwSaveEvent($product, array $data)
    {
        /**
         * @event backend_prod_save
         * @since 8.18.0
         *
         * @param shopProduct $product
         * @param array $data
         *      Product data that was saved
         * @param string $content_id
         *       Which page is being saved
         */
        $params = [
            'product' => $product,
            'data' => $data,
            'content_id' => 'sku',
        ];

        wa('shop')->event('backend_prod_save', $params);
    }
}
