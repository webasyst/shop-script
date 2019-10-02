<?php

class shopMarketingDiscountsOrderTotalSaveController extends shopMarketingSettingsJsonController
{
    public function execute()
    {
        $this->saveByType('order_total');
    }

    protected function saveByType($type)
    {
        $dbsm = new shopDiscountBySumModel();

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
}