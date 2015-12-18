<?php
/**
 * Data cache for Sales Report section in backend.
 */
class shopSalesModel extends waModel
{
    protected $table = 'shop_sales';

    public function deletePeriod($date_start, $date_end=null)
    {
        if (empty($date_end)) {
            if (empty($date_start)) {
                try {
                    $this->exec("TRUNCATE {$this->table}");
                    return;
                } catch (Exception $e) {
                    // DB user does not have enough rights for TRUNCATE.
                    // No problem, falling back to DELETE below.
                }
            }
            $date_end = $date_start;
        }
        $date_sql = self::getDateSql('`date`', $date_start, $date_end);
        $sql = "DELETE FROM {$this->table} WHERE $date_sql";
        $this->exec($sql);
    }

    public static function getHash($type, $options=array())
    {
        if (!empty($options['hash']) && substr($options['hash'], 0, strlen($type)) == $type) {
            return $options['hash'];
        }
        $options = array_intersect_key($options, array(
            'storefront' => 1,
            'abtest_variant_id' => 1,
            'abtest_id' => 1,
        ));
        if (!$options) {
            return $type;
        }
        ksort($options);

        if (function_exists('hash')) {
            return $type.'_'.hash("crc32b", serialize($options));
        } else {
            return $type.'_'.str_pad(dechex(crc32(serialize($options))), 8, '0', STR_PAD_LEFT);
        }
    }

    public function getMinDate()
    {
        static $date_start = null;
        if ($date_start === null) {
            $order_model = new shopOrderModel();
            $date_start = $order_model->getMinDate();
        }
        return $date_start;
    }

    /**
     * Returns data for graph in Sales Report section in backend.
     * Checks if cache is obsolette and rebuilds it if necessary.
     */
    public function getPeriodByDate($type, $date_start, $date_end, $options=array())
    {
        // Check parameters
        empty($date_end) && ($date_end = date('Y-m-d 23:59:59'));
        empty($date_start) && ($date_start = $this->getMinDate());
        if (empty($type) || !is_string($type)) {
            throw new waException('Type is required');
        }

        $options['hash'] = $hash = self::getHash($type, $options);

        $date_group = ifset($options['date_group'], 'days');
        $date_col = ($date_group == 'months') ? "DATE_FORMAT(ss.`date`, '%Y-%m-01')" : 'ss.`date`';

        $type_sql = '';
        if ($type == 'coupons' || $type == 'campaigns' || $type == 'social') {
            $type_sql = "AND name <> ''";
        }

        // Make sure data is prepared in table
        empty($options['ensured']) && $this->ensurePeriod($type, $date_start, $date_end, $options);

        $date_sql = self::getDateSql('ss.`date`', $date_start, $date_end);
        $sql = "SELECT {$date_col} AS `date`,
                    SUM(order_count) AS order_count,
                    SUM(new_customer_count) AS new_customer_count,
                    SUM(sales - purchase - shipping - tax) AS profit,
                    SUM(sales) AS sales,
                    SUM(purchase) AS purchase,
                    SUM(shipping) AS shipping,
                    SUM(tax) AS tax,
                    SUM(cost) AS cost
                FROM {$this->table} AS ss
                WHERE hash=?
                    AND {$date_sql}
                    {$type_sql}
                GROUP BY {$date_col}
                ORDER BY `date`";

        $sales_by_date = $this->query($sql, $hash)->fetchAll('date');

        // Add empty rows
        $empty_row = array(
            'order_count' => 0,
            'new_customer_count' => 0,
            'profit' => 0,
            'sales' => 0,
            'purchase' => 0,
            'shipping' => 0,
            'tax' => 0,
            'cost' => 0,
        );
        $end_ts = strtotime($date_end);
        $start_ts = strtotime($date_start);
        for ($t = $start_ts; $t <= $end_ts; $t = strtotime(date('Y-m-d', $t) . ' +1 day')) {
            $date = date(($date_group == 'months') ? 'Y-m-01' : 'Y-m-d', $t);
            if (empty($sales_by_date[$date])) {
                $sales_by_date[$date] = array(
                    'date' => $date,
                ) + $empty_row;
            }
            foreach($empty_row as $k => $v) {
                $sales_by_date[$date][$k] = (float) $sales_by_date[$date][$k];
            }
        }
        ksort($sales_by_date);

        return $sales_by_date;
    }

    /**
     * Get a single row containing totals for the period.
     */
    public function getTotals($type, $date_start, $date_end, $options=array())
    {
        empty($date_end) && ($date_end = date('Y-m-d 23:59:59'));
        empty($date_start) && ($date_start = $this->getMinDate());
        if (empty($type) || !is_string($type)) {
            throw new waException('Type is required');
        }
        $options['hash'] = $hash = self::getHash($type, $options);
        empty($options['ensured']) && $this->ensurePeriod($type, $date_start, $date_end, $options);
        $date_sql = self::getDateSql('`date`', $date_start, $date_end);

        $type_sql = '';
        if ($type == 'coupons' || $type == 'campaigns' || $type == 'social') {
            $type_sql = "AND name <> ''";
        }

        $sql = "SELECT
                    SUM(order_count) AS order_count,
                    SUM(new_customer_count) AS new_customer_count,
                    SUM(sales - purchase - shipping - tax) AS profit,
                    SUM(sales) AS sales,
                    SUM(purchase) AS purchase,
                    SUM(shipping) AS shipping,
                    SUM(tax) AS tax,
                    SUM(cost) AS cost,
                    COUNT(DISTINCT(name)) AS total_names,
                    DATEDIFF(DATE(?), DATE(?)) + 1 AS total_days
                FROM {$this->table}
                WHERE hash=?
                    AND {$date_sql}
                    {$type_sql}";
        $totals = $this->query($sql, $date_end, $date_start, $hash)->fetchAssoc();
        $totals['avg_order'] = $totals['order_count'] > 0 ? $totals['sales'] / $totals['order_count'] : 0;
        $totals['avg_day'] = $totals['total_days'] ? $totals['sales'] / $totals['total_days'] : 0;
        $totals['roi'] = $totals['cost'] ? $totals['profit']*100 / $totals['cost'] : 0;
        return $totals;
    }

