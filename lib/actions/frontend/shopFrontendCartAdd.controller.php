<?php

class shopFrontendCartAddController extends waJsonController
{
    /**
     * @var shopCart
     */
    protected $cart;

    /**
     * @var shopCartItemsModel
     */
    protected $cart_model;
    /**
     * @var bool
     */
    protected $is_html;

    public function execute()
    {
        $code = waRequest::cookie('shop_cart');
        if (!$code) {
            $code = md5(uniqid(time(), true));
            // header for IE
            wa()->getResponse()->addHeader('P3P', 'CP="NOI ADM DEV COM NAV OUR STP"');
            // set cart cookie
            wa()->getResponse()->setCookie('shop_cart', $code, time() + 30 * 86400, null, '', false, true);
        }
        $this->cart = new shopCart($code);
        $this->cart_model = new shopCartItemsModel();

        $data = waRequest::post();
        $this->is_html = waRequest::request('html');

        // add service
        if (isset($data['parent_id'])) {
            $this->addService($data);
            return;
        }

        // add sku
        $sku_model = new shopProductSkusModel();
        $product_model = new shopProductModel();
        if (!isset($data['product_id'])) {
            $sku = $sku_model->getById($data['sku_id']);
            $product = $product_model->getById($sku['product_id']);
        } else {
            $product = $product_model->getById($data['product_id']);
            if (isset($data['sku_id'])) {
                $sku = $sku_model->getById($data['sku_id']);
            } else {
                if (isset($data['features'])) {
                    $product_features_model = new shopProductFeaturesModel();
                    $sku_id = $product_features_model->getSkuByFeatures($product['id'], $data['features']);
                    if ($sku_id) {
                        $sku = $sku_model->getById($sku_id);
                    } else {
                        $sku = null;
                    }
                } else {
                    $sku = $sku_model->getById($product['sku_id']);
                    if (!$sku['available']) {
                        $sku = $sku_model->getByField(array('product_id' => $product['id'], 'available' => 1));
                    }

                    if (!$sku) {
                        $this->errors = _w('This product is not available for purchase');
                        return;
                    }
                }
            }
        }

        $quantity = waRequest::post('quantity', 1);
        if ($product && $sku) {
            // check quantity
            if (!wa()->getSetting('ignore_stock_count')) {

                // limit by main stock
                if (wa()->getSetting('limit_main_stock') && waRequest::param('stock_id')) {
                    $product_stocks_model = new shopProductStocksModel();
                    $row = $product_stocks_model->getByField(array('sku_id' => $sku['id'], 'stock_id' => waRequest::param('stock_id')));
                    if ($row) {
                        $sku['count'] = $row['count'];
                    }
                }

                $c = $this->cart_model->countSku($code, $sku['id']);
                if ($sku['count'] !== null && $c + $quantity > $sku['count']) {
                    $quantity = $sku['count'] - $c;
                    $name = $product['name'].($sku['name'] ? ' ('.$sku['name'].')' : '');
                    if (!$quantity) {
                        if ($sku['count'] > 0) {
                            $this->errors = sprintf(_w('Only %d pcs of %s are available, and you already have all of them in your shopping cart.'), $sku['count'], $name);
                        } else {
                            $this->errors = sprintf(_w('Oops! %s just went out of stock and is not available for purchase at the moment. We apologize for the inconvenience.'), $name);
                        }
                        return;
                    } else {
                        $this->response['error'] = sprintf(_w('Only %d pcs of %s are available, and you already have all of them in your shopping cart.'), $sku['count'], $name);
                    }
                }
            }
            $services = waRequest::post('services', array());
            if ($services) {
                $variants = waRequest::post('service_variant');
                $temp = array();
                $service_ids = array();
                foreach ($services as $service_id) {
                    if (isset($variants[$service_id])) {
                        $temp[$service_id] = $variants[$service_id];
                    } else {
                        $service_ids[] = $service_id;
                    }
                }
                if ($service_ids) {
                    $service_model = new shopServiceModel();
                    $temp_services = $service_model->getById($service_ids);
                    foreach ($temp_services as $row) {
                        $temp[$row['id']] = $row['variant_id'];
                    }
                }
                $services = $temp;
            }
            $item_id = null;
            $item = $this->cart_model->getItemByProductAndServices($code, $product['id'], $sku['id'], $services);
            if ($item) {
                $item_id = $item['id'];
                $this->cart->setQuantity($item_id, $item['quantity'] + $quantity);
            }
            if (!$item_id) {
                $data = array(
                    'create_datetime' => date('Y-m-d H:i:s'),
                    'product_id' => $product['id'],
                    'sku_id' => $sku['id'],
                    'quantity' => $quantity,
                    'type' => 'product'
                );
                if ($services) {
                    $data_services = array();
                    foreach ($services as $service_id => $variant_id) {
                        $data_services[] = array(
                            'service_id' => $service_id,
                            'service_variant_id' => $variant_id,
                        );
                    }
                } else {
                    $data_services = array();
                }
                $item_id = $this->cart->addItem($data, $data_services);
            }
            if (waRequest::isXMLHttpRequest()) {
                $discount = $this->cart->discount($order);
                if (!empty($order['params']['affiliate_bonus'])) {
                    $discount -= shop_currency(shopAffiliate::convertBonus($order['params']['affiliate_bonus']), $this->getConfig()->getCurrency(true), null, false);
                }
                $this->response['item_id'] = $item_id;
                $this->response['total'] = $this->currencyFormat($this->cart->total());
                $this->response['discount'] = $this->currencyFormat($discount);
                $this->response['discount_coupon'] = $this->currencyFormat(ifset($order['params']['coupon_discount'], 0), true);
                $this->response['count'] = $this->cart->count();
                if (waRequest::get("items")) {
                    $this->response['items'] = $this->getCartItems();
                }
            } else {
                $this->redirect(waRequest::server('HTTP_REFERER'));
            }
        } else {
            throw new waException('product not found');
        }
    }

