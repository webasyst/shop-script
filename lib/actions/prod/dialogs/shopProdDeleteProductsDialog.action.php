<?php

class shopProdDeleteProductsDialogAction extends waViewAction
{
    public function execute()
    {
        $product_id = waRequest::post('product_id', [], waRequest::TYPE_ARRAY_INT);
        $presentation_id = waRequest::post('presentation_id', null, waRequest::TYPE_INT);
        $options = [];
        $hash = '';
        if (!$presentation_id) {
            $hash = 'id/'.join(',', $product_id);
        } else {
            $presentation = new shopPresentation($presentation_id, true);
            if ($presentation->getFilterId() > 0) {
                $options['exclude_products'] = $product_id;
                $hash = 'filter/'.$presentation->getFilterId();
            }
        }

        $collection = new shopProductsCollection($hash, $options + ['filter_by_rights' => true]);
        $total_count = $collection->count();
        if (!$hash || !wa()->getUser()->isAdmin('shop')) {
            $can_delete = false;
        } else {
            $simple_collection = new shopProductsCollection($hash, $options);
            $can_delete = $total_count === $simple_collection->count();
        }

        $this->view->assign([
            'count' => $total_count,
            'can_delete' => $can_delete
        ]);
    }
}
