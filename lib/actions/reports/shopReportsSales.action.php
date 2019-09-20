<?php

/**
 * Sales report in Reports section, and all #/sales/type=??? links in sidebar.
 * Serves lazy-loading for table data there, too.
 */
class shopReportsSalesAction extends waViewAction
{
    private $max_n_graphs = 20;

    public function execute()
    {
        shopReportsSalesAction::jsRedirectIfDisabled();

        // Get parameters from GET/POST
        list($start_date, $end_date, $group_by, $request_options) = self::getTimeframeParams();

        $filter = $this->getFilter();
        $request_options['filter'] = $filter;

        $model_options = array();
        $sales_channel = waRequest::request('sales_channel', null, 'string');
        if ($sales_channel) {
            $request_options['sales_channel'] = $sales_channel;
            $model_options['sales_channel'] = $sales_channel;
        }
        $sort = waRequest::request('sort', '!profit', 'string');
        $request_options['sort'] = $sort;

        $type_id = waRequest::request('type', 'sources', 'string');
        $request_options['type'] = $type_id;
        $roi_enabled = in_array($type_id, array('sources', 'social', 'campaigns', 'customer_sources'));

        $abtest_id = waRequest::request('abtest', '', 'int');
        if ($abtest_id) {
            $request_options['abtest'] = $abtest_id;
        }

        $sales_model = new shopSalesModel();
        if ($roi_enabled) {
            $min_date = $sales_model->getMinDate();
            $max_date = date('Y-m-d 23:59:59');
            $sales_model->ensurePeriod($type_id, $min_date, $max_date, $model_options);
            $model_options['ensured'] = true;
        }

        $def_cur = wa()->getConfig()->getCurrency();

        // Load data for table below main chart
        $table_data = $sales_model->getPeriod($type_id, $start_date, $end_date, $model_options + array(
                'order'  => $sort,
                'start'  => waRequest::request('start', 0, 'int'),
                'filter' => $filter
            ), $total_rows);
        $more_rows_exist = $total_rows > count($table_data);
        $model_options['ensured'] = true;

        // Calculate lifetime ROI
        if ($roi_enabled) {
            $names = array();
            foreach ($table_data as $k => $v) {
                $names[$v['name']] = $k;
            }

            $lifetime_sales = array();
            $rows = $sales_model->getPeriod($type_id, $min_date, $max_date, array(
                    'limit'  => 100500,
                    'filter' => array('name' => array_keys($names)),
                ) + $model_options);
            foreach ($rows as $row) {
                $lifetime_sales[$row['name']] = $row;
            }

            foreach ($table_data as &$row) {
                $row['lifetime_roi'] = round(ifset($lifetime_sales[$row['name']]['roi'], 0));
            }
            unset($row);
        }

        // Load data for A/B tests
        $abtest_variants = array();
        if ($abtest_id) {
            $opts = array('abtest_id' => $abtest_id);
            if ($more_rows_exist) {
                $opts['filter'] = array('name' => array());
                foreach ($table_data as $row) {
                    $opts['filter']['name'][] = $row['name'];
                }
            }

            $abtest_variants_model = new shopAbtestVariantsModel();
            $abtest_variants = $abtest_variants_model->getByField('abtest_id', $abtest_id, 'id');

            // make a separate request for each abtest variant
            foreach ($abtest_variants as &$v) {
                $opts['abtest_variant_id'] = $v['id'];
                $v['data'] = array();
                foreach ($sales_model->getPeriod($type_id, $start_date, $end_date, $model_options + $opts) as $row) {
                    $v['data'][$row['name']] = $row;
                }
            }
            unset($v);
        }

        $this->prepareTableData($type_id, $table_data);

        // For lazy-loading and sorting return just the table alone
        if (waRequest::request('table_only')) {
            $this->setTemplate('templates/actions/reports/sales_report_rows.html');
            $this->view->assign(array(
                'more_rows_exist' => $more_rows_exist,
                'abtest_variants' => $abtest_variants,
                'roi_enabled'     => $roi_enabled,
                'table_data'      => $table_data,
                'def_cur'         => $def_cur,
            ));
            return;
        }

        // Whole-period totals, all in default currency
        $totals = $sales_model->getTotals($type_id, $start_date, $end_date, $model_options);
        if ($abtest_variants) {
            foreach ($abtest_variants as &$v) {
                $opts['abtest_variant_id'] = $v['id'];
                $v['totals'] = $sales_model->getTotals($type_id, $start_date, $end_date, $model_options + $opts);
                $v['totals']['cost_formatted'] = waCurrency::format('%{h}', $v['totals']['cost'], $def_cur);
                $v['totals']['sales_formatted'] = waCurrency::format('%{h}', $v['totals']['sales'], $def_cur);
                $v['totals']['profit_formatted'] = waCurrency::format('%{h}', $v['totals']['profit'], $def_cur);
                $v['totals']['avg_day_formatted'] = waCurrency::format('%{h}', $v['totals']['avg_day'], $def_cur);
                $v['totals']['avg_order_formatted'] = waCurrency::format('%{h}', $v['totals']['avg_order'], $def_cur);
            }
            unset($v);
        }
        if ($roi_enabled) {
            $alltime_totals = $sales_model->getTotals($type_id, $min_date, $max_date, $model_options);
            $totals['lifetime_roi'] = round($alltime_totals['roi']);
        }

        // Formatted currency amounts for template
        $totals['cost_formatted'] = waCurrency::format('%{h}', $totals['cost'], $def_cur);
        $totals['sales_formatted'] = waCurrency::format('%{h}', $totals['sales'], $def_cur);
        $totals['profit_formatted'] = waCurrency::format('%{h}', $totals['profit'], $def_cur);
        $totals['avg_day_formatted'] = waCurrency::format('%{h}', $totals['avg_day'], $def_cur);
        $totals['avg_order_formatted'] = waCurrency::format('%{h}', $totals['avg_order'], $def_cur);

        // Data for main chart
        $default_options = array(
            'date_group' => $group_by,
            'filter'     => $filter
        );
        $graph_data = self::getGraphData($sales_model->getPeriodByDate($type_id, $start_date, $end_date, $model_options + $default_options));

        // details graphs
        $details_graph_data = array();
        if ($this->isDetails()) {
            $request_options['details'] = '1';
            $default_options = array(
                'type_id'    => $type_id,
                'start_date' => $start_date,
                'end_date'   => $end_date,
                'date_group' => $group_by,
                'filter'     => $filter
            );
            $details_graph_data = $this->getDetailsGraphData($table_data, $model_options + $default_options);
        }

        // All abtests for the period for selector
        $abtests = $sales_model->getAvailableABtests($start_date, $end_date, $model_options);

        $this->view->assign(array(
            'sales_channels'     => self::getSalesChannels(),
            'request_options'    => $request_options,
            'more_rows_exist'    => $more_rows_exist,
            'abtest_variants'    => $abtest_variants,
            'menu_types'         => self::getMenuTypes(),
            'roi_enabled'        => $roi_enabled,
            'graph_data'         => $graph_data,
            'table_data'         => $table_data,
            'group_by'           => $group_by,
            'abtests'            => $abtests,
            'def_cur'            => $def_cur,
            'totals'             => $totals,
            'filter'             => $filter,
            'filter_title'       => $this->getFilterTitle($filter, $type_id),
            'type_id'            => $type_id,
            'is_details'         => $this->isDetails(),
            'details_graph_data' => $details_graph_data,
            'max_n_graphs'       => $this->max_n_graphs
        ));

        // orders block
        if (isset($filter['name'])) {

            $list_params = array(
                'report_type'   => $type_id,
                'filter'        => $filter,
                'sales_channel' => $sales_channel,
                'timerange'     => array(
                    'start' => $start_date,
                    'end'   => $end_date
                )
            );

            $this->view->assign('order_list_html', $this->getOrderListHtml($list_params));
            $this->view->assign('order_items_list_html', $this->getOrderItemListHtml($list_params));
        }
    }

