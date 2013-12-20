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
                $this->response['item_total'] = shop_currency_html($cart->getItemTotal($item['parent_id']), true);
            }
        }
        $total = $cart->total();
        $discount = $cart->discount();
        
        $this->response['total'] = shop_currency_html($total, true);
        $this->response['discount'] = shop_currency_html($discount, true);
        $this->response['count'] = $cart->count();
        
        if (shopAffiliate::isEnabled()) {
            $add_affiliate_bonus = shopAffiliate::calculateBonus(array(
                'total' => $total,
                'discount' => $discount,
                'items' => $cart->items(false)
            ));
            $this->response['add_affiliate_bonus'] = sprintf(
                _w("This order will add +%s points to your affiliate bonus."), 
                round($add_affiliate_bonus, 2)
            );
        }
    }
}