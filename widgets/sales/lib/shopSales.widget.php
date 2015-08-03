<?php

class shopSalesWidget extends waWidget
{
    public function defaultAction()
    {
        // Check if user has access to shop reports
        if (!wa()->getUser()->getRights('shop', 'reports')) {
            $this->display(array(), $this->getTemplatePath('NoAccess.html'));
            return;
        }

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

    protected static function getGraphData($start_date, $end_date, $settings)
    {
        $sales_model = new shopSalesModel();
        $sales_by_day = $sales_model->getPeriodByDate('sources', $start_date, $end_date, array(
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
}
