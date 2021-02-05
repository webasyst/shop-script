<?php

class shopProdSaveSeoController extends waJsonController
{
    public function execute()
    {
        $product_data = waRequest::post('product', [], 'array');

        // check rights
        $product_model = new shopProductModel();
        if (!$product_model->checkRights($product_data['id'])) {
            throw new waException(_w("Access denied"), 403);
        }

        $product = new shopProduct($product_data['id']);
        $product->save($product_data, true, $errors);
        if (!$errors) {
            $this->logAction('product_edit', $product_data['id']);
            $this->response = array();
        } else {
            // !!! TODO format errors properly, if any happened
            $this->errors[] = [
                'id' => "seo",
                'text' => _w('Unable to save product.').' '.wa_dump_helper($errors),
            ];
        }
    }
}