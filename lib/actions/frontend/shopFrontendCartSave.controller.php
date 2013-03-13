<?php

class shopFrontendCartSaveController extends waJsonController
{
    public function execute()
    {
        $cart = new shopCart();
        $item_id = waRequest::post('id');
        if ($q = waRequest::post('quantity', 0, 'int')) {
            if (!wa()->getSetting('ignore_stock_count')) {
                $cart_items_model = new shopCartItemsModel();
                $item = $cart_items_model->getById($item_id);
                if ($item['type'] == 'product') {
                    $sku_model = new shopProductSkusModel();
                    $sku = $sku_model->getById($item['sku_id']);
                    // check quantity
                    if ($sku['count'] !== null && $q > $sku['count']) {
                        $q = $sku['count'];
                        $this->response['q'] = $q;
                    }
                }
            }
            $cart->setQuantity($item_id, $q);
            $item = $cart->getItem($item_id);
            $this->response['item_total'] = shop_currency($item['price'] * $item['quantity'], $item['currency']);
        } elseif ($v = waRequest::post('service_variant_id')) {
            $cart->setServiceVariantId($item_id, $v);
        }
        $this->response['total'] = shop_currency($cart->total(), true);
        $this->response['discount'] = shop_currency($cart->discount(), true);
    }
}
