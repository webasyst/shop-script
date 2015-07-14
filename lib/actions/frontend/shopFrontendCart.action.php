<?php

class shopFrontendCartAction extends shopFrontendAction
{
    public function execute()
    {
        $this->getResponse()->addHeader("Cache-Control", "no-store, no-cache, must-revalidate");
        $this->getResponse()->addHeader("Expires", date("r"));

        if (waRequest::method() == 'post') {
            $data = wa()->getStorage()->get('shop/checkout', array());
            if ($coupon_code = waRequest::post('coupon_code')) {
                $data['coupon_code'] = $coupon_code;
            } elseif (isset($data['coupon_code'])) {
                unset($data['coupon_code']);
            }

            if (($use = waRequest::post('use_affiliate')) !== null) {
                if ($use) {
                    $data['use_affiliate'] = 1;
                } elseif (isset($data['use_affiliate'])) {
                    unset($data['use_affiliate']);
                }
            }

            wa()->getStorage()->set('shop/checkout', $data);
            wa()->getStorage()->remove('shop/cart');
        }

        $cart = new shopCart();
        $code = $cart->getCode();

        $errors = array();
        $cart_model = new shopCartItemsModel();
        $items = $cart_model->where('code= ?', $code)->order('parent_id')->fetchAll('id');

        if (waRequest::post('checkout')) {
            $saved_quantity = $cart_model->select('id,quantity')->where("type='product' AND code = s:code", array('code' => $code))->fetchAll('id');
            $quantity = waRequest::post('quantity');
            foreach ($quantity as $id => $q) {
                if (isset($saved_quantity[$id]) && ($q != $saved_quantity[$id])) {
                    $cart->setQuantity($id, $q);
                }
            }

            if (wa()->getSetting('ignore_stock_count')) {
                $check_count = false;
            } else {
                $check_count = true;
                if (wa()->getSetting('limit_main_stock') && waRequest::param('stock_id')) {
                    $check_count = waRequest::param('stock_id');
                }
            }
            $not_available_items = $cart_model->getNotAvailableProducts($code, $check_count);
            foreach ($not_available_items as $row) {
                if ($row['sku_name']) {
                    $row['name'] .= ' ('.$row['sku_name'].')';
                }
                if ($row['available']) {
                    if ($row['count'] > 0) {
                        $errors[$row['id']] = sprintf(_w('Only %d pcs of %s are available, and you already have all of them in your shopping cart.'), $row['count'], $row['name']);
                    } else {
                        $errors[$row['id']] = sprintf(_w('Oops! %s just went out of stock and is not available for purchase at the moment. We apologize for the inconvenience. Please remove this product from your shopping cart to proceed.'), $row['name']);
                    }
                } else {
                    $errors[$row['id']] = sprintf(_w('Oops! %s is not available for purchase at the moment. Please remove this product from your shopping cart to proceed.'), $row['name']);
                }
            }
            foreach ($items as $row) {
                if (!$row['quantity'] && !isset($errors[$row['id']])) {
                    $errors[$row['id']] = null;
                }
            }
            if (!$errors) {
                $this->redirect(wa()->getRouteUrl('/frontend/checkout'));
            }
        }

        $this->setThemeTemplate('cart.html');

        $product_ids = $sku_ids = $service_ids = $type_ids = array();
        foreach ($items as $item) {
            $product_ids[] = $item['product_id'];
            $sku_ids[] = $item['sku_id'];
        }

        $product_ids = array_unique($product_ids);
        $sku_ids = array_unique($sku_ids);

        $product_model = new shopProductModel();
        if (waRequest::param('url_type') == 2) {
            $products = $product_model->getWithCategoryUrl($product_ids);
        } else {
            $products = $product_model->getById($product_ids);
        }
        shopRounding::roundProducts($products);

        $sku_model = new shopProductSkusModel();
        $skus = $sku_model->getByField('id', $sku_ids, 'id');
        shopRounding::roundSkus($skus, $products);

        $image_model = new shopProductImagesModel();

        $delete_items = array();
        foreach ($items as $item_id => &$item) {
            if (!isset($skus[$item['sku_id']])) {
                unset($items[$item_id]);
                $delete_items[] = $item_id;
                continue;
            }
            if ($item['type'] == 'product') {
                $item['product'] = $products[$item['product_id']];
                $sku = $skus[$item['sku_id']];
                if ($sku['image_id'] && $sku['image_id'] != $item['product']['image_id']) {
                    $img = $image_model->getById($sku['image_id']);
                    if ($img) {
                        $item['product']['image_id'] = $sku['image_id'];
                        $item['product']['ext'] = $img['ext'];
                    }
                }
                $item['sku_name'] = $sku['name'];
                $item['sku_code'] = $sku['sku'];
                $item['price'] = $sku['price'];
                $item['compare_price'] = $sku['compare_price'];
                $item['currency'] = $item['product']['currency'];
                $type_ids[] = $item['product']['type_id'];

                if (!$item['quantity'] && !isset($errors[$item_id])) {
                    $errors[$item_id] = _w('Oops! %s is not available for purchase at the moment. Please remove this product from your shopping cart to proceed.');;
                }

                if (isset($errors[$item_id])) {
                    $item['error'] = $errors[$item_id];
                    if (strpos($item['error'], '%s') !== false) {
                        $item['error'] = sprintf($item['error'], $item['product']['name'].($item['sku_name'] ? ' ('.$item['sku_name'].')' : ''));
                    }
                }
            }
        }
        unset($item);

        if ($delete_items) {
            $cart_model->deleteByField(array('code' => $code, 'id' => $delete_items));
        }

        $type_ids = array_unique($type_ids);

        // get available services for all types of products
        $type_services_model = new shopTypeServicesModel();
        $rows = $type_services_model->getByField('type_id', $type_ids, true);
        $type_services = array();
        foreach ($rows as $row) {
            $service_ids[$row['service_id']] = $row['service_id'];
            $type_services[$row['type_id']][$row['service_id']] = true;
        }

        // get services for products and skus, part 1
        $product_services_model = new shopProductServicesModel();
        $rows = $product_services_model->getByProducts($product_ids);
        foreach ($rows as $i => $row) {
            if ($row['sku_id'] && !in_array($row['sku_id'], $sku_ids)) {
                unset($rows[$i]);
                continue;
            }
            $service_ids[$row['service_id']] = $row['service_id'];
        }

        $service_ids = array_unique(array_values($service_ids));

        // Get services
        $service_model = new shopServiceModel();
        $services = $service_model->getByField('id', $service_ids, 'id');
        shopRounding::roundServices($services);

        // get services for products and skus, part 2
        $product_services = $sku_services = array();
        shopRounding::roundServiceVariants($rows, $services);
        foreach ($rows as $row) {
            if (!$row['sku_id']) {
                $product_services[$row['product_id']][$row['service_id']]['variants'][$row['service_variant_id']] = $row;
            }
            if ($row['sku_id']) {
                $sku_services[$row['sku_id']][$row['service_id']]['variants'][$row['service_variant_id']] = $row;
            }
        }

        // Get service variants
        $variant_model = new shopServiceVariantsModel();
        $rows = $variant_model->getByField('service_id', $service_ids, true);
        shopRounding::roundServiceVariants($rows, $services);
        foreach ($rows as $row) {
            $services[$row['service_id']]['variants'][$row['id']] = $row;
            unset($services[$row['service_id']]['variants'][$row['id']]['id']);
        }

        // When assigning services into cart items, we don't want service ids there
        foreach ($services as &$s) {
            unset($s['id']);
        }
        unset($s);

        // Assign service and product data into cart items
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
                        continue;
                    }

