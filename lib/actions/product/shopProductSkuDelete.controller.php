<?php
class shopProductSkuDeleteController extends waJsonController
{
    public function execute()
    {
        $sku_id = waRequest::post('sku_id', 0, waRequest::TYPE_INT);
        if (!$sku_id) {
            $this->setError(_w("Deletion error: unknown SKU."));
        }

        $product_id = waRequest::post('product_id', 0, waRequest::TYPE_INT);
        if (!$product_id) {
            $this->setError(_w("Error when delete: unknown product"));
        }

        $product_skus_model = new shopProductSkusModel();
        if (!$product_skus_model->delete($sku_id)) {
            $this->setError(_w("Хотя бы один артикул должен быть виден на витрине."));
        }

        $product_model = new shopProductModel();
        $product = $product_model->getById($product_id);
        if (!$product) {
            $this->setError(_w("Error when delete"));
        }
        $this->response = $product;
    }
}
