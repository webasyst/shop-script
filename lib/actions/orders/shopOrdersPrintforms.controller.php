<?php

class shopOrdersPrintformsController extends waJsonController
{
    public function execute()
    {
        $hash = $this->getHash();
        if ($hash === null) {
            return;
        }

        $limit = 100;
        $collection = new shopOrdersCollection($hash);
        $orders = $collection->getOrders('*', 0, $limit);
        $order_ids = array();
        foreach ($orders as $order) {
            $order_ids[] = $order['id'];
        }

        $printforms = self::getUnionOfPrintforms($order_ids);
        $this->response = array(
            'printforms' => $printforms
        );
    }

    public static function getUnionOfPrintforms($order_ids = null)
    {
        $order_ids = array_map('intval', (array) $order_ids);

        $printforms = array();
        $count_map = array();

        foreach ($order_ids as $order_id) {
            $order = shopPayment::getOrderData($order_id);
            $order_printforms = shopPrintforms::getOrderPrintforms($order);

            // calculate union
            foreach ($order_printforms as $printform_id => $printform) {
                if (!isset($printforms[$printform_id])) {
                    $printforms[$printform_id] = $printform;
                    $count_map[$printform_id] = 0;
                }
                $count_map[$printform_id] += 1;
            }
        }

        foreach ($printforms as $plugin_id => &$plugin) {
            if (strpos($plugin_id, '.')) {
                $plugin['url'] = "?module=order&action=printform&form_id={$plugin_id}&order_id=:order_id";
            } else {
                $plugin['url'] = "?plugin={$plugin_id}&module=printform&action=display&order_id=:order_id";
            }
        }
        unset($plugin);

        $count = count($order_ids);
        foreach ($printforms as $printform_id => &$printform) {
            $printform['all'] = $count_map[$printform_id] >= $count;
        }
        unset($printform);

        return $printforms;

    }

    public function getHash()
    {
        $order_ids = waRequest::request('order_id', null, waRequest::TYPE_ARRAY_INT);
        if ($order_ids !== null) {
            if ($order_ids) {
                return 'id/'.implode(',', $order_ids);
            } else {
                return null;
            }
        }

        $filter_params = waRequest::request('filter_params', null);
        if ($filter_params === null) {
            return null;
        }

        $hash = '';
        if ($filter_params) {
            if (count($filter_params) == 1) {
                $k = key($filter_params);
                $v = $filter_params[$k];
                if (is_array($v)) {
                    $v = implode("||", $v);
                }
                if ($k == 'storefront') {
                    $k = 'params.'.$k;
                    if (substr($v, -1) == '*') {
                        $v = substr($v, 0, -1);
                    }
                }
                $hash = "search/{$k}={$v}";
            }
        }
        return $hash;
    }
}
