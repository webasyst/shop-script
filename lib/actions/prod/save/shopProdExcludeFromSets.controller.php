<?php

class shopProdExcludeFromSetsController extends waJsonController
{
    public function execute()
    {
        if (!$this->getUser()->getRights('shop', 'setscategories')) {
            $this->errors = [
                'id' => 'access_denied',
                'text' => _w('Access denied'),
            ];
        }

        $product_ids = waRequest::post('product_ids', [], waRequest::TYPE_ARRAY_INT);
        $set_ids = waRequest::post('set_ids', [], waRequest::TYPE_ARRAY);
        $presentation_id = waRequest::post('presentation_id', null, waRequest::TYPE_INT);
        $this->validateData($product_ids, $set_ids, $presentation_id);
        if (!$this->errors) {
            if ($presentation_id) {
                $presentation = new shopPresentation($presentation_id, true);
                $options = [];
                if ($presentation->getFilterId() > 0) {
                    $options['prepare_filter'] = $presentation->getFilterId();
                    $options['exclude_products'] = $product_ids;
                }
                $collection = new shopProductsCollection('', $options);
                $product_ids = $presentation->getProducts($collection, [
                    'fields' => ['id'],
                    'offset' => max(0, waRequest::post('offset', 0, waRequest::TYPE_INT)),
                ]);
                $product_ids = array_keys($product_ids);
            }

            if ($product_ids) {
                $this->exclude($product_ids, $set_ids);
            }

            $this->response = [
                'product_ids' => $product_ids,
                'set_ids' => $set_ids
            ];
        }
    }

    protected function validateData($product_ids, $set_ids, $presentation_id)
    {
        if (!$set_ids) {
            $this->errors = [
                'id' => 'empty_sets',
                'text' => _w('Product sets not found.')
            ];
        } elseif (!$presentation_id && !$product_ids) {
            $this->errors = [
                'id' => 'empty_products',
                'text' => _w('Products not specified.')
            ];
        }
    }

    protected function exclude($product_ids, $set_ids)
    {
        $set_products_model = new shopSetProductsModel();
        $set_products_model->deleteProducts($set_ids, $product_ids);
        $set_model = new shopSetModel();
        $set_model->recount($set_ids);
    }
}
