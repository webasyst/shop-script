<?php

class shopFrontendCartAddController extends waJsonController
{
    public function execute()
    {
        $cart_model = new shopCartItemsModel();
        $code = waRequest::cookie('shop_cart');
        if (!$code) {
            $code = md5(uniqid(time(), true));
            // header for IE
            wa()->getResponse()->addHeader('P3P', 'CP="NOI ADM DEV COM NAV OUR STP"');
            // set cart cookie
            wa()->getResponse()->setCookie('shop_cart', $code, time() + 30 * 86400, null, '', false, true);
        }

        $data = waRequest::post();

        $is_html = waRequest::request('html');

        if (isset($data['parent_id'])) {
            $parent = $cart_model->getById($data['parent_id']);
            unset($parent['id']);
            $parent['parent_id'] = $data['parent_id'];
            $parent['type'] = 'service';
            $parent['service_id'] = $data['service_id'];
            if (isset($data['service_variant_id'])) {
                $parent['service_variant_id'] = $data['service_variant_id'];
            } else {
                $service_model = new shopServiceModel();
                $service = $service_model->getById($data['service_id']);
                $parent['service_variant_id'] = $service['variant_id'];
            }
            $cart = new shopCart();
            
            $id = $cart->addItem($parent);
            $total = $cart->total();
            $discount = $cart->discount();
            
            $this->response['id'] = $id;
            $this->response['total'] = $is_html ? shop_currency_html($total, true) : shop_currency($total, true);
            $this->response['count'] = $cart->count();
            $this->response['discount'] = $is_html ? shop_currency_html($discount, true) : shop_currency($discount, true);
            $item_total = $cart->getItemTotal($data['parent_id']);
            $this->response['item_total'] = $is_html ? shop_currency_html($item_total, true) : shop_currency($item_total, true);
            
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
            
            return;
        }

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
                $c = $cart_model->countSku($code, $sku['id']);
                if ($sku['count'] !== null && $c + $quantity > $sku['count']) {
                    $quantity = $sku['count'] - $c;
                    $name = $product['name'].($sku['name'] ? ' ('.$sku['name'].')' : '');
                    if (!$quantity) {
                        $this->errors = sprintf(_w('Only %d pcs of %s are available, and you already have all of them in your shopping cart.'), $sku['count'], $name);
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
            $item = $cart_model->getItemByProductAndServices($code, $product['id'], $sku['id'], $services);
            if ($item) {
                $item_id = $item['id'];
                $cart_model->updateById($item_id, array('quantity' => $item['quantity'] + $quantity));
                if ($services) {
                    $cart_model->updateByField('parent_id', $item_id, array('quantity' => $item['quantity'] + $quantity));
                }
            }
            if (!$item_id) {
                $data = array(
                    'code' => $code,
                    'contact_id' => $this->getUser()->getId(),
                    'product_id' => $product['id'],
                    'sku_id' => $sku['id'],
                    'create_datetime' => date('Y-m-d H:i:s'),
                    'quantity' => $quantity
                );
                $item_id = $cart_model->insert($data + array('type' => 'product'));
                if ($services) {
                    foreach ($services as $service_id => $variant_id) {
                        $data_service = array(
                            'service_id' => $service_id,
                            'service_variant_id' => $variant_id,
                            'type' => 'service',
                            'parent_id' => $item_id
                        );
                        $cart_model->insert($data + $data_service);
                    }
                }
            }
            // update shop cart session data
            $shop_cart = new shopCart();
            wa()->getStorage()->remove('shop/cart');
            $total = $shop_cart->total();

            if (waRequest::isXMLHttpRequest()) {
                $this->response['item_id'] = $item_id;
                $this->response['total'] = $is_html ? shop_currency_html($total, true) : shop_currency($total, true);
                $this->response['count'] = $shop_cart->count();
            } else {
                $this->redirect(waRequest::server('HTTP_REFERER'));
            }
        } else {
            throw new waException('product not found');
        }

    }
}