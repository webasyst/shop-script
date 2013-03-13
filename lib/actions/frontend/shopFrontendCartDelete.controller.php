<?php

class shopFrontendCartDeleteController extends waJsonController
{
    public function execute()
    {
        $id = waRequest::post('id');
        $cart = new shopCart();
        if ($id) {
            $cart->deleteItem($id);
        }
        $this->response['total'] = shop_currency($cart->total());
        $this->response['discount'] = shop_currency($cart->discount());
    }
}