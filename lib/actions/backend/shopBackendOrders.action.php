<?php

class shopBackendOrdersAction extends waViewAction
{
    public function execute()
    {
        if (!wa()->getUser()->getRights('shop', 'orders')) {
            throw new waRightsException(_w("Access denied"));
        }

        $this->setLayout(new shopBackendLayout());
        $this->getResponse()->setTitle(_w('Orders'));

        $config = $this->getConfig();

        $order_model = new shopOrderModel();

        $state_counters = $order_model->getStateCounters();
        $pending_count =
            (!empty($state_counters['new']) ? $state_counters['new'] : 0) +
            (!empty($state_counters['processing']) ? $state_counters['processing'] : 0) +
            (!empty($state_counters['paid']) ? $state_counters['paid'] : 0);

        $cm = new shopCouponModel();

        $all_count = $order_model->countAll();
        $sales_channels = self::getSalesChannelsWithCounts($order_model);

        $unsettled_count = $order_model->countByField('unsettled', 1);

        /*
         * @event backend_orders
         * @return array[string]array $return[%plugin_id%] array of html output
         * @return array[string][string]string $return[%plugin_id%]['sidebar_top_li'] html output
         * @return array[string][string]string $return[%plugin_id%]['sidebar_bottom_li'] html output
         * @return array[string][string]string $return[%plugin_id%]['sidebar_section'] html output
         */
        $backend_orders = wa()->event('backend_orders');
        $this->getLayout()->assign('backend_orders', $backend_orders);

        $courier_model = new shopApiCourierModel();
        $couriers = $courier_model->getEnabled();
        $courier_model->getOrderCounts($couriers);

        $this->view->assign(array(
            'states'          => $this->getStates(),
            'user_id'         => $this->getUser()->getId(),
            'contacts'        => array() /*$order_model->getContacts()*/,
            'default_view'    => $config->getOption('orders_default_view'),
            'couriers'        => $couriers,
            'coupons_count'   => $cm->countActive(),
            'state_counters'  => $state_counters,
            'pending_count'   => $pending_count,
            'all_count'       => $all_count,
            'sales_channels'  => $sales_channels,
            'backend_orders'  => $backend_orders,
            'unsettled_count' => $unsettled_count
        ));
    }

    public static function getStorefronts()
    {
        return array_fill_keys(shopHelper::getStorefronts(), 0);
    }

    public function getStates()
    {
        $workflow = new shopWorkflow();
        return $workflow->getAllStates();
    }

    /**
     * @param shopOrderModel $order_model
     * @return array
     */
    protected static function getSalesChannelsWithCounts($order_model)
    {
        // Existing storefronts should be at the top,
        // and should show even with zero counts
        $result = array();
        $idna = new waIdna();
        foreach (shopHelper::getStorefronts() as $s) {
            $s = rtrim($s, '/');
            $result['storefront:'.$s] = array(
                'url'        => '#/orders/storefront='.urlencode($s),
                'name'       => $idna->decode($s),
                'count'      => 0,
                'storefront' => $s,
            );
        }

        // Real storefront counts (including deleted storefronts) and sales channels
        $backend_count = 0;
        $channel_names = array();
        foreach ($order_model->getSalesChannelCounters() as $channel_id => $cnt) {
            @list($type, $s) = explode(':', $channel_id, 2);
            if ($type == 'storefront') {
                $s = rtrim($s, '/');
                if (empty($result['storefront:'.$s])) {
                    $result['storefront:'.$s] = array(
                        'url'        => '#/orders/storefront='.urlencode($s),
                        'name'       => $idna->decode($s),
                        'count'      => 0,
                        'storefront' => $s,
                    );
                }
                $result['storefront:'.$s]['count'] += $cnt;
            } elseif ($type == 'backend') {
                $backend_count = $cnt;
            } else {
                $channel_names[$channel_id] = $channel_id;
                $result[$channel_id] = array(
                    'url'        => '#/orders/sales_channel='.urlencode($channel_id),
                    'name'       => $channel_id,
                    'count'      => $cnt,
                    'storefront' => '',
                );
            }
        }

        if ($channel_names) {
            wa('shop')->event('backend_reports_channels', $channel_names);
            $channel_names['buy_button:'] = _w('Buy button');
            foreach ($channel_names as $channel_id => $name) {
                if (!empty($result[$channel_id])) {
                    $result[$channel_id]['name'] = $name;
                }
            }
        }

        // Backend is always the last line
        $result['backend:'] = array(
            'url'        => '#/orders/sales_channel=backend:',
            'name'       => _w('Backend'),
            'count'      => $backend_count,
            'storefront' => 'NULL',
        );

        return $result;
    }
}
