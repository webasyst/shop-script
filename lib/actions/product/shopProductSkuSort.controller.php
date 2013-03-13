<?php
class shopProductSkuSortController extends waJsonController
{
    public function execute()
    {
        $sku_id = waRequest::post('sku_id', 0, waRequest::TYPE_INT);
        $product_id = waRequest::post('product_id', 0, waRequest::TYPE_INT);
        $after_id = waRequest::post('after_id', 0, waRequest::TYPE_INT);

        if (!$sku_id) {
            $this->setError(_w("Error when delete: unknown sku"));
        } elseif (!$product_id) {
            $this->setError(_w("Error when delete: unknown product"));
        } else {
            try {
                $product_skus_model = new shopProductSkusModel();
                $product_skus_model->move($sku_id, $after_id, $product_id);
            } catch (waException $e) {
                $this->setError($e->getMessage());
            }
        }
    }
}
