<?php

class shopProdSetSortController extends waJsonController
{
    public function execute()
    {
        $id = waRequest::post('set_id', null, waRequest::TYPE_STRING_TRIM);
        $sort_products = waRequest::post('sort_products', null, waRequest::TYPE_STRING_TRIM);

        $this->validateData($sort_products);

        $this->save($id, $sort_products);
    }

    protected function validateData(&$sort_products)
    {
        $sort_options = shopSetModel::getSortProductsOptions();
        if (!in_array($sort_products, array_column($sort_options, 'value'))) {
            $sort_products = null;
        }
    }

    /**
     * @param string $id
     * @param string $sort_products
     */
    public function save($id, $sort_products)
    {
        $set_model = new shopSetModel();
        $result = $set_model->updateById($id, ['sort_products' => $sort_products]);
        if ($result) {
            $this->response['sort_products'] = $sort_products;
        } else {
            $this->errors = [
                'id' => 'failed_update',
                'text' => _w('Failed to update sorting.')
            ];
        }
    }
}