    /**
     * Returns data for table in Sales Report section in backend.
     * Checks if cache is obsolette and rebuilds it if necessary.
     */
    public function getPeriod($type, $date_start, $date_end, $options=array(), &$total_rows=null)
    {
        // Check parameters
        empty($date_end) && ($date_end = date('Y-m-d 23:59:59'));
        empty($date_start) && ($date_start = $this->getMinDate());
        if (empty($type) || !is_string($type)) {
            throw new waException('Type is required');
        }

        $options['hash'] = $hash = self::getHash($type, $options);

        // Make sure data is prepared in table
        if (!empty($options['abtest_id'])) {
            $this->ensureABTest($type, $options);
        } else if (empty($options['ensured'])) {
            $this->ensurePeriod($type, $date_start, $date_end, $options);
        }

        $date_sql = self::getDateSql('`date`', $date_start, $date_end);

        $name_sql = '';
        if (!empty($options['names'])) {
            $name_sql = 'AND name IN (:names)';
        }

        // Using derived query because otherwise
        // ORDER BY would not work for some columns (average_order)
        $sql = "SELECT SQL_CALC_FOUND_ROWS t.* FROM
                    (SELECT
                        name,
                        SUM(order_count) AS order_count,
                        SUM(new_customer_count) AS new_customer_count,
                        SUM(sales - purchase - shipping - tax) AS profit,
                        SUM(sales) AS sales,
                        SUM(purchase) AS purchase,
                        SUM(shipping) AS shipping,
                        SUM(tax) AS tax,
                        SUM(cost) AS cost
                    FROM {$this->table}
                    WHERE hash=:hash
                        AND {$date_sql}
                        {$name_sql}
                    GROUP BY name) AS t
                ORDER BY ".$this->getOrderBy(ifset($options['order']))."
                LIMIT ".ifset($options['start'], 0).", ".ifset($options['limit'], wa('shop')->getConfig()->getOption('statrows_per_page'));
        $rows = $this->query($sql, array(
            'hash' => $hash,
            'names' => ifset($options['names']),
        ));
        $total_rows = $this->query("SELECT FOUND_ROWS()")->fetchField();
        $result = array();
        foreach($rows as $row) {

            // Ignore empty rows
            if ($row['order_count'] == 0 && $row['cost'] == 0) {
                $total_rows--;
                continue;
            }

            if ($row['cost'] > 0) {
                $row['roi'] = $row['profit']*100 / $row['cost'];
            } else {
                $row['roi'] = 0;
            }
            $result[] = $row;
        }


        return $result;
    }

    // Helper for getPeriod()
    protected function getOrderBy($order)
    {
        $possible_orders = array(
            '!profit' => 'profit DESC',
            'profit' => 'profit',
            'order_count' => 'order_count',
            '!order_count' => 'order_count DESC',
            'new_customer_count' => 'new_customer_count',
            '!new_customer_count' => 'new_customer_count DESC',
            'sales' => 'sales',
            '!sales' => 'sales DESC',
            'cost' => 'cost',
            '!cost' => 'cost DESC',
            'roi' => 'IF(cost > 0, profit / cost, 0)',
            '!roi' => 'IF(cost > 0, profit / cost, 0) DESC',
            'average_order' => 'IF(order_count > 0, sales / order_count, 0)',
            '!average_order' => 'IF(order_count > 0, sales / order_count, 0) DESC',
        );
        if ($order && isset($possible_orders[$order])) {
            return $possible_orders[$order];
        } else {
            return reset($possible_orders);
        }
    }

    /**
     * Data for the table on the Customers Report page.
     */
    public function getPeriodCustomers($date_start, $date_end, $options=array(), &$total_rows=null)
    {
        $min_date = $this->getMinDate();
        $max_date = date('Y-m-d 23:59:59');
        $hash = self::getHash('customer_sources', $options);
        empty($date_end) && ($date_end = $max_date);
        empty($date_start) && ($date_start = $min_date);

        $this->ensurePeriod('customer_sources', $min_date, $max_date, $options);
        $options['ensured'] = true;

        $sales = $this->getPeriod('customer_sources', $date_start, $date_end, $options, $total_rows);

        // List of source names used in following queries
        $names = array('' => '');
        foreach($sales as $row) {
            if ($row['name'] && ($row['order_count'] || $row['cost'])) {
                $names[$row['name']] = $row['name'];
            }
        }

        // Storefront filtering for queries below
        list($storefront_join, $storefront_where) = $this->getStorefrontSql($options);

        // Total customers for the whole time to calculate LTV
        if ($names) {
            $sql = "SELECT c.source, COUNT(DISTINCT o.contact_id) AS customers_count
                    FROM shop_order AS o
                        JOIN shop_customer AS c
                            ON o.contact_id=c.contact_id
                        {$storefront_join}
                    WHERE (c.source IN (?) OR c.source IS NULL)
                        {$storefront_where}
                        AND o.paid_date IS NOT NULL
                    GROUP BY c.source";
            $customer_lifetime_counts = $this->query($sql, array($names))->fetchAll('source', true);
        }

        // Total profit and sales for the whole time to calculate LTV
        $lifetime_sales = array();
        $rows = $this->getPeriod('customer_sources', $min_date, $max_date, array(
            'limit' => 100500,
            'names' => $names,
        ) + $options);
        foreach($rows as $row) {
            $lifetime_sales[$row['name']] = $row;
        }

        // Total customers for the period
        if ($names) {
            if ($min_date == $date_start && $max_date == $date_end) {
                $customer_counts = $customer_lifetime_counts;
            } else {
                if (wa()->getSetting('reports_date_type', 'paid', 'shop') == 'create') {
                    $order_date_sql = self::getDateSql('o.create_datetime', $date_start, $date_end).' AND o.paid_date IS NOT NULL';
                } else {
                    $order_date_sql = self::getDateSql('o.paid_date', $date_start, $date_end);
                }

                $sql = "SELECT c.source, COUNT(DISTINCT o.contact_id) AS customers_count
                        FROM shop_order AS o
                            JOIN shop_customer AS c
                                ON o.contact_id=c.contact_id
                            {$storefront_join}
                        WHERE {$order_date_sql}
                            AND (c.source IN (?) OR c.source IS NULL)
                            {$storefront_where}
                        GROUP BY c.source";
                $customer_counts = $this->query($sql, array($names))->fetchAll('source', true);
            }
        }

        $empty_row = $this->getEmptyRow() + array('roi' => 0, 'profit' => 0);
        unset($empty_row['hash'], $empty_row['name'], $empty_row['date']);
        foreach($sales as &$row) {
            $row['customers_count'] = ifset($customer_counts[$row['name']], 0);
            $row['customer_acquisition_cost'] = $row['customers_count'] ? $row['cost'] / $row['customers_count'] : 0;
            $row['lifetime_customers_count'] = ifset($customer_lifetime_counts[$row['name']], 0);
            foreach(ifset($lifetime_sales[$row['name']], $empty_row) as $k => $v) {
                if ($k != 'name' && $k != 'new_customer_count') {
                    $row['lifetime_'.$k] = $v;
                }
            }
        }
        unset($row);

        return $sales;
    }