    public function getFilterTitle($filter, $type_id)
    {
        $title = '';
        if (isset($filter['name'])) {
            $title = $this->formatTableRowName($type_id, $filter['name']);
        }
        if (!$title) {
            $title = _w('(not defined)');
        }
        return $title;
    }

    public static function getGraphData($sales_by_day)
    {
        $graph_row_numeric_fields = self::graphRowNumericFields();
        $graph_data = array();
        foreach ($sales_by_day as &$d) {
            $graph_row = array(
                'date' => str_replace('-', '', $d['date'])
            );
            $graph_row['sales'] = ifset($d['sales'], 0);
            $graph_row['profit'] = ifset($d['profit'], 0);
            $graph_row['loss'] = ifset($d['profit'], 0); // profit can be negative; it renders as a red loss below zero
            $graph_data[] = $graph_row;
        }
        unset($d);
        return $graph_data;
    }

    private function addGraphs($graph_1, $graph_2)
    {
        $graph_row_numeric_field_map = array_fill_keys(self::graphRowNumericFields(), true);

        $len = min(count($graph_1), count($graph_2));
        $graph_3 = array();
        for ($i = 0; $i < $len; $i += 1) {
            $graph_3[$i] = array();
            foreach ($graph_1[$i] as $field => $value) {
                if (!empty($graph_row_numeric_field_map[$field])) {
                    $graph_3[$i][$field] = $value + ifset($graph_2[$i][$field], 0);
                } else {
                    $graph_3[$i][$field] = $graph_1[$i][$field];
                }
            }
        }
        return $graph_3;
    }

