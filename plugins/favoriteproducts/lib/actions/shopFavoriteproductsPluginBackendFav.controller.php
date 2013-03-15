<?php

/** Add product to favorites or delete from favorites. */
class shopFavoriteproductsPluginBackendFavController extends waJsonController
{

    public function execute()
    {
        $product_id = waRequest::request('id', 0, 'int');
        $fm = new shopFavoriteproductsPluginModel();
        if ($product_id) {
            if (waRequest::request('fav')) {
                $fm->replace(array(
                    'contact_id' => wa()->getUser()->getId(),
                    'product_id' => $product_id,
                    'datetime' => date('Y-m-d H:i:s'),
                ));
            } else {
                $fm->deleteByField(array(
                    'contact_id' => wa()->getUser()->getId(),
                    'product_id' => $product_id,
                ));
            }
        }
        $this->response = $fm->countByField('contact_id', wa()->getUser()->getId());
    }
}

