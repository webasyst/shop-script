<?php

class shopProdBadgeDeleteController extends waJsonController
{
    public function execute()
    {
        $product_model = new shopProductModel();
        $all_product_ids = null;
        $presentation_id = waRequest::post('presentation_id', null, waRequest::TYPE_INT);
        $product_id = waRequest::post('product_ids', [], waRequest::TYPE_ARRAY_INT);
        $options = ['filter_by_rights' => true];
        $offset = 0;
        $hash = '';
        if (!$presentation_id) {
            $all_product_ids = $product_id;
            $hash = 'id/'.join(',', $all_product_ids);
        } else {
            $presentation = new shopPresentation($presentation_id, true);
            if ($presentation->getFilterId() > 0) {
                $options['exclude_products'] = $product_id;
                $hash = 'filter/'.$presentation->getFilterId();
            }
            $offset = max(0, waRequest::post('offset', 0, waRequest::TYPE_INT));
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

        $count = 100;
        $collection = new shopProductsCollection($hash, $options);
        $total_count = $collection->count();
        $all_updated_products = [];
        while ($offset < $total_count) {
            $products = $collection->getProducts('id, type_id', $offset, $count);
            $product_ids = [];

            foreach ($products as $product) {
                $product_ids[] = $product['id'];
            }

            $product_ids = array_unique($product_ids);

            foreach ($product_ids as $product_id) {
                $right = $product_model->checkRights($product_id);

                if (!$right) {
                    $this->errors = [
                        'id' => 'rights_error',
                        'text' => _w('Access denied'),
                    ];
                    return;
                }
            }

            $product_model->updateById($product_ids, [
                'badge' => '',
                'edit_datetime' => date('Y-m-d H:i:s'),
            ]);
            $all_updated_products = array_merge($all_updated_products, $product_ids);
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

