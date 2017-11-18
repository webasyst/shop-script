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
        $discount = $cart->discount($order);
        if (!empty($order['params']['affiliate_bonus'])) {
            $discount -= shop_currency(shopAffiliate::convertBonus($order['params']['affiliate_bonus']), $this->getConfig()->getCurrency(true), null, false);
        }


        $this->response['total'] = $is_html ? shop_currency_html($total, true) : shop_currency($total, true);
        $this->response['discount'] = $is_html ? shop_currency_html($discount, true) : shop_currency($discount, true);
        $this->response['discount_numeric'] = $discount;
        $discount_coupon = ifset($order['params']['coupon_discount'], 0);
        $this->response['discount_coupon'] = $is_html ? shop_currency_html($discount_coupon, true) : shop_currency($discount_coupon, true);
        $this->response['count'] = $cart->count();

        if (shopAffiliate::isEnabled()) {
            $add_affiliate_bonus = shopAffiliate::calculateBonus(array(
                'total' => $total,
                'discount' => $discount,
                'items' => $cart->items(false)
            ));
            $this->response['add_affiliate_bonus'] = sprintf(
                _w("This order will add <strong>+%s bonuses</strong> to  your account, which you will be able to spend on getting additional discounts later."),
                round($add_affiliate_bonus, 2)
            );
            $affiliate_bonus = $affiliate_discount = 0;
            if ($this->getUser()->isAuth()) {
                $customer_model = new shopCustomerModel();
                $customer = $customer_model->getById($this->getUser()->getId());
                $affiliate_bonus = $customer ? round($customer['affiliate_bonus'], 2) : 0;
            }
            $affiliate_discount = shopFrontendCartAction::getAffiliateDiscount($affiliate_bonus, $order);
            $this->response['affiliate_discount'] = $is_html ? shop_currency_html($affiliate_discount, true) : shop_currency($affiliate_discount, true);
        }
    }
}