<?php

class shopProductsAssignTagsController extends waJsonController
{
    public function execute()
    {
        $tag_model = new shopTagModel();
        $product_tags_model = new shopProductTagsModel();

        $delete_tags = waRequest::post('delete_tags', array(), waRequest::TYPE_ARRAY_INT);
        $tags = waRequest::post('tags', '', waRequest::TYPE_STRING_TRIM);
        $tags = $tags ? explode(',', $tags) : array();

        if (!$delete_tags && !$tags) {
            return;
        }

        $hash = $this->getHash();
        $all_product_ids = null;

        // delete tags of selected products
        if (!$hash) {
            $all_product_ids = $this->getProductsId();
            $hash = 'id/'.join(',', $all_product_ids);
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
        $params = array(
            'delete_tags' => $delete_tags,
            'tags'        => $tags,
            'hash'        => $hash,
            'products_id' => $all_product_ids
        );
        wa('shop')->event('products_tags_set.before', $params);

        // maintain all products of collection with this hash
        $collection = new shopProductsCollection($hash, ['filter_by_rights' => true]);
        $offset = 0;
        $count = 100;
        $total_count = $collection->count();

        $tag_ids = array();
        if ($offset < $total_count) {
            $tag_ids = $tag_model->getIds($tags);
        }

        while ($offset < $total_count) {
            $product_ids = array_keys($collection->getProducts('*', $offset, $count));
            if (!$product_ids) {
                break;
            }

            // delete tags
            if ($delete_tags) {
                $product_tags_model->delete($product_ids, $delete_tags);
            }

            // assign tags
            if ($tag_ids) {
                $product_tags_model->assign($product_ids, $tag_ids);
            }

            $offset += count($product_ids);
        }

        if ($tag_model->countAll() > 100) {
            $cloud = 'search';
        } elseif ($tag_model->countAll() > 1) {
            $cloud = $tag_model->getCloud('id');
        } else {
            $cloud = null;
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
        $params = array(
            'delete_tags' => $delete_tags,
            'tags'        => $tags,
            'hash'        => $hash,
            'products_id' => $all_product_ids
        );
        wa('shop')->event('products_tags_set.after', $params);

        $this->response['denied_message'] = $this->getDeniedMessage($total_count);
        $this->response['cloud'] = $cloud;
    }

    /**
     * Returns an error with the number of products to which access has been denied.
     *
     * @param int $collection_count the number of items to be changed
     * @return string
     */
    protected function getDeniedMessage($collection_count)
    {
        $hash = $this->getHash();

        if ($hash === 'all') {
            $shop_products_model = new shopProductModel();
            $total = $shop_products_model->countAll();
        } else {
            $products_id = $this->getProductsId();
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

    protected function getProductsId()
    {
        return waRequest::post('product_id', array(), waRequest::TYPE_ARRAY_INT);
    }

    /**
     * @return string
     */
    protected function getHash()
    {
        return waRequest::post('hash', '');
    }
}
