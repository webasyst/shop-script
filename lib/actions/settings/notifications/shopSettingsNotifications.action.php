<?php
class shopSettingsNotificationsAction extends waViewAction
{

    public function getEvents()
    {
        $workflow = new shopWorkflow();
        $actions = $workflow->getAllActions();
        $events = array();
        foreach ($actions as $action) {
            /**
             * @var shopWorkflowAction $action
             */
            $name = $action->getOption('log_record');
            if (!$name) {
                $name = $action->getName();
            }
            $events['order.'.$action->getId()] = array(
                'name' => $name,
            );
        }
        return $events;
    }

    public function getSmsFrom()
    {
        $sms_config = wa()->getConfig()->getConfigFile('sms');
        $sms_from = array();
        foreach ($sms_config as $from => $options) {
            $sms_from[$from] = $from.' ('.$options['adapter'].')';
        }
        return $sms_from;
    }

    public static function getTransports()
    {
        return array(
            'email' => array('name' => _w('Email'), 'icon' => 'email'),
            'sms' => array('name' => _w('SMS'), 'icon' => 'mobile'),
            //'http' => array('name' => _w('HTTP Request'), 'icon' => 'globe-small'),
            //'twitter' => array('name' => _w('Tweet'), 'icon' => 'twitter'),
        );
    }

    public function execute()
    {
        $model = new shopNotificationModel();
        $notifications = $model->getAll();
        $this->view->assign('notifications', $notifications);
        $this->view->assign('transports', self::getTransports());

        $this->view->assign('notification_name', $this->getConfig()->getOption('notification_name'));
    }
}
