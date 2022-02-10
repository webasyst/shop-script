<?php
/**
 * Accepts POST data from General tab in product editor.
 */
class shopProdSaveGeneralController extends waJsonController
{
    public function execute()
    {
        $product_raw_data = waRequest::post('product', null, waRequest::TYPE_ARRAY);
        $product_id = ifempty($product_raw_data, 'id', null);
        $product = new shopProduct($product_id);

        $product_data = $this->prepareProductData($product_raw_data);
        $this->checkRights($product_id, $product_data);

        $backend_prod_pre_save = $this->throwPreSaveEvent($product, $product_data);
        foreach ($backend_prod_pre_save as $plugin_id => $result) {
            if ($result['errors']) {
                $this->errors = array_merge($this->errors, $result['errors']);
            }
        }

        if (!$this->errors) {
            /** @var shopProduct $product */
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
     * @param array
     * @return array
     */
    protected function prepareProductData(array $product_raw_data)
    {
        $product_data = $product_raw_data;

        if (isset($product_data['status'])) {
            if ($product_data['status'] < 0) {
                $product_data['status'] = -1;
                switch ($product_data['params']['redirect_type']) {
                    case '404':
                        $product_data['params']['redirect_category_id'] = null;
                        $product_data['params']['redirect_code'] = null;
                        $product_data['params']['redirect_url'] = null;
                        break;
                    case 'home':
                        $product_data['params']['redirect_category_id'] = null;
                        $product_data['params']['redirect_url'] = null;
                        break;
                    case 'category':
                        $product_data['params']['redirect_url'] = null;
                        $product_data['params']['redirect_category_id'] = $product_data['category_id'];
                        break;
                    case 'url':
                        $product_data['params']['redirect_category_id'] = null;
                        if (empty($product_data['params']['redirect_url'])) {
                            $this->errors[] = [
                                'name' => 'product[params][redirect_url]',
                                'text' => _w('Enter a redirect URL.'),
                            ];
                        }
                        break;
                    default:
                        // don't change anything
                        unset(
                            $product_data['params']['redirect_category_id'],
                            $product_data['params']['redirect_code'],
                            $product_data['params']['redirect_url']
                        );
                        break;
                }
            } else {
                $product_data['status'] = $product_data['status'] > 0 ? 1 : 0;
                unset(
                    $product_data['params']['redirect_category_id'],
                    $product_data['params']['redirect_code'],
                    $product_data['params']['redirect_url']
                );
            }
        }
        unset($product_data['params']['redirect_type']);

        if (isset($product_data['params']['multiple_sku'])) {
            // set to 1 if not empty; remove from database if empty
            $product_data['params']['multiple_sku'] = !empty($product_data['params']['multiple_sku']) ? 1 : null;
        }

        if (isset($product_data['category_id'])) {
            if (!isset($product_data['categories']) || !is_array($product_data['categories'])) {
                $product_data['categories'] = [];
            }
            array_unshift($product_data['categories'], $product_data['category_id']);
        } else {
            unset($product_data['categories']);
            unset($product_data['category_id']);
        }

        if (empty($this->errors) && isset($product_data['skus']) && is_array($product_data['skus'])) {
            // Validate SKU prices. They must not exceed certain length
            foreach ($product_data['skus'] as $sku_id => $sku) {
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
                            $product_data['skus'][$sku_id][$sku_field] = null;
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
        }

        shopProdSaveGeneralController::updateMainImage($product_data, $product_data['id'], $product_data['type_id']);

        // shopProduct does not allow to save count_denominator. Only indirectly through order_multiplicity_factor.
        if (!isset($product_data['order_multiplicity_factor'])) {
            if (isset($product_data['count_denominator'])) {
                $product_data['order_multiplicity_factor'] = 1 / $product_data['count_denominator'];
            }
        }
        foreach (['stock_base_ratio' => 16, 'order_count_min' => 15, 'order_count_step' => 15] as $product_field => $length) {
            $max_number = $product_field == 'stock_base_ratio' ? 8 : 12;
            $min_number = $product_field == 'stock_base_ratio' ? 7 : 2;
            if (!isset($product_data[$product_field])
                || (is_string($product_data[$product_field]) && strlen($product_data[$product_field]) === 0)
            ) {
                $product_data[$product_field] = 1;
            } elseif ($product_data[$product_field] <= 0 || !is_numeric($product_data[$product_field]) || strlen($product_data[$product_field]) > $length + 1
                || $product_data[$product_field] > floatval('1'.str_repeat('0', $max_number))
                || $product_data[$product_field] < floatval('0.'.str_repeat('0', $min_number).'1')
            ) {
                $this->errors[] = [
                    'id' => "product_{$product_field}_error",
                    'name' => "product[$product_field]",
                    'text' => _w('Invalid value'),
                ];
            }
        }
        unset($product_data['count_denominator']);

        return $product_data;
    }

    /**
     * @param shopProduct $product
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
            'content_id' => 'general',
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
            'content_id' => 'general',
        ];

        wa('shop')->event('backend_prod_save', $params);
    }

    /**
     * Takes product id (null for new products) and data prepared by $this->prepareProductData()
     * Saves to DB and returns shopProduct just saved.
     * If something goes wrong, writes errors into $this->errors and returns null.
     * @param shopProduct $product
     * @param array $product_data
     * @return shopProduct or null
     * @throws waException
     */
    protected function saveProduct($product, array $product_data)
    {
        if (isset($product_data['params']) && is_array($product_data['params'])) {
            // should not remove params we don't explicitly set
            $product_data['params'] += $product['params'];
            $product_data['params'] = array_filter($product_data['params'], function($value) {
                return $value !== null;
            });
        }

        $product->save($product_data, true, $errors);
        if (!$errors) {
            if ($product_data['id']) {
                $this->logAction('product_edit', $product_data['id']);
            } else {
                $this->logAction('product_add', $product->getId());
                if ($product->type) {
                    wa()->getUser()->setSettings('shop', 'last_type_id', $product->type);
                }
            }
        } else {
            // !!! TODO format errors properly, if any happened
            $this->errors[] = [
                'id' => "general",
                'text' => _w('Unable to save product.').' '.wa_dump_helper($errors),
            ];
            return null;
        }

        $this->throwSaveEvent($product, $product_data);

        return $product;
    }

    /**
     *
     * @param shopProduct $product
     * @return array
     */
    protected function prepareResponse(shopProduct $product)
    {

        $product_model = new shopProductModel();

        // Read all (cached) data from shopProduct, keep only what's stored in shop_product table
        $response = array_intersect_key($product->getData(), $product_model->getEmptyRow());

        $response['categories'] = $product['categories'];
        $response['tags'] = $product['tags'];

        return $response;
    }

    /**
     * @throws waRightsException
     */
    protected function checkRights($id, $data)
    {
        $product_model = new shopProductModel();
        if ($id) {
            if (!$product_model->checkRights($id)) {
                throw new waRightsException(_w("Access denied"));
            }
        } else {
            if (!$product_model->checkRights($data)) {
                throw new waRightsException(_w("Access denied"));
            }
        }
    }

    public static function updateMainImage(&$product_data, $product_id, $type_id)
    {
        // Image of the main SKU must also be set as main product image.
        $sku = null;
        if (isset($product_data['sku_id']) && isset($product_data['skus'][$product_data['sku_id']])) {
            $sku = $product_data['skus'][$product_data['sku_id']];
        } elseif (isset($product_data['skus']) && count($product_data['skus']) == 1) {
            $sku = reset($product_data['skus']);
        }
        $is_simple_product = true;
        $has_features_values = shopProdSkuAction::checkProductFeaturesValues($product_id, $type_id);
        if ($sku && (count($product_data['skus']) > 1 || $has_features_values
                || ifempty($product_data, 'params', 'multiple_sku', null)
                || !empty($product_data['features_selectable_ids']))
        ) {
            $is_simple_product = false;
        }
        if (!empty($sku['image_id'])) {
            $product_images_model = new shopProductImagesModel();
            $image = $product_images_model->getById($sku['image_id']);
            if ($image) {
                $product_data = array_merge($product_data, [
                    'image_filename' => $image['filename'],
                    'image_id' => $image['id'],
                    'ext' => $image['ext'],
                ]);
                if ($is_simple_product) {
                    $product_images_model->updateByField([
                        'product_id' => $product_id,
                        'sort' => 0
                    ], ['sort' => $image['sort']]);
                    $product_images_model->updateByField([
                        'product_id' => $product_id,
                        'id' => $image['id']
                    ], ['sort' => 0]);
                }
            }
        }
    }
}