    public function getCustomersByCountry($date_start, $date_end, $options=array())
    {
        list($storefront_join, $storefront_where) = $this->getStorefrontSql($options);
        if (wa()->getSetting('reports_date_type', 'paid', 'shop') == 'create') {
            $order_date_sql = self::getDateSql('o.create_datetime', $date_start, $date_end).' AND o.paid_date IS NOT NULL';
        } else {
            $order_date_sql = self::getDateSql('o.paid_date', $date_start, $date_end);
        }

        $country_model = new waCountryModel();
        $countries = $country_model->all();

        $sql = "SELECT op.value AS iso3letter, COUNT(DISTINCT o.contact_id) AS customers
                FROM shop_order AS o
                    LEFT JOIN shop_order_params AS op
                        ON o.id=op.order_id
                            AND op.name='shipping_address.country'
                    {$storefront_join}
                WHERE {$order_date_sql}
                    {$storefront_where}
                GROUP BY op.value
                ORDER BY 2 DESC";
        $result = array();
        $unknown = $country_model->getEmptyRow();
        $unknown['name'] = _w('n/a');
        $unknown['customers'] = 0;
        $unknown['percent_of_max'] = 0;
        $max_customers = 0;
        foreach($this->query($sql) as $row) {
            $row['percent_of_max'] = 0;
            if (!empty($countries[$row['iso3letter']])) {
                $row = $countries[$row['iso3letter']] + $row;
                $result[] = $row;
            } else {
                $unknown['customers'] += $row['customers'];
                $row = $unknown;
            }

            if ($max_customers < $row['customers']) {
                $max_customers = $row['customers'];
            }
        }

        if ($unknown['customers']) {
            $result[] = $unknown;
        }

        if ($max_customers > 0) {
            foreach($result as &$row) {
                $row['percent_of_max'] = $row['customers']*100 / $max_customers;
            }
            unset($row);
        }

        return $result;
    }

    protected function getCohortCost($date_start, $date_end, $options)
    {
        $group_by = ifset($options['group'], 'days');

        $storefront_sql = '';
        if (!empty($options['storefront'])) {
            $storefront_sql = "AND storefront='".$this->escape($options['storefront'])."'";
        }

        $name_sql = '';
        if (!empty($options['customer_source'])) {
            $name_sql = "AND type='source' AND name='".$this->escape($options['customer_source'])."'";
        }

        $sql = "SELECT name, start, end, amount, DATEDIFF(end, start) + 1 AS days_count
                FROM shop_expense
                WHERE start <= DATE(?)
                    AND end >= DATE(?)
                    {$name_sql}
                    {$storefront_sql}
                ORDER BY start";
        $expenses = $this->query($sql, array(
            $date_end,
            $date_start,
        ))->fetchAll();

        // Group results by looping over all dates
        $result = array();
        $start_ts = strtotime($date_start);
        $end_ts = $date_end ? strtotime($date_end) : time();
        for ($t = $start_ts; $t <= $end_ts; $t = strtotime(date('Y-m-d', $t) . ' +1 day')) {
            $period_date = self::getPeriodStart($t, $group_by);
            if (empty($result[$period_date])) {
                $result[$period_date] = 0;
            }

            foreach($expenses as $i => $e) {
                if (empty($e['end_ts'])) {
                    $e['start_ts'] = strtotime($e['start']);
                    $e['end_ts'] = strtotime($e['end']);
                }
                if ($e['end_ts'] < $t) {
                    unset($expenses[$i]);
                    continue;
                }
                if ($e['start_ts'] > $t) {
                    break;
                }

                if ($e['days_count'] > 0) {
                    $result[$period_date] += $e['amount'] / $e['days_count'];
                }
            }
        }

        return $result;
    }

