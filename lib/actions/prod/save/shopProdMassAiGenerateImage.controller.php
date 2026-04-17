<?php

class shopProdMassAiGenerateImageController extends waJsonController
{
    public function execute()
    {
        $product_ids = waRequest::post('product_ids', [], waRequest::TYPE_ARRAY_INT);
        $product_ids = array_values(array_unique(array_filter(array_map('intval', $product_ids))));

        if (!$product_ids) {
            $this->errors[] = [
                'id' => 'product_ids_required',
                'text' => _w('No products selected.'),
            ];
            return;
        }

        $product_model = new shopProductModel();
        $products = [];

        foreach ($product_ids as $product_id) {
            $product = new shopProduct($product_id);
            if (!$product['id']) {
                continue;
            }

            if (!$product_model->checkRights($product)) {
                throw new waException(_w('Access denied'), 403);
            }

            $products[] = shopProdMediaAction::formatProductForImageDialog($product);
        }

        if (!$products) {
            $this->errors[] = [
                'id' => 'products_not_found',
                'text' => _w('Products not found.'),
            ];
            return;
        }

        $this->response = [
            'products' => $products,
        ];
    }
}
