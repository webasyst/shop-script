<?php
/**
 * Products tab in Reports section.
 * Responsible for Bestsellers, Assets, and What-to-sell subsections.
 * When `reports_simple` config option is specified, this controller
 * is used to show simplified Sales report (i.e. #/summary/).
 */
class shopReportsproductsActions extends waViewActions
{
    /**
     * Best selling products report: top-100 products and services
     */
    public function defaultAction()
    {
        list($start_date, $end_date, $group_by, $request_options) = shopReportsSalesAction::getTimeframeParams();
        $storefront = waRequest::request('storefront', null, 'string');
        $order_by = waRequest::request('sort', 'profit', 'string');
        $model_options = array();
        if ($storefront) {
            $request_options['storefront'] = $storefront;
            $model_options['storefront'] = $storefront;
        }
        if ($order_by != 'sales') {
            $order_by = 'profit';
        }
        if ($order_by) {
            $request_options['sort'] = $order_by;
        }

        // Top products
        $max_sales = 0;
        $max_profit = 0;
        $product_total_sales = 0;
        $product_total_profit = 0;
        $pm = new shopProductModel();
        $top_products = $pm->getTop(100, $order_by, $start_date, $end_date, $model_options)->fetchAll('id');
        foreach($top_products as &$p) {
            $max_sales = max($p['sales'], $max_sales);
            $max_profit = max($p['profit'], $max_profit);
            $product_total_profit += $p['profit'];
            $product_total_sales += $p['sales'];
            $p['profit_percent'] = 0;
            $p['sales_percent'] = 0;
        }

        if ($max_sales > 0 || $max_profit > 0) {
            $val = 100 / max($max_profit, $max_sales);
            foreach($top_products as &$p) {
                $p['profit_percent'] = round($p['profit'] * $val);
                $p['sales_percent'] = round($p['sales'] * $val);
            }
        }
        unset($p);

        // Top services
        $sm = new shopServiceModel();
        $top_services = $sm->getTop(100, $start_date, $end_date, $model_options)->fetchAll('id');
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

        // Total sales by product type
        $pie_data = array();
        $service_total_percent = 0;
        $tm = new shopTypeModel();
        $pie_total = 0;
        foreach($tm->getSales($start_date, $end_date, $model_options) as $row) {
            $pie_data[] = array(
                'label' => $row['name'],
                'value' => (float) $row['sales'],
            );
            $pie_total += $row['sales'];
        }
        if ($service_total_val > 0) {
            $pie_data[] = array(
                'label' => _w('Services'),
                'value' => $service_total_val,
                'service' => true,
            );
        }
        $pie_total += $service_total_val;
        if ($pie_total) {
            $service_total_percent = round($service_total_val * 100 / $pie_total, 1);
            foreach($pie_data as &$row) {
                $row['label'] .= ' ('.round($row['value'] * 100 / ifempty($pie_total, 1), 1).'%)';
            }
            unset($row);
        }

        // Data for chart
        $graph_data = null;
        if (waRequest::request('show_sales')) {
            $sales_model = new shopSalesModel();
            $graph_data = shopReportsSalesAction::getGraphData($sales_model->getPeriodByDate('sources', $start_date, $end_date, $model_options + array(
                'date_group' => $group_by,
            )));
        }

        $def_cur = wa()->getConfig()->getCurrency();

        $this->view->assign(array(
            'def_cur' => $def_cur,
            'top_products' => $top_products,
            'top_services' => $top_services,
            'product_total_sales' => $product_total_sales,
            'product_total_profit' => $product_total_profit,
            'service_total_percent' => $service_total_percent,
            'storefronts' => shopReportsSalesAction::getStorefronts(),
            'service_total_val' => $service_total_val,
            'request_options' => $request_options,
            'graph_data' => $graph_data,
            'pie_data' => $pie_data,
        ));

        $this->setTemplate('templates/actions/reports/ReportsProducts.html');
    }

