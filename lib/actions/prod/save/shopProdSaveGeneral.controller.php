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

        // When product only has a single SKU, and this SKU has an image,
        // then same image must also be set as main product image.
        if (isset($product_data['skus']) && count($product_data['skus']) == 1) {
            $sku = reset($product_data['skus']);
            if (!empty($sku['image_id'])) {
                $product_images_model = new shopProductImagesModel();
                $image = $product_images_model->getById($sku['image_id']);
                if ($image) {
                    $product_data += [
                        'image_filename' => $image['filename'],
                        'image_id' => $image['id'],
                        'ext' => $image['ext'],
                    ];
                }
            }
        }

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
        if ($errors) {
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
}
