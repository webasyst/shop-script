<?php

class shopDashboardRealtimeMethod extends shopApiMethod
{
    protected $method = 'GET';

    public function execute()
    {
        $max_time = 60 * 60 * 48; // 48 hours

        $period = waRequest::get('period', $max_time, waRequest::TYPE_INT);
        if ($period <= 0) {
            throw new waAPIException('invalid_request', 'period must be a positive integer', 400);
        } elseif ($period > $max_time) {
            throw new waAPIException('invalid_request', 'period cannot be more than 48 hours', 400);
        }

        list($graph_data, $totals) = self::getGraphData($period);
        $this->response = array(
            'totals' => $totals,
            'avg_order_sales' => 0,
            'avg_order_profit' => 0,
            'arpu' => 0,
            'ampu' => 0,
            'roi' => 0,
            'cac' => 0,
            'by_hour' => $graph_data,
        );
        if ($totals['order_count'] > 0) {
            $this->response['avg_order_sales'] = $totals['sales'] / $totals['order_count'];
            $this->response['avg_order_profit'] = $totals['profit'] / $totals['order_count'];
        }
        if ($totals['new_customer_count'] > 0) {
            $this->response['cac'] = $totals['cost'] / $totals['new_customer_count'];
        }
        if ($totals['total_customer_count'] > 0) {
            $this->response['arpu'] = $totals['sales'] / $totals['total_customer_count'];
            $this->response['ampu'] = $totals['profit'] / $totals['total_customer_count'];
        }
        if ($totals['cost'] > 0) {
            $this->response['roi'] = $totals['profit'] * 100 / $totals['cost'];
        }
    }

