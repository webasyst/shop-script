<?php

class shopProductBadgeDeleteController extends waJsonController
{
    public function execute()
    {
        $product_model = new shopProductModel();
        $hash = shopProductsAddToCategoriesController::getHash(waRequest::TYPE_STRING);
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
        $all_updated_products = [];
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

            $product_model->updateById($product_ids, [
                'badge' => '',
                'edit_datetime' => date('Y-m-d H:i:s'),
            ]);
            $all_updated_products += $product_ids;
            $offset += count($product_ids);
            if (!$product_ids) {
                break;
            }
        }

        $count_all_updated_products = count($all_updated_products);
        if ($count_all_updated_products > 1) {
            for ($offset = 0; $offset < $count_all_updated_products; $offset += 5000) {
                $part_updated_products = array_slice($all_updated_products, $offset, 5000);
                $this->logAction('products_edit', count($part_updated_products) . '$' . implode(',', $part_updated_products));
            }
        } elseif (isset($all_updated_products[0]) && is_numeric($all_updated_products[0])) {
            $this->logAction('product_edit', $all_updated_products[0]);
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

