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

        $hash = waRequest::post('hash', '');
        $products_id = null;

        // delete tags of selected products
        if (!$hash) {
            $products_id = waRequest::post('product_id', array(), waRequest::TYPE_ARRAY_INT);
            $hash = 'id/'.join(',', $products_id);
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
            'products_id' => $products_id
        );
        wa('shop')->event('products_tags_set.before', $params);

        // maintain all products of collection with this hash
        $collection = new shopProductsCollection($hash);
        $offset = 0;
        $count = 100;
        $total_count = $collection->count();

        $tag_ids = array();
        if ($offset < $total_count) {
            $tag_ids = $tag_model->getIds($tags);
        }

        while ($offset < $total_count) {
            $products_id = array_keys($collection->getProducts('*', $offset, $count));

            // delete tags
            if ($delete_tags) {
                $product_tags_model->delete($products_id, $delete_tags);
            }

            // assign tags
            if ($tag_ids) {
                $product_tags_model->assign($products_id, $tag_ids);
            }

            $offset += count($products_id);
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
            'products_id' => $products_id
        );
        wa('shop')->event('products_tags_set.after', $params);

        $this->response['cloud'] = $cloud;
    }
}