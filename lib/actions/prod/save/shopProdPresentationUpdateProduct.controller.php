<?php
/**
 * Update one product field
 */
class shopProdPresentationUpdateProductController extends waJsonController
{
    /**prepareFieldData
     * @var shopProductModel $product_model
     */
    protected $product_model = null;

    protected $presentation = null;

    public function execute()
    {
        $presentation_id = waRequest::post('presentation_id', 0, waRequest::TYPE_INT);
        $product_id = waRequest::post('product_id', 0, waRequest::TYPE_INT);
        $sku_id = waRequest::post('sku_id', null, waRequest::TYPE_INT);
        $field_id = waRequest::post('field_id', null, waRequest::TYPE_STRING_TRIM);
        $value = waRequest::post('value');

        $product = new shopProduct($product_id);
        if (!$product->getId()) {
            $this->errors[] = [
                'id' => 'not_found',
                'text' => _w('Product not found.'),
            ];
            return;
        }
        $this->product_model = new shopProductModel();
        $this->checkRights($product_id);

        $this->validateData($product, $presentation_id, $sku_id, $field_id, $value);
        if (!$this->errors) {
            $product_data = $this->prepareFieldData($product, $sku_id, $field_id, $value);
            $this->saveProduct($product, $product_data);
            if (!$this->errors) {
                $this->response = [
                    'value' => $this->getSavedValue($value, $sku_id, $product, $field_id)
                ];
            }
        }
    }

    /**
     * @throws waRightsException
     * @return void
     */
    protected function checkRights($id)
    {
        if (!$id || !$this->product_model->checkRights($id)) {
            throw new waRightsException(_w("Access denied"));
        }
    }

