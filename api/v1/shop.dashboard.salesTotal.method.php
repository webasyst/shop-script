<?php

class shopDashboardSalesTotalMethod extends shopApiMethod
{
    protected $method = 'GET';

    public function execute()
    {
        if (wa()->getUser()->getRights('shop', 'reports')) {
            $totals = self::getTotals();
        } else {
            $totals = array(
                "order_count" => 0,
                "customer_count" => 0,
                "new_customer_count" => 0,
                "cost" => 0,
                "sales" => 0,
                "shipping" => 0,
                "tax" => 0,
                "purchase" => 0,
                "profit" => 0,
                "products" => 0,
                "paid_customer_count" => 0,
                "total_days" => 0,
            );
        }
        $this->response = array(
            'totals' => $totals,
            'avg_order_sales' => 0,
            'avg_order_profit' => 0,
            'arpu' => 0,
            'ampu' => 0,
            'roi' => 0,
            'cac' => 0,
            'ltv' => 0,
        );
        if ($totals['order_count'] > 0) {
            $this->response['avg_order_sales'] = $totals['sales'] / $totals['order_count'];
            $this->response['avg_order_profit'] = $totals['profit'] / $totals['order_count'];
        }
        if ($totals['paid_customer_count'] > 0) {
            $this->response['cac'] = $totals['cost'] / $totals['paid_customer_count'];
            $this->response['arpu'] = $totals['sales'] / $totals['paid_customer_count'];
            $this->response['ampu'] = $totals['profit'] / $totals['paid_customer_count'];
            $this->response['ltv'] = $this->response['ampu'];
        }
        if ($totals['cost'] > 0) {
            $this->response['roi'] = $totals['profit']*100 / $totals['cost'];
        }
    }

    protected static function getTotals()
    {
        // order_count, profit, purchase, sales, shipping, tax, total_days
        $sales_model = new shopSalesModel();
        $totals = $sales_model->getTotals('customer_sources', 0, 0);
        unset($totals['roi'], $totals['avg_day'], $totals['avg_order'], $totals['total_names']);

        // Total number of customers
        $sql = "SELECT COUNT(*) FROM shop_customer";
        $totals['new_customer_count'] = $totals['customer_count'] = $sales_model->query($sql)->fetchField();

        // Total number of customers who eventually paid
        $sql = "SELECT COUNT(DISTINCT o.contact_id) AS customers_count
                FROM shop_order AS o
                WHERE o.paid_date IS NOT NULL";
        $totals['paid_customer_count'] = $sales_model->query($sql)->fetchField();

        // Total products
        $sql = "SELECT COUNT(*) FROM shop_product";
        $totals['products'] = $sales_model->query($sql)->fetchField();

        return $totals;
    }
}
