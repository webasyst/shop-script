<?php
/**
 *
 */
class shopProdSaveRelatedController extends waJsonController
{
    protected $product_id;

    public function execute()
    {
        $data = waRequest::post('product', array(), waRequest::TYPE_ARRAY);

        $this->product_id = (int)$data['id'];
        $product = new shopProduct($this->product_id);
        foreach (array('cross_selling', 'upselling') as $type) {
            if (isset($data[$type])) {
                $value = $data[$type]['value'];
                $product->save(array($type => $value));
                if (isset($data[$type]['products'])) {
                    $this->updateRelatedProducts($type, $data[$type]['products']);
                } else {
                    $related_model = new shopProductRelatedModel();
                    $related_model->deleteByField(array(
                        'product_id' => $this->product_id,
                        'type' => $type,
                    ));
                }
            }
        }
    }

    protected function updateRelatedProducts($type, $products)
    {
        $related_model = new shopProductRelatedModel();
        $related_product_ids = array_map('intval', (array)$products);
        foreach ($related_product_ids as $product_id) {
            $related_model->replace(array(
                'product_id' => $this->product_id,
                'type' => $type,
                'related_product_id' => $product_id,
            ));
        }
        $related_model->deleteAllProductsExcept($this->product_id, $type, $related_product_ids);
    }
}