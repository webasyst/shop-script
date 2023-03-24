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

        $all_count = $order_model->countByField('all');
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
            'unsettled_count' => $unsettled_count,
            'shipping' => $this->shipping(),
            'payments' => $this->payment(),
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
        $channel_counts = [];
        foreach($order_model->getSalesChannelCounters() as $id => $count) {
            $id = shopSalesChannels::canonicId($id);
            $channel_counts[$id] = $count;
        }

        $channels = shopSalesChannels::describeChannels(array_keys($channel_counts));

        $result = [];
        foreach($channels as $c) {
            if (!empty($c['storefront']) && substr($c['id'], 0, 11) == 'storefront:') {
                $url = '#/orders/storefront='.urlencode(substr($c['id'], 11));
            } else {
                $url = '#/orders/sales_channel='.urlencode($c['id']);
            }
            $result[$c['id']] = [
                'url'        => $url,
                'count'      => ifset($channel_counts, $c['id'], 0),
            ] + $c;
        }

        return $result;
    }

    protected function shipping() {
        $plugin_model = new shopPluginModel();
        return $plugin_model->listPlugins('shipping');
    }

    protected function payment() {
        $plugin_model = new shopPluginModel();
        return $plugin_model->listPlugins('payment');
    }
}