    protected function getCohortCustomersCount($date_start, $date_end, $options)
    {
        $group_by = ifset($options['group'], 'days');
        empty($date_start) && ($date_start = $this->getMinDate());
        list($storefront_join, $storefront_where) = $this->getStorefrontSql($options);
        $order_date_sql = self::getDateSql('o.create_datetime', $date_start, $date_end).' AND o.paid_date IS NOT NULL';
        $customer_date_sql = self::getDateSql('cn.create_datetime', $date_start, $date_end);

        $customer_source_join = '';
        $customer_source_where = '';
        if (!empty($options['customer_source'])) {
            $customer_source_where = "AND c.source='".$this->escape($options['customer_source'])."'";
            $customer_source_join = "JOIN shop_customer AS c ON c.contact_id=o.contact_id";
        }

        $sql = "SELECT DATE(cn.create_datetime) AS registration_date, count(DISTINCT cn.id)
                FROM shop_order AS o
                    JOIN wa_contact AS cn
                        ON cn.id=o.contact_id
                    {$customer_source_join}
                    {$storefront_join}
                WHERE {$order_date_sql}
                    AND {$customer_date_sql}
                    {$storefront_where}
                    {$customer_source_where}
                GROUP BY DATE(cn.create_datetime)";
        $cohorts = $this->query($sql)->fetchAll('registration_date', true);

        // Group results by looping over all dates
        $result = array();
        $start_ts = strtotime($date_start);
        $end_ts = $date_end ? strtotime($date_end) : time();
        for ($t = $start_ts; $t <= $end_ts; $t = strtotime(date('Y-m-d', $t) . ' +1 day')) {
            $date = date('Y-m-d', $t);
            $period_date = self::getPeriodStart($t, $group_by);

            if (empty($result[$period_date])) {
                $result[$period_date] = 0;
            }
            $result[$period_date] += ifset($cohorts[$date], 0);
        }

        return $result;
    }

    public function getCohorts($cohorts_type, $date_start, $date_end, $options)
    {
        empty($date_end) && ($date_end = date('Y-m-d 23:59:59'));
        empty($date_start) && ($date_start = $this->getMinDate());

        if ($cohorts_type == 'profit' || $cohorts_type == 'clv' || $cohorts_type == 'roi') {
            // Calculate profit
            $cohorts = $this->getCohorts('subtotal', $date_start, $date_end, $options);
            $cohorts_purchase = $this->getCohorts('purchase', $date_start, $date_end, $options);
            foreach($cohorts_purchase as $reg_date => $series) {
                foreach($series as $order_date => $stats) {
                    if (empty($cohorts[$reg_date][$order_date])) {
                        $cohorts[$reg_date][$order_date] = $stats;
                        $cohorts[$reg_date][$order_date]['metric'] = 0;
                    }
                    $cohorts[$reg_date][$order_date]['metric'] -= $stats['metric'];
                }
            }
            unset($cohorts_purchase);
            if ($cohorts_type == 'profit') {
                return $cohorts;
            }

            if ($cohorts_type == 'clv') {
                // Divide profit by number of customers to calculate CLV
                $cohorts_customers = $this->getCohortCustomersCount($date_start, $date_end, $options);
                foreach($cohorts as $reg_date => $series) {
                    $total_profit_so_far = 0;
                    $customers_count = $cohorts_customers[$reg_date];
                    foreach($series as $order_date => $stats) {
                        $total_profit_so_far += $cohorts[$reg_date][$order_date]['metric'];
                        if ($customers_count) {
                            $cohorts[$reg_date][$order_date]['metric'] = $total_profit_so_far / $customers_count;
                        } else {
                            $cohorts[$reg_date][$order_date]['metric'] = 0;
                        }
                    }
                }
            } else {
                // Calculate ROI based on profit and expenses
                $cohort_cost = $this->getCohortCost($date_start, $date_end, $options);
                foreach($cohorts as $reg_date => $series) {
                    $total_profit_so_far = 0;
                    $cost = $cohort_cost[$reg_date];
                    foreach($series as $order_date => $stats) {
                        $total_profit_so_far += $cohorts[$reg_date][$order_date]['metric'];
                        if ($cost) {
                            $cohorts[$reg_date][$order_date]['metric'] = $total_profit_so_far*100/$cost;
                        } else {
                            $cohorts[$reg_date][$order_date]['metric'] = 0;
                        }
                    }
                }
            }

            return $cohorts;
        }

        list($storefront_join, $storefront_where) = $this->getStorefrontSql($options);
        $customer_date_sql = self::getDateSql('cn.create_datetime', $date_start, $date_end);
        $order_date_col = 'DATE(o.create_datetime)';
        $order_date_sql = self::getDateSql('o.create_datetime', $date_start, $date_end).' AND o.paid_date IS NOT NULL';

        $customer_source_join = '';
        $customer_source_where = '';
        if (!empty($options['customer_source'])) {
            $customer_source_join = "JOIN shop_customer AS c ON c.contact_id=o.contact_id";
            $customer_source_where = "AND c.source='".$this->escape($options['customer_source'])."'";
        }

        $group_by = ifset($options['group'], 'months');

        $metric_join = '';
        switch($cohorts_type) {
            case 'sales':
                $metric_sql = "SUM(o.total*o.rate)";
                break;
            case 'subtotal':
                $metric_sql = "SUM((o.total - o.tax - o.shipping - o.discount)*o.rate)";
                break;
            case 'purchase':
                $metric_join = "JOIN shop_order_items AS oi
                                    ON oi.order_id=o.id
                                        AND oi.type='product'
                                LEFT JOIN shop_product AS p
                                    ON oi.product_id=p.id
                                LEFT JOIN shop_product_skus AS ps
                                    ON oi.sku_id=ps.id
                                LEFT JOIN shop_currency AS pcur
                                    ON pcur.code=p.currency";
                $metric_sql = "SUM(IF(oi.purchase_price > 0, oi.purchase_price*o.rate, IFNULL(ps.purchase_price*pcur.rate, 0))*oi.quantity)";
                break;
            case 'order_count':
                $metric_sql = "COUNT(DISTINCT o.id)";
                break;
            case 'customer_count':
                $metric_sql = "COUNT(DISTINCT o.contact_id)";
                break;
            default:
                throw new waException('Unknown type: '.htmlspecialchars($cohorts_type));
        }

        $sql = "SELECT DATE(cn.create_datetime) AS registration_date, {$order_date_col} AS order_date, {$metric_sql} AS metric
                FROM shop_order AS o
                    JOIN wa_contact AS cn
                        ON cn.id=o.contact_id
                    {$customer_source_join}
                    {$storefront_join}
                    {$metric_join}
                WHERE {$order_date_sql}
                    AND {$customer_date_sql}
                    {$storefront_where}
                    {$customer_source_where}
                GROUP BY DATE(cn.create_datetime), {$order_date_col}";
        $result = array();
        foreach($this->query($sql) as $row) {
            $period_start_reg = self::getPeriodStart($row['registration_date'], $group_by);
            $period_start_order = self::getPeriodStart($row['order_date'], $group_by);
            if (empty($result[$period_start_reg][$period_start_order])) {
                $result[$period_start_reg][$period_start_order] = array(
                    'date' => $period_start_order,
                    'metric' => 0,
                );
            }
            $result[$period_start_reg][$period_start_order]['metric'] += $row['metric'];
        }

        // Loop over all days of a period and add empty dates into $result
        $start_ts = strtotime($date_start);
        $end_ts = $date_end ? strtotime($date_end) : time();
        for ($t = $start_ts; $t <= $end_ts; $t = strtotime(date('Y-m-d', $t) . ' +1 day')) {
            $date = self::getPeriodStart($t, $group_by);
            if (empty($result[$date])) {
                $result[$date] = array();
            }
        }

        // Add empty dates to each cohort in $result,
        foreach($result as $reg_date => $periods) {
            foreach(array_keys($result) as $date) {
                if (empty($periods[$date])) {
                    $result[$reg_date][$date] = array(
                        'date' => $date,
                        'metric' => 0,
                    );
                }
            }
            ksort($result[$reg_date]);
        }
        ksort($result);

        return $result;
    }

