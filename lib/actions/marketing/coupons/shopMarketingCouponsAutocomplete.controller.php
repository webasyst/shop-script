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

        $only_active = $this->checkOnlyActive();
        $datetime = date('Y-m-d H:i:s');
        if ($only_active) {
            $where .= " AND ((`limit` IS NULL) OR (`used` < `limit`)) 
                        AND ((`expire_datetime` IS NULL) OR (`expire_datetime` > '{$datetime}'))";
        }

        $sql = "SELECT *
                FROM {$cm->getTableName()}
                {$where}
                ORDER BY create_datetime DESC
                LIMIT 10";

        $coupons = $cm->query($sql, [$ignore_ids])->fetchAll();

        $products = $this->getProducts();
        $product_ids = array();
        if (!empty($products)) {
            $product_ids = array_column($products, 'value');
        }

        $user_right = !!wa()->getUser()->getRights('shop', 'marketing');

        foreach ($coupons as &$coupon) {
            $coupon['discount_string'] = shopCouponModel::formatValue($coupon);
            if (!empty($coupon['expire_datetime'])) {
                $coupon['expire_datetime_string'] = waDateTime::format('date', $coupon['expire_datetime']);
            }

            $coupon['valid'] = true;
            if (!empty($coupon['products_hash']) && !empty($product_ids)) {
                $hash = $coupon['products_hash'];
                $collection = new shopProductsCollection($hash);
                $collection->addWhere('p.id IN (' . implode(',', $product_ids) . ')');
                $allowed_products = $collection->getProducts('id');
                if (empty($allowed_products)) {
                    $coupon['valid'] = false;
                }
            }

            if ($coupon['valid']
                && (strlen($coupon['limit']) > 0 && $coupon['used'] > $coupon['limit'])
                || (isset($coupon['expire_datetime']) && $datetime > $coupon['expire_datetime'])
            ) {
                $coupon['valid'] = false;
            }
            $coupon['right'] = $user_right;
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

    protected function checkOnlyActive()
    {
        return waRequest::request('only_active', false, waRequest::TYPE_INT);
    }

    protected function getProducts()
    {
        return waRequest::request('products', array(), waRequest::TYPE_ARRAY);
    }
}