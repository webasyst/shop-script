<?php

class shopOrdersGetProductController extends waJsonController
{
    /**
     * @var shopOrderItemsModel
     */
    private $model;

    public function execute()
    {
        $order_id = waRequest::get('order_id', null, waRequest::TYPE_INT);
        $order_id = $order_id ? $order_id : null;

        $product_id = waRequest::get('product_id', 0, waRequest::TYPE_INT);
        if (!$product_id) {
            $this->errors[] = _w("Unknown product");
            return;
        }

        $sku_id = waRequest::get('sku_id', 0, waRequest::TYPE_INT);
        if ($sku_id) {
            $this->response = $this->getSku($sku_id, $order_id);
        } else {
            $this->response = $this->getProduct($product_id, $order_id);
        }
    }

    public function getProduct($product_id, $order_id)
    {
        $product = $this->getModel()->getProduct($product_id, $order_id);

        $sku_ids = array();
        foreach ($product['skus'] as $sku) {
            $sku_ids[] = $sku['id'];
        }
        $sku_stocks = $this->getSkuStocks($sku_ids);

        $this->workup($product, $sku_stocks);
        return $product;
    }

    private function getSkuStocks($sku_ids)
    {
        if (!$sku_ids) {
            return array();
        }
        $product_stocks_model = new shopProductStocksModel();
        return $product_stocks_model->getBySkuId($sku_ids);
    }

    public function workup(&$product, $sku_stocks)
    {
        if (!$product['image_id']) {
            $product['url_crop_small'] = null;
        } else {
            $product['url_crop_small'] = shopImage::getUrl(
                array('id' => $product['image_id'], 'product_id' => $product['id'], 'ext' => $product['ext']),
                $this->getConfig()->getImageSize('crop_small')
            );
        }
        $product['icon'] = shopHelper::getStockCountIcon($product['count'], null, true);
        foreach ($product['skus'] as &$sku) {
            if (empty($sku_stocks[$sku['id']])) {
                $sku['icon'] = shopHelper::getStockCountIcon($sku['count'], null, true);
            } else {
                $icons = array();
                foreach ($sku_stocks[$sku['id']] as $stock_id => $stock) {
                    $icon  = &$icons[];
                    $icon  = shopHelper::getStockCountIcon($stock['count'], $stock_id)." ";
                    $icon .= $stock['count']." ";
                    $icon .= "@".htmlspecialchars($stock['name']);
                    unset($icon);
                }
                $sku['icon'] = implode(', ', $icons);
            }
        }
        unset($sku);
    }

    public function getSku($sku_id, $order_id)
    {
        return $this->getModel()->getSku($sku_id, $order_id);
    }

    /**
     * @return shopOrderItemsModel
     */
    public function getModel()
    {
        if (!$this->model) {
            $this->model = new shopOrderItemsModel();
        }
        return $this->model;
    }
}