    public static function getPeriodStart($date, $group_by)
    {
        switch ($group_by) {
            case 'quarters':
                $ts = is_numeric($date) ? $date : strtotime($date);
                $m = date('n', $ts);
                if ($m <= 3) {
                    return date('Y-01-01', $ts);
                } else if ($m <= 6) {
                    return date('Y-04-01', $ts);
                } else if ($m <= 9) {
                    return date('Y-07-01', $ts);
                }
                return date('Y-10-01', $ts);
            case 'months':
                $ts = is_numeric($date) ? $date : strtotime($date);
                return date('Y-m-01', $ts);
            case 'weeks':
                $ts = is_numeric($date) ? $date : strtotime($date);
                return date('Y-m-d', strtotime('last monday', strtotime('tomorrow', $ts)));
            case 'days':
            default:
                return $date;
        }
    }

    // Helper for getPeriod(). Wraps $this->rebuildPeriod().
    // Only runs rebuilding on the actual missing part, if any.
    public function ensurePeriod($type, $date_start, $date_end, $options)
    {
        // Check if any dates in given period require rebuilding
        $hash = self::getHash($type, $options);
        list($missing_period_start, $missing_period_end) = $this->getMissingPeriod($hash, $date_start, $date_end);

        // Prepare data in table if something is missing
        if ($missing_period_start) {
            $this->rebuildPeriod($type, $missing_period_start, $missing_period_end, $options);
        }
    }

    // Helper for ensurePeriod()
    protected function getMissingPeriod($hash, $date_start, $date_end)
    {
        $date_sql = self::getDateSql('`date`', $date_start, $date_end);

        $missing_period_end = null;
        $missing_period_start = null;
        $sql = "SELECT DISTINCT date
                FROM {$this->table}
                WHERE hash=?
                    AND name=''
                    AND {$date_sql}";
        $dates = $this->query($sql, $hash)->fetchAll('date');
        $start_ts = strtotime($date_start);
        $end_ts = strtotime($date_end);
        for ($t = $start_ts; $t <= $end_ts; $t = strtotime(date('Y-m-d', $t) . ' +1 day')) {
            $date = date('Y-m-d', $t);
            if (empty($dates[$date])) {
                $missing_period_end = $date;
                if ($missing_period_start === null) {
                    $missing_period_start = $date;
                }
            }
        }
        return array($missing_period_start, $missing_period_end);
    }

    /** Like ensurePeriod() but for a single A/B test only. Rebuilds the whole test duration into a separate hash. */
    protected function ensureABTest($type, $options)
    {
        $hash = self::getHash($type, $options);
        $this->rebuildTmpTableCreate();
        $this->rebuildTmpTable($type, null, null, $options);

        // Figure out when AB-test started and ended using order_ids in shop_sales_tmp
        if (wa()->getSetting('reports_date_type', 'paid', 'shop') == 'create') {
            $date_col = 'DATE(o.create_datetime)';
        } else {
            $date_col = 'o.paid_date';
        }
        $sql = "SELECT MIN({$date_col}) AS date_start, MAX({$date_col}) AS date_end
                FROM shop_order AS o
                    JOIN shop_sales_tmp AS st
                        ON o.id=st.order_id
                WHERE {$date_col} > '0000-00-00 00:00:00'";
        $row = $this->query($sql)->fetchAssoc();
        $date_start = $row['date_start'];
        $date_end = $row['date_end'];

        // Update shop_sales for this AB-test
        if (empty($date_start)) {
            // When there are no orders in shop_sales_tmp, there's nothing else to do
            $this->exec("DELETE FROM {$this->table} WHERE hash=?", $hash);
        } else {
            // Rebuild missing data
            list($missing_period_start, $missing_period_end) = $this->getMissingPeriod($hash, $date_start, $date_end);
            if ($missing_period_start) {
                $this->rebuildFromTmp($type, $missing_period_start, $missing_period_end, $options);
            }
        }

        // Clean up
        $this->rebuildTmpTableDrop();
    }

