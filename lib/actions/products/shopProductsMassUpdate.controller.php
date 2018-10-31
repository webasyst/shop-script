<?php

class shopProductsMassUpdateController extends waJsonController
{
    public function execute()
    {
        shopProductStocksLogModel::setContext(shopProductStocksLogModel::TYPE_PRODUCT);
        shopProductMassUpdate::update($this->getSkus(), $this->getProducts());
        shopProductStocksLogModel::clearContext();
    }

    public function getSkus()
    {
        $skus = array();
        $post = $this->getRequest()->post();
        foreach (ifset($post['product'], array()) as $product_id => $product) {
            foreach (ifset($product['sku'], array()) as $sku_id => $sku) {
                $sku_id = (int)$sku_id;
                if ($sku && $sku_id > 0) {
                    $skus[$sku_id] = $sku;
                    $skus[$sku_id]['id'] = $sku_id;
                }
            }
        }
        return $skus;
    }

    public function getProducts()
    {
        $products = array();
        $post = $this->getRequest()->post();
        foreach (ifset($post['product'], array()) as $product_id => $product) {
            if (isset($product['sku'])) {
                unset($product['sku']);
            }
            $product_id = (int)$product_id;
            if ($product && $product_id > 0) {
                $products[$product_id] = $product;
                $products[$product_id]['id'] = $product_id;
            }
        }
        return $products;
    }
}