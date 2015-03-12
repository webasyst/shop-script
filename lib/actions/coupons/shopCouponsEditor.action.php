<?php

/**
 * Single coupon editor form, and controller that accepts POST data from that form.
 */
class shopCouponsEditorAction extends waViewAction
{
    public function execute()
    {
        $id = waRequest::request('id', 0, 'int');

        $coupm = new shopCouponModel();
        $coupon = $coupm->getById($id);
        if ($coupon) {
            $coupon['value'] = (float) $coupon['value'];
            if (waRequest::request('delete')) {
                $coupm->delete($id);
                exit;
            }
        } else if ($id) {
            throw new waException('Coupon not found.', 404);
        } else {
            // show form to create new coupon
            $coupon = $coupm->getEmptyRow();
            $coupon['code'] = self::generateCode();
        }

        //
        // Process POST data
        //
        $duplicate_code_error = null;
        if (waRequest::post()) {
            $post_coupon = waRequest::post('coupon');
            if (is_array($post_coupon)) {
                $post_coupon = array_intersect_key($post_coupon, $coupon) + array(
                    'code' => '',
                    'type' => '%',
                );
                if (empty($post_coupon['limit'])) {
                    $post_coupon['limit'] = null;
                }
                if (!empty($post_coupon['value'])) {
                    $post_coupon['value'] = (float) str_replace(',', '.', $post_coupon['value']);
                }

                if (empty($post_coupon['code'])) {
                    throw new waException('Bad parameters', 500); // rely on JS validation
                }

                if (!empty($post_coupon['expire_datetime']) && strlen($post_coupon['expire_datetime']) == 10) {
                    $post_coupon['expire_datetime'] .= ' 23:59:59';
                }

                if ($post_coupon['type'] == '%') {
                    $post_coupon['value'] = min(max($post_coupon['value'], 0), 100);
                }
                
                if ($id) {
                    $coupm->updateById($id, $post_coupon);
                    echo '<script>window.location.hash = "#/coupons/'.$id.'";$.orders.dispatch();</script>';
                    exit;
                } else {
                    $post_coupon['create_contact_id'] = wa()->getUser()->getId();
                    $post_coupon['create_datetime'] = date('Y-m-d H:i:s');
                    try {
                        $id = $coupm->insert($post_coupon);
                        echo '<script>'.
                                    'var counter = $("#s-coupons .count");'.
                                    'var cnt = parseInt(counter.text(), 10) || 0;'.
                                    'counter.text(cnt + 1);'.
                                    'window.location.hash = "#/coupons/'.$id.'";'.
                                '</script>';
                        exit;
                    } catch (waDbException $e) {
                        // Duplicate code. Show error in form.
                        $coupon = $post_coupon + $coupon;
                        $duplicate_code_error = true;
                    }
                }
            }
        }

        // Coupon types
        $curm = new shopCurrencyModel();
        $currencies = $curm->getAll('code');
        $types = self::getTypes($currencies);

        // Orders this coupon was used for
        $orders = array();
        $overall_discount = 0;
        $overall_discount_formatted = '';
        if ($coupon['id']) {
            $om = new shopOrderModel();
            $cm = new shopCurrencyModel();
            $orders = $om->getByCoupon($coupon['id']);
            shopHelper::workupOrders($orders);
            foreach($orders as &$o) {
                $discount = ifset($o['params']['coupon_discount'], 0);
                $o['coupon_discount_formatted'] = waCurrency::format('%{h}', $discount, $o['currency']);
                if ($discount) {
                    $overall_discount += $cm->convert($discount, $o['currency'], $cm->getPrimaryCurrency());
                    $o['coupon_discount_percent'] = round($discount*100.0 / ($discount + $o['total']), 1);
                } else {
                    $o['coupon_discount_percent'] = 0;
                }
            }
            unset($o);
            $overall_discount_formatted = waCurrency::format('%{h}', $overall_discount, $cm->getPrimaryCurrency());
        }
        $this->view->assign('types', $types);
        $this->view->assign('orders', $orders);
        $this->view->assign('coupon', $coupon);
        $this->view->assign('duplicate_code_error', $duplicate_code_error);
        $this->view->assign('overall_discount', $overall_discount);
        $this->view->assign('overall_discount_formatted', $overall_discount_formatted);
        $this->view->assign('formatted_value', shopCouponsAction::formatValue($coupon, $currencies));
        $this->view->assign('is_enabled', shopCouponsAction::isEnabled($coupon));
    }

    public static function getTypes($currencies)
    {
        $result = array(
            '%' => _w('% Discount'),
        );
        foreach($currencies as $c) {
            $info = waCurrency::getInfo($c['code']);
            $result[$c['code']] = $info['sign'].' '.$c['code'];
        }
        $result['$FS'] = _w('Free shipping');
        return $result;
    }

    public static function generateCode()
    {
        $alphabet = "QWERTYUIOPASDFGHJKLZXCVBNM1234567890";
        $result = '';
        while(strlen($result) < 8) {
            $result .= $alphabet{mt_rand(0, strlen($alphabet)-1)};
        }
        return $result;
    }
}

