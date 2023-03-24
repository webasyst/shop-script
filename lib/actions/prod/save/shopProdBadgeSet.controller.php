<?php

class shopProdBadgeSetController extends waJsonController
{
    public function execute()
    {
        $code = waRequest::post('code', null, waRequest::TYPE_STRING_TRIM);
        if (!$code) {
            $this->errors[] = [
                'id' => 'empty_code',
                'text' => _w('Empty code'),
            ];
            return;
        }

        $product_model = new shopProductModel();

        $all_product_ids = null;
        $product_id = waRequest::post('product_ids', [], waRequest::TYPE_ARRAY_INT);
        $presentation_id = waRequest::post('presentation_id', null, waRequest::TYPE_INT);
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

        if (!$this->errors) {
            /**
             * Attaches stickers to products in bulk and single edits. Get data before changes
             *
             * @param array|string $all_product_ids Products id(s)
             * @param string $code Badge code
             * @param string $hash Collection Hash
             *
             * @event product_badge_set.before
             */
            $params = array(
                'code'        => $code,
                'products_id' => $all_product_ids,
                'hash'        => $hash,
            );
            wa('shop')->event('product_badge_set.before', $params);

            $count = 100;
            $collection = new shopProductsCollection($hash, $options);
            $total_count = $collection->count();
            $all_updated_products = [];
            while ($offset < $total_count) {
                $product_ids = array_keys($collection->getProducts('id', $offset, $count));
                $product_model->updateById($product_ids, [
                    'badge' => $code,
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
             * Attaches stickers to products in bulk and single edits
             *
             * @param array|string $all_product_ids Products id(s)
             * @param string $code Badge code
             * @param string $hash Collection Hash
             *
             * @event product_badge_set.after
             */
            $params = array(
                'code'        => $code,
                'products_id' => $all_product_ids,
                'hash'        => $hash,
            );
            wa('shop')->event('product_badge_set.after', $params);

            $badges = shopProductModel::badges();
            $this->response = isset($badges[$code]) ? $badges[$code]['code'] : $code;
        }
    }
}
