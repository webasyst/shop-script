<?php

class shopReportsTopAction extends waViewAction
{
    public function execute()
    {
        list($start_date, $end_date, $group_by) = shopReportsSalesAction::getTimeframeParams();

        $mode = waRequest::request('mode', 'sales');
        if ($mode !== 'sales') {
            $mode = 'profit';
        }

        // Top products
        $pm = new shopProductModel();
        $top_products = $pm->getTop(10, $mode, $start_date, $end_date)->fetchAll('id');
        $max_val = 0;
        $product_total_val = 0;
        foreach($top_products as &$p) {
            $p['profit'] = $p['sales'] - $p['purchase'];
            $p['val'] = $p[$mode];
            $max_val = max($p['val'], $max_val);
            $product_total_val += $p['val'];
        }
        foreach($top_products as &$p) {
            $p['val_percent'] = round($p['val'] * 100 / ifempty($max_val, 1));
        }
        unset($p);

        // Top services
        $sm = new shopServiceModel();
        $top_services = $sm->getTop(10, $start_date, $end_date)->fetchAll('id');
        $max_val = 0;
        $service_total_val = 0;
        foreach($top_services as $s) {
            $max_val = max($s['total'], $max_val);
            $service_total_val += $s['total'];
        }
        foreach($top_services as &$s) {
            $s['total_percent'] = round($s['total'] * 100 / ifempty($max_val, 1));
        }
        unset($s);

        // Total sales or pofit for the period
        $om = new shopOrderModel();
        if ($mode == 'sales') {
            $total_val = $om->getTotalSales($start_date, $end_date);
        } else {
            $total_val = $om->getTotalProfit($start_date, $end_date);
        }

        // Total sales by product type
        $sales_by_type = array();
        $tm = new shopTypeModel();
        $pie_total = 0;
        foreach($tm->getSales($start_date, $end_date) as $row) {
            $sales_by_type[] = array(
                $row['name'], (float) $row['sales']
            );
            $pie_total += $row['sales'];
        }
        $sales_by_type[] = array(
            _w('Services'), $service_total_val
        );
        $pie_total += $service_total_val;
        if ($pie_total) {
            foreach($sales_by_type as &$row) {
                $row[0] .= ' ('.round($row[1] * 100 / ifempty($pie_total, 1), 1).'%)';
            }
            unset($row);
        }

        $def_cur = wa()->getConfig()->getCurrency();

        $this->view->assign('mode', $mode);
        $this->view->assign('def_cur', $def_cur);
        $this->view->assign('total_val', $total_val);
        $this->view->assign('top_products', $top_products);
        $this->view->assign('top_services', $top_services);
        $this->view->assign('product_total_val', $product_total_val);
        $this->view->assign('service_total_val', $service_total_val);
        $this->view->assign('pie_data', array($sales_by_type));
    }
}