    protected static function getGraphData($period)
    {
        $now = time();
        $time_diff = $now - $period;
        $start_datetime = date('Y-m-d H:00:00', $time_diff);

        $sales_by_hour = [];
        $totals = [
            'total_customer_count' => 0,
        ];
        for ($step = $time_diff - ($time_diff % 3600); $step <= $now; $step += 3600) {
            $hour = date('Y-m-d H:00:00', $step);
            $sales_by_hour[$hour] = [
                'datetime' => $hour,
                'order_count' => 0,
                'new_customer_count' => 0,
                'profit' => 0,
                'sales' => 0,
                'purchase' => 0,
                'shipping' => 0,
                'tax' => 0,
                'cost' => 0,
            ];
        }

        if (wa()->getUser()->getRights('shop', 'orders')) {
            $order_model = new shopOrderModel();
            $orders_by_hour_sql = "SELECT DATE_FORMAT(o.paid_datetime, '%Y-%m-%d %H:00:00') `paid_by_hour`,
                    COUNT(DISTINCT o.id) `order_count`,
                    SUM(o.is_first) `new_customer_count`,
                    IFNULL(oi.profit, 0) `profit`,
                    SUM(o.total * o.rate) `sales`,
                    IFNULL(oi.purchase, 0) `purchase`,
                    SUM(o.shipping * o.rate) `sum_shipping`,
                    SUM(o.tax * o.rate) `sum_tax`
                FROM shop_order o
                    LEFT JOIN (
                        SELECT oi.order_id,
                            o.total * o.rate - SUM(IF(oi.purchase_price > 0, oi.purchase_price * o.rate, IFNULL(ps.purchase_price * pcur.rate, 0)) * oi.quantity) - o.shipping * o.rate - o.tax * o.rate `profit`,
                            SUM(IF(oi.purchase_price > 0, oi.purchase_price * o.rate, IFNULL(ps.purchase_price * pcur.rate, 0)) * oi.quantity) `purchase`
                        FROM shop_order_items oi
                            LEFT JOIN shop_order AS o
                                ON o.id = oi.order_id
                            LEFT JOIN shop_product AS p
                                ON p.id = oi.product_id
                            LEFT JOIN shop_product_skus AS ps
                                ON ps.id = oi.sku_id
                            LEFT JOIN shop_currency AS pcur
                                ON pcur.code = p.currency
                        WHERE oi.type = 'product'
                        GROUP BY oi.order_id
                    ) oi ON oi.order_id = o.id
                WHERE o.paid_datetime >= ?
                GROUP BY HOUR(o.paid_datetime), DAY(o.paid_datetime)
                ORDER BY o.paid_datetime DESC";
            $orders = $order_model->query($orders_by_hour_sql, $start_datetime)->fetchAll('paid_by_hour');
            if ($orders) {
                $costs = self::getCosts($now, $start_datetime, $time_diff);

                foreach ($orders as &$order) {
                    $order = [
                        'datetime' => $order['paid_by_hour'],
                        'order_count' => $order['order_count'],
                        'new_customer_count' => $order['new_customer_count'],
                        'profit' => $order['profit'],
                        'sales' => $order['sales'],
                        'purchase' => $order['purchase'],
                        'shipping' => $order['sum_shipping'],
                        'tax' => $order['sum_tax'],
                        'cost' => 0,
                    ];
                    if ($costs) {
                        $paid_datetime = date_create($order['datetime']);
                        if ($paid_datetime) {
                            $paid_date = date_format($paid_datetime, 'Y-m-d');
                            if (isset($costs[$paid_date])) {
                                $order['cost'] = $costs[$paid_date];
                            }
                        }
                    }
                    foreach ($order as $key => $value) {
                        if (is_numeric($value)) {
                            $order[$key] = (float)$value;
                        }
                    }
                }
                unset($order);
            }

            // Total customers for the period
            $sql = "SELECT COUNT(DISTINCT o.contact_id)
                    FROM `{$order_model->getTableName()}` AS o
                        JOIN shop_customer AS c
                            ON o.contact_id = c.contact_id
                    WHERE o.paid_datetime >= ?";
            $totals['total_customer_count'] = (float)$order_model->query($sql, $start_datetime)->fetchField();

            $sales_by_hour = array_merge($sales_by_hour, $orders);
        }

        $graph_data = [];
        foreach ($sales_by_hour as $sale) {
            $graph_data[] = $sale;
            foreach ($sale as $key => $value) {
                if ($key != 'datetime') {
                    if (!isset($totals[$key])) {
                        $totals[$key] = 0;
                    }
                    $totals[$key] += $value;
                }
            }
        }

        return [$graph_data, $totals];
    }

    /**
     * @param $now
     * @param $start_datetime
     * @param $time_diff
     * @return array
     * @throws waDbException
     */
    protected static function getCosts($now, $start_datetime, $time_diff)
    {
        $end_date = date('Y-m-d', $now);
        $expense_model = new shopExpenseModel();
        $sql = "SELECT `start`, `end`, `amount`, DATEDIFF(`end`, `start`) + 1 `days_count`
                FROM {$expense_model->getTableName()}
                WHERE `start` <= DATE(?) AND `end` >= DATE(?)
                ORDER BY `start`";
        $expenses = $expense_model->query($sql, [$end_date, $start_datetime])->fetchAll();

        $costs = [];
        $start_ts = strtotime(date('Y-m-d', $time_diff));
        $end_ts = strtotime($end_date);
        for ($t = $start_ts; $t <= $end_ts; $t = strtotime(date('Y-m-d', $t) . ' +1 day')) {
            $date = date('Y-m-d', $t);
            foreach ($expenses as $key => $expense) {
                if (strtotime($expense['end']) < $t) {
                    unset($expenses[$key]);
                    continue;
                }
                if (strtotime($expense['start']) > $t) {
                    break;
                }
                if ($expense['days_count'] > 0) {
                    if (!isset($costs[$date])) {
                        $costs[$date] = 0;
                    }
                    $costs[$date] += $expense['amount'] / $expense['days_count'] / 24;
                }
            }
        }
        return $costs;
    }
}
