<?php

class shopProdSaveSeoController extends waJsonController
{
    /**
     * @throws waException
     */
    public function execute()
    {
        $product_data = waRequest::post('product', [], 'array');

        $product = new shopProduct($product_data['id']);
        if (!$product->getId()) {
            $this->errors[] = [
                'id' => 'not_found',
                'text' => _w('Product not found.'),
            ];
            return;
        }
        // check rights
        $product_model = new shopProductModel();
        if (!$product_model->checkRights($product_data['id'])) {
            throw new waException(_w("Access denied"), 403);
        }

        $backend_prod_pre_save = $this->throwPreSaveEvent($product, $product_data);
        foreach ($backend_prod_pre_save as $plugin_id => $result) {
            if ($result['errors']) {
                $this->errors = array_merge($this->errors, $result['errors']);
            }
        }

        if (!$this->errors) {
            try {
                $product->save($product_data, true, $errors);
            } catch (waDbException $dbe) {
                if ($dbe->getCode() === 1366) {
                    $this->errors[] = ['text' => _w('Enable the emoji support in system settings.')];
                } else {
                    throw $dbe;
                }
            }
            if (!$errors) {
                if (!$this->errors) {
                    $this->logAction('product_edit', $product_data['id']);
                    $this->response['product_id'] = $product->getId();
                }
            } else {
                // !!! TODO format errors properly, if any happened
                $this->errors[] = [
                    'id' => "seo",
                    'text' => _w('Unable to save product.').' '.wa_dump_helper($errors),
                ];
            }
        }

        $this->throwSaveEvent($product, $product_data);
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
         * @since 8.19.0
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
            'content_id' => 'seo',
        ];

        $backend_prod_pre_save = wa('shop')->event('backend_prod_presave', $params);

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

    /**
     * @param $product
     * @param array $data
     * @throws waException
     */
    protected function throwSaveEvent($product, array $data)
    {
        /**
         * @event backend_prod_save
         * @since 8.19.0
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
            'content_id' => 'seo',
        ];

        wa('shop')->event('backend_prod_save', $params);
    }
}
