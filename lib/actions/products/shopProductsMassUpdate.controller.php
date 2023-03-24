<?php

class shopProductsMassUpdateController extends waJsonController
{
    public function execute()
    {
        shopProductStocksLogModel::setContext(shopProductStocksLogModel::TYPE_PRODUCT);
        $products = $this->getProducts();
        shopProductMassUpdate::update($this->getSkus(), $products['products']);
        $count_all_updated_products = count($products['products_id']);
        for ($offset = 0; $offset < $count_all_updated_products; $offset += 5000) {
            $part_updated_products = array_slice($products['products_id'], $offset, 5000);
            $this->logAction('products_edit', count($part_updated_products) . '$' . implode(',', $part_updated_products));
        }
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
        $products = array(
            'products' => [],
            'products_id' => [],
        );
        $post = $this->getRequest()->post();
        foreach (ifset($post['product'], array()) as $product_id => $product) {
            if (isset($product['sku'])) {
                unset($product['sku']);
            }
            $product_id = (int)$product_id;
            if ($product && $product_id > 0) {
                $products['products'][$product_id] = $product;
                $products['products'][$product_id]['id'] = $product_id;
            }
            if ($product_id > 0) {
                $products['products_id'][] = $product_id;
            }
        }
        return $products;
    }
}