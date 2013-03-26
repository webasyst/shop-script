<?php
class shopBackendOrdersAction extends waViewAction
{
    public function execute()
    {
        if (!wa()->getUser()->getRights('shop', 'orders')) {
            throw new waException(_w("Access denied"));
        }

        $this->setLayout(new shopBackendLayout());
        $this->getResponse()->setTitle(_w('Orders'));

        $config = $this->getConfig();

        $order_model = new shopOrderModel();

        $state_counters = $order_model->getStateCounters();
        $pending_count =
            (!empty($state_counters['new'])        ? $state_counters['new'] : 0) +
            (!empty($state_counters['processing']) ? $state_counters['processing'] : 0) +
            (!empty($state_counters['paid'])       ? $state_counters['paid'] : 0);

        $cm = new shopCouponModel();

        /*
         * @event backend_orders
         * @return array[string]array $return[%plugin_id%] array of html output
         * @return array[string][string]string $return[%plugin_id%]['sidebar_top_li'] html output
         * @return array[string][string]string $return[%plugin_id%]['sidebar_bottom_li'] html output
         * @return array[string][string]string $return[%plugin_id%]['sidebar_section'] html output
         */
        $backend_orders = wa()->event('backend_orders');
        $this->view->assign(array(
            'states'           => $this->getStates(),
            'user_id'          => $this->getUser()->getId(),
            'contacts'         => array() /*$order_model->getContacts()*/,
            'default_view'     => $config->getOption('orders_default_view'),
            'coupons_count'    => $cm->countActive(),
            'state_counters'   => $state_counters,
            'pending_count'    => $pending_count,
            'all_count'        => $order_model->countAll(),
            'contact_counters' => $order_model->getContactCounters(),
            'backend_orders'   => $backend_orders,
        ));
    }

    public function getStates()
    {
        $workflow = new shopWorkflow();
        return $workflow->getAllStates();
    }
}