    private static function graphRowNumericFields()
    {
        return array('sales', 'profit', 'loss');
    }

    public static function getSalesChannels($with_storefronts = true)
    {
        // Storefront channels
        $result = array();
        $m = new waModel();
        $sql = "SELECT DISTINCT value FROM shop_order_params WHERE name='sales_channel' ORDER BY value";
        foreach (array_keys($m->query($sql)->fetchAll('value')) as $id) {
            $name = $id;
            @list($type, $data) = explode(':', $id, 2);
            if ($with_storefronts) {
                if ($type == 'storefront') {
                    if (!self::$idna) {
                        self::$idna = new waIdna();
                    }
                    $name = self::$idna->decode($data);
                }
                $result[$id] = $name;
            }
        }

        /**
         * @event backend_reports_channels
         *
         * Hook allows to set human-readable sales channel names for custom channels.
         *
         * Event $params is an array with keys being channel identifiers as specified
         * in `sales_channel` order param.
         *
         * Plugins are expected to modify values in $params, setting human readable names
         * to show in channel selector.
         *
         * @param array [string]string
         * @return null
         */
        wa('shop')->event('backend_reports_channels', $result);

        // Buy button and backend
        unset($result['backend:'], $result['buy_button:']);
        $result['buy_button:'] = _w('Buy button');
        $result['backend:'] = _w('Backend');
        if (!empty($result['other:'])) {
            $result['other:'] = _w('Unknown channel');
        }
        return $result;
    }

    public static function getStorefronts()
    {
        $m = new waModel();
        $sql = "SELECT DISTINCT value FROM shop_order_params WHERE name='storefront' ORDER BY value";
        return array_keys($m->query($sql)->fetchAll('value'));
    }

    public static function getTimeframeParams()
    {
        $request_options = array();

        $timeframe = waRequest::request('timeframe');
        $request_options['timeframe'] = $timeframe;
        if ($timeframe === 'all') {
            $start_date = null;
            $end_date = null;
        } elseif ($timeframe == 'custom') {
            $from = waRequest::request('from', 0);
            $from_timestamp = strtotime($from . ' 00:00:00');
            if ($from_timestamp) {
                $from = $from_timestamp;
            }
            $start_date = $from ? date('Y-m-d', $from) : null;

            $to = waRequest::request('to', 0);
            $to_timestamp = strtotime($to . ' 23:59:59');
            if ($to_timestamp) {
                $to = $to_timestamp;
            }
            $end_date = $to ? date('Y-m-d', $to) : null;

            $from && ($request_options['from'] = $from);
            $to && ($request_options['to'] = $to);
        } else {
            if (!wa_is_int($timeframe)) {
                $timeframe = 30;
            }
            $start_date = date('Y-m-d', time() - $timeframe * 24 * 3600);
            $end_date = null;
        }

        $group_by = waRequest::request('groupby', 'days');
        if ($group_by !== 'months') {
            $group_by = 'days';
        } else {
            $request_options['groupby'] = $group_by;
        }

        return array($start_date, $end_date, $group_by, $request_options);
    }

