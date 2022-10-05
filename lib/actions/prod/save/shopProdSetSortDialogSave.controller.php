<?php

class shopProdSetSortDialogSaveController extends waJsonController
{
    public function execute()
    {
        $set_id = waRequest::post('set_id', null, waRequest::TYPE_STRING_TRIM);
        $sort_products = waRequest::post('sort_products', null, waRequest::TYPE_STRING_TRIM);

        $this->validateData($set_id, $sort_products);
        if (!$this->errors) {
            if ($sort_products) {
                $products = $this->getSetProducts($set_id, $sort_products);
            } else {
                $products = waRequest::post('products', [], waRequest::TYPE_ARRAY_INT);
            }
            if (empty($products)) {
                $this->errors = [
                    'id' => 'empty_products',
                    'text' => _w('Products not specified.')
                ];
            }
            if (!$this->errors) {
                $set_products_model = new shopSetProductsModel();
                $set_products_model->clearSet($set_id);
                $response = $set_products_model->add($products, $set_id);
                if ($response !== false) {
                    $this->response['sort_products_changed'] = $products;
                } else {
                    $this->errors = [
                        'id' => 'update_fail',
                        'text' => _w('Failed to update the set.')
                    ];
                }
            }
        }
    }

    protected function validateData($set_id, $sort_products)
    {
        if ($sort_products) {
            $correct_sort = preg_match('/(name|price)\s(ASC|DESC)/', $sort_products);
            if (empty($correct_sort)) {
                $this->errors = [
                    'id' => 'incorrect_sort',
                    'text' => _w('No sort order specified.')
                ];
            }
        }

        if (!$this->errors) {
            if (mb_strlen($set_id)) {
                $set_model = new shopSetModel();
                $set = $set_model->select('id')->where('id = ?', $set_id)->fetchField('id');
                if ($set) {
                    return;
                }
            }
            $this->errors = [
                'id' => 'not_found',
                'text' => _w('The set to update was not found.')
            ];
        }
    }

    protected function getSetProducts($set_id, $sort_products)
    {
        $set_products_model = new shopSetProductsModel();
        $set_products = $set_products_model->select('product_id')->where('set_id = ?', $set_id)->fetchAll('product_id');
        $products = [];
        if ($set_products) {
            $product_model = new shopProductModel();
            $products = $product_model->select('id')->where('id IN (?)', [array_keys($set_products)])->order($sort_products)->fetchAll('id');
        }
        return array_keys($products);
    }
}
