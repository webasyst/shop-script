<?php

class shopProdAssignTagsDialogAction extends waViewAction
{
    public function execute()
    {
        $product_id = waRequest::post('product_id', [], waRequest::TYPE_ARRAY_INT);
        $presentation_id = waRequest::post('presentation_id', null, waRequest::TYPE_INT);
        $options = [];
        $offset = 0;
        $hash = '';
        if (!$presentation_id) {
            $hash = 'id/'.join(',', $product_id);
        } else {
            $presentation = new shopPresentation($presentation_id, true);
            if ($presentation->getFilterId() > 0) {
                $options['exclude_products'] = $product_id;
                $hash = 'filter/'.$presentation->getFilterId();
            }
            $offset = max(0, waRequest::post('offset', 0, waRequest::TYPE_INT));
        }

        $collection = new shopProductsCollection($hash, $options + ['filter_by_rights' => true]);
        $total_count = $collection->count();
        if (!$hash || !wa()->getUser()->isAdmin('shop')) {
            $can_assign_tags = false;
        } else {
            $simple_collection = new shopProductsCollection($hash, $options);
            $can_assign_tags = $total_count === $simple_collection->count();
        }

        $this->view->assign([
            'can_assign_tags' => $can_assign_tags
        ]);
        if (!$can_assign_tags) {
            return;
        }

        $product_ids = [];
        $count = 100;
        while ($offset < $total_count) {
            $product_ids = array_keys($collection->getProducts('id', $offset, $count));
            if (!$product_ids) {
                break;
            }
            $offset += count($product_ids);
        }

        $product_tags_model = new shopProductTagsModel();
        $tags = $product_tags_model->getTags($product_ids);

        $tag_model = new shopTagModel();
        $this->view->assign([
            'tags' => $tags,
            'popular_tags' => $tag_model->popularTags(),
        ]);
    }
}