    /**
     * Cart items for json response
     * @return array
     */
    protected function getCartItems()
    {
        $rows = $this->cart->items();
        $items = array();
        foreach($rows as $row) {
            $item = array();
            foreach (array('id', 'product_id', 'name', 'quantity', 'sku_id', 'sku_code', 'sku_name') as $key) {
                $item[$key] = $row[$key];
            }
            $p = $row['product'];
            $item['image_url'] = shopImage::getUrl(array(
                'product_id' => $row['product_id'],
                'filename' => $p['image_filename'],
                'id' => $p['image_id'],
                'ext' => $p['ext']
            ), "96x96");
            $item['frontend_url'] = wa()->getRouteUrl('shop/frontend/product', array(
                'product_url' => $p['url'], 'category_url' => ifset($p['category_url'], '')));
            $item['price'] = $this->currencyFormat($row['price'], $row['currency']);
            $price = shop_currency($row['price'] * $row['quantity'], $row['currency'], null, false);
            $item['services'] = array();
            if (!empty($row['services'])) {
                foreach ($row['services'] as $s) {
                    $item_s = array();
                    foreach (array('id', 'parent_id', 'name', 'quantity', 'service_id', 'service_name', 'service_variant_id', 'variant_name') as $key) {
                        if (isset($s[$key])) {
                            $item_s[$key] = $s[$key];
                        }
                    }
                    $item_s['price'] = $this->currencyFormat($s['price'], $s['currency']);
                    $price += shop_currency($s['price'] * $s['quantity'], $s['currency'], null, false);
                    $item['services'][] = $item_s;
                }
            }
            $item['full_price'] = $this->currencyFormat($price, true);
            $items[] = $item;
        }
        return $items;
    }

    /**
     * @param float $val
     * @param string|bool $currency
     * @return string
     */
    protected function currencyFormat($val, $currency = true)
    {
        return $this->is_html ? shop_currency_html($val, $currency) : shop_currency($val, $currency);
    }

    /**
     * @param $data
     */
    protected function addService($data)
    {
        $item = $this->cart_model->getById($data['parent_id']);
        if (!$item) {
            $this->errors = _w('Error');
            return;
        }
        unset($item['id']);
        $item['parent_id'] = $data['parent_id'];
        $item['type'] = 'service';
        $item['service_id'] = $data['service_id'];
        if (isset($data['service_variant_id'])) {
            $item['service_variant_id'] = $data['service_variant_id'];
        } else {
            $service_model = new shopServiceModel();
            $service = $service_model->getById($data['service_id']);
            $item['service_variant_id'] = $service['variant_id'];
        }

        if ($row = $this->cart_model->getByField(array('parent_id' => $data['parent_id'], 'service_variant_id' => $item['service_variant_id']))) {
            $id = $row['id'];
        } else {
            $id = $this->cart->addItem($item);
        }
        $total = $this->cart->total();
        $discount = $this->cart->discount($order);
        if (!empty($order['params']['affiliate_bonus'])) {
            $discount -= shop_currency(shopAffiliate::convertBonus($order['params']['affiliate_bonus']), $this->getConfig()->getCurrency(true), null, false);
        }

        $this->response['id'] = $id;
        $this->response['total'] = $this->currencyFormat($total);
        $this->response['count'] = $this->cart->count();
        $this->response['discount'] = $this->currencyFormat($discount);
        $this->response['discount_coupon'] = $this->currencyFormat(ifset($order['params']['coupon_discount'], 0), true);

        $item_total = $this->cart->getItemTotal($data['parent_id']);
        $this->response['item_total'] = $this->currencyFormat($item_total);

        if (shopAffiliate::isEnabled()) {
            $add_affiliate_bonus = shopAffiliate::calculateBonus(array(
                'total' => $total,
                'discount' => $discount,
                'items' => $this->cart->items(false)
            ));
            $this->response['add_affiliate_bonus'] = sprintf(
                _w("This order will add +%s points to your affiliate bonus."),
                round($add_affiliate_bonus, 2)
            );
        }
    }
}