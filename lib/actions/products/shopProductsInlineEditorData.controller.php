<?php
/** Load data required for inline editor in product lists. */
class shopProductsInlineEditorDataController extends waJsonController
{
    public function execute()
    {
        $ids = waRequest::post('ids', array(), 'array_int');

        $this->response = array();

        $products = [];
        if ($ids) {
            $product_model = new shopProductModel();
            $products = $product_model->select('`id`, `count_denominator`')->where('`id` IN (?)', [$ids])->fetchAll('id');
        }

        // Prices
        $product_skus_model = new shopProductSkusModel();
        foreach($product_skus_model->getByField('product_id', $ids, true) as $sku) {
            $this->response[$sku['product_id']]['price'] = $sku['price'];
            if (isset($products[$sku['product_id']])) {
                $sku['count'] = (string)shopFrac::discardZeros($sku['count']);
            }
            $this->response[$sku['product_id']]['stocks'] = $sku['count'];
        }

        // Stocks
        $product_stocks_model = new shopProductStocksModel();
        foreach($product_stocks_model->getByField('product_id', $ids, true) as $stock) {
            if (!is_array(ifset($this->response[$stock['product_id']]['stocks']))) {
                $this->response[$stock['product_id']]['stocks'] = array();
            }
            if (isset($products[$stock['product_id']])) {
                $stock['count'] = (string)shopFrac::discardZeros($stock['count']);
            }
            $this->response[$stock['product_id']]['stocks'][$stock['stock_id']] = $stock['count'];
        }
    }
}

