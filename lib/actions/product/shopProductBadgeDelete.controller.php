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
            $products = $collection->getProducts('id, type_id', $offset, $count);
            $products_id = [];
            $type_ids = [];

            foreach ($products as $product) {
                $type_ids[] = $product['type_id'];
                $products_id[] = $product['id'];
            };

            $type_ids = array_unique($products_id);

            foreach ($type_ids as $type_id) {
                $right = $product_model->checkRights($type_id);

                if (!$right) {
                    throw new waException(_w("Access denied"));
                }
            }

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

