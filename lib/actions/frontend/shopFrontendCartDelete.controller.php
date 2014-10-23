<?php

class shopFrontendCartDeleteController extends waJsonController
{
    public function execute()
    {
        $id = waRequest::post('id');
        $cart = new shopCart();

        $is_html = waRequest::request('html');

        if ($id) {
            $item = $cart->deleteItem($id);
            if ($item && !empty($item['parent_id'])) {
                $item_total = $cart->getItemTotal($item['parent_id']);
                $this->response['item_total'] = $is_html ? shop_currency_html($item_total, true) : shop_currency($item_total, true);
            }
        }
        $total = $cart->total();
        $discount = $cart->discount();

        
        $this->response['total'] = $is_html ? shop_currency_html($total, true) : shop_currency($total, true);
        $this->response['discount'] = $is_html ? shop_currency_html($discount, true) : shop_currency($discount, true);
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