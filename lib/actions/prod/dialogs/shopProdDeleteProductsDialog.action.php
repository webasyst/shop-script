<?php

class shopProdDeleteProductsDialogAction extends waViewAction
{
    public function execute()
    {
        $product_ids = waRequest::post('product_ids', [], waRequest::TYPE_ARRAY_INT);
        $presentation_id = waRequest::post('presentation_id', null, waRequest::TYPE_INT);
        $options = [];
        $hash = '';
        if (!$presentation_id) {
            $hash = 'id/'.join(',', $product_ids);
        } else {
            $presentation = new shopPresentation($presentation_id, true);
            if ($presentation->getFilterId() > 0) {
                $options['exclude_products'] = $product_ids;
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

        $this->setTemplate('templates/actions/prod/main/dialogs/products.delete_products.html');
    }
}
