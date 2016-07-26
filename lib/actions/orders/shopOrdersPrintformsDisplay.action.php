<?php

class shopOrdersPrintformsDisplayAction extends waViewAction
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

        $forms = $this->getForms();
        $this->view->assign('printforms', $this->getPrintforms($order_ids, $forms));
    }

    public function getPrintforms($order_ids, $forms)
    {
        if (!$order_ids || !$forms) {
            return array();
        }

        $forms_map = array_fill_keys($forms, true);

        $printforms = array();
        foreach ($order_ids as $order_id) {
            $order = shopPayment::getOrderData($order_id);
            $order_printforms = shopPrintforms::getOrderPrintforms($order);
            foreach ($order_printforms as $form_id => $plugin) {
                if (isset($forms_map[$form_id])) {
                    if (strpos($form_id, '.')) {
                        $url = array(
                            'module'   => 'order',
                            'action'   => 'printform',
                            'form_id'  => $form_id,
                            'order_id' => $order_id,
                            'mass_print' => '1',
                        );
                    } else {
                        $url = array(
                            'plugin'   => $form_id,
                            'module'   => 'printform',
                            'action'   => 'display',
                            'order_id' => $order_id,
                            'mass_print' => '1',
                        );

                    }
                    $printforms[] = '?'.http_build_query($url);
                }
            }
        }

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

    public function getForms()
    {
        return (array)$this->getRequest()->request('form');
    }
}
