<?php

class shopKpiWidget extends waWidget
{
    public function defaultAction()
    {
        $settings = $this->getSettings();

        $period = (int) $settings['period'];
        $period = ifempty($period, 30*24*3600);
        $date_end = date('Y-m-d 23:59:59');
        $date_start = date('Y-m-d', strtotime($date_end) - $period);

        $total = self::getTotal($settings, $date_start, $date_end);
        list($dynamic, $dynamic_html, $dynamic_color) = self::getDynamic($total, $period, $settings);

        if ($settings['metric'] == 'roi') {
            $total .= '%';
        }

        $this->display(array(
            'settings' => $settings,
            'widget_id' => $this->id,
            'total_formatted' => self::formatTotal($total, $this->info),
            'widget_url' => $this->getStaticUrl(),
            'title' => $this->getTitle($settings),
            'dynamic_color' => $dynamic_color,
            'dynamic_html' => $dynamic_html,
            'dynamic' => $dynamic,
            'total' => $total,
        ));
    }

    protected static function getTotal($settings, $date_start, $date_end)
    {
        if (wa()->getSetting('reports_date_type', 'paid', 'shop') == 'create') {
            $order_date_sql = shopSalesModel::getDateSql('o.create_datetime', $date_start, $date_end).' AND o.paid_date IS NOT NULL';
        } else {
            $order_date_sql = shopSalesModel::getDateSql('o.paid_date', $date_start, $date_end);
        }

        $sales_model = new shopSalesModel();
        list($storefront_join, $storefront_where) = $sales_model->getStorefrontSql(array(
            'storefront' => $settings['storefront'],
        ));

        // Total customers for the period
        $total_customers = 0;
        if (in_array($settings['metric'], array('arpu', 'ampu'))) {
            $sql = "SELECT COUNT(DISTINCT o.contact_id)
                    FROM shop_order AS o
                        JOIN shop_customer AS c
                            ON o.contact_id=c.contact_id
                        {$storefront_join}
                    WHERE {$order_date_sql}
                        {$storefront_where}";
            $total_customers = $sales_model->query($sql)->fetchField();
        }

        // Total sales/profit/customers/expenses for the period
        $total_sales = 0;
        $total_profit = 0;
        $new_customers = 0;
        $total_expenses = 0;
        if (in_array($settings['metric'], array('arpu', 'ampu', 'roi', 'cac'))) {
            $opts = array( 'storefront' => $settings['storefront'] );
            $sales_model->ensurePeriod('customer_sources', $date_start, $date_end, $opts);
            $date_sql = shopSalesModel::getDateSql('`date`', $date_start, $date_end);
            $sql = "SELECT  SUM(sales) AS sales,
                            SUM(sales - purchase - shipping - tax) AS profit,
                            SUM(new_customer_count) AS new_customer_count,
                            SUM(cost) AS cost
                    FROM shop_sales
                    WHERE hash=?
                        AND ".$date_sql;
            $row = $sales_model->query($sql, shopSalesModel::getHash('customer_sources', $opts))->fetchAssoc();
            $new_customers = $row['new_customer_count'];
            $total_expenses = $row['cost'];
            $total_profit = $row['profit'];
            $total_sales = $row['sales'];
        }

        switch($settings['metric']) {
            case 'arpu':
                if (!$total_sales || !$total_customers) {
                    return 0;
                }
                return $total_sales / $total_customers;

            case 'ampu':
                if (!$total_profit || !$total_customers) {
                    return 0;
                }
                return $total_profit / $total_customers;

            case 'cac':
                if (!$total_expenses || !$new_customers) {
                    return 0;
                }
                return $total_expenses / $new_customers;

            case 'roi':
                if (!$total_profit || !$total_expenses) {
                    return 0;
                }
                return round($total_profit*100 / $total_expenses);

            case 'ltv':
                // Total customers for the whole time
                $sql = "SELECT COUNT(DISTINCT o.contact_id) AS customers_count
                        FROM shop_order AS o
                            {$storefront_join}
                        WHERE o.paid_date IS NOT NULL
                            {$storefront_where}";
                $customers_count = $sales_model->query($sql)->fetchField();
                if (!$customers_count) {
                    return 0;
                }

                $totals = $sales_model->getTotals('sources', 0, 0);
                return $totals['profit'] / $customers_count;
        }

        return 0;
    }

    protected static function getDynamic($total, $period, $settings)
    {
        $no_dynamic = array(null, null, null);
        if (!$settings['compare'] || $settings['metric'] == 'ltv') {
            return $no_dynamic;
        }

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

        $date_start = date('Y-m-d', strtotime($date_end) - $period);
        $prev_total = self::getTotal($settings, $date_start, $date_end);
        if (!$prev_total || $prev_total <= 0) {
            return $no_dynamic;
        }

        $dynamic = ($total - $prev_total)*100 / $prev_total;
        $dynamic_html = round($dynamic);
        if ($dynamic > 0) {
            $dynamic_html = '+'.$dynamic_html;
        }
        $dynamic_html .= '%';

        if ($settings['metric'] == 'cac') {
            $dynamic = -$dynamic;
        }
        if ($dynamic > 0) {
            $dynamic_color = 'green';
        } else {
            $dynamic_color = 'red';
        }
        if ($settings['metric'] == 'cac') {
            $dynamic = -$dynamic;
        }

        return array($dynamic, $dynamic_html, $dynamic_color);
    }

    public static function formatTotal($total, $info)
    {
        if (!is_numeric($total)) {
            return $total;
        }

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

    protected function getTitle($settings)
    {
        $titles = array(
            'arpu' => _wp('ARPU'),
            'ampu' => _wp('AMPU'),
            'cac' => _wp('CAC'),
            'ltv' => _wp('LTV'),
            'roi' => _wp('ROI'),
        );
        return ifset($titles[$settings['metric']], $settings['metric']);
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
}