    /**
     *
     * @param shopProduct $product
     * @param int $presentation_id
     * @param int $sku_id
     * @param string $field_id
     * @param mixed $value
     * @return void
     */
    protected function validateData($product, $presentation_id, $sku_id, $field_id, &$value)
    {
        if (!$product) {
            $this->errors = [
                'id' => 'product',
                'text' => _w('Product not found.'),
            ];
        }
        $skus = $product->skus;
        if ($sku_id && !isset($skus[$sku_id])) {
            $this->errors = [
                'id' => 'sku',
                'text' => _w('Product variant not found.'),
            ];
        }
        $presentation_model = new shopPresentationModel();
        $this->presentation = $presentation_model->select('id, `view`')->where('id = ?', $presentation_id)->fetchAssoc();
        if (!$this->presentation) {
            $this->errors = [
                'id' => 'presentation',
                'text' => _w('Saved view not found.'),
            ];
        } elseif ($this->presentation['view'] == shopPresentation::VIEW_THUMBS) {
            $this->errors = [
                'id' => 'presentation_view',
                'text' => _w('Editing is not available in the “Tiles” view mode.'),
            ];
        }
        if (!$this->errors) {
            // this fields that cannot be edited
            $blacklist = ['contact_id', 'image_id', 'image_filename', 'ext', 'min_price', 'max_price',
                'min_base_price', 'max_base_price', 'cross_selling', 'upselling', 'base_price_selectable',
                'compare_price_selectable', 'purchase_price_selectable', 'product_id', 'sort', 'primary_price',
                'dimension_id', 'file_name', 'file_size', 'file_description', 'virtual'];
            $columns_list = $this->getDividedColumnsList($field_id, $sku_id, $this->presentation['id']);
            if (!isset($columns_list[$field_id])) {
                $this->errors = [
                    'id' => 'column',
                    'text' => _w('Column not found.'),
                ];
            } elseif ($columns_list[$field_id]['editable'] === false || in_array($field_id, $blacklist)) {
                $this->errors = [
                    'id' => 'field_readonly',
                    'text' => _w('The column cannot be edited.'),
                ];
                return;
            }
        } else {
            return;
        }

        $stocks = shopHelper::getStocks();
        if (strpos($field_id, 'stocks_') === 0) {
            $stock_id = substr($field_id, 7);
            if (!isset($stocks[$stock_id])) {
                $this->errors = [
                    'id' => 'stock',
                    'text' => _w('Stock not found.'),
                ];
            }
        }

        $is_complex_product = $this->checkProductMode($product);
        if ($field_id == 'count' && ((!$is_complex_product && !$stocks && !$sku_id) || ($stocks && !$sku_id))) {
            $this->errors = [
                'id' => 'wrong_data',
                'text' => _w('Failed to change the value.'),
            ];
        }

        if ($field_id == 'status' && $product->sku_id == $sku_id && empty($value)) {
            $this->errors[] = [
                'id' => 'main_sku_visibility',
                'text' => _w('The main SKU cannot be hidden. Either select another main SKU or make the main SKU visible.'),
            ];
        }

        if ($field_id == 'sku_id') {
            if (!isset($skus[$value])) {
                $this->errors[] = [
                    'id' => 'wrong_data',
                    'text' => _w('Failed to update the visibility in the storefront and the availability for purchase.'),
                ];
            } elseif (empty($skus[$value]['status'])) {
                $this->errors[] = [
                    'id' => 'main_sku_visibility',
                    'text' => _w('Failed to change the main SKU because its visibility in the storefront is disabled.'),
                ];
            }
        }

        // Validate SKU prices. They must not exceed certain length
        if ($sku_id && in_array($field_id, ['price', 'purchase_price', 'compare_price'])) {
            $value = str_replace(',', '.', $value);
            if (strlen($value) > 0 && (!is_numeric($value) || strlen($value) > 16
                || $value > floatval('1' . str_repeat('0', 11)))
            ) {
                $this->errors[] = [
                    'id' => $field_id . '_error',
                    'name' => "product[skus][$sku_id][$field_id]",
                    'text' => _w('Invalid value'),
                ];
            }
        }
        if ($field_id == 'stock_base_ratio') {
            $max_number = 8;
            $min_number = 7;
            $changed_value = false;
            if (is_string($value) && strlen($value) === 0) {
                if ($sku_id) {
                    // sku value
                    $value = null;
                    $changed_value = true;
                } elseif (!isset($value)) {
                    // product value
                    $value = 1;
                    $changed_value = true;
                }
            }
            if (!$changed_value && ($value <= 0 || !is_numeric($value) || strlen($value) > 17
                || $value > floatval('1'.str_repeat('0', $max_number))
                || $value < floatval('0.'.str_repeat('0', $min_number).'1'))
            ) {
                $this->errors[] = [
                    'id' => $field_id . '_error',
                    'name' => "product[skus][$sku_id][$field_id]",
                    'text' => _w('Invalid value'),
                ];
            }
        }

        $count_status = 0;
        foreach ($skus as $sku) {
            if (!empty($sku['status'])) {
                $count_status++;
            }
        }
        if (!empty($skus) && empty($count_status)) {
            $this->errors[] = [
                'id' => 'main_sku_status',
                'text' => _w('At least one SKU must be visible in the storefront.'),
            ];
        }
    }

    /**
     * @param $field_id
     * @param $sku_id
     * @param $presentation_id
     * @return array
     * @throws waException
     */
    protected function getDividedColumnsList($field_id, $sku_id, $presentation_id)
    {
        static $columns_list = null;

        if ($columns_list === null) {
            $presentation = new shopPresentation($presentation_id);
            $columns_list = $presentation->getColumnsList();
            if ((!$sku_id && $field_id == 'sku_id') || ($sku_id && ($field_id == 'available' || $field_id == 'status'))) {
                $columns_list[$field_id] = $columns_list['visibility'];
            }
            unset($columns_list['visibility']);
        }

        return $columns_list;
    }

