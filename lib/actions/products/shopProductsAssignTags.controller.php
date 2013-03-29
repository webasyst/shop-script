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

        // delete tags of selected products
        if (!$hash) {
            $product_ids = waRequest::post('product_id', array(), waRequest::TYPE_ARRAY_INT);
            if (!$product_ids) {
                return;
            }

            // delete tags of selected products
            if ($delete_tags) {
                $product_tags_model->delete($product_ids, $delete_tags);
            }

            // assign tags to selected products
            if ($tags) {
                $tag_ids = $tag_model->getIds($tags);
                $product_tags_model->assign($product_ids, $tag_ids);
            }
        } else {

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
                $product_ids = array_keys($collection->getProducts('*', $offset, $count));

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
        }

        $this->response['cloud'] = $tag_model->getCloud('id');
    }
}