    /**
     * Deletes cached data for given period and then prepares it again by processing stats
     * saved in shop_order_*
     */
    protected function rebuildPeriod($type, $date_start, $date_end, $options)
    {
        // Check parameters
        empty($date_end) && ($date_end = date('Y-m-d 23:59:59'));
        if (empty($date_start)) {
            throw new waException('Start date is required');
        }
        if (empty($type) || !is_string($type)) {
            throw new waException('Type is required');
        }

        // Create and fill temporary table shop_sales_tmp
        // to assign names (as in shop_sales.name) to orders.
        $this->rebuildTmpTableCreate();
        $this->rebuildTmpTable($type, $date_start, $date_end, $options);

        // Using [name => order_id] link in shop_sales_tmp, fill in data into shop_sales
        $this->rebuildFromTmp($type, $date_start, $date_end, $options);
        $this->rebuildTmpTableDrop();
    }

    /** Helper for rebuildPeriod() */
    protected function rebuildTmpTableCreate()
    {
        $this->exec("CREATE TEMPORARY TABLE IF NOT EXISTS shop_sales_tmp (
            order_id INT(11) NOT NULL PRIMARY KEY,
            name VARCHAR(255) NOT NULL
        ) DEFAULT CHARSET utf8");
        $this->exec("TRUNCATE shop_sales_tmp"); // being paranoid...
    }

    /** Helper for rebuildPeriod() */
    protected function rebuildTmpTableDrop()
    {
        $this->exec("DROP TEMPORARY TABLE IF EXISTS shop_sales_tmp");
    }

    /** Helper for rebuildPeriod() */
    protected function rebuildTmpTable($type, $date_start, $date_end, $options)
    {
        // SQL part to filter orders by date
        if (empty($options['abtest_id'])) {
            if (wa()->getSetting('reports_date_type', 'paid', 'shop') == 'create') {
                $order_date_sql = self::getDateSql('o.create_datetime', $date_start, $date_end).' AND o.paid_date IS NOT NULL';
            } else {
                $order_date_sql = self::getDateSql('o.paid_date', $date_start, $date_end);
            }
        } else {
            $order_date_sql = 'o.paid_date IS NOT NULL';
        }

        // Fill in our temporary table.
        // This part differs for different `$type`s
        switch($type) {
            case 'sources':
                $this->rebuildTmpByParam($order_date_sql, $options, 'referer_host');
                break;
            case 'customer_sources':
                $this->rebuildTmpCustomerSources($order_date_sql, $options);
                break;
            case 'countries':
                $this->rebuildTmpCountries($order_date_sql, $options);
                break;
            case 'shipping':
                $this->rebuildTmpByParam($order_date_sql, $options, 'shipping_name');
                break;
            case 'payment':
                $this->rebuildTmpByParam($order_date_sql, $options, 'payment_name');
                break;
            case 'coupons':
                $this->rebuildTmpCoupons($order_date_sql, $options);
                break;
            case 'campaigns':
                $this->rebuildTmpByParam($order_date_sql, $options, 'utm_campaign');
                break;
            case 'landings':
                $this->rebuildTmpByParam($order_date_sql, $options, 'landing');
                break;
            case 'social':
                $this->rebuildTmpSocial($order_date_sql, $options);
                break;
            default:
                throw new waException('Unknown type: '.$type);
        }
    }

