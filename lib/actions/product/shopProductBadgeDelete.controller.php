<?php

class shopProductBadgeDeleteController extends waJsonController
{
    public function execute()
    {
        $product_model = new shopProductModel();

        $hash = waRequest::post('hash', '', 'string');
        $products_id = null;

        if (!$hash) {
            $products_id = waRequest::request('product_id', array(), waRequest::TYPE_ARRAY_INT);
            if (!$products_id) {
                $products_id = waRequest::get('id', array(), waRequest::TYPE_ARRAY_INT);
            }
            $hash = 'id/'.join(',', $products_id);
        }

        $product = $product_model->getById($products_id);
        if (!$product) {
            throw new waException(_w("Unknown product"));
        }
        if (!$product_model->checkRights(reset($product))) {
            throw new waException(_w("Access denied"));
        }

        /**
         * Removes stickers from products with bulk and single changes. Get data before changes
         *
         * @param array|string $products_id Products id(s)
         * @param string $hash Collection Hash
         *
         * @event product_badge_set.before
         */
        $params = array(
            'products_id' => $products_id,
            'hash'       => $hash,
        );
        wa('shop')->event('product_badge_delete.before', $params);

        $offset = 0;
        $count = 100;
        $collection = new shopProductsCollection($hash);
        $total_count = $collection->count();
        while ($offset < $total_count) {
            $products_id = array_keys($collection->getProducts('*', $offset, $count));
            $product_model->updateById($products_id, array('badge' => null));
            $offset += count($products_id);
        }

        /**
         * Removes stickers from products with bulk and single changes
         *
         * @param array|string $products_id Products id(s)
         * @param string $hash Collection Hash
         *
         * @event product_badge_delete.after
         */
        $params = array(
            'products_id' => $products_id,
            'hash'       => $hash,
        );
        wa('shop')->event('product_badge_delete.after', $params);
    }
}

