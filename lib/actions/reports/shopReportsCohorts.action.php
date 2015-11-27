<?php
class shopReportsCohortsAction extends waViewAction
{
    public function execute()
    {
        shopReportsSalesAction::jsRedirectIfDisabled();

        list($start_date, $end_date, $group_by, $request_options) = shopReportsSalesAction::getTimeframeParams();

        $default_group_by = 'quarters';
        if ($start_date) {
            $start_ts = strtotime($start_date);
            $end_ts = $end_date ? strtotime($end_date) : time();
            $months = 3600*24*30;
            if ($end_ts - $start_ts > 24*$months) {
                $default_group_by = 'quarters';
            } else if ($end_ts - $start_ts > 6*$months) {
                $default_group_by = 'months';
            } else {
                $default_group_by = 'weeks';
            }
        }

        $group_by = waRequest::request('group_period', $default_group_by, 'string');
        $cohorts_type = waRequest::request('type', 'sales', 'string');
        $storefront = waRequest::request('storefront', null, 'string');
        $customer_source = waRequest::request('source', '', 'string');
        $request_options = array(
            'type' => $cohorts_type,
            'storefront' => $storefront,
            //'group_period' => $group_by,
            'source' => $customer_source,
        ) + $request_options;

        $model_options = array(
            'customer_source' => $customer_source,
            'storefront' => $storefront,
            'group' => $group_by,
        );

        $sales_model = new shopSalesModel();
        $cohorts = $sales_model->getCohorts($cohorts_type, $start_date, $end_date, $model_options);

        // Prepare data for template
        $table_data = array();
        $chart_data = array();
        $table_totals = array();
        $table_headers = array();
        $def_cur = wa()->getConfig()->getCurrency();
        end($cohorts);
        $min_ts = null;
        $max_ts = strtotime(key($cohorts));
        $all_zeroes = true;
        $max_metric = 0;
        foreach($cohorts as $reg_date => $periods) {
            $total = 0;
            $reg_ts = strtotime($reg_date);
            if ($min_ts === null) {
                $min_ts = $reg_ts;
            }
            $color = self::getCohortColor($min_ts, $max_ts, $reg_ts);
            $max_for_period = 0;
            $chart_serie = array();
            foreach($periods as $order_date => $stats) {
                $order_ts = strtotime($order_date);
                if ($order_ts >= $reg_ts) {
                    $chart_serie[] = array(
                        'y' => max($stats['metric'], 0),
                        'x' => $order_ts*1000,
                    );
                    $stats += array(
                        'order_ts' => $order_ts,
                        'percent_of_max' => 0,
                        'color' => $color,
                    );
                    $total += $stats['metric'];
                    $max_for_period = max($max_for_period, $stats['metric']);
                    $table_data[$reg_date][] = $stats;
                } else {
                    $chart_serie[] = array(
                        'y' => 0,
                        'x' => $order_ts*1000,
                    );
                }
            }

            if ($cohorts_type == 'clv' || $cohorts_type == 'roi') {
                $table_totals[$reg_date] = $max_for_period;
            } else {
                $table_totals[$reg_date] = $total;
            }

            if (in_array($cohorts_type, array('sales', 'profit', 'clv'))) {
                $cash_type = wa_currency_html($table_totals[$reg_date], $def_cur);
            } else if ($cohorts_type == 'roi') {
                $cash_type = round($table_totals[$reg_date]);
            } else {
                $cash_type = round($table_totals[$reg_date], 2);
            }

            $table_headers[$reg_date] = $row_header = shopExpenseModel::getCohortHeader($group_by, $reg_ts);

            $all_zeroes = $all_zeroes && $table_totals[$reg_date] <= 0;
            $max_metric = max($max_metric, $max_for_period);

            $chart_data[] = array(
                'color' => $color,
                'data' => $chart_serie,
                'cash_type' => $cash_type,
                'name' => _w('Cohort').': '.$row_header,
                'date' => waDateTime::format('humandate', $reg_date),
            );
        }

        if ($max_metric > 0) {
            foreach($table_data as &$row) {
                foreach($row as &$stats) {
                    if ($stats['metric'] > 0) {
                        $stats['percent_of_max'] = $stats['metric']*100/$max_metric;
                    }
                }
            }
            unset($row, $stats);
        }

        $this->view->assign(array(
            'sources' => shopReportsmarketingcostsActions::getSources(),
            'storefronts' => shopReportsSalesAction::getStorefronts(),
            'request_options' => $request_options,
            'table_headers' => $table_headers,
            'table_totals' => $table_totals,
            'table_data' => $table_data,
            'chart_data' => $chart_data,
            'all_zeroes' => $all_zeroes,
            'group_by' => $group_by,
            'def_cur' => $def_cur,
        ));
    }

    protected function getCohortColor($min_ts, $max_ts, $reg_ts)
    {
        $min_color = 0x3fd9f0;
        $max_color = 0x6f50cb;

        if ($max_ts == $min_ts) {
            return $min_color;
        }

        $min_r = ($min_color >> 16) & 0xff;
        $max_r = ($max_color >> 16) & 0xff;
        $min_g = ($min_color >> 8) & 0xff;
        $max_g = ($max_color >> 8) & 0xff;
        $min_b = ($min_color) & 0xff;
        $max_b = ($max_color) & 0xff;

        //mt_srand(crc32($reg_date));
        //$rnd = mt_rand(0, 512) / 512;
        //mt_srand();
        $rnd = ($reg_ts - $min_ts) / ($max_ts - $min_ts);

        $r = round($min_r + ($max_r - $min_r)*$rnd);
        $g = round($min_g + ($max_g - $min_g)*$rnd);
        $b = round($min_b + ($max_b - $min_b)*$rnd);

        $color = ($r << 16) + ($g << 8) + $b;

        return sprintf('#%06X', $color);
    }
}

