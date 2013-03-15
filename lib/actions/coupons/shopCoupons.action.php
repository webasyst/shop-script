<?php

/**
 * "Coupons" module represents discount coupons settings page in Orders tab.
 *
 * This controller loads basic layout with inner sidebar, then uses
 * XHR to load content from other controllers.
 */
class shopCouponsAction extends waViewAction
{
    public function execute()
    {
        $curm = new shopCurrencyModel();
        $currencies = $curm->getAll('code');

        $coupm = new shopCouponModel();
        $coupons = $coupm->order('id DESC')->fetchAll('code');
        foreach($coupons as &$c) {
            $c['enabled'] = self::isEnabled($c);
            $c['hint'] = self::formatValue($c, $currencies);
        }
        unset($c);

        $order_model = new shopOrderModel();
        $count_new = $order_model->getStateCounters('new');

        $this->view->assign(array(
            'coupons' => $coupons,
            'order_count_new' => $count_new
        ));
    }

    public static function formatValue($c, $curr = null)
    {
        static $currencies = null;
        if ($currencies === null) {
            if ($curr) {
                $currencies = $curr;
            } else {
                $curm = new shopCurrencyModel();
                $currencies = $curm->getAll('code');
            }
        }

        if ($c['type'] == '$FS') {
            return _w('Free shipping');
        } else if ($c['type'] === '%') {
            return waCurrency::format('%0', $c['value'], 'USD').'%';
        } else if (!empty($currencies[$c['type']])) {
            return waCurrency::format('%0{s}', $c['value'], $c['type']);
        } else {
            // Coupon of unknown type. Possibly from a plugin?..
            return '';
        }
    }

    public static function isEnabled($c)
    {
        $result = $c['limit'] === null || $c['limit'] > $c['used'];
        return $result && ($c['expire_datetime'] === null || strtotime($c['expire_datetime']) > time());
    }
}