    /**
     * Stock assets report: total cost and estimated time to sell
     */
    public function assetsAction()
    {
        shopReportsSalesAction::jsRedirectIfDisabled();

        $stock_id = (int) waRequest::request('stock', 0, 'int');
        $limit = (int) waRequest::request('limit', 100, 'int');
        $limit || ($limit = 100);
        $order_by = waRequest::request('sort', '', 'string');
        if ($order_by !== 'stock') {
            $order_by = 'net_worth';
        }

        $request_options = array(
            'stock' => $stock_id,
            'sort' => $order_by,
            'limit' => $limit,
        );

        $product_model = new shopProductModel();

        // Product info and net worth
        if ($stock_id) {
            $stock_expr = "SUM(IF(ps.count > 0, ps.count, 0))";
            $net_worth_expr = "SUM(s.price*c.rate*IF(ps.count > 0, ps.count, 0))";
            $stock_join = "JOIN shop_product_stocks AS ps ON ps.sku_id=s.id";
            $stock_where = "AND ps.stock_id={$stock_id}";
        } else {
            $stock_expr = "SUM(IF(s.count > 0, s.count, 0))";
            $net_worth_expr = "SUM(s.price*c.rate*IF(s.count > 0, s.count, 0))";
            $stock_where = "";
            $stock_join = "";
        }
        $sql = "SELECT p.*, {$net_worth_expr} AS net_worth, {$stock_expr} AS stock
            FROM shop_product AS p
                JOIN shop_product_skus AS s
                    ON s.product_id=p.id
                JOIN shop_currency AS c
                    ON c.code=p.currency
                {$stock_join}
            WHERE s.count > 0
                {$stock_where}
            GROUP BY p.id
            ORDER BY {$order_by} DESC
            LIMIT {$limit}";
        $products = array();
        $total_stock = 0;
        $net_worth = 0;
        foreach($product_model->query($sql) as $p) {
            $total_stock += $p['stock'];
            $net_worth += $p['net_worth'];
            $products[$p['id']] = $p + array(
                'sold' => 0,
                'est' => 0,
            );
        }

        // Number of orders in last 90 days and estimated time to sell
        if ($products) {
            $product_ids = array_keys($products);
            $time_threshold = time()-90*24*3600;
            $sql = "SELECT oi.product_id, SUM(oi.quantity) AS sold
                    FROM shop_order_items AS oi
                        JOIN shop_order AS o
                            ON oi.order_id=o.id
                    WHERE oi.type='product'
                        AND oi.product_id IN (?)
                        AND o.paid_date >= ?
                    GROUP BY oi.product_id";
            $rows = $product_model->query($sql, array($product_ids, date('Y-m-d', $time_threshold)));

            $max_est = 0;
            foreach($rows as $row) {
                if ($row['sold'] > 0) {
                    $p = &$products[$row['product_id']];

                    // Normalize number of sales for products created recently
                    if (!empty($products[$row['product_id']]['create_datetime'])) {
                        $create_ts = strtotime($products[$row['product_id']]['create_datetime']);
                        if ($create_ts > $time_threshold) {
                            $days = max(30, (time() - $create_ts) / 24 / 3600);
                            $row['sold'] = $row['sold']*90/$days;
                        }
                    }

                    $p['sold'] = $row['sold'];
                    $p['est'] = 90 * $p['stock'] / $row['sold'];
                    if ($max_est < $p['est']) {
                        $max_est = $p['est'];
                    }
                }
            }
            if ($max_est > 18*30) {
                $max_est = 18*30;
            }
            foreach($products as &$p) {
                $p['est_bar'] = 100;
                if ($p['est'] < $max_est) {
                    $p['est_bar'] = 100 * $p['est'] / $max_est;
                }
            }
            unset($p);
        }

        $this->setTemplate('templates/actions/reports/ReportsProductsAssets.html');
        $this->view->assign(array(
            'sort' => $order_by,
            'stocks' => wao(new shopStockModel())->getAll('id'),
            'def_cur' => wa()->getConfig()->getCurrency(),
            'request_options' => $request_options,
            'total_stock' => $total_stock,
            'net_worth' => $net_worth,
            'products' => $products,
            'limit' => $limit,
        ));
    }

