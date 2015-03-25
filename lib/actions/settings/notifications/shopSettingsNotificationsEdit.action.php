<?php
class shopSettingsNotificationsEditAction extends shopSettingsNotificationsAction
{
    public function execute()
    {
        $id = waRequest::get('id');
        $model = new shopNotificationModel();
        $n = $model->getById($id);

        $params_model = new shopNotificationParamsModel();
        $params = $params_model->getParams($id);

        // Orders used as sample data for testing
        $om = new shopOrderModel();
        $test_orders = $om->where("paid_date IS NOT NULL AND state_id <> 'deleted'")->order('id DESC')->limit(8)->fetchAll('id');
        $test_orders += $om->where("state_id='processing'")->order('id DESC')->limit(2)->fetchAll('id');
        krsort($test_orders);
        shopHelper::workupOrders($test_orders);
        $im = new shopOrderItemsModel();
        foreach($im->getByField('order_id', array_keys($test_orders), true) as $i) {
            $test_orders[$i['order_id']]['items'][] = $i;
        }
        foreach($test_orders as &$o) {
            $o['items'] = ifset($o['items'], array());
            $o['total_formatted'] = waCurrency::format('%{h}', $o['total'], $o['currency']);
        }

        $this->view->assign('n', $n);
        $this->view->assign('params', $params);
        $this->view->assign('transports', self::getTransports());
        $this->view->assign('events', $this->getEvents());
        $this->view->assign('test_orders', $test_orders);
        $this->view->assign('default_email_from', $this->getConfig()->getGeneralSettings('email'));
        $this->view->assign('sms_from', $this->getSmsFrom());
        $this->view->assign('routes', wa()->getRouting()->getByApp('shop'));
    }
}
