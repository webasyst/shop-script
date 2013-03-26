<?php

class shopReportsProfitAction extends waViewAction
{
    public function execute()
    {
        list($start_date, $end_date, $group_by) = shopReportsSalesAction::getTimeframeParams();

        // Init by-day arrays with zeroes.
        $by_day = array(); // Data for main graph: 'yyyy-mm-dd' => array(...)
        $sales_by_day = array(); // Total sales data
        $om = new shopOrderModel();
        foreach($om->getSales($start_date, $end_date, $group_by) as $date => $row) {
            $sales_by_day[$date] = $row['total'];
            $by_day[$date] = array(
                'date' => $date,
                'total_percent' => 0, // percent from max single-day profit
                'total' => 0, // profit
            );
        }

        // Max profit in a single day
        $max_day_profit = 0;

        // Totals for period, in default currency
        $total = array(
            'profit' => 0,
            'purchase' => 0,
            'shipping' => 0,
            'sales' => 0,
            'tax' => 0,
        );

        // Loop over all days of a period that had at least one order paid,
        // and gather data into vars listed above.
        foreach ($om->getProfit($start_date, $end_date, $group_by) as $row) {

            $sales = ifset($sales_by_day[$row['date']], 0);
            $profit = $sales - $row['purchase'] - $row['shipping'] - $row['tax'];
            $max_day_profit = max($max_day_profit, $profit);

            $by_day[$row['date']]['total'] = $profit;
            $total['sales'] += $sales;
            $total['profit'] += $profit;
            $total['purchase'] += $row['purchase'];
            $total['shipping'] += $row['shipping'];
            $total['tax'] += $row['tax'];
        }

        // Data for main chart
        $profit_data = array();
        foreach($by_day as &$d) {
            $d['total_percent'] = $max_day_profit ?  ($d['total']*100 / ifempty($max_day_profit, 1)) : 0;
            $profit_data[] = array($d['date'], $d['total']);
        }
        unset($d);

        // Data for pie chart
        $pie_data = array();
        $pie_total = $total['shipping'] + $total['profit'] + $total['purchase'] + $total['tax'];
        if ($pie_total) {
            $pie_data[] = array(
                _w('Shipping').' ('.round($total['shipping'] * 100 / ifempty($pie_total, 1), 1).'%)', (float) $total['shipping']
            );
            $pie_data[] = array(
                _w('Profit').' ('.round($total['profit'] * 100 / ifempty($pie_total, 1), 1).'%)', (float) $total['profit']
            );
            $pie_data[] = array(
                _w('Product purchases').' ('.round($total['purchase'] * 100 / ifempty($pie_total, 1), 1).'%)', (float) $total['purchase']
            );
            $pie_data[] = array(
                _w('Tax').' ('.round($total['tax'] * 100 / ifempty($pie_total, 1), 1).'%)', (float) $total['tax']
            );
            $pie_data = array($pie_data);
        }

        $def_cur = wa()->getConfig()->getCurrency();
        $this->view->assign('total', $total);
        $this->view->assign('by_day', $by_day);
        $this->view->assign('def_cur', $def_cur);
        $this->view->assign('group_by', $group_by);
        $this->view->assign('pie_data', $pie_data);
        $this->view->assign('profit_data', $profit_data);
        $this->view->assign('avg_profit', $by_day ? round($total['profit'] / count($by_day), 2) : 0);
    }
}