                    if ($s['currency'] == '%') {
                        foreach ($s['variants'] as $v_id => $v) {
                            $s['variants'][$v_id]['price'] = $v['price'] *  $item['price'] / 100;
                        }
                        $s['currency'] = $item['currency'];
                    }

                    if (count($s['variants']) == 1) {
                        $v = reset($s['variants']);
                        $s['price'] = $v['price'];
                        unset($s['variants']);
                    }
                }
                unset($s);
                uasort($item_services, array('shopServiceModel', 'sortServices'));

                $items[$item_id]['services'] = $item_services;
            } else {
                $items[$item['parent_id']]['services'][$item['service_id']]['id'] = $item['id'];
                if (isset($item['service_variant_id'])) {
                    $items[$item['parent_id']]['services'][$item['service_id']]['variant_id'] = $item['service_variant_id'];
                }
                unset($items[$item_id]);
            }
        }


        foreach ($items as $item_id => $item) {
            $price = shop_currency($item['price'] * $item['quantity'], $item['currency'], null, false);
            if (isset($item['services'])) {
                foreach ($item['services'] as $s) {
                    if (!empty($s['id'])) {
                        if (isset($s['variants'])) {
                            $price += shop_currency($s['variants'][$s['variant_id']]['price'] * $item['quantity'], $s['currency'], null, false);
                        } else {
                            $price += shop_currency($s['price'] * $item['quantity'], $s['currency'], null, false);
                        }
                    }
                }
            }
            $items[$item_id]['full_price'] = $price;
        }


        $total = $cart->total(false);
        $order = array('total' => $total, 'items' => $items);
        $order['discount'] = $discount = shopDiscounts::calculate($order);
        $order['total'] = $total = $total - $order['discount'];

        $data = wa()->getStorage()->get('shop/checkout');
        $this->view->assign('cart', array(
            'items' => $items,
            'total' => $total,
            'count' => $cart->count()
        ));

        $this->view->assign('coupon_code', isset($data['coupon_code']) ? $data['coupon_code'] : '');
        if (!empty($data['coupon_code']) && !empty($order['params']['coupon_discount'])) {
            $this->view->assign('coupon_discount', $order['params']['coupon_discount']);
        }
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

            $order['currency'] = $this->getConfig()->getCurrency(false);
            $add_affiliate_bonus = shopAffiliate::calculateBonus($order);
            $this->view->assign('add_affiliate_bonus', round($add_affiliate_bonus, 2));
        }
        $this->view->assign('discount', $discount);

        /**
         * @event frontend_cart
         * @return array[string]string $return[%plugin_id%] html output
         */
        $this->view->assign('frontend_cart', wa()->event('frontend_cart'));

        $this->getResponse()->setTitle(_w('Cart'));

        $checkout_flow = new shopCheckoutFlowModel();
        $checkout_flow->add(array(
            'code' => $code,
            'step' => 0,
            'description' => null /* TODO: Error message here if exists */
        ));

    }

}