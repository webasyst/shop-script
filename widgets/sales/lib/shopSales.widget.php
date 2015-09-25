<?php

class shopSalesWidget extends waWidget
{
    public function defaultAction()
    {
        $settings = $this->getSettings();
        list($graph_data, $total) = self::getGraphData($settings);
        list($dynamic_html, $dynamic_class) = self::getDynamic($total, $settings);

        $this->display(array(
            'settings' => $settings,
            'widget_id' => $this->id,
            'total_formatted' => self::formatTotal($total, $this->info),
            'widget_url' => $this->getStaticUrl(),
            'title' => self::getTitle($settings),
            'dynamic_class' => $dynamic_class,
            'dynamic_html' => $dynamic_html,
            'graph_data' => $graph_data,
            'total' => $total,
        ));
    }

    protected static function getTitle($settings)
    {
        if ($settings['metric'] == 'profit') {
            return sprintf_wp('Last %d days profit', round($settings['period'] / 3600/24));
        } else {
            return sprintf_wp('Last %d days sales', round($settings['period'] / 3600/24));
        }
    }

    public static function formatTotal($total, $info)
    {
        $currency = wa()->getConfig()->getCurrency();
        if ($info['size'] == '2x1') {
            return wa_currency($total, $currency, '%0{h}');
        } else {

            //
            // For a square widget we shorten the amount string for it to fit in the available space.
            // For large numbers we show at most 3 digits and a letter modifier, e.g.:
            // 10543 -> 10.5K
            //

            list($coeff, $exp) = explode('e', sprintf('%e', $total));
            $coeff = round($coeff, 2);
            $exp = (int) $exp;

            if ($exp < 3) {
                return wa_currency($total, $currency, '%0{h}');
            }

            // We don't use currency formatting with shortened number
            // because currency symbols after the letter look ugly.
            if ($exp < 6) {
                return self::formatHelper($coeff, $exp, 3, 'K');
            } else {
                return self::formatHelper($coeff, $exp, 6, 'M');
            }
        }
    }

    protected static function formatHelper($coeff, $exp, $exp_limit, $letter)
    {
        $decimals = 2;
        while ($exp > $exp_limit) {
            $coeff *= 10;
            $decimals--;
            $exp--;
        }
        // make sure not to show last zeroes after comma
        while ($decimals > 0 && $coeff == round($coeff, $decimals - 1)) {
            $decimals--;
        }
        return waLocale::format($coeff, max(0, $decimals)).$letter;
    }

    protected function getSettingsConfig()
    {
        $result = parent::getSettingsConfig();
        foreach(shopReportsSalesAction::getStorefronts() as $s) {
            if ($s) {
                $result['storefront']['options'][] = array(
                    'value' => $s,
                    'title' => $s,
                );
            }
        }
        return $result;
    }

    protected static function getGraphData($settings)
    {
        $period = (int) $settings['period'];
        $end_date = date('Y-m-d 23:59:59');
        $start_date = date('Y-m-d 23:59:59', strtotime($end_date) - ifempty($period, 30*24*3600));

        $sales_model = new shopSalesModel();
        $sales_by_day = $sales_model->getPeriodByDate('sources', $start_date, $end_date, array(
            'storefront' => ifempty($settings['storefront']),
            'date_group' => 'days',
        ));

        $total = 0;
        $graph_data = array();
        foreach($sales_by_day as $d) {
            $total += $d[ifempty($settings['metric'], 'sales')];
            $graph_data[] = array(
                'date' => str_replace('-', '', $d['date']),
                'sales' => $d['sales'],
                'profit' => $d['profit'],
            );
        }

        return array($graph_data, $total);
    }

    protected static function getDynamic($total, $settings)
    {
        $period = (int) $settings['period'];
        $period = ifempty($period, 30*24*3600);

        $no_dynamic = array(null, null);
        switch ($settings['compare']) {
            case 'previous':
                $date_end = date('Y-m-d 23:59:59', time() - $period);
                break;
            case 'year_ago':
                $date_end = date('Y-m-d 23:59:59', time() - 365*24*3600);
                break;
            default:
                return $no_dynamic;
        }

        $sales_model = new shopSalesModel();
        $date_start = date('Y-m-d', strtotime($date_end) - $period);
        $totals = $sales_model->getTotals('sources', $date_start, $date_end, array(
            'storefront' => ifempty($settings['storefront']),
        ));

        $prev_total = ifset($totals[ifempty($settings['metric'], 'sales')], 0);
        if ($prev_total <= 0) {
            return $no_dynamic;
        }

        $dynamic = ($total - $prev_total)*100 / $prev_total;
        $dynamic_html = round($dynamic);
        if ($dynamic > 0) {
            $dynamic_html = '+'.$dynamic_html;
        }
        $dynamic_html .= '%';

        if ($dynamic > 0) {
            $dynamic_class = 'green';
        } else {
            $dynamic_class = 'red';
        }

        return array($dynamic_html, $dynamic_class);
    }
}
