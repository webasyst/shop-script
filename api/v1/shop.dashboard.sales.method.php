<?php

class shopDashboardSalesMethod extends shopApiMethod
{
    protected $method = 'GET';

    public function execute()
    {
        $period = waRequest::get('period', 30*24*3600, 'int');
        if ($period <= 0) {
            throw new waAPIException('invalid_request', 'period must be a positive integer', 400);
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
            'by_day' => $graph_data,
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
            $this->response['roi'] = $totals['profit']*100 / $totals['cost'];
        }

    }

    protected static function getGraphData($period)
    {
        $end_date = date('Y-m-d 23:59:59');
        $start_date = date('Y-m-d 00:00:00', strtotime($end_date) - $period);

        $totals = array();
        if (wa()->getUser()->getRights('shop', 'reports')) {
            $sales_model = new shopSalesModel();
            $sales_by_day = $sales_model->getPeriodByDate('customer_sources', $start_date, $end_date, array(
                'date_group' => 'days',
            ));

            // Total customers for the period
            $order_date_sql = shopSalesModel::getDateSql('o.paid_date', $start_date, $end_date);
            $sql = "SELECT COUNT(DISTINCT o.contact_id)
                    FROM shop_order AS o
                        JOIN shop_customer AS c
                            ON o.contact_id=c.contact_id
                    WHERE {$order_date_sql}";
            $totals['total_customer_count'] = $sales_model->query($sql)->fetchField();
        } else {
            $sales_by_day = array(
                array(
                    "date" => date('Y-m-d'),
                    "order_count" => 0,
                    "new_customer_count" => 0,
                    "total_customer_count" => 0,
                    "profit" => 0,
                    "sales" => 0,
                    "purchase" => 0,
                    "shipping" => 0,
                    "tax" => 0,
                    "cost" => 0,
                ),
            );
        }

        $graph_data = array();
        foreach ($sales_by_day as $d) {
            $graph_data[] = $d;
            unset($d['date']);
            foreach ($d as $k => $v) {
                if (!isset($totals[$k])) {
                    $totals[$k] = 0;
                }
                $totals[$k] += $v;
            }
        }

        return array($graph_data, $totals);
    }
}
