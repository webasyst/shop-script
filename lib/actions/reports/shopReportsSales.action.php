<?php

class shopReportsSalesAction extends waViewAction
{
    public function execute()
    {
        $start_time = date('Y-m-d', time() - 30*24*3600); // !!! TODO: use parameter for this

        // Data for main graph: 'yyyy-mm-dd' => array(...).
        // Init it with zeroes.
        $sales_by_day = array();
        $now = time();
        for ($t = strtotime($start_time); $t < $now; $t += 3600*24) {
            $date = date('Y-m-d', $t);
            $sales_by_day[$date] = array(
                'date' => $date,
                'total_percent' => 0,
                'total' => 0,
            );
        }

        if (empty($sales_by_day)) {
            throw new waException('Bad parameters');
        }

        // Total sales for period, in default currency
        $total_sales = array(
            'returning_customers' => 0,
            'new_customers' => 0,
            'total' => 0,
        );

        // Total number of paid orders for the period
        $total_orders = array(
            'returning_customers' => 0,
            'new_customers' => 0,
            'total' => 0,
        );

        // Max total sales in a single day
        $max_day_sales = 0;

        // Loop over all days of a period that had at least one order paid,
        // and gather data into vars listed above.
        $om = new shopOrderModel();
        foreach ($om->getSales($start_time) as $row) {
            $max_day_sales = max($max_day_sales, (float) $row['total']);
            $sales_by_day[$row['paid_date']]['total'] = (float) $row['total'];
            $total_orders['new_customers'] += (int) $row['customer_first_count'];
            $total_sales['new_customers'] += (float) $row['customer_first_total'];
            $total_orders['total'] += (int) $row['count'];
            $total_sales['total'] += (float) $row['total'];
        }
        $total_sales['returning_customers'] += $total_sales['total'] - $total_sales['new_customers'];
        $total_orders['returning_customers'] += $total_orders['total'] - $total_orders['new_customers'];

        // Data for main chart
        $sales_data = array();
        foreach($sales_by_day as &$d) {
            $d['total_percent'] = $max_day_sales ? ($d['total']*100 / $max_day_sales) : 0;
            $sales_data[] = array($d['date'], $d['total']);
        }
        unset($d);

        $def_cur = wa()->getConfig()->getCurrency();

        $this->view->assign('sales_by_day', $sales_by_day);
        $this->view->assign('sales_data', $sales_data);
        $this->view->assign('def_cur', $def_cur);
        $this->view->assign('stat', array(
            'total_formatted' => waCurrency::format('%{s}', $total_sales['total'], $def_cur),
            'percent_returning' => round($total_sales['returning_customers'] * 100 / ifempty($total_sales['total'],1), 1),
            'percent_new' => round($total_sales['new_customers'] * 100 / ifempty($total_sales['total'],1), 1),
            'avg_total_formatted' => waCurrency::format('%{s}', round($total_sales['total'] / ifempty($total_orders['total'],1), 1), $def_cur),
            'avg_total_new_formatted' => waCurrency::format('%{s}', round($total_sales['new_customers'] / ifempty($total_orders['new_customers'],1), 2), $def_cur),
            'avg_total_returning_formatted' => waCurrency::format('%{s}', round($total_sales['returning_customers'] / ifempty($total_orders['returning_customers'],1), 2), $def_cur),
            'avg_total_daily_formatted' => waCurrency::format('%{s}', round($total_sales['total'] / count($sales_by_day), 2), $def_cur),
        ));
    }
}
