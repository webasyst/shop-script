<?php

class shopYandexmarketPluginReportsAction extends waViewAction
{
    /**
     * @var shopYandexmarketPlugin
     */
    private $plugin;

    public function execute()
    {

        $this->plugin = wa('shop')->getPlugin('yandexmarket');

        $storefront = waRequest::request('storefront', null, 'string');


        try {
            if (!empty($storefront)) {
                $graph_data = $this->getSpended($storefront);
            } else {
                $options = array(
                    'balance' => true,
                    'orders'  => true,
                    'offers'  => true,
                    'outlets' => true,
                );
                $campaigns = $this->plugin->getCampaigns($options);
            }

            $limits = $this->plugin->apiLimits();
        } catch (waException $ex) {
            $error = $ex->getMessage();
            $error_code = $ex->getCode();
        }

        $this->view->assign(compact('def_cur', 'request_options', 'campaigns', 'limits', 'error', 'error_code', 'storefront', 'group_by', 'group_by_options', 'graph_data'));

    }

    private function getSpended($storefront)
    {
        list($start_date, $end_date, $group_by, $request_options) = shopReportsSalesAction::getTimeframeParams();

        $config = wa('shop')->getConfig();
        /**
         * @var shopConfig $config
         */
        $def_cur = $config->getCurrency();

        $default_group_by = 'main';
        if ($start_date && empty($end_date)) {
            $start_ts = strtotime($start_date);
            $end_ts = $end_date ? strtotime($end_date) : time();
            $months = 3600 * 24 * 30;
            if ($end_ts - $start_ts > 24 * $months) {
                $default_group_by = 'main-monthly';
            } elseif ($end_ts - $start_ts > 6 * $months) {
                $default_group_by = 'main-weekly';
            } else {
                $default_group_by = 'main-daily';
            }
        }

        $group_by = waRequest::request('group_period', $default_group_by, 'string');

        $group_by_options = array(
            'main'         => _w("day"),
            'main-daily'   => _w("day"),
            'main-weekly'  => _w("week"),
            'main-monthly' => _w("month"),
        );

        $sales = $this->plugin->getStats($storefront, $start_date, $end_date, $group_by);
        $graph_data = self::getGraphData($sales);

        $this->view->assign(compact('def_cur', 'request_options', 'campaigns', 'limits', 'error', 'error_code', 'storefront', 'group_by', 'group_by_options'));

    }


    public static function getGraphData($sales_by_day)
    {
        $graph_data = array();
        foreach ($sales_by_day as &$d) {
            $graph_data[] = array(
                'date'   => str_replace('-', '', $d['date']),
                'sales'  => $d['sales'],
                'profit' => $d['profit'],
                'loss'   => $d['profit'],
            );
        }
        unset($d);
        return $graph_data;
    }
}