    public static function getMenuTypes()
    {
        return array(
            'sources'        => array(
                'menu_name'   => _w("All sources"),
                'header_name' => _w("Sales by source"),
            ),
            'social'         => array(
                'menu_name'   => _w("Social"),
                'header_name' => _w("Sales by social media"),
            ),
            'countries'      => array(
                'menu_name'   => _w("Countries & Regions"),
                'header_name' => _w("Sales by country and region"),
            ),
            'campaigns'      => array(
                'menu_name'   => _w("Campaigns"),
                'header_name' => _w("Sales by campaign"),
            ),
            'sales_channels' => array(
                'menu_name'   => _w("Sales channels"),
                'header_name' => _w("Sales by sales channel")
            ),
            'storefronts'    => array(
                'menu_name'   => _w("Storefronts"),
                'header_name' => _w("Sales by storefront")
            ),
            'shipping'       => array(
                'menu_name'   => _w("Shipping"),
                'header_name' => _w("Sales by shipping option"),
            ),
            'payment'        => array(
                'menu_name'   => _w("Payment"),
                'header_name' => _w("Sales by payment method"),
            ),
            'coupons'        => array(
                'menu_name'   => _w("Coupons"),
                'header_name' => _w("Sales by coupon"),
            ),
            'landings'       => array(
                'menu_name'   => _w("Landings"),
                'header_name' => _w("Sales by landing page"),
            ),
        );
    }

    public static function isDisabled()
    {
        return wa('shop')->getConfig()->getOption('reports_simple');
    }

    public static function jsRedirectIfDisabled()
    {
        if (self::isDisabled()) {
            echo "<script>window.location.hash = '#/summary/';</script>";
            exit;
        }
    }

    protected function formatTableRowName($type_id, $name, $params = array())
    {
        $type_name = array();
        foreach (explode('_', $type_id) as $part) {
            $type_name[] = ucfirst($part);
        }
        $method_name = __FUNCTION__.'ByType'.join('', $type_name);

        if (method_exists($this, $method_name)) {
            /**
             * @use self::formatTableRowNameByTypeCampaigns
             * @use self::formatTableRowNameByTypeCountries
             * @use self::formatTableRowNameByTypeCoupons
             * @use self::formatTableRowNameByTypeSalesChannels
             * @use self::formatTableRowNameByTypeSocial
             * @use self::formatTableRowNameByTypeSources
             */
            return $this->{$method_name}($name, $params);
        }
        return $name;
    }

    public static function formatTableRowNameByTypeCountries($name)
    {
        static $country_model;
        static $region_model;
        static $regions;

        if ($country_model === null) {
            $country_model = new waCountryModel();
            $country_model->preload();
        }

        if ($region_model === null) {
            $region_model = new waRegionModel();
        }

        if ($regions === null) {
            $regions = array();
        }

        if ($name) {
            @list($country, $region) = explode(' ', $name);
            $c = $country_model->get($country);
            if ($c) {
                if (!isset($regions[$country])) {
                    $regions[$country] = $region_model->getByCountry($country);
                    if (!$regions[$country]) {
                        $regions[$country] = array();
                    }
                }
                $rs = $regions[$country];
                if (!$region) {
                    $region = _w('region not specified');
                } elseif (!empty($rs[$region])) {
                    $region = $rs[$region]['name'];
                }
                $name = $c['name'].' ('.$region.')';
            }
        }

        return $name ? $name : _w('(not defined)');
    }

    protected function formatTableRowNameByTypeCampaigns($name)
    {
        return $name ? $name : null;
    }

    protected function formatTableRowNameByTypeCoupons($name, $coupons = array())
    {
        if (empty($coupons[$name])) {
            $coupon_model = new shopCouponModel();
            $coupons[$name] = $coupon_model->getById($name);
        }
        if (!empty($coupons[$name])) {
            $name = $coupons[$name]['code'];
        }
        return $name;
    }

    /**
     * @var waIdna
     */
    private static $idna;

    protected function formatTableRowNameByTypeSources($name)
    {
        if ($name && !self::$idna) {
            self::$idna = new waIdna();
        }
        return $name ? self::$idna->decode($name) : _w('(direct)');
    }

    protected function formatTableRowNameByTypeStorefronts($name)
    {
        if ($name && !self::$idna) {
            self::$idna = new waIdna();
        }
        return $name ? self::$idna->decode($name) : _w('(not defined)');
    }