    protected function rebuildFromTmp($type, $date_start, $date_end, $options)
    {
        // SQL part to filter orders by date
        if (wa()->getSetting('reports_date_type', 'paid', 'shop') == 'create') {
            $date_col = 'DATE(o.create_datetime)';
        } else {
            $date_col = 'o.paid_date';
        }

        // Obtain lock
        try {
            $this->exec(
                "LOCK TABLES
                    {$this->table} WRITE,
                    shop_expense READ,
                    shop_sales_tmp AS st READ,
                    shop_order AS o READ,
                    shop_product AS p READ,
                    shop_product_skus AS ps READ,
                    shop_currency AS pcur READ,
                    shop_order_items AS oi READ"
            );
        } catch (waDbException $e) {
            // Oh, well... nvm then.
            $no_lock = true;
        }

        // Delete old cached data we're about to rebuild
        $hash = self::getHash($type, $options);
        $date_sql = self::getDateSql('`date`', $date_start, $date_end);
        $sql = "DELETE FROM {$this->table} WHERE $date_sql AND hash=?";
        $this->exec($sql, $hash);

        // Fill in data into shop_sales using [order=>name] bound in temporary table
        // `order_count`, `sales`, `shipping`, `tax`, `new_customer_count`:
        $escaped_hash = $this->escape($hash);
        $sql = "INSERT IGNORE INTO {$this->table} (hash, `date`, name, order_count, sales, shipping, tax, new_customer_count)
                SELECT
                    '{$escaped_hash}' AS `hash`,
                    {$date_col} as `date`,
                    st.name AS `name`,
                    COUNT(*) AS `order_count`,
                    SUM(o.total*o.rate) AS `sales`,
                    SUM(o.shipping*o.rate) AS `shipping`,
                    SUM(o.tax*o.rate) AS `tax`,
                    SUM(o.is_first) AS `new_customer_count`
                FROM shop_sales_tmp AS st
                    JOIN shop_order AS o
                        ON o.id=st.order_id
                GROUP BY st.name, {$date_col}";
        $this->exec($sql);

        // Purchase costs: `purchase`
        $sql = "INSERT INTO {$this->table} (hash, `date`, name, purchase)
                    SELECT
                        '{$escaped_hash}',
                        {$date_col},
                        st.name,
                        SUM(IF(oi.purchase_price > 0, oi.purchase_price*o.rate, IFNULL(ps.purchase_price*pcur.rate, 0))*oi.quantity)
                    FROM shop_sales_tmp AS st
                        JOIN shop_order AS o
                            ON o.id=st.order_id
                        JOIN shop_order_items AS oi
                            ON oi.order_id=o.id
                        LEFT JOIN shop_product AS p
                            ON oi.product_id=p.id
                        LEFT JOIN shop_product_skus AS ps
                            ON oi.sku_id=ps.id
                        LEFT JOIN shop_currency AS pcur
                            ON pcur.code=p.currency
                    WHERE oi.type='product'
                    GROUP BY st.name, {$date_col}
                ON DUPLICATE KEY UPDATE purchase=VALUES(purchase)";
        $this->exec($sql);

        // Marketing costs: `cost`. Used in the loop below.
        $expenses = array();
        if (empty($options['abtest_id']) && in_array($type, array('sources', 'campaigns', 'social', 'customer_sources'))) {

            $name_sql = '';
            if ($type == 'social') {
                $social_domains = wa('shop')->getConfig()->getOption('social_domains');
                if (!$social_domains || !is_array($social_domains)) {
                    $name_sql = 'AND 1=0';
                } else {
                    $domains = array();
                    foreach(array_keys($social_domains) as $d) {
                        $domains[] = "'".$this->escape($d)."'";
                    }
                    $name_sql = 'AND name IN ('.join(',', $domains).')';
                }
            }

            if ($type == 'customer_sources') {
                $type_sql = '';
            } else if ($type == 'campaigns') {
                $type_sql = "AND type='campaign'";
            } else {
                $type_sql = "AND type='source'";
            }

            $storefront_sql = '';
            if (!empty($options['storefront'])) {
                $storefront_sql = "AND storefront='".$this->escape($options['storefront'])."'";
            }
            $sql = "SELECT name, start, end, amount, DATEDIFF(end, start) + 1 AS days_count
                    FROM shop_expense
                    WHERE start <= DATE(?)
                        AND end >= DATE(?)
                        {$type_sql}
                        {$name_sql}
                        {$storefront_sql}
                    ORDER BY start";
            $expenses = $this->query($sql, array(
                $date_end,
                $date_start,
            ))->fetchAll();
        }

        // List of dates we have an empty record for. Used in the following loop.
        $sql = "SELECT DISTINCT `date`
                FROM {$this->table}
                WHERE hash=?
                    AND name=''
                    AND {$date_sql}";
        $dates = $this->query($sql, $hash)->fetchAll('date');

        // Loop over all days of a period, bulding expenses for each date
        // and making sure a special empty name record exists for each date.
        $empty_values = array();
        $marketing_costs = array();
        $start_ts = strtotime($date_start);
        $end_ts = strtotime($date_end);
        for ($t = $start_ts; $t <= $end_ts; $t = strtotime(date('Y-m-d', $t) . ' +1 day')) {
            $date = date('Y-m-d', $t);
            if (empty($dates[$date])) {
                $empty_values[] = "('$escaped_hash','$date','')";
            }

            // Prepare data for marketing costs for this day
            foreach($expenses as $i => $e) {
                if (strtotime($e['end']) < $t) {
                    unset($expenses[$i]);
                    continue;
                }
                if (strtotime($e['start']) > $t) {
                    break;
                }

                if ($e['days_count'] > 0) {
                    if (empty($marketing_costs[$date][$e['name']])) {
                        $marketing_costs[$date][$e['name']] = 0;
                    }
                    $marketing_costs[$date][$e['name']] += $e['amount'] / $e['days_count'];
                }
            }
        }
        unset($dates);

        // Insert/update marketing costs in shop_sales
        $cost_values = array();
        foreach($marketing_costs as $date => $exps) {
            foreach($exps as $name => $amount) {
                $amount = $this->castValue('float', $amount);
                $cost_values[] = "('{$escaped_hash}', '".$this->escape($name)."', '{$date}', '{$amount}')";
            }
        }
        while ($cost_values) {
            $part = array_splice($cost_values, 0, min(100, count($cost_values)));
            $sql = "INSERT INTO {$this->table} (hash, name, `date`, cost) VALUES ".join(', ', $part)
                    ." ON DUPLICATE KEY UPDATE cost=VALUES(cost)";
            $this->exec($sql);
        }

        // Insert empty rows so that there are no gaps in the period
        while ($empty_values) {
            $part = array_splice($empty_values, 0, min(50, count($empty_values)));
            $sql = "INSERT IGNORE INTO {$this->table} (hash, `date`, name) VALUES ".join(', ', $part);
            $this->exec($sql);
        }

        // Release lock
        empty($no_lock) && $this->exec("UNLOCK TABLES");
    }

    protected function rebuildTmpCountries($date_sql, $options)
    {
        list($abtest_join, $abtest_where) = $this->getAbtestSql($options);
        list($storefront_join, $storefront_where) = $this->getStorefrontSql($options);
        $sql = "INSERT INTO shop_sales_tmp (order_id, name)
                SELECT o.id, CONCAT(IFNULL(op.value, ''), ' ', IFNULL(op3.value, ''))
                FROM shop_order AS o
                    LEFT JOIN shop_order_params AS op
                        ON op.order_id=o.id
                            AND op.name='shipping_address.country'
                    LEFT JOIN shop_order_params AS op3
                        ON op3.order_id=o.id
                            AND op3.name='shipping_address.region'
                    {$storefront_join}
                    {$abtest_join}
                WHERE {$date_sql}
                    {$storefront_where}
                    {$abtest_where}";
        $this->exec($sql);
    }

