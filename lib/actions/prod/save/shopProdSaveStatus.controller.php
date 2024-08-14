<?php

class shopProdSaveStatusController extends waJsonController
{
    public function execute()
    {
        $product_id = waRequest::post('product_id', null, waRequest::TYPE_INT);
        $product_data = waRequest::post('product_data', [], waRequest::TYPE_ARRAY);
        $product = new shopProduct($product_id);
        if (!$product->getId()) {
            $this->errors[] = [
                'id' => 'not_found',
                'text' => _w('Product not found.'),
            ];
        }
        $product_data = $this->prepareProductData($product, $product_data);
        if (!$this->errors) {
            $this->saveProduct($product, $product_data);
        }
    }

    /**
     * @param shopProduct $product
     * @param array $product_data
     * @return array
     */
    protected function prepareProductData($product, $product_data)
    {
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
                        $product_data['params']['redirect_category_id'] = $product->category_id;
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

        if (isset($product_data['params']) && is_array($product_data['params'])) {
            // should not remove params we don't explicitly set
            $product_data['params'] += $product->params;
            $product_data['params'] = array_filter($product_data['params'], function($value) {
                return $value !== null;
            });
        }

        return $product_data;
    }

    /**
     * @param shopProduct $product
     * @param array $product_data
     */
    protected function saveProduct($product, $product_data)
    {
        $errors = [];
        $product->save($product_data, true, $errors);
        if (!$errors) {
            $this->logAction('product_edit', $product->getId());
        } else {
            $this->errors[] = [
                'id' => 'general',
                'text' => _w('Unable to save product.'),
            ];
        }
    }
}
