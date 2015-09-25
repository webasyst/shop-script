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
        if (in_array($settings['metric'], array('arpu', 'ampu', 'cac'))) {
            $sql = "SELECT COUNT(DISTINCT o.contact_id)
                    FROM shop_order AS o
                        JOIN shop_customer AS c
                            ON o.contact_id=c.contact_id
                        {$storefront_join}
                    WHERE {$order_date_sql}
                        {$storefront_where}";
            $total_customers = $sales_model->query($sql)->fetchField();
        }

        // Total profit for the period
        $total_profit = 0;
        if (in_array($settings['metric'], array('ampu', 'roi'))) {
            $opts = array( 'storefront' => $settings['storefront'] );
            $sales_model->ensurePeriod('sources', $date_start, $date_end, $opts);
            $date_sql = shopSalesModel::getDateSql('`date`', $date_start, $date_end);
            $sql = "SELECT SUM(sales - purchase - shipping - tax)
                    FROM shop_sales
                    WHERE hash=?
                        AND ".$date_sql;
            $total_profit = $sales_model->query($sql, shopSalesModel::getHash('sources', $opts))->fetchField();
        }

        // Total marketing expenses for the period
        $total_expenses = 0;
        if (in_array($settings['metric'], array('cac', 'roi'))) {
            $expense_model = new shopExpenseModel();
            $data = $expense_model->getChart(array(
                'storefront' => $settings['storefront'],
                'start_date' => $date_start,
                'end_date' => $date_end,
                'group_by' => 'months',
            ));
            $total_expenses = 0;
            foreach($data as $serie) {
                foreach($serie['data'] as $p) {
                    $total_expenses += $p['y'];
                }
            }
        }

        switch($settings['metric']) {
            case 'arpu':
                // Total sales for the period
                $sql = "SELECT SUM(o.total*o.rate)
                        FROM shop_order AS o
                            {$storefront_join}
                        WHERE {$order_date_sql}
                            {$storefront_where}";
                $total_sales = $sales_model->query($sql)->fetchField();
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
                if (!$total_expenses || !$total_customers) {
                    return 0;
                }
                return $total_expenses / $total_customers;

            case 'roi':
                if (!$total_profit || !$total_expenses) {
                    return 0;
                }
                return round($total_profit*100 / $total_expenses);

            case 'ltv':
                // Total customers for the whole time
                $sql = "SELECT COUNT(DISTINCT o.contact_id) AS customers_count
                        FROM shop_order AS o
                            JOIN shop_customer AS c
                                ON o.contact_id=c.contact_id
                            {$storefront_join}
                        WHERE o.paid_date IS NOT NULL
                            {$storefront_where}";
                $customers_count = $sales_model->query($sql)->fetchField();
                if (!$customers_count) {
                    return 0;
                }

                // Total sales for the whole time, minus tax and shipping costs
                $sql = "SELECT SUM((o.total - o.tax - o.shipping)*o.rate)
                        FROM shop_order AS o
                            {$storefront_join}
                        WHERE o.paid_date IS NOT NULL
                            {$storefront_where}";
                $total_sales = $sales_model->query($sql)->fetchField();

                // Total purchase expenses for the whole time
                $sql = "SELECT SUM(IF(oi.purchase_price > 0, oi.purchase_price*o.rate, IFNULL(ps.purchase_price*pcur.rate, 0))*oi.quantity)
                        FROM shop_order AS o
                            JOIN shop_order_items AS oi
                                ON oi.order_id=o.id
                                    AND oi.type='product'
                            LEFT JOIN shop_product AS p
                                ON oi.product_id=p.id
                            LEFT JOIN shop_product_skus AS ps
                                ON oi.sku_id=ps.id
                            LEFT JOIN shop_currency AS pcur
                                ON pcur.code=p.currency
                            {$storefront_join}
                        WHERE o.paid_date IS NOT NULL
                            {$storefront_where}";
                $total_purchase = $sales_model->query($sql)->fetchField();
                return ($total_sales - $total_purchase) / $customers_count;
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
