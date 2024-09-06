<?php

class shopCouponGetListMethod extends shopApiMethod
{
    public function execute()
    {
        $offset = waRequest::request('offset', 0, 'int');
        $limit = waRequest::request('limit', 100, 'int');
        $search = waRequest::request('search', null, 'string');
        $active_only = !!waRequest::request('active_only', true);

        $this->validateRequest($offset, $limit, $search, $active_only);

        list($coupons, $total_count) = $this->getCoupons($offset, $limit, $search, $active_only);
        $this->response = [
            'offset' => $offset,
            'limit' => $limit,
            'count' => $total_count,
            'coupons' => $coupons,
        ];
    }

    protected function validateRequest($offset, $limit, $search, $active_only)
    {
        if ($offset < 0) {
            throw new waAPIException('invalid_param', 'Param offset must be greater than or equal to zero');
        }
        if ($limit < 0) {
            throw new waAPIException('invalid_param', 'Param limit must be greater than or equal to zero');
        }
    }

    protected function getCoupons($offset, $limit, $search, $active_only)
    {
        $params = [];
        $active_only_sql = $search_sql = '';
        if ($search !== null && strlen($search)) {
            $search_sql = "AND (`code` LIKE ? OR `comment` LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        if ($active_only) {
            $active_only_sql = "AND (expire_datetime IS NULL OR expire_datetime > ?) AND (`limit` is NULL OR `limit` < `used`)";
            $params[] = date('Y-m-d H:i:s');
        }

        $coupon_model = new shopCouponModel();
        $from_and_where_sql = "
            FROM shop_coupon
            WHERE 1=1 {$search_sql} {$active_only_sql}
        ";

        $total_count = $coupon_model->query("
            SELECT COUNT(*) {$from_and_where_sql}
        ", $params)->fetchField();

        if ($total_count <= 0) {
            return [[], 0];
        }

        $limit = (int) $limit;
        $offset = (int) $offset;
        $coupons = $coupon_model->query("
            SELECT * {$from_and_where_sql}
            ORDER BY code DESC
            LIMIT {$offset}, {$limit}
        ", $params)->fetchAll('id');

        return [array_values($coupons), $total_count];
    }
}
