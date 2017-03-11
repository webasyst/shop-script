<?php

class shopReportsOrderListAction extends shopOrderListAction
{
    private static $cache = array(
        'orders' => array()
    );

    private $cache_key;

    public function __construct($params = null)
    {
        parent::__construct($params);
        $this->cache_key = md5(json_encode(array(
            'hash' => $this->getHash(),
            'offset' => $this->getOffset(),
            'limit' => $this->getCount()
        )));
    }

    public function execute()
    {
        $offset = $this->getOffset();
        $limit = $this->getCount();

        $orders = $this->getOrders($offset, $limit);

        $now_loaded_count = count($orders);
        $already_loaded_count = $now_loaded_count + $offset;

        $this->view->assign(array(
            'orders' => array_values($orders),
            'disabled_lazyload' => $this->getRequest()->get('disabled_lazyload'),
            'offset' => $offset,
            'limit' => $limit,
            'already_loaded_count' => $already_loaded_count,
            'now_loaded_count' => $now_loaded_count,
            'total_count' => $this->getTotalCount(),
            'report_type' => $this->getReportType(),
            'sales_channel' => $this->getSalesChannel(),
            'timerange' => $this->getTimerange(),
            'filter' => $this->getFilter()
        ));
    }

    public function getOrders($offset, $limit)
    {
        if (!array_key_exists($this->cache_key, self::$cache['orders'])) {
            self::$cache['orders'][$this->cache_key] = parent::getOrders($offset, $limit);
        }
        return self::$cache['orders'][$this->cache_key];
    }

    public function getOffset()
    {
        return $this->getParameter('offset');
    }

    public function getHash()
    {
        $params = $this->getFilterParams();
        return $params['hash'];
    }

    /**
     * @param bool $str
     * @return array|string
     */
    public function getFilterParams($str = false)
    {
        $hash = 'id/0';
        $report_type = $this->getReportType();
        $filter = $this->getFilter();
        switch ($report_type) {
            case 'customer_sources':
                $hash = $this->buildHashByCustomerSource($filter);
                break;
            case 'countries':
                $hash = $this->buildHashByCountry($filter);
                break;
            case 'coupons':
                $hash = $this->buildHashByCoupons($filter);
                break;
            case 'social':
                $hash = $this->buildHashBySocial($filter);
                break;
            default:

                $param_map = array(
                    'sources' => 'referer_host',
                    'shipping' => 'shipping_name',
                    'payment' => 'payment_name',
                    'campaigns' => 'utm_campaign',
                    'landings' => 'landing',
                    'sales_channels' => 'sales_channel',
                    'storefronts' => 'storefront'
                );
                if (isset($param_map[$report_type])) {
                    $hash = $this->buildHashByParam($filter, $param_map[$report_type]);
                }

                break;
        }
        if ($hash && $hash !== 'id/0') {
            $timerange = $this->getTimerange();
            $paid_date_hash = '';
            if (!empty($timerange['start'])) {
                $start = date('Y-m-d', strtotime($timerange['start']));
                $paid_date_hash .= '&paid_date>=' . $start;
            }
            if (!empty($timerange['end'])) {
                $shift = 24*3600 - 1;
                $end = date('Y-m-d', strtotime('+' . $shift . ' second', strtotime($timerange['end'])));
                $paid_date_hash .= '&paid_date<=' . $end;
            }
            $hash .= $paid_date_hash ? $paid_date_hash : '&paid_date!=NULL';
            $sales_channel = $this->getSalesChannel();
            if ($sales_channel) {
                $hash .= '&params.sales_channel=' . $sales_channel;
            }
        }
        if (!$str) {
            return array('hash' => $hash);
        }
        return $hash;
    }

    private function buildHashByParam($filter, $param_name)
    {
        $param_value = $filter['name'] ? $filter['name'] : 'EMPTY';
        return "search/params.{$param_name}={$param_value}";
    }

    private function buildHashByCustomerSource($filter)
    {
        $param_value = $filter['name'] ? $filter['name'] : 'EMPTY';
        return "search/customer.source={$param_value}";
    }

    private function buildHashByCountry($filter)
    {
        $name = explode(' ', $filter['name'], 2);
        $country = trim(ifset($name[0], ''));
        $region = trim(ifset($name[1], ''));
        $country = $country ? $country : 'EMPTY';
        $region = $region ? $region : 'EMPTY';
        return "search/params.shipping_address.country={$country}&params.shipping_address.region={$region}";
    }

    private function buildHashBySocial($filter)
    {
        $hash = 'id/0';
        $referer_host = $filter['name'];
        $social_domains = wa('shop')->getConfig()->getOption('social_domains');
        $social_domains = is_array($social_domains) && $social_domains ? array_keys($social_domains) : array();
        if ($social_domains && $referer_host && in_array($referer_host, $social_domains)) {
            $hash = 'search/params.referer_host=' . $referer_host;
        }
        return $hash;
    }

    private function buildHashByCoupons($filter)
    {
        return 'search/params_coupon='.$filter['name'];
    }

    public function getReportType()
    {
        return $this->getParameter('report_type');
    }

    public function getFilter()
    {
        $filter = (array) $this->getParameter('filter');
        foreach ($filter as $field => $value) {
            $filter[$field] = urldecode($value);
        }
        $filter['name'] = trim(ifset($filter['name'], ''));
        return $filter;
    }

    public function getSalesChannel()
    {
        return $this->getParameter('sales_channel');
    }

    public function getTimerange()
    {
        return (array) $this->getParameter('timerange');
    }

    public function getTotalCount()
    {
        $total_count = $this->getParameter('total_count');
        if ($total_count === null) {
            $total_count = parent::getTotalCount();
        }
        return (int) $total_count;
    }

    public function getParameter($name)
    {
        $value = $this->getRequest()->request($name);
        if ($value === null && isset($this->params[$name])) {
            $value = $this->params[$name];
        }
        return $value;
    }
}