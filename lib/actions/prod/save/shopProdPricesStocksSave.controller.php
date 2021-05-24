<?php

/**
 * Class shopProdPricesStocksSaveController
 *
 * Контроллер для сохранения количества товара на складах
 */
class shopProdPricesStocksSaveController extends waJsonController
{
    public function execute()
    {
        $product_id = waRequest::request('product_id', null, waRequest::TYPE_INT);
        $sku_id     = waRequest::request('sku_id', null, waRequest::TYPE_INT);
        $stocks     = waRequest::post('stocks', null);
        if (empty($stocks) || !is_array($stocks)) {
            $count  = waRequest::post('count', null, waRequest::TYPE_STRING_TRIM);
            $stocks = [$count];
        }
        if (empty($product_id) || empty($sku_id)) {
            throw new waException(_w('Not found'), 404);
        }
        $product_model = new shopProductModel();
        if (!$product_model->checkRights($product_id)) {
            throw new waRightsException(_w('Access denied'));
        }

        $product = new shopProduct($product_id);
        $product_skus_model = new shopProductSkusModel();
        $result = $product_skus_model->setData($product, [$sku_id => ['stock' => $stocks]]);

        if (empty($result) && empty($result[$sku_id])) {
            $this->errors[] = _w('Saving has failed.');
        } else {
            $this->response = $result;
        }
    }
}
