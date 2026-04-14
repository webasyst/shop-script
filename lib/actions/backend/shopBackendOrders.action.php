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

        /** @var shopConfig $config */
        $config = $this->getConfig();

        $order_model = new shopOrderModel();

        $state_counters = $order_model->getStateCounters();
        $pending_count =
            (!empty($state_counters['new']) ? $state_counters['new'] : 0) +
            (!empty($state_counters['processing']) ? $state_counters['processing'] : 0) +
            (!empty($state_counters['auth']) ? $state_counters['auth'] : 0) +
            (!empty($state_counters['pickup']) ? $state_counters['pickup'] : 0) +
            (!empty($state_counters['paid']) ? $state_counters['paid'] : 0);
        $processing_count_new = $pending_count;

        $cm = new shopCouponModel();

        if (!shopRights::isAssistant()) {
            $all_count = $order_model->countByField('all');
        }
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
        $assistants = shopRights::getAssistants();
        $order_model->getOrderCounts($assistants);

        $this->view->assign(array(
            'states'          => $this->getStates(),
            'user_id'         => $this->getUser()->getId(),
            'contacts'        => array() /*$order_model->getContacts()*/,
            'default_view'    => $config->getOption('orders_default_view'),
            'couriers'        => $couriers,
            'coupons_count'   => $cm->countActive(),
            'state_counters'  => $state_counters,
            'currency'        => $config->getCurrency(),
            'pending_count'   => $pending_count,
            'all_count'       => ifset($all_count),
            'sales_channels'  => $sales_channels,
            'backend_orders'  => $backend_orders,
            'unsettled_count' => $unsettled_count,
            'shipping'        => $this->shipping(),
            'payments'        => $this->payment(),
            'contacts_as_courier' => $assistants['couriers'],
            'manager_users'       => $assistants['managers'] + $assistants['admins'],
            'fulfillment_users'   => $assistants['fulfillments'],
            'cashier_users'       => $assistants['cashiers'],
            'processing_count_new' => $processing_count_new,
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

    /**
     * Return array ids of users who have access right to given rules
     *
     * @param string $name
     * @param array $rights minimal user rights
     * @return array
     * @throws waDbException
     */
    protected static function getUsersByRights($name, $rights)
    {
        $conditions = [
            "(r.app_id = s:app_id AND r.name = s:name AND r.value IN (:rights))",
            "(r.app_id = 'webasyst' AND r.name = 'backend' AND r.value > 0)",
        ];
        if ($name != 'backend') {
            $conditions[] = "(r.app_id = s:app_id AND r.name = 'backend' AND r.value > 1)";
        }

        $fields = [];
        foreach ($rights as $field_name => $level) {
            $fields[] = "r.name = s:name AND r.value = $level `$field_name`";
        }

        $sql = "SELECT DISTINCT IF(r.group_id < 0, -r.group_id, g.contact_id) AS cid, " . implode(', ', $fields) . "
                FROM wa_contact_rights r
                    LEFT JOIN wa_user_groups g ON r.group_id = g.group_id
                WHERE (r.group_id < 0 OR g.contact_id IS NOT NULL)
                    AND (" . implode(' OR ', $conditions) . ")";

        $contact_rights_model = new waContactRightsModel();
        return $contact_rights_model->query($sql, [
            'app_id' => 'shop',
            'name' => $name,
            'rights' => $rights,
        ])->fetchAll('cid', true);
    }
}