    protected function formatTableRowNameByTypeSocial($name)
    {
        $social_domains = wa('shop')->getConfig()->getOption('social_domains');
        if (!empty($social_domains[$name]['name'])) {
            $name = _w($social_domains[$name]['name']);
        }
        return $name;
    }

    protected function formatTableRowNameByTypeSalesChannels($name)
    {
        if ($name === 'buy_button:') {
            $name = _w('Buy button');
        } elseif ($name === 'backend:') {
            $name = _w('Backend');
        } elseif (!$name) {
            $name = _w('(not defined)');
        }
        return $name;
    }

    protected function prepareTableData($type_id, &$table_data)
    {

        $params = array();
        if ($type_id === 'coupons') {
            $coupon_ids = array();
            foreach ($table_data as $i => $row) {
                if (!$row['name']) {
                    unset($table_data[$i]);
                } else {
                    $coupon_ids[$row['name']] = $row['name'];
                }
            }
            $table_data = array_values($table_data);
            if ($coupon_ids) {
                $coupon_model = new shopCouponModel();
                $coupons = $coupon_model->getById($coupon_ids);
                $params = $coupons;
            }
        }

        foreach ($table_data as $i => &$row) {
            $row['orig_name'] = $row['name'];
            $row['name'] = $this->formatTableRowName($type_id, $row['name'], $params);
            if ($row['name'] === null) {
                unset($table_data[$i]);
            }
            if (!$row['name']) {
                $row['name'] = _w('(not defined)');
            }
        }
        unset($row);

        $table_data = array_values($table_data);

        if ($type_id == 'social') {
            $social_domains = wa('shop')->getConfig()->getOption('social_domains');
            foreach ($table_data as &$row) {
                if (!empty($social_domains[$row['orig_name']]['icon_class'])) {
                    $row['icon_class'] = $social_domains[$row['orig_name']]['icon_class'];
                }
            }
            unset($row);
        }
    }

    public function getFilter()
    {
        $filter = (array)$this->getRequest()->request('filter');
        foreach ($filter as $field => $value) {
            $filter[$field] = urldecode($value);
        }
        return $filter;
    }

    public function getOrderListHtml($params)
    {
        $vars = $this->view->getVars();
        $this->view->clearAllAssign();
        $order_list_action = new shopReportsOrderListAction($params);
        $html = $order_list_action->display();
        $this->view->clearAllAssign();
        $this->view->assign($vars);
        return $html;
    }

    public function getOrderItemListHtml($params)
    {
        $vars = $this->view->getVars();
        $this->view->clearAllAssign();
        $order_list_action = new shopReportsOrderItemListAction($params);
        $html = $order_list_action->display();
        $this->view->clearAllAssign();
        $this->view->assign($vars);
        return $html;
    }

    protected function isDetails()
    {
        return $this->getRequest()->request('details');
    }

    protected function getDetailsGraphData($table_data, $options)
    {
        $sales_model = new shopSalesModel();

        $type_id = ifset($options['type_id']);
        $start_date = ifset($options['start_date']);
        $end_date = ifset($options['end_date']);

        $graph_data = array();
        $graph_names = array();

        $name_map = array();
        foreach ($table_data as $row) {
            $name = isset($row['orig_name']) ? $row['orig_name'] : $row['name'];
            $name_map[$name] = true;
        }

        $max_n = $this->max_n_graphs;
        $count = count($table_data);
        $n = min($max_n, $count);
        for ($i = 0; $i < $n; $i += 1) {
            $name = isset($table_data[$i]['orig_name']) ? $table_data[$i]['orig_name'] : $table_data[$i]['name'];
            unset($name_map[$name]);
            $options['filter'] = array('name' => $name);
            $graph_data[$i] = self::getGraphData(
                $sales_model->getPeriodByDate(
                    $type_id,
                    $start_date,
                    $end_date,
                    $options
                )
            );
            $graph_names[$i] = $table_data[$i]['name'];
        }

        // rest graphs in one merged graph
        $options['filter'] = array('name' => array_keys($name_map));
        if ($options['filter']['name']) {
            $graph_data[] = self::getGraphData(
                $sales_model->getPeriodByDate(
                    $type_id,
                    $start_date,
                    $end_date,
                    $options
                )
            );
            $graph_names[] = _w('Other');
        }

        return array('data' => $graph_data, 'names' => $graph_names);
    }
}
