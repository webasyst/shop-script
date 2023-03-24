<?php

class shopProdChangeVisibilityController extends waJsonController
{
    public function execute()
    {
        $set_status = waRequest::post('status', null, waRequest::TYPE_INT);
        if ($set_status > 0) {
            $set_status = 1;
        } elseif ($set_status < 0) {
            $set_status = -1;
        } else {
            $set_status = 0;
        };
        $update_sku_availability = waRequest::post('update_skus', null, waRequest::TYPE_INT);

        $products_id = [];
        $products_id_denied = [];
        $products_id_attempted = [];
        $products_id_successfull = [];

        $presentation_id = waRequest::post('presentation_id', null, waRequest::TYPE_INT);
        $options = [];
        $offset = 0;
        $hash = '';
        $product_id = waRequest::post('product_ids', [], waRequest::TYPE_ARRAY_INT);
        if (!$presentation_id) {
            if (!$product_id) {
                $this->response += [
                    'successfull' => $products_id_successfull,
                    'attempted'   => $products_id_attempted,
                    'denied'      => $products_id_denied,
                ];
            } else {
                foreach ($product_id as $id) {
                    $products_id_attempted[$id] = $id;
                }
            }
            $hash = 'id/'.join(',', $product_id);
        } else {
            $presentation = new shopPresentation($presentation_id, true);
            if ($presentation->getFilterId() > 0) {
                $options['exclude_products'] = $product_id;
                $hash = 'filter/'.$presentation->getFilterId();
            }
            $offset = max(0, waRequest::post('offset', 0, waRequest::TYPE_INT));
        }

        $product_model = new shopProductModel();
        $product_skus_model = new shopProductSkusModel();

        $is_admin = wa()->getUser()->isAdmin('shop'); // separate admin check to allow broken products with no type
        $types_allowed = shopHelper::getWritableTypes();

        $collection = new shopProductsCollection($hash, $options);
        $total_count = $collection->count();
        $count = 100;

        /**
         * Change the visibility of the product. Get data before changes
         *
         * @param bool $set_status
         * @param bool $update_sku_availability
         * @param string $hash
         * @param array $products_id
         * @param array $products_id_denied
         * @param array $products_id_attempted
         * @param array $products_id_successfull
         * @param object $collection
         *
         * @event products_visibility_set.before
         */
        $params = [
            'status'                  => $set_status,
            'update_sku'              => $update_sku_availability,
            'hash'                    => $hash,
            'products_id'             => $products_id,
            'products_id_denied'      => $products_id_denied,
            'products_id_attempted'   => $products_id_attempted,
            'products_id_successfull' => $products_id_successfull,
            'collection'              => $collection,
        ];
        wa('shop')->event('products_visibility_set.before', $params);

        // Read and update products in batches
        $all_updated_products = [];
        while ($offset < $total_count) {
            $products = $collection->getProducts('id,type_id', $offset, $count);

            // Filter products user does not have access to
            $products_id = [];
            foreach ($products as $p) {
                if (empty($types_allowed[$p['type_id']]) && !$is_admin) {
                    $products_id_denied[] = $p['id'];
                } else {
                    $products_id[] = $p['id'];
                    $products_id_successfull[] = $p['id'];
                    $products_id_attempted[$p['id']] = $p['id'];
                }
            }

            // Update products and skus
            $product_model->updateById($products_id, [
                'status' => $set_status,
                'edit_datetime' => date('Y-m-d H:i:s'),
            ]);
            $all_updated_products += $products_id;
            if ($update_sku_availability) {
                $product_skus_model->updateByField('product_id', $products_id, [
                    'available' => (int)($set_status > 0),
                ]);
            }

            $offset += count($products);
            if (!$products) {
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
         * Change the visibility of the product
         *
         * @param bool $set_status
         * @param bool $update_sku_availability
         * @param string $hash
         * @param array $products_id
         * @param array $products_id_denied
         * @param array $products_id_attempted
         * @param array $products_id_successfull
         * @param object $collection
         *
         * @event products_visibility_set.after
         */
        $params = [
            'status'                  => $set_status,
            'update_sku'              => $update_sku_availability,
            'hash'                    => $hash,
            'products_id'             => $products_id,
            'products_id_denied'      => $products_id_denied,
            'products_id_attempted'   => $products_id_attempted,
            'products_id_successfull' => $products_id_successfull,
            'collection'              => $collection,
        ];
        wa('shop')->event('products_visibility_set.after', $params);

        $this->response += [
            'denied'      => $products_id_denied,
            'successfull' => $products_id_successfull,
            'attempted'   => array_values($products_id_attempted),
        ];
    }
}