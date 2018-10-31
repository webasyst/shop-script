<?php

class shopFrontendCartSaveController extends waJsonController
{
    public function execute()
    {
        $cart = new shopCart();
        $item_id = waRequest::post('id');
        $cart_items_model = new shopCartItemsModel();
        $item = $cart_items_model->getById($item_id);

        $is_html = waRequest::request('html');
        if ($q = waRequest::post('quantity', 0, 'int')) {
            if ($q < 0) {
                $q = 1;
                $this->response['q'] = 1;
            }
            if (!wa()->getSetting('ignore_stock_count')) {
                if ($item['type'] == 'product') {
                    $product_model = new shopProductModel();
                    $p = $product_model->getById($item['product_id']);
                    $sku_model = new shopProductSkusModel();
                    $sku = $sku_model->getById($item['sku_id']);

                    // limit by main stock
                    if (wa()->getSetting('limit_main_stock') && waRequest::param('stock_id')) {
                        $stock_id = waRequest::param('stock_id');
                        $product_stocks_model = new shopProductStocksModel();
                        $sku_stock = shopHelper::fillVirtulStock($product_stocks_model->getCounts($sku['id']));
                        if (isset($sku_stock[$stock_id])) {
                            $sku['count'] = $sku_stock[$stock_id];
                        }
                    }
                    // check quantity
                    if ($sku['count'] !== null && $q > $sku['count']) {
                        $q = $sku['count'];
                        $name = $p['name'].($sku['name'] ? ' ('.$sku['name'].')' : '');
                        if ($q > 0) {
                            $this->response['error'] = sprintf(_w('Only %d pcs of %s are available, and you already have all of them in your shopping cart.'), $q, $name);
                        } else {
                            $this->response['error'] = sprintf(_w('Oops! %s just went out of stock and is not available for purchase at the moment. We apologize for the inconvenience.'), $name);
                        }
                        $this->response['q'] = $q;
                    }
                }
            }
            $cart->setQuantity($item_id, $q);
            $this->response['item_total'] = $is_html ?
                shop_currency_html($cart->getItemTotal($item_id), true) :
                shop_currency($cart->getItemTotal($item_id), true);
        } elseif ($v = waRequest::post('service_variant_id')) {
            $cart->setServiceVariantId($item_id, $v);
            $this->response['item_total'] = $is_html ?
                shop_currency_html($cart->getItemTotal($item['parent_id']), true):
                shop_currency($cart->getItemTotal($item['parent_id']), true);
        }

        $total = $cart->total();
        $discount = $cart->discount($order);

        if (!empty($order['params']['affiliate_bonus'])) {
            $discount -= shop_currency(shopAffiliate::convertBonus($order['params']['affiliate_bonus']), $this->getConfig()->getCurrency(true), null, false);
        }

        $this->response['total'] = $is_html ? shop_currency_html($total, true) : shop_currency($total, true);
        $this->response['discount'] = $is_html ? shop_currency_html($discount, true) : shop_currency($discount, true);
        $this->response['discount_numeric'] = $discount;
        $discount_coupon = ifset($order, 'params', 'coupon_discount', 0);
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
            $order['total'] = $total;
            $affiliate_discount = shopFrontendCartAction::getAffiliateDiscount($affiliate_bonus, $order);
            $this->response['affiliate_discount'] = $is_html ? shop_currency_html($affiliate_discount, true) : shop_currency($affiliate_discount, true);
        }
    }
}
