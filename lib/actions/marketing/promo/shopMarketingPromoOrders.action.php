<?php

class shopMarketingPromoOrdersAction extends shopMarketingViewAction
{
    /**
     * Limit for orders count
     * @var int
     */
    protected $limit = 25;

    /**
     * @var shopOrdersCollection
     */
    protected $collection;

    protected $promo_id;
    protected $orders;

    public function execute()
    {
        $orders = $this->getOrders();

        $additional_html = $this->backendMarketingPromoOrdersEvent(ref([
            'page'   => $this->getPage(),
            'orders' => &$orders,
        ]));

        $this->view->assign(array(
            'orders'             => $orders,
            'orders_total_count' => $this->getCollection()->count(),
            'orders_total_sum'   => $this->getCollection()->getSum(),
            'orders_paid_count'  => $this->getCollection()->getTotalPaidNum(),
            'orders_paid_sum'    => $this->getCollection()->getTotalPaidSum(),
            'page'               => $this->getPage(),
            'pages_count'        => $this->getPagesCount(),
            'additional_html'    => $additional_html,
        ));
    }

    /**
     * Get already obtained orders
     * @return array
     */
    protected function getOrders()
    {
        if ($this->orders === null) {
            $this->orders = [];
            try {
                $this->orders = $this->obtainOrders();
            } catch (waException $e) {
                // ..
            }
        }
        return $this->orders;
    }

    /**
     * Obtain (load) from DB
     * @return array
     */
    protected function obtainOrders()
    {
        $orders = array();
        if (empty($this->getPromoId())) {
            return $orders;
        }

        $offset = $this->getOffset();

        // A little bit optimization
        $total_count = $this->getCollection()->count();
        if ($offset >= $total_count) {
            return array();
        }

        $orders = $this->getCollection()->getOrders('*,items,params,contact,courier', $offset, $this->limit);
        $this->workupOrders($orders);

        return $orders;
    }

    /**
     * Get promo_id
     * @return int
     */
    protected function getPromoId()
    {
        if ($this->promo_id === null) {
            $this->promo_id = (int)$this->getParameter('promo_id');
        }

        return $this->promo_id;
    }

    /**
     * Get offset
     * @return int
     */
    protected function getOffset()
    {
        $page = $this->getPage();
        return ($page - 1) * $this->limit;
    }

    /**
     * Get page
     * @return int
     */
    protected function getPage()
    {
        $page = (int)$this->getParameter('page');
        if ($page < 1) {
            $page = 1;
        }
        return $page;
    }

    /**
     * Get parameter either form request or constructor params
     * @param $name
     * @return null|mixed
     */
    protected function getParameter($name)
    {
        // Request params
        $request = wa()->getRequest()->request();

        return ifset($request, $name, null);
    }

    /**
     * @return shopOrdersCollection
     */
    protected function getCollection()
    {
        if ($this->collection === null) {
            $promo_id = $this->getPromoId();
            $this->collection = new shopOrdersCollection('search/promo_id=' . $promo_id);
        }
        return $this->collection;
    }

    /**
     * Workup orders for view
     * @param array &$orders
     */
    protected function workupOrders(&$orders)
    {
        shopHelper::workupOrders($orders);
        foreach ($orders as &$order) {
            $order['total_formatted'] = waCurrency::format('%{h}', $order['total'], $order['currency']);
            $order['shipping_name'] = ifset($order['params']['shipping_name'], '');
            $order['payment_name'] = ifset($order['params']['payment_name'], '');
            // !!! TODO: shipping and payment icons
        }
        unset($order);
    }

    /**
     * @return float
     */
    protected function getPagesCount()
    {
        $count = $this->getCollection()->count();
        $pages_count = (int)ceil((float)$count / $this->limit);
        return $pages_count;
    }

    protected function backendMarketingPromoOrdersEvent(&$params)
    {
        /**
         * Orders tab on single promo page in marketing section.
         * Hook allows to modify data before sending to template for rendering,
         * as well as add custom HTML to the tab.
         *
         * @event backend_marketing_promo_orders
         * @param array [string]array $params
         * @param array [string]int   $params['page']    current page number being loaded
         * @param array [string]array $params['orders']  list of orders (writable)
         *
         * @return array[string][string]string $return[%plugin_id%]['top']   custom HTML to add above the orders table
         */
        $event_result = wa()->event('backend_marketing_promo_orders', $params);

        $additional_html = [
            'top' => [],
        ];

        foreach($event_result as $res) {
            if (!is_array($res)) {
                $res = [
                    'top' => $res,
                ];
            }
            foreach($res as $k => $v) {
                if (isset($additional_html[$k])) {
                    if (!is_array($v)) {
                        $v = [$v];
                    }
                    foreach($v as $html) {
                        $additional_html[$k][] = $html;
                    }
                }
            }
        }

        return $additional_html;
    }
}