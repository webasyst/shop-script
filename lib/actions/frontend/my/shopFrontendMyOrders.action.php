<?php

/**
 * List of orders in customer account.
 */
class shopFrontendMyOrdersAction extends shopFrontendAction
{
    public function execute()
    {
        $contact = wa()->getUser();
        $scm = new shopCustomerModel();

        // Customer orders
        $om = new shopOrderModel();
        $orders = $om->where('contact_id=?', $contact->getId())->order('id DESC')->fetchAll('id');

        // Items for all orders, one query
        $im = new shopOrderItemsModel();
        foreach($im->getByField('order_id', array_keys($orders), true) as $row) {
            $orders[$row['order_id']]['items'][] = $row;
        }

        // Params for all orders, one query
        $opm = new shopOrderParamsModel();
        foreach($opm->getByField('order_id', array_keys($orders), true) as $row) {
            $orders[$row['order_id']]['params'][$row['name']] = $row['value'];
        }

        // Prepare order data for template
        $url_tmpl = wa()->getRouteUrl('/frontend/myOrder', array('id' => '%ID%'));
        $workflow = new shopWorkflow();
        foreach($orders as $k => &$o) {
            if ($o['state_id'] == 'deleted') {
                unset($orders[$k]);
                continue;
            }
            $o['id_str'] = shopHelper::encodeOrderId($o['id']);
            $o['total_formatted'] = waCurrency::format('%{h}', $o['total'], $o['currency']);
            $o['shipping_name'] = ifset($o['params']['shipping_name'], '');
            $o['payment_name'] = ifset($o['params']['payment_name'], '');
            $o['state'] = $workflow->getStateById($o['state_id']);
            $o['url'] = str_replace('%ID%', $o['id'], $url_tmpl);
        }

        /**
         * @event frontend_my_orders
         * @return array[string]string $return[%plugin_id%] html output
         */
        $this->view->assign('frontend_my_orders', wa()->event('frontend_my_orders', $orders));

        $this->view->assign('orders', array_values($orders));

        $this->view->assign('my_nav_selected', 'orders');

        // Set up layout and template from theme
        $this->setThemeTemplate('my.orders.html');
        if (!waRequest::isXMLHttpRequest()) {
            $this->setLayout(new shopFrontendLayout());
            $this->getResponse()->setTitle(_w('Orders'));
            $this->view->assign('breadcrumbs', self::getBreadcrumbs());
            $this->layout->assign('nofollow', true);
        }
    }

    public static function getBreadcrumbs()
    {
        return array(
            array(
                'name' => _w('My account'),
                'url' => wa()->getRouteUrl('/frontend/my'),
            ),
        );
    }
}

