<?php

class shopDialogAssignTagsAction extends waViewAction
{
    public function execute()
    {
        $product_tags_model = new shopProductTagsModel();

        $tags = array();
        $hash = waRequest::post('hash', '');

        // get tags by products
        if (!$hash) {

            $product_ids = waRequest::post('product_id', array(), waRequest::TYPE_ARRAY_INT);
            if (!$product_ids) {
                return;
            }
            $tags = $product_tags_model->getTags($product_ids);

        // get tags by hash of collection
        } else {

            // add all products of collection with this hash
            $collection = new shopProductsCollection($hash);
            $offset = 0;
            $count = 100;
            $total_count = $collection->count();
            while ($offset < $total_count) {
                $ids  = array_keys($collection->getProducts('*', $offset, $count));
                $tags += $product_tags_model->getTags($ids);
                $offset += count($ids);
            }
        }

        $tag_model = new shopTagModel();

        $this->view->assign(array(
            'tags' => $tags,
            'popular_tags' => $tag_model->popularTags()
        ));
    }
}