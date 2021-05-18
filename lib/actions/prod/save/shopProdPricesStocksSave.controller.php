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
        $product_id = waRequest::post('product_id', null, waRequest::TYPE_INT);
        $sku_id     = waRequest::post('sku_id', null, waRequest::TYPE_INT);
        $stocks     = waRequest::post('stocks', [], waRequest::TYPE_ARRAY);

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
            $this->errors[] = _w('Не удалось сохранить');
        } else {
            $this->response = $result;
        }
    }
}