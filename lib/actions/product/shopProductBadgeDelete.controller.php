<?php

class shopProductBadgeDeleteController extends waJsonController
{
    public function execute()
    {
        $product_model = new shopProductModel();
        $hash = waRequest::post('hash', '', 'string');
        $all_product_ids = null;

        if (!$hash) {
            $all_product_ids = waRequest::request('product_id', array(), waRequest::TYPE_ARRAY_INT);
            if (!$all_product_ids) {
                $all_product_ids = waRequest::get('id', array(), waRequest::TYPE_ARRAY_INT);
            }
            $hash = 'id/'.join(',', $all_product_ids);
        }
        
        /**
         * Removes stickers from products with bulk and single changes. Get data before changes
         *
         * @param array|string $all_product_ids Products id(s)
         * @param string $hash Collection Hash
         *
         * @event product_badge_set.before
         */
        $params = array(
            'products_id' => $all_product_ids,
            'hash'        => $hash,
        );
        wa('shop')->event('product_badge_delete.before', $params);

        $offset = 0;
        $count = 100;
        $collection = new shopProductsCollection($hash);
        $total_count = $collection->count();
        while ($offset < $total_count) {
            $products = $collection->getProducts('id, type_id', $offset, $count);
            $product_ids = [];

            foreach ($products as $product) {
                $product_ids[] = $product['id'];
            };

            $product_ids = array_unique($product_ids);

            foreach ($product_ids as $product_id) {
                $right = $product_model->checkRights($product_id);

                if (!$right) {
                    throw new waException(_w("Access denied"));
                }
            }

            $product_model->updateById($product_ids, array('badge' => null));
            $offset += count($product_ids);
            if (!$product_ids) {
                break;
            }
        }

        /**
         * Removes stickers from products with bulk and single changes
         *
         * @param array|string $all_product_ids Products id(s)
         * @param string $hash Collection Hash
         *
         * @event product_badge_delete.after
         */
        $params = array(
            'products_id' => $all_product_ids,
            'hash'        => $hash,
        );
        wa('shop')->event('product_badge_delete.after', $params);
    }
}