    protected function rebuildTmpCoupons($date_sql, $options)
    {
        list($abtest_join, $abtest_where) = $this->getAbtestSql($options);
        list($storefront_join, $storefront_where) = $this->getStorefrontSql($options);
        $sql = "INSERT INTO shop_sales_tmp (order_id, name)
                SELECT o.id, IFNULL(op3.value, IFNULL(op.value, ''))
                FROM shop_order AS o
                    LEFT JOIN shop_order_params AS op
                        ON op.order_id=o.id
                            AND op.name='coupon_id'
                    LEFT JOIN shop_order_params AS op3
                        ON op3.order_id=o.id
                            AND op3.name='coupon_code'
                    {$storefront_join}
                    {$abtest_join}
                WHERE {$date_sql}
                    {$storefront_where}
                    {$abtest_where}";
        $this->exec($sql);
    }

    protected function rebuildTmpSocial($date_sql, $options)
    {
        $social_domains = wa('shop')->getConfig()->getOption('social_domains');
        if (!$social_domains || !is_array($social_domains)) {
            return;
        }

        list($abtest_join, $abtest_where) = $this->getAbtestSql($options);
        list($storefront_join, $storefront_where) = $this->getStorefrontSql($options);
        $sql = "INSERT INTO shop_sales_tmp (order_id, name)
                SELECT o.id, IFNULL(op.value, '')
                FROM shop_order AS o
                    JOIN shop_order_params AS op
                        ON op.order_id=o.id
                            AND op.name=?
                    {$storefront_join}
                    {$abtest_join}
                WHERE {$date_sql}
                    {$storefront_where}
                    {$abtest_where}
                    AND op.value IN (?)";
        $this->exec($sql, array('referer_host', array_keys($social_domains)));
    }

    protected function rebuildTmpCustomerSources($date_sql, $options)
    {
        list($abtest_join, $abtest_where) = $this->getAbtestSql($options);
        list($storefront_join, $storefront_where) = $this->getStorefrontSql($options);
        $sql = "INSERT INTO shop_sales_tmp (order_id, name)
                SELECT o.id, IFNULL(c.source, '')
                FROM shop_order AS o
                    LEFT JOIN shop_customer AS c
                        ON o.contact_id=c.contact_id
                    {$storefront_join}
                    {$abtest_join}
                WHERE {$date_sql}
                    {$storefront_where}
                    {$abtest_where}";
        $this->exec($sql);
    }

    protected function rebuildTmpByParam($date_sql, $options, $param_name)
    {
        list($abtest_join, $abtest_where) = $this->getAbtestSql($options);
        list($storefront_join, $storefront_where) = $this->getStorefrontSql($options);
        $sql = "INSERT INTO shop_sales_tmp (order_id, name)
                SELECT o.id, IFNULL(op.value, '')
                FROM shop_order AS o
                    LEFT JOIN shop_order_params AS op
                        ON op.order_id=o.id
                            AND op.name=?
                    {$storefront_join}
                    {$abtest_join}
                WHERE {$date_sql}
                    {$storefront_where}
                    {$abtest_where}";
        $this->exec($sql, $param_name);
    }

    protected function getAbtestSql($options)
    {
        $join = '';
        $where = '';
        if (!empty($options['abtest_id'])) {
            $join = "JOIN shop_order_params AS opabt
                                    ON opabt.order_id=o.id
                                        AND opabt.name='abt".((int)$options['abtest_id'])."'";
            if (!empty($options['abtest_variant_id'])) {
                $where = "AND opabt.value='".$this->escape($options['abtest_variant_id'])."'";
            } else {
                $where = "";
            }
        }
        return array($join, $where);
    }

    public function getStorefrontSql($options)
    {
        $storefront_join = '';
        $storefront_where = '';
        if (!empty($options['storefront'])) {
            $storefront_join = "JOIN shop_order_params AS opst
                                    ON opst.order_id=o.id
                                        AND opst.name='storefront'";
            $storefront_where = "AND opst.value='".$this->escape($options['storefront'])."'";
        }
        return array($storefront_join, $storefront_where);
    }

    public function getAvailableABtests($date_start, $date_end, $options=array())
    {
        list($storefront_join, $storefront_where) = $this->getStorefrontSql($options);
        if (wa()->getSetting('reports_date_type', 'paid', 'shop') == 'create') {
            $order_date_sql = self::getDateSql('o.create_datetime', $date_start, $date_end).' AND o.paid_date IS NOT NULL';
        } else {
            $order_date_sql = self::getDateSql('o.paid_date', $date_start, $date_end);
        }

        $sql = "SELECT DISTINCT(op.name) AS abt_param
                FROM shop_order AS o
                    JOIN shop_order_params AS op
                        ON op.order_id=o.id
                    {$storefront_join}
                WHERE {$order_date_sql}
                    {$storefront_where}
                    AND op.name LIKE 'abt%'";
        $abt_ids = array();
        foreach($this->query($sql) as $row) {
            $abt_id = substr($row['abt_param'], 3);
            $abt_ids[] = $abt_id;
        }

        $result = array();
        $abtest_model = new shopAbtestModel();
        foreach($abtest_model->getById($abt_ids) as $row) {
            $result[$row['id']] = $row['name'];
        }
        return $result;
    }

    public static function getDateSql($fld, $start_date, $end_date)
    {
        $paid_date_sql = array();
        if ($start_date) {
            $paid_date_sql[] = $fld." >= DATE('".$start_date."')";
        }
        if ($end_date) {
            $paid_date_sql[] = $fld." <= (DATE('".$end_date."') + INTERVAL ".(24*3600 - 1)." SECOND)";
        }
        if ($paid_date_sql) {
            return implode(' AND ', $paid_date_sql);
        } else {
            return $fld." IS NOT NULL";
        }
    }
}

