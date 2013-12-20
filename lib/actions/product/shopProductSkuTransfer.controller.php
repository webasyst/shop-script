<?php

class shopProductSkuTransferController extends waJsonController
{
    public function execute() {
        $sku_id = waRequest::post('sku_id', 0, waRequest::TYPE_INT);
        $src_stock = waRequest::post('src_stock', 0, waRequest::TYPE_INT);
        $dst_stock = waRequest::post('dst_stock', 0, waRequest::TYPE_INT);
        $count = waRequest::post('count', 0, waRequest::TYPE_INT);

        if (
            $src_stock == $dst_stock ||
            !$src_stock ||
            !$dst_stock ||
            !$count
        ) {
            $this->errors[] = _w("Error when transfer");
            return;
        }

        $product_skus_model = new shopProductSkusModel();
        shopProductStocksLogModel::setContext(
                shopProductStocksLogModel::TYPE_STOCK
        );
        if (!$product_skus_model->transfer($sku_id, $count, $src_stock, $dst_stock)) {
            $this->errors[] = _w("Error when transfer");
            return;
        }
        
        shopProductStocksLogModel::clearContext();

        $sku = $product_skus_model->getById($sku_id);

        /*
        $product_stocks_model = new shopProductStocksModel();
        $data = $product_stocks_model->getStocksOfProduct($sku['product_id'], array($src_stock, $dst_stock), 'sku.count DESC');

        foreach ($data as &$stock) {
            foreach ($stock as &$stock_sku) {
                $stock_sku['icon'] = shopHelper::getStockCountIcon($stock_sku['count']);
            }
        }
        unset($stock, $stock_sku);
        */

        $stock_skus = array();

        $product_model = new shopProductModel();
        $data = $product_model->getProductStocksByProductId($sku['product_id']);
        if (isset($data[$sku['product_id']])) {
            $data = $data[$sku['product_id']];

            if (isset($data['stocks'][$src_stock])) {
                $stock_skus[$src_stock] = array();
                foreach ($data['stocks'][$src_stock] as $stock_sku) {
                    $stock_sku['icon'] = shopHelper::getStockCountIcon($stock_sku['count'], $src_stock);
                    $stock_skus[$src_stock][] = $stock_sku;
                }
            }
            if (isset($data['stocks'][$dst_stock])) {
                $stock_skus[$dst_stock] = array();
                foreach ($data['stocks'][$dst_stock] as $stock_sku) {
                    $stock_sku['icon'] = shopHelper::getStockCountIcon($stock_sku['count'], $dst_stock);
                    $stock_skus[$dst_stock][] = $stock_sku;
                }
            }
        }

        $this->response = array(
            'stocks'     => $stock_skus ? $stock_skus : new stdClass(), /* { } */
            'product_id' => $sku['product_id']
        );
    }
}