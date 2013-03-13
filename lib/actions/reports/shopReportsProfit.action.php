<?php

class shopReportsProfitAction extends waViewAction
{
    public function execute()
    {
        $start_time = date('Y-m-d', time() - 30*24*3600); // !!! TODO: use parameter for this

        // Data for main graph: 'yyyy-mm-dd' => array(...).
        // Init it with zeroes.
        $by_day = array();
        $now = time();
        for ($t = strtotime($start_time); $t < $now; $t += 3600*24) {
            $date = date('Y-m-d', $t);
            $by_day[$date] = array(
                'date' => $date,
                'total_percent' => 0, // percent from max single-day profit
                'total' => 0, // profit
            );
        }

        if (empty($by_day)) {
            throw new waException('Bad parameters');
        }

        // Totals for period, in default currency
        $total = array(
            'profit' => 0,
            'purchase' => 0,
            'shipping' => 0,
            'sales' => 0,
            'tax' => 0,
        );

        // Max profit in a single day
        $max_day_profit = 0;

        // Total sales data
        $sales_by_day = array();
        $om = new shopOrderModel();
        foreach ($om->getSales($start_time) as $row) {
            $sales_by_day[$row['paid_date']] = $row['total'];
        }

        // Loop over all days of a period that had at least one order paid,
        // and gather data into vars listed above.
        foreach ($om->getProfit($start_time) as $row) {

            $sales = ifset($sales_by_day[$row['paid_date']], 0);
            $profit = $sales - $row['purchase'] - $row['shipping'] - $row['tax'];
            $max_day_profit = max($max_day_profit, $profit);

            $by_day[$row['paid_date']]['total'] = $profit;
            $total['sales'] += $sales;
            $total['profit'] += $profit;
            $total['purchase'] += $row['purchase'];
            $total['shipping'] += $row['shipping'];
            $total['tax'] += $row['tax'];
        }

        // Data for main chart
        $profit_data = array();
        foreach($by_day as &$d) {
            $d['total_percent'] = $max_day_profit ?  ($d['total']*100 / $max_day_profit) : 0;
            $profit_data[] = array($d['date'], $d['total']);
        }
        unset($d);

        // Data for pie chart
        $pie_data = array();
        $pie_total = $total['shipping'] + $total['profit'] + $total['purchase'] + $total['tax'];
        if ($pie_total) {
            $pie_data[] = array(
                _w('Shipping').' ('.round($total['shipping'] * 100 / $pie_total, 1).'%)', (float) $total['shipping']
            );
            $pie_data[] = array(
                _w('Profit').' ('.round($total['profit'] * 100 / $pie_total, 1).'%)', (float) $total['profit']
            );
            $pie_data[] = array(
                _w('Product purchases').' ('.round($total['purchase'] * 100 / $pie_total, 1).'%)', (float) $total['purchase']
            );
            $pie_data[] = array(
                _w('Tax').' ('.round($total['tax'] * 100 / $pie_total, 1).'%)', (float) $total['tax']
            );
            $pie_data = array($pie_data);
        }

        $def_cur = wa()->getConfig()->getCurrency();
        $this->view->assign('total', $total);
        $this->view->assign('by_day', $by_day);
        $this->view->assign('def_cur', $def_cur);
        $this->view->assign('pie_data', $pie_data);
        $this->view->assign('profit_data', $profit_data);
        $this->view->assign('avg_profit', round($total['profit'] / count($by_day), 2));
    }
}

