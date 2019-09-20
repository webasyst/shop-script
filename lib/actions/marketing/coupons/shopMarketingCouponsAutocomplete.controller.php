<?php

class shopMarketingCouponsAutocompleteController extends waJsonController
{
    public function execute()
    {
        $data = $this->getCoupons();
        
        $this->getResponse()->addHeader('Content-Type', 'application/json');
        $this->getResponse()->sendHeaders();
        
        die(json_encode($data));
    }

    /**
     * @return array
     * @throws waException
     */
    protected function getCoupons()
    {
        $q = $this->getTerm();
        $cm = new shopCouponModel();
        $query = $cm->escape($q);

        $where = "WHERE (code LIKE '%{$query}%' OR comment LIKE '%{$query}%')";

        $ignore_ids = $this->getIgnoreIds();
        if (!empty($ignore_ids)) {
            $where .= " AND id NOT IN (?)";
        }

        $sql = "SELECT *
                FROM {$cm->getTableName()}
                {$where}
                ORDER BY create_datetime DESC
                LIMIT 10";

        $coupons = $cm->query($sql, [$ignore_ids])->fetchAll();

        foreach ($coupons as &$coupon) {
            $coupon['discount_string'] = shopMarketingCouponsAction::formatValue($coupon);
            if (!empty($coupon['expire_datetime'])) {
                $coupon['expire_datetime_string'] = waDateTime::format('date', $coupon['expire_datetime']);
            }
            
            $coupon = [
                'value' => $coupon['id'],
                'label' => $coupon['code'],
                'data'  => $coupon,
            ];
        }
        unset($coupon);

        return $coupons;
    }

    /**
     * @return string
     */
    protected function getTerm()
    {
        return trim((string)waRequest::request('term', null, waRequest::TYPE_STRING_TRIM));
    }

    protected function getIgnoreIds()
    {
        return waRequest::request('coupon_id', [], waRequest::TYPE_ARRAY_TRIM);
    }
}