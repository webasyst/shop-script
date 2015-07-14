<?php

class shopSalesWidget extends waWidget
{
    public function defaultAction()
    {
        $settings = $this->getSettings();
        $start_date = date('Y-m-d', time() - 30 * 24 * 3600);   // 30 days ago
        $end_date = date('Y-m-d', time() + 3600*24); // tomorrow

        list($graph_data, $total) = self::getGraphData($start_date, $end_date, $settings);

        $this->display(array(
            'widget_id' => $this->id,
            'total_formatted' => self::formatTotal($total, $this->info),
            'widget_url' => $this->getStaticUrl(),
            'graph_data' => $graph_data,
            'settings' => $settings,
            'total' => $total,
        ));
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

            if ($exp < 6) {
                $total_str = self::formatHelper($coeff, $exp, 3, 'K');
            } else {
                $total_str = self::formatHelper($coeff, $exp, 6, 'M');
            }

            return str_replace('0', $total_str, wa_currency(0, $currency, '%0{h}'));
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

    protected static function getGraphData($start_date, $end_date, $settings)
    {
        $sales_model = new shopSalesModel();
        $sales_by_day = $sales_model->getPeriodByDate('sources', $start_date, $end_date, array(
            'date_group' => 'days',
        ));

        $total = 0;
        $graph_data = array();
        foreach($sales_by_day as $d) {
            $value = $d[ifempty($settings['metric'], 'sales')];
            $total += $value;
            $graph_data[] = array(
                'date' => str_replace('-', '', $d['date']),
                'sales' => $value,
                'profit' => 0,
            );
        }

        return array($graph_data, $total);
    }
}