    /**
     * Formatting the data for the shopProduct class.
     * @param array
     * @return array
     */
    protected function prepareFieldData(shopProduct $product, $sku_id, $field_id, &$value)
    {
        $columns_list = $this->getDividedColumnsList($field_id, $sku_id, $this->presentation['id']);
        $updated_column = $columns_list[$field_id];
        $changed_column_value = [
            'skus' => $product->skus
        ];
        if (isset($updated_column['feature_id'])) {
            if ($value === null) {
                $value = '';
            }
            // remove nesting
            if (is_array($value) && 1 === count($value) && isset($value['value']) && '0' === $value['value']) {
                if ($sku_id) {
                    $changed_column_value['skus'][$sku_id]['features'][$updated_column['feature_code']] = $value['value'];
                } else {
                    $changed_column_value['features'][$updated_column['feature_code']] = $value['value'];
                }
            } elseif (substr($updated_column['type'], 0, 3) == '2d.' || substr($updated_column['type'], 0, 3) == '3d.') {
                $count_subfield = substr($updated_column['type'], 0, 1);
                $subfields_transferred = true;
                if (count($value) == $count_subfield) {
                    for ($i = 0; $i < $count_subfield; $i++) {
                        if (!isset($value[$updated_column['feature_code'] . '.' . $i])) {
                            $subfields_transferred = false;
                        }
                    }
                }
                if ($subfields_transferred) {
                    if ($sku_id) {
                        $changed_column_value['skus'][$sku_id]['features'] = $value;
                    } else {
                        $changed_column_value['features'] = $value;
                    }
                }
            } else {
                if (!is_array($value) || !isset($value[$updated_column['feature_code']])) {
                    $value = [
                        $updated_column['feature_code'] => $value
                    ];
                }
                if ($sku_id) {
                    if ($updated_column['multiple'] && $updated_column['available_for_sku']) {
                        $skus_features = $product->sku_features;
                        foreach ($skus_features as $id_sku => $sku_features) {
                            $sku_value = '';
                            if (isset($sku_features[$updated_column['feature_code']])) {
                                if (is_object($sku_features[$updated_column['feature_code']])) {
                                    $sku_value = $sku_features[$updated_column['feature_code']]->value;
                                } else {
                                    $sku_value = $sku_features[$updated_column['feature_code']];
                                }
                            }
                            $changed_column_value['skus'][$id_sku]['features'][$updated_column['feature_code']] = $sku_value;
                        }
                        $product_features_model = new shopProductFeaturesModel();
                        $product_features_model->deleteByField([
                            'product_id' => $product->getId(),
                            'sku_id' => null,
                            'feature_id' => $updated_column['feature_id'],
                        ]);
                    }
                    $changed_column_value['skus'][$sku_id]['features'] = $value;
                } else {
                    $changed_column_value['features'] = $value;
                }
            }
        } else {
            $params = $product->params;
            $is_complex_product = $this->checkProductMode($product);
            if ($sku_id && $updated_column['editing_rule'] != shopPresentation::EDITING_RULE_ONLY_PRODUCT) {
                $product_skus_model = new shopProductSkusModel();
                $skus_metadata = $product_skus_model->getMetadata();
                if (isset($skus_metadata[$field_id])) {
                    if ($field_id == 'count' && $value === '') {
                        $value = null;
                    }
                    $changed_column_value['skus'][$sku_id][$field_id] = $value;
                } elseif (isset($updated_column['stock_id'])) {
                    $changed_column_value['skus'][$sku_id]['stock'][$updated_column['stock_id']] = $value;
                }
            } elseif (!($updated_column['editing_rule'] == shopPresentation::EDITING_RULE_SIMPLE_MODE
                && $is_complex_product && $this->presentation['view'] == shopPresentation::VIEW_TABLE)
            ) {
                $product_metadata = $this->product_model->getMetadata();
                if (isset($product_metadata[$field_id])) {
                    if ($field_id == 'status' && !$sku_id) {
                        $value = $value > 0 ? 1 : 0;
                        unset(
                            $params['redirect_category_id'],
                            $params['redirect_code'],
                            $params['redirect_url'],
                            $params['redirect_type']
                        );
                    }
                    if ($field_id == 'category_id') {
                        $category_ids = array_keys($product->categories);
                        array_unshift($category_ids, $value, $product->category_id);
                        $changed_column_value['categories'] = array_unique(array_map('intval', $category_ids));
                    }

                    if ($field_id == 'sku_id' && $product->sku_id != $value) {
                        $product_data = $product->getData();
                        $product_data[$field_id] = $value;
                        shopProdSaveGeneralController::updateMainImage($product_data, $product->id, $product->type_id);
                    }

                    $changed_column_value[$field_id] = $value;
                }
            }

            if ($field_id == 'params') {
                $divided_params = explode("\n", $value);
                if (is_array($divided_params)) {
                    foreach ($divided_params as $param) {
                        $param_key_value = explode('=', $param, 2);
                        if (is_array($param_key_value) && count($param_key_value) == 2) {
                            $param_key = $param_key_value[0];
                            $param_value = trim($param_key_value[1]);
                            if (mb_strlen($param_key)) {
                                $params[$param_key] = $param_value;
                            }
                            // set to 1 if not empty; remove from database if empty
                            if ($param_key == 'multiple_sku') {
                                $params['multiple_sku'] = !empty($param_value) ? 1 : null;
                            }
                        }
                    }
                }
            }
            $params = array_filter($params, function($value) {
                return $value !== null;
            });
            $changed_column_value['params'] = $params;

            $is_changed_type = false;
            if ($field_id == 'type_id' && $product->type_id != $value) {
                $type_model = new shopTypeModel();
                $type = $type_model->getById($value);
                if ($type) {
                    $is_changed_type = true;
                    foreach (['order_multiplicity_factor', 'stock_unit_id', 'base_unit_id', 'stock_base_ratio', 'order_count_min', 'order_count_step'] as $field) {
                        $changed_column_value[$field] = $type[$field];
                        if ($field == 'stock_base_ratio' || $field == 'order_count_min' || $field == 'order_count_step') {
                            foreach ($changed_column_value['skus'] as &$sku) {
                                $sku[$field] = null;
                            }
                            unset($sku);
                        }
                    }
                }
            }

            if (in_array($field_id, ['currency', 'sku_id', 'stock_base_ratio']) || $is_changed_type) {
                $currency_model = new shopCurrencyModel();
                $primary_currency = wa('shop')->getConfig()->getCurrency();
                $from_currency = $field_id == 'currency' ? $changed_column_value['currency'] : $primary_currency;
                $price = [];
                $base_prices = [];
                $product_price = $product_compare_price = $product_base_price = 0;
                $product_stock_base_ratio = $is_changed_type ? $changed_column_value['stock_base_ratio'] : $product->stock_base_ratio;
                foreach ($changed_column_value['skus'] as &$sku) {
                    $sku_price = $sku['price'];
                    $price[] = $sku_price;
                    $stock_base_ratio = isset($sku['stock_base_ratio']) ? $sku['stock_base_ratio'] : $product_stock_base_ratio;
                    $base_price = 0;
                    if ($stock_base_ratio > 0) {
                        $base_price = $sku_price / $stock_base_ratio;
                    }
                    $base_price = min(99999999999.9999, max(0.0001, $base_price));
                    $base_prices[] = $base_price;
                    if ($product->sku_id == $sku['id']) {
                        $product_price = $sku['price'];
                        $product_compare_price = $sku['compare_price'];
                        $product_base_price = $base_price;
                    }

                    $sku['primary_price'] = $currency_model->convert($sku['primary_price'], $from_currency, $primary_currency);
                }
                unset($sku);

                if (!$price) {
                    $price[] = 0;
                }
                if (!$base_prices) {
                    $base_prices[] = 0;
                }
                $changed_column_value['min_price'] = $currency_model->convert(min($price), $from_currency, $primary_currency);
                $changed_column_value['max_price'] = $currency_model->convert(max($price), $from_currency, $primary_currency);
                $changed_column_value['base_price'] = $currency_model->convert($product_base_price, $from_currency, $primary_currency);
                $changed_column_value['min_base_price'] = $currency_model->convert(min($base_prices), $from_currency, $primary_currency);
                $changed_column_value['max_base_price'] = $currency_model->convert(max($base_prices), $from_currency, $primary_currency);
                $changed_column_value['price'] = $currency_model->convert($product_price, $from_currency, $primary_currency);
                if (isset($skus[$product['sku_id']]['compare_price'])) {
                    $changed_column_value['compare_price'] = $currency_model->convert($product_compare_price, $from_currency, $primary_currency);
                }
            }
        }

        return $changed_column_value;
    }

