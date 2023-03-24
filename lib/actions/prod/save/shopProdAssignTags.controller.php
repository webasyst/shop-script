<?php

class shopProdAssignTagsController extends waJsonController
{
    public function execute()
    {
        $tag_model = new shopTagModel();
        $product_tags_model = new shopProductTagsModel();

        $product_id = waRequest::post('product_ids', [], waRequest::TYPE_ARRAY_INT);
        $delete_tags = waRequest::post('delete_tags', [], waRequest::TYPE_ARRAY_INT);
        $tags = waRequest::post('tags', [], waRequest::TYPE_ARRAY);

        if (!$delete_tags && !$tags) {
            $this->errors[] = [
                'id' => 'empty_tags',
                'text' => _w('Not tags to update are specified.'),
            ];
        }

        $all_product_ids = null;
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

        /**
         * Attaches tags to the product. Get data before changes
         *
         * @param array[string]int products_id[%id][id] Product id(s)
         * @param array $tags tags id
         * @param hash $hash Collection Hash
         * @param array[string]int $delete_tags tags id
         *
         * @event products_tags_set.before
         */
        $params = [
            'delete_tags' => $delete_tags,
            'tags'        => $tags,
            'hash'        => $hash,
            'products_id' => $all_product_ids
        ];
        wa('shop')->event('products_tags_set.before', $params);

        $collection = new shopProductsCollection($hash, $options);
        $count = 100;
        $total_count = $collection->count();
        $tag_ids = [];
        if ($offset < $total_count) {
            $tag_ids = $tag_model->getIds($tags);
        }
        $all_updated_products = [];
        $index_search = new shopIndexSearch();
        while ($offset < $total_count) {
            $product_ids = array_keys($collection->getProducts('id', $offset, $count));
            if (!$product_ids) {
                break;
            }

            if ($delete_tags) {
                $product_tags_model->delete($product_ids, $delete_tags);
            }
            if ($tag_ids) {
                $product_tags_model->assign($product_ids, $tag_ids);
            }
            $all_updated_products = array_merge($all_updated_products, $product_ids);
            $offset += count($product_ids);
            foreach ($product_ids as $id) {
                $index_search->onUpdate($id);
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
         * Attaches tags to the product
         *
         * @param array[string]int products_id[%id][id] Product id(s)
         * @param array $tags tags id
         * @param string $hash Collection Hash
         * @param array[string]int $delete_tags tags id
         *
         * @event products_tags_set.after
         */
        $params = [
            'delete_tags' => $delete_tags,
            'tags'        => $tags,
            'hash'        => $hash,
            'products_id' => $all_product_ids
        ];
        wa('shop')->event('products_tags_set.after', $params);

        $this->response['denied_message'] = $this->getDeniedMessage($total_count, $product_id);

        if ($tag_model->countAll() > 100) {
            $cloud = 'search';
        } elseif ($tag_model->countAll() > 1) {
            $cloud = $tag_model->getCloud('id');
        } else {
            $cloud = null;
        }
        $this->response['cloud'] = $cloud;
    }

    /**
     * Returns an error with the number of products to which access has been denied.
     *
     * @param int $collection_count the number of items to be changed
     * @return string
     */
    protected function getDeniedMessage($collection_count, $products_id)
    {
        $hash = shopProductsAddToCategoriesController::getHash();

        if ($hash === 'all') {
            $shop_products_model = new shopProductModel();
            $total = $shop_products_model->countAll();
        } else {
            $total = count($products_id);
        }

        $access_denied_products = $total - $collection_count;
        $access_denied_products =  max(0, $access_denied_products);

        $result = '';
        if ($access_denied_products > 0) {
            $result = _w('Editing of %d product is not allowed.', 'Editing of %d products is not allowed.', $access_denied_products);
        }

        return $result;
    }
}
