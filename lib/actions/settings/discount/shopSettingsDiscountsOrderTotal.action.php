<?php

/**
 * Settings form for discounts by order total amount,
 * and submit controller for that form.
 *
 * Also used as a superclass by DiscountsCustomerTotalAction.
 */
class shopSettingsDiscountsOrderTotalAction extends waViewAction
{
    public function execute()
    {
        $this->execByType('order_total');
    }

    public function execByType($type)
    {
        $dbsm = new shopDiscountBySumModel();

        if (waRequest::post()) {
            $sums = waRequest::post('rate_sum');
            $discounts = waRequest::post('rate_discount');
            $rows = array();

            $dbsm->deleteByField('type', $type);
            if (is_array($sums) && is_array($discounts)) {
                foreach ($sums as $k => $sum) {
                    $sum = str_replace(',', '.', $sum);
                    if (!is_numeric($sum) || $sum < 0) {
                        continue;
                    }
                    $discount = (float) str_replace(',', '.', ifset($discounts[$k], 0));
                    $discount = min(max($discount, 0), 100);
                    if ($sum || $discount) {
                        $rows[] = array(
                            'sum' => $sum,
                            'discount' => $discount,
                            'type' => $type,
                        );
                    }
                }
                if ($rows) {
                    $dbsm->multipleInsert($rows);
                }
            }
        }

        $enabled = shopDiscounts::isEnabled($type);
        $def_cur = waCurrency::getInfo(wa()->getConfig()->getCurrency());

        $rates = $dbsm->getByType($type);
        foreach($rates as &$r) {
            $r['sum'] = (float) $r['sum'];
            $r['discount'] = (float) $r['discount'];
        }

        $this->view->assign('rates', $rates);
        $this->view->assign('enabled', $enabled);
        $this->view->assign('def_cur_sym', ifset($def_cur['sign_html'], ifset($def_cur['sign'], wa()->getConfig()->getCurrency())));
    }
}

