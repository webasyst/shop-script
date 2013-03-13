<?php

class shopFrontendCartAction extends waViewAction
{
    public function execute()
    {
        if (!waRequest::isXMLHttpRequest()) {
            $this->setLayout(new shopFrontendLayout());
        }


        if (waRequest::method() == 'post') {
            if ($coupon_code = waRequest::post('coupon_code')) {
                $data = wa()->getStorage()->get('shop/checkout', array());
                $data['coupon_code'] = $coupon_code;
                wa()->getStorage()->set('shop/checkout', $data);
                wa()->getStorage()->remove('shop/cart');
            }

            if (($use = waRequest::post('use_affiliate')) !== null) {
                $data = wa()->getStorage()->get('shop/checkout', array());
                if ($use) {
                    $data['use_affiliate'] = 1;
                } elseif (isset($data['use_affiliate'])) {
                    unset($data['use_affiliate']);
                }
                wa()->getStorage()->set('shop/checkout', $data);
                wa()->getStorage()->remove('shop/cart');
            }
        }

        if (waRequest::post('checkout')) {
            $this->redirect(wa()->getRouteUrl('/frontend/checkout'));
        }

        $this->setThemeTemplate('cart.html');

        $cart = new shopCart();
        $code = $cart->getCode();

        $cart_model = new shopCartItemsModel();
        $items = $cart_model->where('code= ?', $code)->order('parent_id')->fetchAll('id');


        $product_ids = $sku_ids = $service_ids = $type_ids = array();
        foreach ($items as $item) {
            $product_ids[] = $item['product_id'];
            $sku_ids[] = $item['sku_id'];
        }

        $product_ids = array_unique($product_ids);
        $sku_ids = array_unique($sku_ids);

        $product_model = new shopProductModel();
        $products = $product_model->getByField('id', $product_ids, 'id');

        $sku_model = new shopProductSkusModel();
        $skus = $sku_model->getByField('id', $sku_ids, 'id');

        foreach ($items as &$item) {
            if ($item['type'] == 'product') {
                $item['product'] = $products[$item['product_id']];
                $sku = $skus[$item['sku_id']];
                $item['sku_name'] = $sku['name'];
                $item['price'] = $sku['price'];
                $item['currency'] = $item['product']['currency'];
                $type_ids[] = $item['product']['type_id'];
            }
        }
        unset($item);

        $type_ids = array_unique($type_ids);

        // get available services for all types of products
        $type_services_model = new shopTypeServicesModel();
        $rows = $type_services_model->getByField('type_id', $type_ids, true);
        $type_services = array();
        foreach ($rows as $row) {
            $service_ids[] = $row['service_id'];
            $type_services[$row['type_id']][$row['service_id']] = true;
        }

        // get services for all products
        $product_services_model = new shopProductServicesModel();
        $rows = $product_services_model->getByProducts($product_ids);

        $product_services = $sku_services = array();
        foreach ($rows as $row) {
            if ($row['sku_id'] && !in_array($row['sku_id'], $sku_ids)) {
                continue;
            }
            $service_ids[] = $row['service_id'];
            if (!$row['sku_id']) {
                $product_services[$row['product_id']][$row['service_id']]['variants'][$row['service_variant_id']] = $row;
            }
            if ($row['sku_id']) {
                $sku_services[$row['sku_id']][$row['service_id']]['variants'][$row['service_variant_id']] = $row;
            }
        }

        $service_ids = array_unique($service_ids);

        $service_model = new shopServiceModel();
        $variant_model = new shopServiceVariantsModel();
        $services = $service_model->getByField('id', $service_ids, 'id');
        foreach ($services as &$s) {
            unset($s['id']);
        }
        unset($s);

        $rows = $variant_model->getByField('service_id', $service_ids, true);
        foreach ($rows as $row) {
            $services[$row['service_id']]['variants'][$row['id']] = $row;
            unset($services[$row['service_id']]['variants'][$row['id']]['id']);
        }

        foreach ($items as $item_id => $item) {
            if ($item['type'] == 'product') {
                $p = $item['product'];
                $item_services = array();
                // services from type settings
                if (isset($type_services[$p['type_id']])) {
                    foreach ($type_services[$p['type_id']] as $service_id => &$s) {
                        $item_services[$service_id] = $services[$service_id];
                    }
                }
                // services from product settings
                if (isset($product_services[$item['product_id']])) {
                    foreach ($product_services[$item['product_id']] as $service_id => $s) {
                        if (!isset($s['status']) || $s['status']) {
                            if (!isset($item_services[$service_id])) {
                                $item_services[$service_id] = $services[$service_id];
                            }
                            // update variants
                            foreach ($s['variants'] as $variant_id => $v) {
                                if ($v['status']) {
                                    if ($v['price'] !== null) {
                                        $item_services[$service_id]['variants'][$variant_id]['price'] = $v['price'];
                                    }
                                } else {
                                    unset($item_services[$service_id]['variants'][$variant_id]);
                                }
                            }
                        } elseif (isset($item_services[$service_id])) {
                            // remove disabled service
                            unset($item_services[$service_id]);
                        }
                    }
                }
                // services from sku settings
                if (isset($sku_services[$item['sku_id']])) {
                    foreach ($sku_services[$item['sku_id']] as $service_id => $s) {
                        if (!isset($s['status']) || $s['status']) {
                            // update variants
                            foreach ($s['variants'] as $variant_id => $v) {
                                if ($v['status']) {
                                    if ($v['price'] !== null) {
                                        $item_services[$service_id]['variants'][$variant_id]['price'] = $v['price'];
                                    }
                                } else {
                                    unset($item_services[$service_id]['variants'][$variant_id]);
                                }
                            }
                        } elseif (isset($item_services[$service_id])) {
                            // remove disabled service
                            unset($item_services[$service_id]);
                        }
                    }
                }
                foreach ($item_services as $s_id => &$s) {
                    if (!$s['variants']) {
                        unset($item_services[$s_id]);
                    } elseif (count($s['variants']) == 1) {
                        $v = reset($s['variants']);
                        $s['price'] = $v['price'];
                        unset($s['variants']);
                    }
                }
                $items[$item_id]['services'] = $item_services;
            } else {
                $items[$item['parent_id']]['services'][$item['service_id']]['id'] = $item['id'];
                if (isset($item['service_variant_id'])) {
                    $items[$item['parent_id']]['services'][$item['service_id']]['variant_id'] = $item['service_variant_id'];
                }
                unset($items[$item_id]);
            }
        }

        $order = array('total' => $cart->total(false));
        $discount = shopDiscounts::calculate($order);
        $total = $cart->total();
        $data = wa()->getStorage()->get('shop/checkout');
        $this->view->assign('cart', array(
            'items' => $items,
            'total' => $total,
            'count' => $cart->count()
        ));
        $this->view->assign('coupon_code', isset($data['coupon_code']) ? $data['coupon_code'] : '');
        if (shopAffiliate::isEnabled()) {
            $affiliate_bonus = 0;
            if ($this->getUser()->isAuth()) {
                $customer_model = new shopCustomerModel();
                $customer = $customer_model->getById($this->getUser()->getId());
                $affiliate_bonus = $customer ? round($customer['affiliate_bonus'], 2) : 0;
            }
            $this->view->assign('affiliate_bonus', $affiliate_bonus);
            $use = !empty($data['use_affiliate']);
            $this->view->assign('use_affiliate', $use);
            if ($use) {
                $discount -= shop_currency(shopAffiliate::convertBonus($order['params']['affiliate_bonus']), $this->getConfig()->getCurrency(true), null, false);
                $this->view->assign('used_affiliate_bonus', $order['params']['affiliate_bonus']);
            }
        }
        $this->view->assign('discount', $discount);

        /**
         * @event frontend_cart
         * @return array[string]string $return[%plugin_id%] html output
         */
        $this->view->assign('frontend_cart', wa()->event('frontend_cart'));

        $this->getResponse()->setTitle(_w('Cart'));
    }

}