<?php

class shopDashboardSalesTotalMethod extends waAPIMethod
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
        if ($totals['new_customer_count'] > 0) {
            $this->response['cac'] = $totals['cost'] / $totals['new_customer_count'];
            $this->response['arpu'] = $totals['sales'] / $totals['new_customer_count'];
            $this->response['ampu'] = $totals['profit'] / $totals['new_customer_count'];
            $this->response['ltv'] = $this->response['ampu'];
        }
        if ($totals['cost'] > 0) {
            $this->response['roi'] = $totals['profit']*100 / $totals['cost'];
        }
    }

    protected static function getTotals()
    {
        $totals = array();
        $m = new waModel();

        // Total number of orders
        $sql = "SELECT COUNT(*) FROM shop_order WHERE paid_date IS NOT NULL";
        $totals['order_count'] = $m->query($sql)->fetchField();

        // Total number of customers
        $sql = "SELECT COUNT(*) FROM shop_customer";
        $totals['customer_count'] = $m->query($sql)->fetchField();
        $totals['new_customer_count'] = $totals['customer_count'];

        // Total marketing expenses
        $sql = "SELECT SUM(amount) FROM shop_expense";
        $totals['cost'] = $m->query($sql)->fetchField();

        // Sales, shipping, and tax
        $sql = "SELECT SUM(o.total) AS sales, SUM(o.shipping) AS shipping, SUM(o.tax) AS tax
                FROM shop_order AS o
                WHERE o.paid_date IS NOT NULL";
        $totals += $m->query($sql)->fetchAssoc();

        // Purchase and profit
        $sql = "SELECT SUM(oi.purchase_price*o.rate*oi.quantity)
                FROM shop_order AS o
                    JOIN shop_order_items AS oi
                        ON oi.order_id=o.id
                            AND oi.type='product'
                WHERE o.paid_date IS NOT NULL";
        $totals['purchase'] = $m->query($sql)->fetchField();
        $totals['profit'] = $totals['sales'] - $totals['purchase'];

        // Total products
        $sql = "SELECT COUNT(*) FROM shop_product";
        $totals['products'] = $m->query($sql)->fetchField();

        return $totals;
    }
}

