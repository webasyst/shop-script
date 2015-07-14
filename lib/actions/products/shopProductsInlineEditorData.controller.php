<?php
/** Load data required for inline editor in product lists. */
class shopProductsInlineEditorDataController extends waJsonController
{
    public function execute()
    {
        $ids = waRequest::post('ids', array(), 'array_int');

        $this->response = array();

        // Prices
        $product_skus_model = new shopProductSkusModel();
        foreach($product_skus_model->getByField('product_id', $ids, true) as $sku) {
            $this->response[$sku['product_id']]['price'] = $sku['price'];
            $this->response[$sku['product_id']]['stocks'] = $sku['count'];
        }

        // Stocks
        $product_stocks_model = new shopProductStocksModel();
        foreach($product_stocks_model->getByField('product_id', $ids, true) as $stock) {
            if (!is_array(ifset($this->response[$stock['product_id']]['stocks']))) {
                $this->response[$stock['product_id']]['stocks'] = array();
            }
            $this->response[$stock['product_id']]['stocks'][$stock['stock_id']] = $stock['count'];
        }
    }
}

