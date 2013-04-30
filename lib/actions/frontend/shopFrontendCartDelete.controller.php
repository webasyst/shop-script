<?php

class shopFrontendCartDeleteController extends waJsonController
{
    public function execute()
    {
        $id = waRequest::post('id');
        $cart = new shopCart();

        if ($id) {
            $item = $cart->deleteItem($id);
            if ($item && !empty($item['parent_id'])) {
                $this->response['item_total'] = shop_currency($cart->getItemTotal($item['parent_id']), true);
            }
        }
        $this->response['total'] = shop_currency($cart->total());
        $this->response['discount'] = shop_currency($cart->discount());
        $this->response['count'] = $cart->count();
    }
}