<?php
/**
 *
 */
class shopProdSaveRelatedController extends waJsonController
{
    protected $product_id;

    protected $model;

    public function execute()
    {
        $data = waRequest::post('product', [], waRequest::TYPE_ARRAY);

        $this->product_id = (int)$data['id'];
        $product = new shopProduct($this->product_id);
        if (!$product->getId()) {
            $this->errors[] = [
                'id' => 'not_found',
                'text' => _w('Product not found.'),
            ];
            return;
        }
        foreach (['cross_selling', 'upselling'] as $type) {
            if (isset($data[$type])) {
                $value = $data[$type]['value'];
                $product->save([$type => $value]);
                $this->model = new shopProductRelatedModel();
                $this->model->deleteByField([
                    'product_id' => $this->product_id,
                    'type' => $type,
                ]);
                if (isset($data[$type]['products'])) {
                    $this->updateRelatedProducts($type, $data[$type]['products']);
                }
            }
        }
        $this->logAction('product_edit', $this->product_id);

        $this->response['product_id'] = $product->getId();
    }

    protected function updateRelatedProducts($type, $products)
    {
        $related_product_ids = array_map('intval', (array)$products);
        foreach ($related_product_ids as $sort => $product_id) {
            $this->model->replace([
                'product_id' => $this->product_id,
                'type' => $type,
                'related_product_id' => $product_id,
                'sort' => $sort,
            ]);
        }
    }
}
