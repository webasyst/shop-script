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
        if ($product_id !== null && !$product->getId()) {
            $this->errors[] = [
                'id' => 'not_found',
                'text' => _w('Product not found.'),
            ];
            return;
        }

        $product_data = $this->prepareProductData($product_raw_data, $product);
        $this->checkRights($product_id, $product_data);
        if (!$this->errors) {
            shopProdSaveGeneralController::updateMainImage($product_data, $product_data['id'], $product_data['type_id']);
        }
        $backend_prod_pre_save = $this->throwPreSaveEvent($product, $product_data);
        foreach ($backend_prod_pre_save as $plugin_id => $result) {
            if ($result['errors']) {
                $this->errors = array_merge($this->errors, $result['errors']);
            }
        }

        if (!$this->errors) {
            /** @var shopProduct $product */
            try {
                $product = $this->saveProduct($product, $product_data);
            } catch (waDbException $dbe) {
                $ex_code = $dbe->getCode();
                if (in_array($ex_code, [1267, 1366])) {
                    $this->errors[] = ['text' => _w('Enable the emoji support in system settings.')];
                } else {
                    throw $dbe;
                }
            }
        }

        if (!$this->errors) {
            $this->response = $this->prepareResponse($product);
        }
    }

    /**
     * Takes data from POST and prepares format suitable as input for for shopProduct class.
     * If something gets wrong, writes errors into $this->errors.
     *
     * @param array $product_raw_data
     * @param shopProduct $product
     * @return array
     */
    protected function prepareProductData(array $product_raw_data, $product)
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
                        if ($product->type_id != $product_raw_data['type_id']) {
                            $product_data['skus'][$sku_id][$sku_field] = null;
                        } else {
                            $max_number = $sku_field == 'stock_base_ratio' ? 8 : 12;
                            $min_number = $sku_field == 'stock_base_ratio' ? 7 : 2;
                            if (is_string($sku[$sku_field]) && strlen($sku[$sku_field]) === 0) {
                                $product_data['skus'][$sku_id][$sku_field] = null;
                            } elseif ($sku[$sku_field] <= 0 || !is_numeric($sku[$sku_field]) || strlen($sku[$sku_field]) > $length + 1
                                || $sku[$sku_field] > floatval('1' . str_repeat('0', $max_number))
                                || $sku[$sku_field] < floatval('0.' . str_repeat('0', $min_number) . '1')
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
        }

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

    /**
     * @param $product_data
     * @param int $product_id
     * @param int $type_id
     * @return void
     * @throws waDbException
     */
    public static function updateMainImage(&$product_data, $product_id, $type_id)
    {
        if ($product_id > 0) {
            $original_product = new shopProduct($product_id);
            $features_selectable_model = new shopProductFeaturesSelectableModel();
            $is_simple_product = self::isSimpleProduct($product_data, $product_id, $type_id);
            $original_product_data = [
                'skus' => $original_product->getSkus(),
                'params' => $original_product->params,
                'features_selectable_ids' => $features_selectable_model->getByProduct($product_id)
            ];
            $is_simple_product_before_save = self::isSimpleProduct($original_product_data, $product_id, $original_product->type_id);

            $product_images_model = new shopProductImagesModel();
            $image = null;
            if ($is_simple_product) {
                if ($is_simple_product_before_save) {
                    $main_sku = reset($product_data['skus']);
                    if (!empty($main_sku['image_id'])) {
                        $image = $product_images_model->getById($main_sku['image_id']);
                        if ($image) {
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
                } else {
                    $image = $product_images_model->select('id, filename, ext')->where('product_id = ?', (int)$product_id)->order('sort')->limit(1)->fetchAssoc();
                }
                foreach ($product_data['skus'] as $sku_id => $sku_data) {
                    if (isset($sku_data['image_id'])) {
                        $product_data['skus'][$sku_id]['image_id'] = null;
                    }
                }
            } else {
                $image = $product_images_model->select('id, filename, ext')->where('product_id = ?', (int)$product_id)->order('sort')->limit(1)->fetchAssoc();
            }

            if ($image) {
                $product_data = array_merge($product_data, [
                    'image_filename' => $image['filename'],
                    'image_id' => $image['id'],
                    'ext' => $image['ext'],
                ]);
            }
        }
    }

    protected static function isSimpleProduct($product_data, $product_id, $type_id)
    {
        $is_simple_product = true;
        $product_features_model = new shopProductFeaturesModel();
        $has_features_values = $product_features_model->checkProductFeaturesValues($product_id, $type_id);
        $count_skus = 0;

        if (!empty($product_data['skus']) && is_array($product_data['skus'])) {
            foreach ($product_data['skus'] as $sku_data) {
                if (!empty($sku_data)) {
                    $count_skus++;
                }
            }
        }
        if ($count_skus > 1 || $has_features_values || ifempty($product_data, 'params', 'multiple_sku', null)
            || !empty($product_data['features_selectable_ids'])
        ) {
            $is_simple_product = false;
        }

        return $is_simple_product;
    }
}