    /**
     * What to sell report
     */
    public function whattosellAction()
    {
        shopReportsSalesAction::jsRedirectIfDisabled();

        $only_sold = !!waRequest::request('only_sold');
        $limit = (int) waRequest::request('limit', 100, 'int');
        $limit || ($limit = 100);
        $size = wa('shop')->getConfig()->getOption('enable_2x') ? '48x48@2x' : '48x48';

        list($start_date, $end_date, $group_by, $request_options) = shopReportsSalesAction::getTimeframeParams();
        $request_options += array(
            'only_sold' => ifempty($only_sold, ''),
            'limit' => $limit,
        );

        $product_model = new shopProductModel();

        $filter_join_sql = "";
        if ($only_sold) {
            $order_date_sql = shopSalesModel::getDateSql('o.paid_date', $start_date, $end_date);

            $product_model->exec("CREATE TEMPORARY TABLE IF NOT EXISTS sku_ids (
                id int(11) unsigned not null primary key
            )");
            $product_model->exec("TRUNCATE sku_ids");
            $sql = "INSERT INTO sku_ids
                    SELECT DISTINCT oi.sku_id
                    FROM shop_order_items AS oi
                        JOIN shop_order AS o
                            ON o.id=oi.order_id
                    WHERE {$order_date_sql}";
            $product_model->exec($sql);

            $filter_join_sql = "JOIN sku_ids ON sku_ids.id=s.id";
        }

        // Get top-100 products by margin
        $sql = "SELECT p.id, p.name, p.image_id, p.image_filename, p.ext, p.sku_count, p.create_datetime,
                    GROUP_CONCAT(s.id SEPARATOR ',') AS sku_ids,
                    GROUP_CONCAT(s.name SEPARATOR ', ') AS sku_names,
                    (s.price - s.purchase_price)*c.rate AS margin,
                    AVG(s.price*c.rate) AS price,
                    AVG(s.purchase_price*c.rate) AS purchase,
                    SUM(s.count) AS count
                FROM shop_product AS p
                    JOIN shop_product_skus AS s
                        ON s.product_id=p.id
                    JOIN shop_currency AS c
                        ON c.code=p.currency
                    {$filter_join_sql}
                WHERE 1=1
                GROUP BY margin, p.id
                ORDER BY margin DESC, p.id DESC
                LIMIT {$limit}";
        $max_margin = 0;
        $skus = array(); // sku_id => index
        $products = array();
        $product_ids = array();
        foreach($product_model->query($sql) as $p) {
            if ($max_margin < $p['margin']) {
                $max_margin = $p['margin'];
            }
            $p['sku_ids'] = explode(',', $p['sku_ids']);
            $skus += array_fill_keys($p['sku_ids'], count($products));
            if ($p['sku_count'] == count($p['sku_ids'])) {
                $p['sku_names'] = '';
            }

            $product_ids[$p['id']] = 1;
            $products[] = array(
                'price' => (float) $p['price'],
                'margin' => (float) $p['margin'],
                'purchase' => (float) $p['purchase'],
                'sku_count' => (int) $p['sku_count'],
                'count' => (int) $p['count'],
            ) + $p + array(
                'sold' => 0,
                'image_url' => shopImage::getUrl(array(
                    'product_id' => $p['id'],
                    'id' => $p['image_id'], 'filename' => $p['image_filename'], 'ext' => $p['ext']), $size),
            );
        }

        // Fetch number of sales last 3 months (normalized for new products)
        if ($product_ids) {
            $sql = "SELECT oi.product_id, oi.sku_id, sum(oi.quantity) AS sold
                    FROM shop_order_items AS oi
                        JOIN shop_order AS o
                            ON o.id=oi.order_id
                    WHERE oi.product_id IN (?)
                        AND o.paid_date >= ?
                        AND oi.type='product'
                    GROUP BY product_id, sku_id";
            $product_ids = array_keys($product_ids);
            $time_threshold = time()-90*24*3600;
            foreach($product_model->query($sql, array($product_ids, date('Y-m-d', $time_threshold))) as $oi) {
                if (isset($skus[$oi['sku_id']])) {
                    // Normalize number of sales for products created recently
                    if (!empty($products[$oi['product_id']]['create_datetime'])) {
                        $create_ts = strtotime($products[$oi['product_id']]['create_datetime']);
                        if ($create_ts > $time_threshold) {
                            $days = max(30, (time() - $create_ts) / 24 / 3600);
                            $oi['sold'] = $oi['sold']*90/$days;
                        }
                    }

                    $i = $skus[$oi['sku_id']];
                    $products[$i]['sold'] += $oi['sold'];
                }
            }
            unset($skus);

            // Number of sales per month averaged for last 3 months
            foreach($products as &$p) {
                $p['sold'] = round($p['sold']/3.0);
            }
            unset($p);
        }

        $def_cur = wa()->getConfig()->getCurrency();
        $this->setTemplate('templates/actions/reports/ReportsProductsWhatToSell.html');
        $this->view->assign(array(
            'def_cur' => $def_cur,
            'cur_tmpl' => str_replace('0', '%s', wa_currency_html(0, $def_cur)),
            'cur_tmpl_plain' => str_replace('0', '%s', wa_currency(0, $def_cur)),
            'locale_info' => waLocale::getInfo(wa()->getLocale()),
            'request_options' => $request_options,
            'only_sold' => $only_sold,
            'products' => $products,
            'limit' => $limit,
        ));
    }
}

