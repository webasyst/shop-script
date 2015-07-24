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
            $sku = $this->getSku($sku_id, $order_id);
            $this->response['sku'] = $sku;
            $this->response['service_ids'] = array_keys($sku['services']);
        } else {
            $product = $this->getProduct($product_id, $order_id);
            $this->response['product'] = $product;
            $this->response['sku_ids'] = array_keys($product['skus']);
            $this->response['service_ids'] = array_keys($product['services']);
        }
    }

    public function getProduct($product_id, $order_id)
    {
        $product = $this->getModel()->getProduct($product_id, $order_id, $order_id ? null : waRequest::get('currency'));

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
        foreach(array('price', 'min_price', 'max_price') as $key) {
            $product[$key] = round($product[$key], 2);
        }
        if (!empty($product['services']) && is_array($product['services'])) {
            $this->workupServices($product['services']);
        }

        if (!$product['image_id']) {
            $product['url_crop_small'] = null;
        } else {
            $product['url_crop_small'] = shopImage::getUrl(array(
                    'id' => $product['image_id'],
                    'filename' => $product['image_filename'],
                    'product_id' => $product['id'],
                    'ext' => $product['ext']
                ), $this->getConfig()->getImageSize('crop_small')
            );
        }
        // aggregated stocks count icon for product
        $product['icon'] = shopHelper::getStockCountIcon($product['count'], null, true);
        foreach ($product['skus'] as &$sku) {
            $this->workupSku($sku, $sku_stocks);
        }
        unset($sku);
    }

    private function workupSku(&$sku, $sku_stocks)
    {
        $sku['price'] = round($sku['price'], 2);
        if (!empty($sku['services']) && is_array($sku['services'])) {
            $this->workupServices($sku['services']);
        }
        // detaled stocks count icon for sku
        if (empty($sku_stocks[$sku['id']])) {
            $sku['icon'] = shopHelper::getStockCountIcon($sku['count'], null, true);
        } else {
            $icons = array();
            foreach ($sku_stocks[$sku['id']] as $stock_id => $stock) {
                $icon  = &$icons[$stock_id];
                $icon  = shopHelper::getStockCountIcon($stock['count'], $stock_id)." ";
                $icon .= $stock['count']." ";
                $icon .= "<span class='small'>@".htmlspecialchars($stock['name'])."</span>";
                unset($icon);
            }
//             $sku['icon'] = implode(', ', $icons);
            $sku['icon'] = shopHelper::getStockCountIcon($sku['count'], null, true);
            $sku['icons'] = $icons;
        }
    }

    public function getSku($sku_id, $order_id)
    {
        $sku = $this->getModel()->getSku($sku_id, $order_id, $order_id ? null : waRequest::get('currency'));
        $sku_stocks = $this->getSkuStocks(array($sku_id));
        $this->workupSku($sku, $sku_stocks);
        return $sku;
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

    private function workupServices(&$services)
    {
        foreach($services as &$s) {
            $s['price'] = round($s['price'], 2);
            if (!empty($s['variants']) && is_array($s['variants'])) {
                foreach($s['variants'] as &$v) {
                    $v['price'] = round($v['price'], 2);
                }
            }
        }
    }
}