    /**
     * Saves to DB and returns shopProduct just saved.
     * If something goes wrong, writes errors into $this->errors and returns null.
     * @param shopProduct $product
     * @param array $product_data
     * @return shopProduct or null
     * @throws waException
     */
    protected function saveProduct(shopProduct $product, array $product_data)
    {
        $errors = null;
        try {
            // Save product
            if ($product->save($product_data, true, $errors)) {
                $this->logAction('product_edit', $product['id']);
            }
        } catch (Exception $ex) {
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

        if ($errors) {
            $this->errors[] = [
                'id' => "general",
                'text' => _w('Unable to save product.').' '.wa_dump_helper($errors),
            ];
        }

        return $product;
    }

    protected function getSavedValue($value, $sku_id, $product, $field_id)
    {
        $saved_value = $value;
        if (!$sku_id) {
            if (mb_strpos($field_id, 'feature_') === 0 && is_array($value)) {
                $features = $product->getFeatures();
                $code = explode('.', key($value))[0];
                if (isset($features[$code])) {
                    $feature = $features[$code];
                    if ($feature instanceof shopCompositeValue) {
                        $saved_value = [];
                        $count = count($value);
                        for ($i = 0; $i < $count; $i++) {
                            if (isset($feature[$i]) && is_object($feature[$i])) {
                                $composite_value = $feature[$i];
                                $saved_value[$code.$i] = ['value' => $this->getFeatureValue($composite_value)];
                                if ($i == 0 && $composite_value instanceof shopDimensionValue) {
                                    $saved_value[$code.$i]['unit'] = (string)$composite_value->unit;
                                }
                            }
                        }
                    } elseif (is_object($feature)) {
                        $saved_value = [
                            $code => [
                                'value' => [],
                            ],
                        ];
                        if ($feature instanceof shopRangeValue) {
                            $saved_value[$code]['value']['begin'] = $this->getFeatureValue($feature->begin);
                            $saved_value[$code]['value']['end'] = $this->getFeatureValue($feature->end);
                            if ($feature->end instanceof shopDimensionValue) {
                                $saved_value[$code]['unit'] = (string)$feature->end->unit;
                            }
                        } else {
                            $saved_value[$code]['value'] = $this->getFeatureValue($feature);
                            if ($feature instanceof shopColorValue) {
                                $saved_value[$code]['code'] = (string)$feature->code;
                            }
                            if ($feature instanceof shopDimensionValue) {
                                $saved_value[$code]['unit'] = (string)$feature->unit;
                            }
                        }
                    }
                }
            } elseif (isset($product[$field_id])) {
                $saved_value = $product[$field_id];
            }
        } else {
            if (mb_strpos($field_id, 'stocks_') === 0) {
                $stock_id = mb_substr($field_id, 7);
                if (isset($product['skus'][$sku_id]['stock'][$stock_id])) {
                    $saved_value = $product['skus'][$sku_id]['stock'][$stock_id];
                }
            } elseif (isset($product['skus'][$sku_id][$field_id])) {
                $saved_value = $product['skus'][$sku_id][$field_id];
            }
        }
        return $saved_value;
    }

    /**
     * @param $value
     * @return double
     */
    protected function getFeatureValue($value)
    {
        if ($value instanceof shopDateValue) {
            return shopDateValue::timestampToDate($value->timestamp);
        } elseif (is_object($value)) {
            return (string)$value->value;
        }
        return (string)$value;
    }

    protected function checkProductMode($product)
    {
        static $is_complex_product;
        if ($is_complex_product === null) {
            $features_selectable_model = new shopProductFeaturesSelectableModel();
            $selected_selectable_feature_ids = $features_selectable_model->getProductFeatureIds($product['id']);
            $product_features_model = new shopProductFeaturesModel();
            $has_features_values = $product_features_model->checkProductFeaturesValues($product['id'], $product['type_id']);
            $is_complex_product = (count($product['skus']) > 1 || $has_features_values
                || ifempty($product, 'params', 'multiple_sku', null) || $selected_selectable_feature_ids);
        }
        return $is_complex_product;
    }
}
